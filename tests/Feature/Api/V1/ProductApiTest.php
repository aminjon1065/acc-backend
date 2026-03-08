<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('shop owner can create and list own products', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/products', [
            'name' => 'Coffee',
            'code' => 'COF-001',
            'unit' => 'piece',
            'cost_price' => 5,
            'sale_price' => 10,
            'stock_quantity' => 100,
            'low_stock_alert' => 5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Coffee')
        ->assertJsonPath('data.shop_id', $shop->id);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('owner cannot access product from another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);

    $productInShopB = Product::factory()->create([
        'shop_id' => $shopB->id,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/products/'.$productInShopB->id)
        ->assertNotFound();
});

test('super admin can access products from all shops', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    Product::factory()->count(2)->create([
        'shop_id' => $shopA->id,
    ]);

    Product::factory()->count(3)->create([
        'shop_id' => $shopB->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/products?limit=10')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});
