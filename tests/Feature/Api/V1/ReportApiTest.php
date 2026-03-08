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
    ]);

    Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'total' => 120,
    ]);

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'total' => 80,
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
        ->assertJsonPath('data.sales_total', 200)
        ->assertJsonPath('data.sales_count', 2);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/expenses')
        ->assertSuccessful()
        ->assertJsonPath('data.expenses_total', 40);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/profit')
        ->assertSuccessful()
        ->assertJsonPath('data.sales_total', 200)
        ->assertJsonPath('data.cost_of_goods_sold', 30)
        ->assertJsonPath('data.expenses_total', 40)
        ->assertJsonPath('data.profit', 130);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/stock')
        ->assertSuccessful()
        ->assertJsonPath('data.products_count', 1)
        ->assertJsonPath('data.low_stock_products_count', 1);
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
