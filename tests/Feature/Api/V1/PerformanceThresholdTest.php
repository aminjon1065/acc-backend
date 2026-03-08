<?php

use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

it('keeps core api endpoints under baseline response-time threshold', function () {
    $maxEndpointMs = (int) env('PERF_ENDPOINT_MAX_MS', 1500);

    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $products = Product::factory()->count(50)->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 200,
    ]);

    foreach (range(1, 40) as $index) {
        $sale = Sale::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'total' => 0,
            'paid' => 0,
            'debt' => 0,
        ]);

        $subset = $products->shuffle()->take(2)->values();
        $runningTotal = 0;

        foreach ($subset as $product) {
            $lineTotal = 2 * (float) $product->sale_price;
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'price' => (float) $product->sale_price,
                'cost_price' => (float) $product->cost_price,
                'total' => $lineTotal,
            ]);
            $runningTotal += $lineTotal;
        }

        $sale->update([
            'total' => $runningTotal,
            'paid' => $runningTotal,
        ]);
    }

    Expense::factory()->count(20)->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
    ]);

    $checkEndpoint = function (User $user, string $path) use ($maxEndpointMs): void {
        $start = hrtime(true);
        $response = $this->actingAs($user, 'sanctum')->getJson($path);
        $elapsedMs = (int) ((hrtime(true) - $start) / 1_000_000);

        $response
            ->assertSuccessful()
            ->assertJsonPath('success', true);

        expect($elapsedMs)->toBeLessThanOrEqual(
            $maxEndpointMs,
            "Endpoint [{$path}] exceeded {$maxEndpointMs}ms: {$elapsedMs}ms."
        );
    };

    $checkEndpoint($owner, '/api/v1/products?limit=20');
    $checkEndpoint($owner, '/api/v1/reports/sales');
    $checkEndpoint($owner, '/api/v1/reports/profit');
    $checkEndpoint($admin, '/api/v1/reports/stock');
});
