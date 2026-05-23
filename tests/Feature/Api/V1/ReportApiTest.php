<?php

use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('owner can view sales, expenses, profit and stock reports', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 7,
        'low_stock_alert' => 10,
        'sale_price' => 100,
    ]);

    Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'total' => 120,
        'payment_type' => 'cash',
    ]);

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'total' => 80,
        'payment_type' => 'cash',
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'cost_price' => 6,
        'price' => 10,
        'total' => 50,
    ]);

    Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'total' => 40,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/sales')
        ->assertSuccessful()
        ->assertJsonPath('data.total_amount', 200)
        ->assertJsonPath('data.total_sales', 2)
        ->assertJsonPath('data.cash', 200)
        ->assertJsonPath('data.card', 0)
        ->assertJsonPath('data.transfer', 0)
        ->assertJsonPath('data.sales_total', 200)
        ->assertJsonPath('data.sales_count', 2);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/expenses')
        ->assertSuccessful()
        ->assertJsonPath('data.total_amount', 40)
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.expenses_total', 40);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/profit')
        ->assertSuccessful()
        ->assertJsonPath('data.total_sales', 200)
        ->assertJsonPath('data.total_cost', 30)
        ->assertJsonPath('data.total_expenses', 40)
        ->assertJsonPath('data.profit', 130)
        ->assertJsonPath('data.sales_total', 200)
        ->assertJsonPath('data.cost_of_goods_sold', 30)
        ->assertJsonPath('data.expenses_total', 40)
        ->assertJsonPath('data.profit', 130);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/stock')
        ->assertSuccessful()
        ->assertJsonPath('data.total_products', 1)
        ->assertJsonPath('data.total_value', 700)
        ->assertJsonPath('data.low_stock', 1)
        ->assertJsonPath('data.out_of_stock', 0)
        ->assertJsonPath('data.data.0.id', $product->id)
        ->assertJsonPath('data.products_count', 1)
        ->assertJsonPath('data.low_stock_products_count', 1);
});

test('seller profit report excludes cost of goods and computes profit as revenue minus expenses', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'sale_price' => 100,
    ]);

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'total' => 200,
        'payment_type' => 'cash',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 4,
        'cost_price' => 30,
        'price' => 50,
        'total' => 200,
    ]);
    Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'total' => 60,
    ]);

    $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/reports/profit')
        ->assertSuccessful()
        ->assertJsonPath('data.total_sales', 200)
        ->assertJsonPath('data.total_expenses', 60)
        // profit must equal revenue − expenses, NOT revenue − cost − expenses
        ->assertJsonPath('data.profit', 140)
        // every cost-derived field is zeroed so a seller can't back-calculate
        // cost_price from the response payload.
        ->assertJsonPath('data.total_cost', 0)
        ->assertJsonPath('data.cost_of_goods_sold', 0)
        ->assertJsonPath('data.cost_of_goods_sold_gross', 0)
        ->assertJsonPath('data.returns_cogs', 0);
});

test('owner report is scoped to own shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);

    Sale::factory()->create([
        'shop_id' => $shopA->id,
        'total' => 100,
    ]);

    Sale::factory()->create([
        'shop_id' => $shopB->id,
        'total' => 500,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/reports/sales')
        ->assertSuccessful()
        ->assertJsonPath('data.sales_total', 100);
});

test('sales report includes per-sale receipts with items for PDF export', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
        'name' => 'Owner Olga',
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 20,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    $saleId = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'customer_name' => 'Vasya Pupkin',
            'discount' => 5,
            'paid' => 15,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 10]],
        ])->assertSuccessful()->json('data.id');

    $payload = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/sales')
        ->assertSuccessful()
        ->json('data');

    $receipts = $payload['receipts'] ?? [];
    expect($receipts)->toHaveCount(1);
    $receipt = $receipts[0];
    expect($receipt['sale_id'])->toBe($saleId);
    expect($receipt['customer_name'])->toBe('Vasya Pupkin');
    expect($receipt['seller_name'])->toBe('Owner Olga');
    expect($receipt['payment_type'])->toBe('cash');
    expect((float) $receipt['discount'])->toBe(5.0);
    expect((float) $receipt['paid'])->toBe(15.0);
    expect((float) $receipt['debt'])->toBe(0.0);
    expect($receipt['items'])->toHaveCount(1);
    expect($receipt['items'][0]['product_id'])->toBe($product->id);
    expect((float) $receipt['items'][0]['quantity'])->toBe(2.0);
    expect((float) $receipt['items'][0]['cost_price'])->toBe(4.0);
});

test('sales report receipts hide cost_price for sellers', function () {
    $shop = Shop::factory()->create();
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 20,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/sales', [
            'paid' => 10,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10]],
        ])->assertSuccessful();

    $receipts = $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/reports/sales')
        ->assertSuccessful()
        ->json('data.receipts');

    expect($receipts)->toHaveCount(1);
    expect($receipts[0]['items'][0]['cost_price'])->toBeNull();
});

test('stock report exposes cost_price, sale_price, and both totals per row', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'code' => 'SKU-42',
        'unit' => 'шт',
        'stock_quantity' => 4,
        'low_stock_alert' => 2,
        'cost_price' => 30,
        'sale_price' => 50,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/stock')
        ->assertSuccessful()
        // sale-priced totals
        ->assertJsonPath('data.total_value', 200)
        ->assertJsonPath('data.data.0.id', $product->id)
        ->assertJsonPath('data.data.0.stock_quantity', 4)
        ->assertJsonPath('data.data.0.sale_price', 50)
        ->assertJsonPath('data.data.0.value', 200)
        // cost-priced totals
        ->assertJsonPath('data.total_cost_value', 120)
        ->assertJsonPath('data.data.0.cost_price', 30)
        ->assertJsonPath('data.data.0.cost_value', 120)
        // extended detail fields (for richer seller PDF export)
        ->assertJsonPath('data.data.0.code', 'SKU-42')
        ->assertJsonPath('data.data.0.unit', 'шт')
        ->assertJsonPath('data.data.0.low_stock_alert', 2);
});
