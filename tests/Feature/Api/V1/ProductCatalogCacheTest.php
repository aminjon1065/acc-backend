<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('product list cache is invalidated after creating a product', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products?limit=10')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/products', [
            'name' => 'Cached Product',
            'cost_price' => 10,
            'sale_price' => 15,
            'pricing_mode' => 'fixed',
            'stock_quantity' => 5,
        ])
        ->assertCreated();

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products?limit=10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Cached Product');
});

test('product show cache is invalidated after updating a product', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Old Name',
        'sale_price' => 100,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Old Name');

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/products/{$product->id}", [
            'name' => 'New Name',
            'sale_price' => 150,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.sale_price', 150);
});

test('product show cache is invalidated after purchase and sale stock changes', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'cost_price' => 20,
        'sale_price' => 30,
        'stock_quantity' => 10,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.stock_quantity', 10);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'price' => 20,
                ],
            ],
        ])
        ->assertSuccessful();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.stock_quantity', 15);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 4,
                ],
            ],
            'payment_type' => 'cash',
            'paid' => 120,
        ])
        ->assertSuccessful();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.stock_quantity', 11);
});
