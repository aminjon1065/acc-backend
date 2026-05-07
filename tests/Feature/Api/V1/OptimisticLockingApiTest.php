<?php

use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

/**
 * Optimistic-locking contract tests.
 *
 * Every entity that can be edited concurrently exposes a `version` column.
 * Clients send their last-known version; the server rejects writes whose
 * version differs from the canonical row with HTTP 409 + a `server_data`
 * payload carrying the authoritative state.
 *
 * Three semantic guarantees we lock in here:
 *   1. Mismatch → 409 + server_data is the up-to-date resource (not the
 *      client's stale copy).
 *   2. Match → request proceeds normally.
 *   3. Absence (legacy client) → request still proceeds. Once mobile rollout
 *      ships universal version-aware writes, FormRequests can mark the field
 *      required and this row of tests is what we'll change.
 */
function lockingActor(): array
{
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    return [$shop, $owner];
}

// ─── Sale.update ──────────────────────────────────────────────────────────────

test('sale update returns 409 when client version is stale', function () {
    [$shop, $owner] = lockingActor();
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
    ]);
    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'version' => 5,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/sales/{$sale->id}", [
            'version' => 4,
            'customer_name' => 'Alice',
        ])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('server_data.id', $sale->id)
        ->assertJsonPath('server_data.version', 5);
});

test('sale update succeeds when client version matches', function () {
    [$shop, $owner] = lockingActor();
    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'version' => 1,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/sales/{$sale->id}", [
            'version' => 1,
            'customer_name' => 'Bob',
        ])
        ->assertSuccessful();
});

test('sale update succeeds when client omits version', function () {
    [$shop, $owner] = lockingActor();
    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'version' => 2,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/sales/{$sale->id}", [
            'customer_name' => 'Carol',
        ])
        ->assertSuccessful();
});

// ─── Product.update + destroy ─────────────────────────────────────────────────

test('product update returns 409 when client version is stale', function () {
    [$shop, $owner] = lockingActor();
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'version' => 7,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/products/{$product->id}", [
            'version' => 1,
            'name' => 'Renamed product',
        ])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('server_data.id', $product->id)
        ->assertJsonPath('server_data.version', 7);
});

test('product destroy returns 409 when client version is stale', function () {
    [$shop, $owner] = lockingActor();
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'version' => 3,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/products/{$product->id}", ['version' => 2])
        ->assertStatus(409)
        ->assertJsonPath('success', false);
});

// ─── Expense.update + destroy ─────────────────────────────────────────────────

test('expense update returns 409 when client version is stale', function () {
    [$shop, $owner] = lockingActor();
    $expense = Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'version' => 4,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/expenses/{$expense->id}", [
            'version' => 1,
            'name' => 'Updated rent',
        ])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('server_data.id', $expense->id)
        ->assertJsonPath('server_data.version', 4);
});

test('expense destroy returns 409 when client version is stale', function () {
    [$shop, $owner] = lockingActor();
    $expense = Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'version' => 2,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/expenses/{$expense->id}", ['version' => 1])
        ->assertStatus(409)
        ->assertJsonPath('success', false);
});

// ─── Debt.storeTransaction + destroy ──────────────────────────────────────────

test('debt storeTransaction returns 409 when client version is stale', function () {
    [$shop, $owner] = lockingActor();
    $debt = Debt::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'version' => 6,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/debts/{$debt->id}/transactions", [
            'version' => 5,
            'type' => 'give',
            'amount' => 100,
        ])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('server_data.id', $debt->id)
        ->assertJsonPath('server_data.version', 6);
});

test('debt destroy returns 409 when client version is stale', function () {
    [$shop, $owner] = lockingActor();
    $debt = Debt::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'version' => 9,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/debts/{$debt->id}", ['version' => 8])
        ->assertStatus(409)
        ->assertJsonPath('success', false);
});
