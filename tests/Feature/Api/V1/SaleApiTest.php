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
            'type' => 'product',
            'discount' => 1,
            'paid' => 5,
            'payment_type' => 'cash',
            'notes' => 'Front counter sale',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'product')
        ->assertJsonPath('data.notes', 'Front counter sale')
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

test('sale calculates bulk pricing automatically when threshold is met and no explicit price is sent', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 100,
        'sale_price' => 10,
        'bulk_price' => 8,
        'bulk_threshold' => 10,
    ]);

    // Quantity exactly at threshold
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'discount' => 0,
            'paid' => 80,
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.total', 80)
        ->assertJsonPath('data.items.0.price', 8);

    // Quantity below threshold
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'discount' => 0,
            'paid' => 90,
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 9,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.total', 90)
        ->assertJsonPath('data.items.0.price', 10);
});

test('sale requires explicit price for manual pricing mode', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'pricing_mode' => 'manual',
        'sale_price' => 10,
        'stock_quantity' => 20,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});

test('service sale persists metadata and service item naming', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'service',
            'customer_name' => 'Repair Client',
            'notes' => 'Includes diagnostics',
            'payment_type' => 'card',
            'items' => [
                [
                    'name' => 'Phone repair',
                    'unit' => 'job',
                    'quantity' => 1,
                    'price' => 50,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'service')
        ->assertJsonPath('data.notes', 'Includes diagnostics')
        ->assertJsonPath('data.items.0.service_name', 'Phone repair')
        ->assertJsonPath('data.items.0.product_id', null);
});
