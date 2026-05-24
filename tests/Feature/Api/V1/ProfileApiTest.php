<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Hash;

test('authenticated user can update their name via api', function () {
    $user = User::factory()->create([
        'name' => 'Old name',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/profile', ['name' => 'New name'])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'New name');

    expect($user->fresh()->name)->toBe('New name');
});

test('user can change their password with the correct current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword!1'),
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/profile', [
            'current_password' => 'OldPassword!1',
            'password' => 'NewPassword!2',
        ])
        ->assertSuccessful();

    expect(Hash::check('NewPassword!2', $user->fresh()->password))->toBeTrue();
});

test('password change rejects wrong current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword!1'),
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/profile', [
            'current_password' => 'WRONG',
            'password' => 'NewPassword!2',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);

    expect(Hash::check('OldPassword!1', $user->fresh()->password))->toBeTrue();
});

test('password change requires current_password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword!1'),
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/profile', ['password' => 'NewPassword!2'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

test('unauthenticated requests are rejected', function () {
    $this->patchJson('/api/v1/profile', ['name' => 'whatever'])
        ->assertUnauthorized();
});
