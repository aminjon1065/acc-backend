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

    // Sellers must not see margin / cost data — DashboardController strips
    // period_expenses_total, period_cogs, period_profit, and stock_total_cost
    // from the response on purpose. Owners and super admins still receive
    // them; that's covered by other tests.
    expect($response->json('data.period_expenses_total'))->toBeNull();
    expect($response->json('data.period_cogs'))->toBeNull();
    expect($response->json('data.period_profit'))->toBeNull();
    expect($response->json('data.stock_total_cost'))->toBeNull();

    expect((float) $response->json('data.debts_receivable'))->toBe(20.0);
    expect((float) $response->json('data.debts_payable'))->toBe(12.0);
    expect((float) $response->json('data.debts_net'))->toBe(8.0);
    expect((float) $response->json('data.stock_total_qty'))->toBe(12.0);
    expect((float) $response->json('data.stock_total_sales_value'))->toBe(102.0);
    expect($response->json('data.recent_debt_transactions'))->toHaveCount(2);
    expect(collect($response->json('data.unpaid_debts'))->pluck('person_name')->all())
        ->toContain('Customer Debt', 'Supplier Debt');

    CarbonImmutable::setTestNow();
});

it('attributes owner-processed returns to the original seller on the seller dashboard', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 10:00:00'));

    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner,
    ]);
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'cost_price' => 4,
        'sale_price' => 10,
        'stock_quantity' => 10,
    ]);

    // Seller rang up a sale of 6 units.
    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'total' => 60,
        'paid' => 60,
        'payment_type' => 'cash',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 6,
        'cost_price' => 4,
        'price' => 10,
        'total' => 60,
    ]);

    // OWNER processes a 1-unit return against the seller's sale.
    $return = \App\Models\SaleReturn::query()->create([
        'shop_id' => $shop->id,
        'sale_id' => $sale->id,
        'user_id' => $owner->id,
        'refund_method' => 'cash',
        'total' => 10,
    ]);
    \App\Models\SaleReturnItem::query()->create([
        'shop_id' => $shop->id,
        'sale_return_id' => $return->id,
        'product_id' => $product->id,
        'name' => 'unit',
        'quantity' => 1,
        'price' => 10,
        'total' => 10,
    ]);

    Sanctum::actingAs($seller, ['dashboard:view']);

    $payload = $this->getJson('/api/v1/dashboard?period=day')
        ->assertSuccessful()
        ->json('data');

    // Net revenue must reflect the return: 60 − 10 = 50.
    expect((float) $payload['period_sales_total'])->toBe(50.0);
    expect((float) $payload['period_returns_total'])->toBe(10.0);

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

it('treats the week period as a rolling last-7-days window', function () {
    // Wednesday 2026-06-17. Last 7 days = 06-11 .. 06-17.
    // The calendar week (Mon–Sun) would be 06-15 .. 06-21, which excludes a
    // sale made on Sat 06-13 — but that sale IS within the last 7 days and
    // must show under "week".
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-17 12:00:00'));

    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner,
    ]);

    Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'total' => 250,
        'paid' => 250,
        'debt' => 0,
        'discount' => 0,
        'created_at' => CarbonImmutable::parse('2026-06-13 10:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-06-13 10:00:00'),
    ]);

    Sanctum::actingAs($owner, ['dashboard:view']);

    $data = $this->getJson('/api/v1/dashboard?period=week')
        ->assertSuccessful()
        ->json('data');

    expect($data['date_from'])->toBe('2026-06-11');
    expect($data['date_to'])->toBe('2026-06-17');
    expect((float) $data['period_sales_total'])->toBe(250.0);

    CarbonImmutable::setTestNow();
});
