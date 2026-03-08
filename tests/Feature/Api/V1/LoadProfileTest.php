<?php

use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

it('handles baseline load profile for core listing and report endpoints', function () {
    if (! env('RUN_PERFORMANCE_TESTS', false)) {
        $this->markTestSkipped('Set RUN_PERFORMANCE_TESTS=1 to run performance scenario.');
    }

    $shopsCount = (int) env('LOAD_TEST_SHOPS', 12);
    $productsPerShop = (int) env('LOAD_TEST_PRODUCTS_PER_SHOP', 40);
    $salesPerShop = (int) env('LOAD_TEST_SALES_PER_SHOP', 25);
    $expensesPerShop = (int) env('LOAD_TEST_EXPENSES_PER_SHOP', 15);

    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $owners = collect();
    $productsByShop = [];

    foreach (range(1, $shopsCount) as $index) {
        $shop = Shop::factory()->create([
            'name' => "Load Shop {$index}",
        ]);

        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => UserRole::Owner->value,
            'email' => "owner{$index}@load.test",
        ]);

        $owners->push($owner);

        $products = Product::factory()->count($productsPerShop)->create([
            'shop_id' => $shop->id,
            'created_by' => $owner->id,
            'stock_quantity' => 500,
            'low_stock_alert' => 20,
        ])->values();

        $productsByShop[$shop->id] = $products;

        foreach (range(1, $salesPerShop) as $saleIndex) {
            $sale = Sale::factory()->create([
                'shop_id' => $shop->id,
                'user_id' => $owner->id,
                'total' => 0,
                'discount' => 0,
                'paid' => 0,
                'debt' => 0,
            ]);

            $chosenProducts = $products->shuffle()->take(3)->values();
            $saleTotal = 0;

            foreach ($chosenProducts as $product) {
                $quantity = 2.0;
                $price = (float) $product->sale_price;
                $lineTotal = $quantity * $price;

                SaleItem::factory()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'cost_price' => (float) $product->cost_price,
                    'total' => $lineTotal,
                ]);

                $saleTotal += $lineTotal;
            }

            $sale->update([
                'total' => $saleTotal,
                'paid' => $saleTotal,
                'debt' => 0,
            ]);
        }

        Expense::factory()->count($expensesPerShop)->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
        ]);
    }

    $sampleOwner = $owners->first();
    $sampleShopProducts = $productsByShop[$sampleOwner->shop_id];
    $sampleProductId = $sampleShopProducts->first()->id;

    $this->actingAs($sampleOwner, 'sanctum')
        ->getJson('/api/v1/products?limit=20')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data', 'links', 'meta']);

    $this->actingAs($sampleOwner, 'sanctum')
        ->getJson('/api/v1/products/'.$sampleProductId)
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->actingAs($sampleOwner, 'sanctum')
        ->getJson('/api/v1/reports/sales')
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->actingAs($sampleOwner, 'sanctum')
        ->getJson('/api/v1/reports/profit')
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/reports/stock')
        ->assertSuccessful()
        ->assertJsonPath('success', true);
})->group('performance');
