<?php

use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('owner can list and create users only in own shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $owner = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);

    User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Seller->value,
    ]);

    User::factory()->create([
        'shop_id' => $shopB->id,
        'role' => UserRole::Seller->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/users')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/users', [
            'shop_id' => $shopB->id,
            'name' => 'Seller A',
            'email' => 'seller-a@example.com',
            'password' => 'password123',
            'role' => UserRole::Seller->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.shop_id', $shopA->id)
        ->assertJsonPath('data.role', UserRole::Seller->value);
});

test('owner cannot create super admin user', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'Forbidden User',
            'email' => 'forbidden@example.com',
            'password' => 'password123',
            'role' => UserRole::SuperAdmin->value,
        ])
        ->assertForbidden();
});

test('seller cannot list or create users', function () {
    $shop = Shop::factory()->create();
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);

    $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/users')
        ->assertForbidden();

    $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password123',
            'role' => UserRole::Seller->value,
        ])
        ->assertForbidden();
});

test('super admin can create user in specific shop and filter list', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/users', [
            'shop_id' => $shopA->id,
            'name' => 'Owner A',
            'email' => 'owner-a@example.com',
            'password' => 'password123',
            'role' => UserRole::Owner->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.shop_id', $shopA->id);

    User::factory()->create([
        'shop_id' => $shopB->id,
        'role' => UserRole::Seller->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/users?shop_id='.$shopA->id)
        ->assertSuccessful()
        ->assertJsonPath('data.0.shop_id', $shopA->id);
});
