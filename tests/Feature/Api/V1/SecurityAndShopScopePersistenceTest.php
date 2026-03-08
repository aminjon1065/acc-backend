<?php

use App\Models\DebtTransaction;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('api responses include secure headers', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertSuccessful()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('sale items purchase items and debt transactions persist shop_id', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 100,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'price' => 2,
                ],
            ],
        ])
        ->assertSuccessful();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 5,
                ],
            ],
        ])
        ->assertSuccessful();

    $debtResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'Buyer',
            'opening_balance' => 20,
        ])
        ->assertSuccessful();

    $debtId = $debtResponse->json('data.id');

    expect(PurchaseItem::query()->latest('id')->firstOrFail()->shop_id)->toBe($shop->id);
    expect(SaleItem::query()->latest('id')->firstOrFail()->shop_id)->toBe($shop->id);
    expect(
        DebtTransaction::query()
            ->where('debt_id', $debtId)
            ->latest('id')
            ->firstOrFail()
            ->shop_id
    )->toBe($shop->id);
});
