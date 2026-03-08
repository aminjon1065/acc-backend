<?php

use App\Models\Expense;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('owner can create expense with calculated total', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/expenses', [
            'name' => 'Rent',
            'quantity' => 2,
            'price' => 300,
            'note' => 'Monthly office rent',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.total', 600)
        ->assertJsonPath('data.name', 'Rent');
});

test('owner cannot access expense from another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);
    $expenseB = Expense::factory()->create([
        'shop_id' => $shopB->id,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/expenses/'.$expenseB->id)
        ->assertNotFound();
});

test('super admin must provide shop_id when creating expense', function () {
    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/expenses', [
            'name' => 'Global Expense',
            'quantity' => 1,
            'price' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shop_id');
});
