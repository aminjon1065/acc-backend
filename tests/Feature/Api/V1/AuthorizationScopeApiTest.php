<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('seller cannot access shop settings endpoints', function () {
    $shop = Shop::factory()->create();
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);

    $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/settings')
        ->assertForbidden();

    $this->actingAs($seller, 'sanctum')
        ->putJson('/api/v1/settings', [
            'tax_percent' => 5,
        ])
        ->assertForbidden();
});

test('owner cannot update user from another shop even with direct id', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);

    $userInShopB = User::factory()->create([
        'shop_id' => $shopB->id,
        'role' => UserRole::Seller->value,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->putJson('/api/v1/users/'.$userInShopB->id, [
            'name' => 'Hacked Name',
        ])
        ->assertNotFound();
});

test('owner cannot delete product from another shop by id tampering', function () {
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
        ->deleteJson('/api/v1/products/'.$productInShopB->id)
        ->assertNotFound();
});

test('owner cannot view sale from another shop by id tampering', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);

    $sellerB = User::factory()->create([
        'shop_id' => $shopB->id,
        'role' => UserRole::Seller->value,
    ]);

    $saleInShopB = Sale::factory()->create([
        'shop_id' => $shopB->id,
        'user_id' => $sellerB->id,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/sales/'.$saleInShopB->id)
        ->assertNotFound();
});

test('seller cannot create product expense purchase debt or view reports', function () {
    $shop = Shop::factory()->create();
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 10,
    ]);

    $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/products', [
            'name' => 'Blocked',
            'cost_price' => 1,
            'sale_price' => 2,
            'stock_quantity' => 5,
        ])
        ->assertForbidden();

    $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/expenses', [
            'name' => 'Blocked Expense',
            'quantity' => 1,
            'price' => 10,
        ])
        ->assertForbidden();

    $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 10,
                ],
            ],
        ])
        ->assertForbidden();

    $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'Blocked Debt',
        ])
        ->assertForbidden();

    $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/reports/sales')
        ->assertForbidden();
});

test('suspended shop user cannot access protected api endpoints', function () {
    $shop = Shop::factory()->create([
        'status' => 'suspended',
    ]);

    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Shop is suspended.');
});
