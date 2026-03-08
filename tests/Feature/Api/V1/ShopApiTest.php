<?php

use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('super admin can manage shops', function () {
    $admin = User::factory()->create([
        'role' => UserRole::SuperAdmin->value,
        'shop_id' => null,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/shops', [
            'name' => 'Alpha Shop',
            'owner_name' => 'Owner A',
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Alpha Shop');

    $shop = Shop::query()->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->patchJson('/api/v1/shops/'.$shop->id, [
            'status' => 'suspended',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'suspended');
});

test('owner can only see own shop and cannot create new shops', function () {
    $ownerShop = Shop::factory()->create();
    $anotherShop = Shop::factory()->create();

    $owner = User::factory()->create([
        'shop_id' => $ownerShop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/shops')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownerShop->id);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/shops/'.$anotherShop->id)
        ->assertNotFound();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/shops', [
            'name' => 'Blocked Shop',
        ])
        ->assertForbidden();
});
