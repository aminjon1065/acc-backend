<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('owner can create purchase and stock increases', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 5,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'supplier_name' => 'Supplier A',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'price' => 3,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.total_amount', 30)
        ->assertJsonPath('data.items.0.product_id', $product->id);

    expect((float) $product->fresh()->stock_quantity)->toBe(15.0);
});

test('purchase markup promotes product to markup pricing mode', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'pricing_mode' => 'fixed',
        'markup_percent' => null,
        'cost_price' => 5,
        'sale_price' => 7,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10,
                    'markup_percent' => 40,
                ],
            ],
        ])
        ->assertSuccessful();

    $product->refresh();

    expect($product->pricing_mode)->toBe('markup');
    expect((float) $product->markup_percent)->toBe(40.0);
    expect((float) $product->sale_price)->toBe(14.0);
});

test('owner cannot create purchase with product from another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);
    $productB = Product::factory()->create([
        'shop_id' => $shopB->id,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                [
                    'product_id' => $productB->id,
                    'quantity' => 1,
                    'price' => 100,
                ],
            ],
        ])
        ->assertNotFound();
});

test('super admin must provide shop_id when creating purchase', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
    ]);

    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 1,
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shop_id');
});
