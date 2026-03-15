<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

it('returns product movements ordered by latest activity', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner,
    ]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'name' => 'Tracked Product',
        'stock_quantity' => 11,
        'low_stock_alert' => 15,
    ]);

    $purchase = Purchase::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
    ]);
    $purchase->forceFill([
        'created_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
    ])->saveQuietly();

    $purchaseItem = PurchaseItem::query()->create([
        'shop_id' => $shop->id,
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => 15,
        'price' => 10,
        'total' => 150,
    ]);
    $purchaseItem->forceFill([
        'created_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
    ])->saveQuietly();

    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
    ]);
    $sale->forceFill([
        'created_at' => CarbonImmutable::parse('2026-03-12 12:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-12 12:00:00'),
    ])->saveQuietly();

    $saleItem = SaleItem::query()->create([
        'shop_id' => $shop->id,
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 4,
        'price' => 16,
        'cost_price' => 10,
        'total' => 64,
    ]);
    $saleItem->forceFill([
        'created_at' => CarbonImmutable::parse('2026-03-12 12:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-12 12:00:00'),
    ])->saveQuietly();

    Sanctum::actingAs($owner, ['products:view']);

    $response = $this->getJson("/api/v1/products/{$product->id}/movements")
        ->assertSuccessful()
        ->assertJsonPath('data.product_id', $product->id)
        ->assertJsonPath('data.movements.0.type', 'sale')
        ->assertJsonPath('data.movements.0.reference_id', $sale->id)
        ->assertJsonPath('data.movements.1.type', 'purchase')
        ->assertJsonPath('data.movements.1.reference_id', $purchase->id);

    expect((float) $response->json('data.current_stock'))->toBe(11.0);
});

it('supports product mobile filters and low stock flag', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner,
    ]);

    Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'name' => 'Alpha Cola',
        'code' => 'A-1',
        'stock_quantity' => 2,
        'low_stock_alert' => 5,
    ]);

    Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'name' => 'Zero Stock',
        'code' => 'Z-1',
        'stock_quantity' => 0,
        'low_stock_alert' => 1,
    ]);

    Sanctum::actingAs($owner, ['products:viewAny']);

    $this->getJson('/api/v1/products?search=Alpha&stock_status=low_stock')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Alpha Cola')
        ->assertJsonPath('data.0.is_low_stock', true);
});
