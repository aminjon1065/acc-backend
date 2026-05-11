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
        ->assertJsonPath('data.transactions.0.debt_id', fn (string $debtId) => $debtId !== '')
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

test('overpayment flips debt direction (receivable → payable)', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    // Customer owes us 1000 (receivable).
    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'Anvar B.',
            'opening_balance' => 1000,
        ])
        ->assertSuccessful();

    $debtId = $createResponse->json('data.id');

    // Customer pays 1500 — overpaying by 500. Old behaviour rejected this
    // with a validation error; the bazaar-friendly behaviour accepts and
    // flips the books so we now owe the customer 500.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/debts/{$debtId}/transactions", [
            'type' => 'repay',
            'amount' => 1500,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.balance', 500)
        ->assertJsonPath('data.direction', 'payable');
});

test('overpayment in payable direction flips back to receivable', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    // Direct DB seed: we owe the supplier 300 (payable, balance unsigned).
    $debt = Debt::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'person_name' => 'Supplier S.',
        'direction' => 'payable',
        'balance' => 300,
    ]);

    // We repay 800 — clearing the 300 and dropping 500 of advance/credit
    // so the supplier now owes us 500.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/debts/{$debt->id}/transactions", [
            'type' => 'repay',
            'amount' => 800,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.balance', 500)
        ->assertJsonPath('data.direction', 'receivable');
});
