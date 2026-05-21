<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

// When a super_admin pauses a shop, the entire tenant must lose access on the
// API surface — no listing, no creating, no fetching cached data via /auth/me
// hints. These tests pin that behavior end-to-end so a future scope-filter
// rewrite can't silently leak suspended-shop data again.

test('seller of suspended shop is blocked by active_shop middleware', function () {
    $shop = Shop::factory()->create(['status' => 'suspended']);
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);

    $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertForbidden()
        ->assertJsonPath('message', 'Shop is suspended.');
});

test('owner with only suspended shops is blocked', function () {
    $owner = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::Owner->value,
    ]);
    Shop::factory()->create(['owner_id' => $owner->id, 'status' => 'suspended']);
    Shop::factory()->create(['owner_id' => $owner->id, 'status' => 'suspended']);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertForbidden()
        ->assertJsonPath('message', 'Shop is suspended.');
});

test('owner with one active and one suspended shop cannot see suspended data', function () {
    $owner = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::Owner->value,
    ]);
    $active = Shop::factory()->create(['owner_id' => $owner->id, 'status' => 'active']);
    $suspended = Shop::factory()->create(['owner_id' => $owner->id, 'status' => 'suspended']);

    Product::factory()->create(['shop_id' => $active->id, 'name' => 'Visible']);
    Product::factory()->create(['shop_id' => $suspended->id, 'name' => 'Hidden']);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Visible');
    expect($names)->not->toContain('Hidden');
});

test('canAccessShop rejects suspended shop for its owner', function () {
    $owner = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::Owner->value,
    ]);
    $active = Shop::factory()->create(['owner_id' => $owner->id, 'status' => 'active']);
    $suspended = Shop::factory()->create(['owner_id' => $owner->id, 'status' => 'suspended']);

    expect($owner->canAccessShop($active->id))->toBeTrue();
    expect($owner->canAccessShop($suspended->id))->toBeFalse();
});

test('auth/me returns only active owned_shop_ids', function () {
    $owner = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::Owner->value,
    ]);
    $active = Shop::factory()->create(['owner_id' => $owner->id, 'status' => 'active']);
    Shop::factory()->create(['owner_id' => $owner->id, 'status' => 'suspended']);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    expect($response->json('data.owned_shop_ids'))->toBe([$active->id]);
});

test('super_admin is unaffected by suspended shop status', function () {
    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);
    Shop::factory()->create(['status' => 'suspended']);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertOk();
});
