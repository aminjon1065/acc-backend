<?php

use App\Models\Currency;
use App\Models\User;
use App\UserRole;

test('authenticated user can view currencies', function () {
    Currency::factory()->create([
        'code' => 'TJS',
        'name' => 'Somoni',
        'rate' => 1,
        'is_default' => true,
    ]);

    $owner = User::factory()->create([
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/currencies')
        ->assertSuccessful()
        ->assertJsonPath('data.0.code', 'TJS');
});

test('only super admin can update exchange rates', function () {
    $currency = Currency::factory()->create([
        'code' => 'USD',
        'rate' => 10,
    ]);

    $owner = User::factory()->create([
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->putJson('/api/v1/currencies/'.$currency->id, [
            'rate' => 11,
        ])
        ->assertForbidden();

    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/currencies/'.$currency->id, [
            'rate' => 11.25,
            'is_default' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.rate', 11.25)
        ->assertJsonPath('data.is_default', true);
});
