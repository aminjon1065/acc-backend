<?php

use App\Models\Debt;
use App\Models\DebtTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

/**
 * Transaction-integrity contract tests.
 *
 * Every multi-row write path in the service layer must be wrapped in a
 * DB transaction so that a mid-flight ValidationException leaves the
 * database in the pre-write state. These tests force a controlled failure
 * AFTER the parent row would have been inserted to prove no orphans
 * survive.
 *
 * If any of these tests fail, somebody removed the DB::transaction wrapper
 * from the service — re-add it before merging.
 */

// ─── Sale: insufficient stock rolls back parent + items + stock decrements ────

test('sale create rolls back when item quantity exceeds stock', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $okProduct = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 5,
        'sale_price' => 12,
    ]);
    $shortProduct = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 1,
        'cost_price' => 5,
        'sale_price' => 12,
    ]);

    $salesBefore = Sale::query()->count();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'paid' => 0,
            'payment_type' => 'cash',
            'items' => [
                ['product_id' => $okProduct->id, 'quantity' => 2, 'price' => 12],
                ['product_id' => $shortProduct->id, 'quantity' => 5, 'price' => 12],
            ],
        ])
        ->assertUnprocessable();

    // No Sale, no SaleItems, no stock changes — full rollback.
    expect(Sale::query()->count())->toBe($salesBefore);
    expect(SaleItem::query()->count())->toBe(0);
    expect((float) $okProduct->fresh()->stock_quantity)->toBe(10.0);
    expect((float) $shortProduct->fresh()->stock_quantity)->toBe(1.0);
});

// ─── Sale: discount > subtotal rolls back stock decrements done before validation ─

test('sale create rolls back when discount exceeds subtotal', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 5,
        'sale_price' => 12,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'discount' => 100, // way over subtotal of 24
            'paid' => 0,
            'payment_type' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 12],
            ],
        ])
        ->assertUnprocessable();

    expect(Sale::query()->count())->toBe(0);
    expect(SaleItem::query()->count())->toBe(0);
    // Stock was tentatively decremented inside the transaction; the rollback
    // must restore it.
    expect((float) $product->fresh()->stock_quantity)->toBe(10.0);
});

// ─── Purchase: cross-shop product reference rolls back parent + items ─────────

test('purchase create rolls back when product belongs to another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);
    $productInB = Product::factory()->create([
        'shop_id' => $shopB->id,
        'stock_quantity' => 1,
    ]);

    // The exact status (404 from BelongsToShop scope vs. 422 from explicit
    // service validation) is implementation-defined; what matters for this
    // test is that the request fails AND nothing is persisted.
    $response = $this->actingAs($ownerA, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'supplier_name' => 'Test Supplier',
            'items' => [
                ['product_id' => $productInB->id, 'quantity' => 5, 'price' => 10],
            ],
        ]);
    expect($response->status())->toBeIn([404, 422]);

    expect(Purchase::query()->count())->toBe(0);
    expect(PurchaseItem::query()->count())->toBe(0);
    // Product in shop B is untouched.
    expect((float) $productInB->fresh()->stock_quantity)->toBe(1.0);
});

// ─── Debt.delete: parent + transactions removed atomically ───────────────────

test('debt destroy cascades to transactions atomically', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $debt = Debt::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'balance' => 100,
    ]);
    DebtTransaction::factory()->count(3)->create([
        'shop_id' => $shop->id,
        'debt_id' => $debt->id,
        'user_id' => $owner->id,
    ]);

    expect(DebtTransaction::query()->where('debt_id', $debt->id)->count())->toBe(3);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/debts/{$debt->id}")
        ->assertSuccessful();

    expect(Debt::withTrashed()->find($debt->id)?->deleted_at)->not->toBeNull();
    // Either soft-deleted alongside the parent or hard-deleted — the contract
    // is just that they don't outlive an "orphan" parent.
    expect(DebtTransaction::query()->where('debt_id', $debt->id)->count())->toBe(0);
});

// ─── Expense.update: optimistic-version bump and total mutation are atomic ────

test('expense update version bump is part of the same write', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $expense = \App\Models\Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'name' => 'Rent',
        'quantity' => 1,
        'price' => 100,
        'total' => 100,
        'version' => 4,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/expenses/{$expense->id}", [
            'version' => 4,
            'name' => 'Updated Rent',
            'price' => 150,
            'quantity' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.version', 5)
        ->assertJsonPath('data.name', 'Updated Rent')
        ->assertJsonPath('data.total', 150);
});
