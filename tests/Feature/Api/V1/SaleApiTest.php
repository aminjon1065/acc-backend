<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('owner can create sale and stock decreases with debt calculation', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'discount' => 1,
            'paid' => 5,
            'payment_type' => 'cash',
            'notes' => 'Front counter sale',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'product')
        ->assertJsonPath('data.notes', 'Front counter sale')
        ->assertJsonPath('data.total', 19)
        ->assertJsonPath('data.debt', 14)
        ->assertJsonPath('data.items.0.cost_price', 4);

    expect((float) $product->fresh()->stock_quantity)->toBe(8.0);
});

test('sale fails when product stock is insufficient', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    // Pin cost_price so the validator's price-below-cost rule doesn't fire
    // first (default ProductFactory randomizes cost in 1..300). The test
    // targets the stock-check path.
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 1,
        'cost_price' => 5,
        'sale_price' => 10,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10,
                ],
            ],
        ])
        ->assertUnprocessable()
        // Per-item key contract — see SaleService::createSale.
        ->assertJsonValidationErrors('items.0.quantity');

    expect((float) $product->fresh()->stock_quantity)->toBe(1.0);
});

test('owner cannot create sale with product from another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);
    $productB = Product::factory()->create([
        'shop_id' => $shopB->id,
        'stock_quantity' => 50,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [
                [
                    'product_id' => $productB->id,
                    'quantity' => 1,
                    'price' => 10,
                ],
            ],
        ])
        ->assertStatus(422)
        // Mobile parses `items.{i}.product_id` to evict the ghost line.
        ->assertJsonValidationErrors('items.0.product_id');
});

test('sale calculates bulk pricing automatically when threshold is met and no explicit price is sent', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 100,
        'sale_price' => 10,
        'bulk_price' => 8,
        'bulk_threshold' => 10,
    ]);

    // Quantity exactly at threshold
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'discount' => 0,
            'paid' => 80,
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.total', 80)
        ->assertJsonPath('data.items.0.price', 8);

    // Quantity below threshold
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'discount' => 0,
            'paid' => 90,
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 9,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.total', 90)
        ->assertJsonPath('data.items.0.price', 10);
});

test('sale requires explicit price for manual pricing mode', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'pricing_mode' => 'manual',
        'sale_price' => 10,
        'stock_quantity' => 20,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertUnprocessable()
        // Per-item key — see SaleService::resolveProductPrice.
        ->assertJsonValidationErrors('items.0.price');
});

test('service sale persists metadata and service item naming', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'service',
            'customer_name' => 'Repair Client',
            'notes' => 'Includes diagnostics',
            'payment_type' => 'card',
            'items' => [
                [
                    'name' => 'Phone repair',
                    'unit' => 'job',
                    'quantity' => 1,
                    'price' => 50,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'service')
        ->assertJsonPath('data.notes', 'Includes diagnostics')
        ->assertJsonPath('data.items.0.service_name', 'Phone repair')
        ->assertJsonPath('data.items.0.product_id', null);
});

test('idempotency key replays cached response on duplicate request', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    $payload = [
        'type' => 'product',
        'discount' => 0,
        'paid' => 10,
        'payment_type' => 'cash',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'price' => 10],
        ],
    ];

    $idempotencyKey = 'test-idempotency-key-'.uniqid();

    $this->actingAs($owner, 'sanctum')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/sales', $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.total', 10);

    // Duplicate with same idempotency key must return the same response without creating a second sale
    $response = $this->actingAs($owner, 'sanctum')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/sales', $payload)
        ->assertSuccessful();

    $response->assertHeader('X-Idempotent-Replayed', 'true');

    // Stock should have been decremented only once
    expect((float) $product->fresh()->stock_quantity)->toBe(9.0);
});

test('idempotency key returns conflict for same key with different body', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    $idempotencyKey = 'test-idempotency-key-conflict-'.uniqid();

    $this->actingAs($owner, 'sanctum')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'discount' => 0,
            'paid' => 10,
            'payment_type' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 10],
            ],
        ])
        ->assertSuccessful();

    // Same key, different body must get 409
    $this->actingAs($owner, 'sanctum')
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'discount' => 5,
            'paid' => 10,
            'payment_type' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 10],
            ],
        ])
        ->assertStatus(409)
        ->assertJsonPath('error', 'idempotency_conflict');
});

test('owner can patch sale metadata without touching items', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 10,
        'cost_price' => 100,
        'sale_price' => 150,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'customer_name' => 'Alice',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'paid' => 300,
        ])
        ->assertSuccessful();
    $saleId = $createResponse->json('data.id');

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/sales/{$saleId}", [
            'customer_name' => 'Bob',
            'payment_type' => 'card',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.customer_name', 'Bob')
        ->assertJsonPath('data.payment_type', 'card')
        ->assertJsonPath('data.items.0.product_id', $product->id) // items untouched
        ->assertJsonPath('data.total', 300); // total preserved

    // Stock should NOT have moved — the partial patch must not touch items.
    expect($product->fresh()->stock_quantity)->toEqual(8);
});

