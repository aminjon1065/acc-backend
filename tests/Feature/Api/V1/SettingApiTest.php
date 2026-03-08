<?php

use App\Models\Currency;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('owner can view and update own shop settings', function () {
    Currency::factory()->create([
        'code' => 'TJS',
        'name' => 'Somoni',
        'rate' => 1,
        'is_default' => true,
    ]);

    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/settings')
        ->assertSuccessful()
        ->assertJsonPath('data.shop_id', $shop->id);

    Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'rate' => 10,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->putJson('/api/v1/settings', [
            'default_currency' => 'USD',
            'tax_percent' => 12.5,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.default_currency', 'USD')
        ->assertJsonPath('data.tax_percent', 12.5);
});

test('super admin must pass shop_id for settings endpoints', function () {
    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/settings')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shop_id');
});

test('super admin can manage specific shop settings by shop_id', function () {
    Currency::factory()->create([
        'code' => 'TJS',
        'name' => 'Somoni',
        'rate' => 1,
        'is_default' => true,
    ]);

    $shop = Shop::factory()->create();
    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/settings?shop_id='.$shop->id, [
            'default_currency' => 'TJS',
            'tax_percent' => 5,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.shop_id', $shop->id)
        ->assertJsonPath('data.tax_percent', 5);
});
