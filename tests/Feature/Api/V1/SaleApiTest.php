<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('owner can create sale and stock decreases with debt calculation', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'discount' => 1,
            'paid' => 5,
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.total', 19)
        ->assertJsonPath('data.debt', 14)
        ->assertJsonPath('data.items.0.cost_price', 4);

    expect((float) $product->fresh()->stock_quantity)->toBe(8.0);
});

test('sale fails when product stock is insufficient', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 1,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10,
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    expect((float) $product->fresh()->stock_quantity)->toBe(1.0);
});

test('owner cannot create sale with product from another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);
    $productB = Product::factory()->create([
        'shop_id' => $shopB->id,
        'stock_quantity' => 50,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [
                [
                    'product_id' => $productB->id,
                    'quantity' => 1,
                    'price' => 10,
                ],
            ],
        ])
        ->assertNotFound();
});