test('seller cannot patch a sale', function () {
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
        'stock_quantity' => 10,
        'cost_price' => 100,
        'sale_price' => 150,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSuccessful();
    $saleId = $createResponse->json('data.id');

    $this->actingAs($seller, 'sanctum')
        ->patchJson("/api/v1/sales/{$saleId}", ['customer_name' => 'Snuck in'])
        ->assertForbidden();
});

test('owner can delete a sale and stock is restored', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 10,
        'cost_price' => 100,
        'sale_price' => 150,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])
        ->assertSuccessful();
    $saleId = $createResponse->json('data.id');

    expect($product->fresh()->stock_quantity)->toEqual(7);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/sales/{$saleId}")
        ->assertSuccessful();

    // Stock restored to pre-sale level.
    expect($product->fresh()->stock_quantity)->toEqual(10);

    // Sale is soft-deleted — index / show no longer returns it.
    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/sales/{$saleId}")
        ->assertNotFound();
});

test('seller cannot delete a sale', function () {
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
        'stock_quantity' => 5,
        'cost_price' => 100,
        'sale_price' => 150,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSuccessful();
    $saleId = $createResponse->json('data.id');

    $this->actingAs($seller, 'sanctum')
        ->deleteJson("/api/v1/sales/{$saleId}")
        ->assertForbidden();
});

test('return restocks once and rejects further returns beyond original quantity', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    // Sell 1 unit. Stock goes 10 → 9.
    $sale = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'paid' => 10,
            'payment_type' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 10],
            ],
        ])
        ->assertSuccessful()
        ->json('data');

    expect((float) $product->fresh()->stock_quantity)->toBe(9.0);

    // First return: 1 unit. Stock goes 9 → 10.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertSuccessful();

    expect((float) $product->fresh()->stock_quantity)->toBe(10.0);

    // Second return must be rejected — nothing left to return on this sale.
    // Without the fix, this would credit another unit and stock would tick to 11.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.quantity']);

    expect((float) $product->fresh()->stock_quantity)->toBe(10.0);
});

test('partial returns aggregate against the original sold quantity', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    // Sell 5 units. Stock 10 → 5.
    $sale = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'paid' => 50,
            'payment_type' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'price' => 10],
            ],
        ])
        ->assertSuccessful()
        ->json('data');

    // Return 2, then 2 more, then attempt 2 (only 1 left → rejected).
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertSuccessful();
    expect((float) $product->fresh()->stock_quantity)->toBe(7.0);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertSuccessful();
    expect((float) $product->fresh()->stock_quantity)->toBe(9.0);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertUnprocessable();
    expect((float) $product->fresh()->stock_quantity)->toBe(9.0);

    // Last unit can still be returned.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSuccessful();
    expect((float) $product->fresh()->stock_quantity)->toBe(10.0);
});

test('return rejects non-positive quantities', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 5,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    $sale = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'paid' => 10,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10]],
        ])
        ->json('data');

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 0]],
        ])
        ->assertUnprocessable();
});

test('sale accepts a mixed cart of products and services in one transaction', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 10,
        'cost_price' => 4,
        'sale_price' => 100,
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'paid' => 250,
            'payment_type' => 'cash',
            'items' => [
                // Real product line — counts toward stock + cost.
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 100],
                // Service line — no product_id, name is the service label.
                ['name' => 'Доставка и установка', 'quantity' => 1, 'price' => 150, 'unit' => 'услуга'],
            ],
        ])
        ->assertSuccessful()
        // A cart with any product line is tagged "product" so the sale
        // shows up in stock-affecting reports / the dashboard.
        ->assertJsonPath('data.type', 'product')
        ->assertJsonPath('data.total', 250)
        ->assertJsonPath('data.debt', 0);

    expect((float) $product->fresh()->stock_quantity)->toBe(9.0);

    $items = collect($response->json('data.items'));
    expect($items)->toHaveCount(2);
    expect($items->firstWhere('product_id', $product->id))->not->toBeNull();
    $serviceLine = $items->first(fn ($i) => $i['product_id'] === null);
    expect($serviceLine)->not->toBeNull();
    expect($serviceLine['service_name'])->toBe('Доставка и установка');
    expect((float) $serviceLine['total'])->toBe(150.0);
});

test('sale rejects a service line without a name', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'paid' => 100,
            'payment_type' => 'cash',
            'items' => [
                // Service line without name — should be rejected.
                ['quantity' => 1, 'price' => 100],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.name');
});
