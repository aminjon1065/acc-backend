<?php

use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('mobile user can login and receive token', function () {
    $shop = Shop::factory()->create();
    $user = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
        'email' => 'owner@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'owner@example.com',
        'password' => 'password',
        'device_name' => 'ios-app',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.shop_id', $shop->id)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['token', 'token_type', 'user'],
        ]);
});

test('mobile user cannot login with invalid password', function () {
    User::factory()->create([
        'email' => 'owner@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'owner@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'errors']);
});

test('authenticated user can request profile and logout', function () {
    $user = User::factory()->create([
        'role' => UserRole::Seller->value,
    ]);

    $token = $user->createToken('test-device')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->tokens()->count())->toBe(0);
});

test('authenticated user can refresh api token', function () {
    $user = User::factory()->create([
        'role' => UserRole::Owner->value,
    ]);

    $token = $user->createToken('old-device')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/refresh', [
            'device_name' => 'new-device',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Token refreshed.')
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['token', 'token_type', 'user'],
        ]);

    expect($user->fresh()->tokens()->count())->toBe(1);
});
