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

test('seller can create their own expense', function () {
    $shop = \App\Models\Shop::factory()->create();
    $seller = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Seller->value,
    ]);

    $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/expenses', [
            'name' => 'Кофе для команды',
            'quantity' => 2,
            'price' => 50,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Кофе для команды')
        ->assertJsonPath('data.total', 100);
});

test('seller sees only their own expenses', function () {
    $shop = \App\Models\Shop::factory()->create();
    $sellerA = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Seller->value,
    ]);
    $sellerB = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Seller->value,
    ]);

    \App\Models\Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $sellerA->id,
        'name' => 'A expense',
    ]);
    \App\Models\Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $sellerB->id,
        'name' => 'B expense',
    ]);

    $list = $this->actingAs($sellerA, 'sanctum')
        ->getJson('/api/v1/expenses')
        ->assertSuccessful()
        ->json('data');
    expect(collect($list)->pluck('name')->all())->toBe(['A expense']);
});

test('seller can delete their own expense but not someone else\'s', function () {
    $shop = \App\Models\Shop::factory()->create();
    $sellerA = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Seller->value,
    ]);
    $sellerB = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Seller->value,
    ]);
    $ownExpense = \App\Models\Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $sellerA->id,
    ]);
    $otherExpense = \App\Models\Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $sellerB->id,
    ]);

    // Own row — allowed.
    $this->actingAs($sellerA, 'sanctum')
        ->deleteJson("/api/v1/expenses/{$ownExpense->id}")
        ->assertSuccessful();

    // Cross-seller row — route-model binding resolves the expense, the
    // authorize() gate catches it via ExpensePolicy::delete returning
    // false for "not your row", so the response is 403 Forbidden.
    $this->actingAs($sellerA, 'sanctum')
        ->deleteJson("/api/v1/expenses/{$otherExpense->id}")
        ->assertForbidden();
});
