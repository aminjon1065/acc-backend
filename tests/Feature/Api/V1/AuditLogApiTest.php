<?php

use App\Models\Currency;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\UserRole;

test('auth endpoints write audit logs', function () {
    $shop = Shop::factory()->create();
    $user = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
        'email' => 'owner@example.com',
        'password' => 'password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'owner@example.com',
        'password' => 'password',
        'device_name' => 'ios-app',
    ])->assertSuccessful();

    $token = $loginResponse->json('data.token');

    $refreshResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/refresh', [
            'device_name' => 'ios-app-v2',
        ])
        ->assertSuccessful();

    $refreshedToken = $refreshResponse->json('data.token');

    $this->withHeader('Authorization', 'Bearer '.$refreshedToken)
        ->postJson('/api/v1/auth/logout')
        ->assertSuccessful();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'shop_id' => $shop->id,
        'event' => 'auth.login',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'shop_id' => $shop->id,
        'event' => 'auth.refresh',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'shop_id' => $shop->id,
        'event' => 'auth.logout',
    ]);
});

test('financial operations write audit logs', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 20,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    $expenseResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/expenses', [
            'name' => 'Rent',
            'quantity' => 1,
            'price' => 200,
        ])
        ->assertSuccessful();

    $expenseId = $expenseResponse->json('data.id');

    $this->actingAs($owner, 'sanctum')
        ->patchJson('/api/v1/expenses/'.$expenseId, [
            'price' => 250,
        ])
        ->assertSuccessful();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson('/api/v1/expenses/'.$expenseId)
        ->assertSuccessful();

    $purchaseResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'supplier_name' => 'Supplier A',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 3,
                ],
            ],
        ])
        ->assertSuccessful();

    $saleResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 10,
                ],
            ],
        ])
        ->assertSuccessful();

    $debtResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/debts', [
            'person_name' => 'John Customer',
            'opening_balance' => 30,
        ])
        ->assertSuccessful();

    $debtId = $debtResponse->json('data.id');

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/debts/'.$debtId.'/transactions', [
            'type' => 'repay',
            'amount' => 10,
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'shop_id' => $shop->id,
        'event' => 'expenses.created',
        'auditable_type' => Expense::class,
        'auditable_id' => $expenseId,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'shop_id' => $shop->id,
        'event' => 'expenses.updated',
        'auditable_type' => Expense::class,
        'auditable_id' => $expenseId,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'shop_id' => $shop->id,
        'event' => 'expenses.deleted',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'shop_id' => $shop->id,
        'event' => 'purchases.created',
        'auditable_type' => Purchase::class,
        'auditable_id' => $purchaseResponse->json('data.id'),
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'shop_id' => $shop->id,
        'event' => 'sales.created',
        'auditable_type' => Sale::class,
        'auditable_id' => $saleResponse->json('data.id'),
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'shop_id' => $shop->id,
        'event' => 'debts.created',
        'auditable_type' => Debt::class,
        'auditable_id' => $debtId,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'shop_id' => $shop->id,
        'event' => 'debts.transaction_recorded',
        'auditable_type' => Debt::class,
        'auditable_id' => $debtId,
    ]);
});

test('settings and currency updates write audit logs', function () {
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
        ->putJson('/api/v1/settings', [
            'default_currency' => 'TJS',
            'tax_percent' => 12.5,
        ])
        ->assertSuccessful();

    $currency = Currency::factory()->create([
        'code' => 'USD',
        'rate' => 10,
    ]);

    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/currencies/'.$currency->id, [
            'rate' => 11.25,
            'is_default' => true,
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'shop_id' => $shop->id,
        'event' => 'settings.updated',
        'auditable_type' => ShopSetting::class,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $admin->id,
        'event' => 'currencies.updated',
        'auditable_type' => Currency::class,
        'auditable_id' => $currency->id,
    ]);
});
