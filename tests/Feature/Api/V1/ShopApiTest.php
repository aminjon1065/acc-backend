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

test('shop owner_name resolves from FK relation when owner assigned', function () {
    $admin = User::factory()->create([
        'role' => UserRole::SuperAdmin->value,
        'shop_id' => null,
    ]);
    $assignedOwner = User::factory()->create([
        'role' => UserRole::Owner->value,
        'name' => 'Assigned Owner',
    ]);
    $shop = Shop::factory()->create([
        'owner_id' => null,
        'owner_name' => 'Legacy Text Label',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/shops/'.$shop->id)
        ->assertSuccessful()
        ->assertJsonPath('data.owner_name', 'Legacy Text Label');

    $this->actingAs($admin, 'sanctum')
        ->patchJson('/api/v1/shops/'.$shop->id, [
            'owner_id' => $assignedOwner->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.owner_id', $assignedOwner->id)
        ->assertJsonPath('data.owner_name', 'Assigned Owner');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/shops/'.$shop->id)
        ->assertSuccessful()
        ->assertJsonPath('data.owner_name', 'Assigned Owner');
});

test('shops ids endpoint returns id+updated_at scoped to actor', function () {
    $owner = \App\Models\User::factory()->create(['role' => \App\UserRole::Owner->value]);
    $owned = \App\Models\Shop::factory()->create(['owner_id' => $owner->id]);
    \App\Models\Shop::factory()->create(); // unrelated, must NOT appear

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/shops/ids')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($owned->id);
    expect(count($ids))->toBe(1);
    expect($response->json('data.0'))->toHaveKey('updated_at');
});
