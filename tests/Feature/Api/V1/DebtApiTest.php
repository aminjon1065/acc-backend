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

test('owner can rename a debt contact', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $debt = Debt::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'person_name' => 'Ivan',
        'direction' => 'receivable',
        'balance' => 100,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/debts/{$debt->id}", [
            'person_name' => 'Ivan Petrov',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.person_name', 'Ivan Petrov')
        ->assertJsonPath('data.balance', 100)
        ->assertJsonPath('data.direction', 'receivable');

    expect($debt->fresh()->person_name)->toBe('Ivan Petrov');
});

test('seller cannot rename someone else\'s debt', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $sellerA = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);
    $sellerB = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);

    $debt = Debt::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $sellerA->id,
        'person_name' => 'Customer',
        'direction' => 'receivable',
        'balance' => 50,
    ]);

    // Seller A (the creator) can rename their own debt — sellers have
    // update permission scoped to their own rows in DebtPolicy.
    $this->actingAs($sellerA, 'sanctum')
        ->patchJson("/api/v1/debts/{$debt->id}", ['person_name' => 'Renamed'])
        ->assertSuccessful();

    // Seller B (different seller in same shop) is blocked — they didn't
    // create this debt so the policy denies the update.
    $this->actingAs($sellerB, 'sanctum')
        ->patchJson("/api/v1/debts/{$debt->id}", ['person_name' => 'Hijack'])
        ->assertForbidden();
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

test('seller can edit own transaction amount and balance recomputes', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);

    $createResponse = $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'Client',
            'opening_balance' => 100,
        ])
        ->assertSuccessful();
    $debtId = $createResponse->json('data.id');
    $txId = $createResponse->json('data.transactions.0.id');

    // Fix the opening: should have been 1000 not 100.
    $this->actingAs($seller, 'sanctum')
        ->patchJson("/api/v1/debts/{$debtId}/transactions/{$txId}", [
            'type' => 'give',
            'amount' => 1000,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.balance', 1000)
        ->assertJsonPath('data.direction', 'receivable');

    // Owner from same shop can also edit any tx — sanity check.
    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/debts/{$debtId}/transactions/{$txId}", [
            'type' => 'give',
            'amount' => 500,
            'note' => 'Corrected by manager',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.balance', 500);
});

test('seller cannot edit transactions on someone else\'s debt', function () {
    $shop = Shop::factory()->create();
    $sellerA = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);
    $sellerB = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);

    $createResponse = $this->actingAs($sellerA, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'A debt',
            'opening_balance' => 100,
        ])
        ->assertSuccessful();
    $debtId = $createResponse->json('data.id');
    $txId = $createResponse->json('data.transactions.0.id');

    $this->actingAs($sellerB, 'sanctum')
        ->patchJson("/api/v1/debts/{$debtId}/transactions/{$txId}", [
            'type' => 'give',
            'amount' => 999,
        ])
        ->assertForbidden();
});

test('deleting all transactions zeroes the debt balance', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'Test',
            'opening_balance' => 200,
        ])
        ->assertSuccessful();
    $debtId = $createResponse->json('data.id');
    $txId = $createResponse->json('data.transactions.0.id');

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/debts/{$debtId}/transactions/{$txId}")
        ->assertSuccessful()
        ->assertJsonPath('data.balance', 0)
        ->assertJsonPath('data.transactions', []);
});

test('editing tx type from give to repay flips direction on overpay', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    // Receivable debt with opening 100.
    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'Counterparty',
            'opening_balance' => 100,
            'direction' => 'receivable',
        ])
        ->assertSuccessful();
    $debtId = $createResponse->json('data.id');

    // Add a "we paid them 300" tx.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/debts/{$debtId}/transactions", [
            'type' => 'repay',
            'amount' => 300,
        ])
        ->assertSuccessful();

    // Sum = 100 (give) - 300 (repay) = -200 → flip to payable, balance 200.
    $check = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/debts/{$debtId}")
        ->json('data');
    expect((float) $check['balance'])->toBe(200.0);
    expect($check['direction'])->toBe('payable');
});
