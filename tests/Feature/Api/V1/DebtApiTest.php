<?php

use App\Models\Debt;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('owner can create debt and apply transactions with balance updates', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'John Customer',
            'opening_balance' => 100,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.opening_balance', 100)
        ->assertJsonPath('data.transactions.0.debt_id', fn (int $debtId) => $debtId > 0)
        ->assertJsonPath('data.balance', 100);

    $debtId = $createResponse->json('data.id');

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/debts/{$debtId}/transactions", [
            'type' => 'repay',
            'amount' => 30,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.balance', 70);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/debts/{$debtId}/transactions", [
            'type' => 'take',
            'amount' => 10,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.balance', 60);
});

test('owner cannot access debt from another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);
    $debtB = Debt::factory()->create([
        'shop_id' => $shopB->id,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/debts/'.$debtB->id)
        ->assertNotFound();

    $this->actingAs($ownerA, 'sanctum')
        ->postJson('/api/v1/debts/'.$debtB->id.'/transactions', [
            'type' => 'repay',
            'amount' => 10,
        ])
        ->assertNotFound();
});

test('super admin must provide shop_id when creating debt', function () {
    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'No Shop Debt',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shop_id');
});
