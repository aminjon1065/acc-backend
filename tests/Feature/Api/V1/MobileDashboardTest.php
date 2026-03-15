<?php

use App\Models\Debt;
use App\Models\DebtTransaction;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

it('returns the aggregated mobile dashboard payload for a seller', function () {
    CarbonImmutable::setTestNow('2026-03-15 10:00:00');

    $shop = Shop::factory()->create();
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller,
    ]);

    $productLow = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $seller->id,
        'name' => 'Low Stock Cola',
        'stock_quantity' => 2,
        'low_stock_alert' => 5,
        'cost_price' => 10,
        'sale_price' => 16,
    ]);

    Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $seller->id,
        'stock_quantity' => 10,
        'low_stock_alert' => 3,
        'cost_price' => 4,
        'sale_price' => 7,
    ]);

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'customer_name' => 'Customer One',
        'total' => 50,
        'paid' => 30,
        'debt' => 20,
        'discount' => 0,
        'created_at' => CarbonImmutable::now()->subHours(2),
        'updated_at' => CarbonImmutable::now()->subHours(2),
    ]);

    SaleItem::query()->create([
        'shop_id' => $shop->id,
        'sale_id' => $sale->id,
        'product_id' => $productLow->id,
        'quantity' => 2,
        'price' => 25,
        'cost_price' => 10,
        'total' => 50,
        'created_at' => CarbonImmutable::now()->subHours(2),
        'updated_at' => CarbonImmutable::now()->subHours(2),
    ]);

    Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'name' => 'Delivery',
        'quantity' => 1,
        'price' => 8,
        'total' => 8,
        'created_at' => CarbonImmutable::now()->subHour(),
        'updated_at' => CarbonImmutable::now()->subHour(),
    ]);

    $receivable = Debt::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'person_name' => 'Customer Debt',
        'direction' => 'receivable',
        'balance' => 20,
    ]);

    $payable = Debt::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'person_name' => 'Supplier Debt',
        'direction' => 'payable',
        'balance' => 12,
    ]);

    DebtTransaction::factory()->create([
        'shop_id' => $shop->id,
        'debt_id' => $receivable->id,
        'user_id' => $seller->id,
        'type' => 'give',
        'amount' => 20,
        'created_at' => CarbonImmutable::now()->subMinutes(30),
        'updated_at' => CarbonImmutable::now()->subMinutes(30),
    ]);

    DebtTransaction::factory()->create([
        'shop_id' => $shop->id,
        'debt_id' => $payable->id,
        'user_id' => $seller->id,
        'type' => 'give',
        'amount' => 12,
        'created_at' => CarbonImmutable::now()->subMinutes(20),
        'updated_at' => CarbonImmutable::now()->subMinutes(20),
    ]);

    Sanctum::actingAs($seller, ['dashboard:view']);

    $response = $this->getJson('/api/v1/dashboard?period=day');

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.low_stock_count', 1)
        ->assertJsonPath('data.low_stock_products.0.name', 'Low Stock Cola')
        ->assertJsonPath('data.recent_sales.0.id', $sale->id)
        ->assertJsonPath('data.recent_expenses.0.name', 'Delivery');

    expect((float) $response->json('data.period_sales_total'))->toBe(50.0);
    expect((float) $response->json('data.period_expenses_total'))->toBe(8.0);
    expect((float) $response->json('data.period_cogs'))->toBe(20.0);
    expect((float) $response->json('data.period_profit'))->toBe(22.0);
    expect((float) $response->json('data.debts_receivable'))->toBe(20.0);
    expect((float) $response->json('data.debts_payable'))->toBe(12.0);
    expect((float) $response->json('data.debts_net'))->toBe(8.0);
    expect((float) $response->json('data.stock_total_qty'))->toBe(12.0);
    expect((float) $response->json('data.stock_total_cost'))->toBe(60.0);
    expect((float) $response->json('data.stock_total_sales_value'))->toBe(102.0);
    expect($response->json('data.recent_debt_transactions'))->toHaveCount(2);
    expect(collect($response->json('data.unpaid_debts'))->pluck('person_name')->all())
        ->toContain('Customer Debt', 'Supplier Debt');

    CarbonImmutable::setTestNow();
});

it('allows sellers to list debts for the mobile app', function () {
    $shop = Shop::factory()->create();
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller,
    ]);

    $debt = Debt::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'person_name' => 'Seller Visible Debt',
        'direction' => 'receivable',
        'balance' => 15,
    ]);

    Sanctum::actingAs($seller, ['debts:viewAny']);

    $this->getJson('/api/v1/debts')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $debt->id)
        ->assertJsonPath('data.0.direction', 'receivable');
});
