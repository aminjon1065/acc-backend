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

test('sale show endpoint exposes shop_name for receipt rendering', function () {
    $shop = Shop::factory()->create(['name' => 'Branch Karatag']);
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 5,
        'cost_price' => 2,
        'sale_price' => 8,
    ]);

    $saleId = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'paid' => 8,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 8]],
        ])
        ->assertSuccessful()
        ->json('data.id');

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/sales/{$saleId}")
        ->assertSuccessful()
        ->assertJsonPath('data.shop_id', $shop->id)
        ->assertJsonPath('data.shop_name', 'Branch Karatag');
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

test('owner can patch sale notes without touching items', function () {
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

    $saleId = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'notes' => 'Изначальная заметка',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid' => 150,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.notes', 'Изначальная заметка')
        ->json('data.id');

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/sales/{$saleId}", [
            'notes' => 'Обновлённая заметка',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.notes', 'Обновлённая заметка');

    // Clearing the note must persist too (null, not the stale value).
    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/sales/{$saleId}", [
            'notes' => null,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.notes', null);
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

test('sales list supports search by customer name, notes, and seller name', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
        'name' => 'Owner Olivia',
    ]);
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
        'name' => 'Cashier Carlos',
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 50,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    $matching = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'customer_name' => 'Vasya Pupkin',
            'paid' => 10,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10]],
        ])
        ->assertSuccessful()
        ->json('data.id');

    $note = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'paid' => 10,
            'payment_type' => 'cash',
            'notes' => 'Холодильник со склада',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10]],
        ])
        ->assertSuccessful()
        ->json('data.id');

    $sellerSale = $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/sales', [
            'paid' => 10,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10]],
        ])
        ->assertSuccessful()
        ->json('data.id');

    // Other sale that should NOT match any of the three queries.
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'paid' => 10,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10]],
        ])
        ->assertSuccessful();

    $byCustomer = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/sales?search=Vasya')
        ->assertSuccessful()
        ->json('data');
    expect(collect($byCustomer)->pluck('id')->all())->toEqual([$matching]);

    $byNote = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/sales?search='.urlencode('Холодильник'))
        ->assertSuccessful()
        ->json('data');
    expect(collect($byNote)->pluck('id')->all())->toEqual([$note]);

    $bySeller = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/sales?search=Carlos')
        ->assertSuccessful()
        ->json('data');
    expect(collect($bySeller)->pluck('id')->all())->toEqual([$sellerSale]);
});

test('sales list filters by payment type, sale type, and debt-only flag', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 100,
        'cost_price' => 4,
        'sale_price' => 10,
    ]);

    // Mix of payment types, types, and debt presence.
    $cashFull = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'paid' => 10,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10]],
        ])->json('data.id');

    $cardDebt = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'paid' => 4,
            'payment_type' => 'card',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10]],
        ])->json('data.id');

    $service = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'paid' => 20,
            'payment_type' => 'transfer',
            'items' => [['name' => 'Доставка', 'quantity' => 1, 'price' => 20, 'unit' => 'усл.']],
        ])->json('data.id');

    // payment_type filter
    $cards = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/sales?payment_type=card')
        ->assertSuccessful()
        ->json('data');
    expect(collect($cards)->pluck('id')->all())->toEqual([$cardDebt]);

    // type filter
    $services = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/sales?type=service')
        ->assertSuccessful()
        ->json('data');
    expect(collect($services)->pluck('id')->all())->toEqual([$service]);

    // debt_only filter
    $withDebt = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/sales?debt_only=1')
        ->assertSuccessful()
        ->json('data');
    expect(collect($withDebt)->pluck('id')->all())->toEqual([$cardDebt]);

    // Combined: cash + product type — only cashFull should remain.
    $combined = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/sales?payment_type=cash&type=product')
        ->assertSuccessful()
        ->json('data');
    expect(collect($combined)->pluck('id')->all())->toEqual([$cashFull]);
});

test('profit report subtracts returns from sales and COGS', function () {
    $shop = \App\Models\Shop::factory()->create();
    $owner = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Owner->value,
    ]);
    $product = \App\Models\Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 10,
        'cost_price' => 40,
        'sale_price' => 100,
    ]);

    // Sell 2 units, then return 1.
    $sale = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertSuccessful()
        ->json('data');
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSuccessful();

    $today = now()->toDateString();
    $report = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/reports/profit?date_from={$today}&date_to={$today}")
        ->assertSuccessful()
        ->json('data');

    // Gross sale = 200 (2 × 100), return = 100 (1 × 100) → net 100.
    // Gross COGS = 80 (2 × 40), returns_cogs = 40 (1 × 40) → net 40.
    // Profit = 100 - 40 - 0 = 60.
    expect((float) $report['sales_gross'])->toBe(200.0);
    expect((float) $report['returns_total'])->toBe(100.0);
    expect((float) $report['returns_cogs'])->toBe(40.0);
    expect((float) $report['sales_total'])->toBe(100.0);
    expect((float) $report['cost_of_goods_sold'])->toBe(40.0);
    expect((float) $report['profit'])->toBe(60.0);
});

test('sales report exposes returns_total and net sales', function () {
    $shop = \App\Models\Shop::factory()->create();
    $owner = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Owner->value,
    ]);
    $product = \App\Models\Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 5,
        'cost_price' => 10,
        'sale_price' => 50,
    ]);

    $sale = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])
        ->assertSuccessful()
        ->json('data');
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertSuccessful();

    $today = now()->toDateString();
    $report = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/reports/sales?date_from={$today}&date_to={$today}")
        ->assertSuccessful()
        ->json('data');

    // Gross = 150 (3 × 50), returns = 100 (2 × 50), net = 50.
    expect((float) $report['sales_gross'])->toBe(150.0);
    expect((float) $report['returns_total'])->toBe(100.0);
    expect((float) $report['total_amount'])->toBe(50.0);

    // Per-line returns breakdown — mobile renders this as the "Возвраты"
    // table in the PDF export. One row per `sale_return_items` line.
    expect($report['returns'])->toBeArray()->toHaveCount(1);
    $returnRow = $report['returns'][0];
    expect((string) $returnRow['sale_id'])->toBe((string) $sale['id']);
    expect((float) $returnRow['quantity'])->toBe(2.0);
    expect((float) $returnRow['price'])->toBe(50.0);
    expect((float) $returnRow['total'])->toBe(100.0);
    expect($returnRow['seller_name'])->toBe($owner->name);
    expect($returnRow['product_name'])->toBe($product->name);
});

test('sales report returns array is empty when there are no refunds', function () {
    $shop = \App\Models\Shop::factory()->create();
    $owner = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Owner->value,
    ]);
    $product = \App\Models\Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 5,
        'cost_price' => 10,
        'sale_price' => 50,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSuccessful();

    $today = now()->toDateString();
    $report = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/reports/sales?date_from={$today}&date_to={$today}")
        ->assertSuccessful()
        ->json('data');

    expect((float) $report['returns_total'])->toBe(0.0);
    expect($report['returns'])->toBe([]);
});

test('stock report period balance: opening + incoming - outgoing + returned = closing', function () {
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-15 12:00:00'));

    $shop = \App\Models\Shop::factory()->create();
    $owner = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Owner->value,
    ]);
    $product = \App\Models\Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 100,
        'cost_price' => 5,
        'sale_price' => 10,
    ]);

    // Purchase 20 units inside the window (period 06-10 → 06-20).
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-12 09:00:00'));
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [['product_id' => $product->id, 'quantity' => 20, 'price' => 5]],
        ])
        ->assertSuccessful();

    // Sell 8 inside the window.
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-14 10:00:00'));
    $sale = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 8]],
        ])
        ->assertSuccessful()
        ->json('data');

    // Return 3 inside the window.
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-14 16:00:00'));
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])
        ->assertSuccessful();

    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-25 12:00:00'));
    $report = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/stock?date_from=2026-06-10&date_to=2026-06-20')
        ->assertSuccessful()
        ->json('data');

    expect($report['mode'])->toBe('period');
    $row = collect($report['data'])->firstWhere('id', $product->id);
    expect($row)->not->toBeNull();
    // Current stock now = 100 + 20 - 8 + 3 = 115
    // Closing at 06-20 = 115 (no movements after 06-20).
    // Opening at 06-10 = 115 - 20 + 8 - 3 = 100.
    expect((float) $row['opening_qty'])->toBe(100.0);
    expect((float) $row['incoming_qty'])->toBe(20.0);
    expect((float) $row['outgoing_qty'])->toBe(8.0);
    expect((float) $row['returned_qty'])->toBe(3.0);
    expect((float) $row['closing_qty'])->toBe(115.0);
    // Sanity: opening + in - out + returned = closing
    expect($row['opening_qty'] + $row['incoming_qty'] - $row['outgoing_qty'] + $row['returned_qty'])
        ->toBe($row['closing_qty']);

    \Carbon\Carbon::setTestNow();
});

test('stock report without dates returns snapshot mode', function () {
    $shop = \App\Models\Shop::factory()->create();
    $owner = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Owner->value,
    ]);
    \App\Models\Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 50,
    ]);

    $report = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/reports/stock')
        ->assertSuccessful()
        ->json('data');

    expect($report['mode'])->toBe('snapshot');
    expect((float) $report['stock_quantity_total'])->toBe(50.0);
});

test('seller report shows only their own sales and expenses', function () {
    $shop = \App\Models\Shop::factory()->create();
    $sellerA = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Seller->value,
    ]);
    $sellerB = \App\Models\User::factory()->create([
        'shop_id' => $shop->id,
        'role' => \App\UserRole::Seller->value,
    ]);
    $product = \App\Models\Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 100,
        'cost_price' => 10,
        'sale_price' => 50,
    ]);

    // Seller A sells 2 units; Seller B sells 5.
    $this->actingAs($sellerA, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertSuccessful();
    $this->actingAs($sellerB, 'sanctum')
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])
        ->assertSuccessful();

    // Seller A records an expense; Seller B records another.
    $this->actingAs($sellerA, 'sanctum')
        ->postJson('/api/v1/expenses', [
            'name' => 'A expense',
            'quantity' => 1,
            'price' => 30,
        ])
        ->assertSuccessful();
    $this->actingAs($sellerB, 'sanctum')
        ->postJson('/api/v1/expenses', [
            'name' => 'B expense',
            'quantity' => 1,
            'price' => 70,
        ])
        ->assertSuccessful();

    $today = now()->toDateString();

    // Sales report for seller A: only their 2 × 50 = 100.
    $salesA = $this->actingAs($sellerA, 'sanctum')
        ->getJson("/api/v1/reports/sales?date_from={$today}&date_to={$today}")
        ->assertSuccessful()
        ->json('data');
    expect((float) $salesA['total_amount'])->toBe(100.0);
    expect((int) $salesA['total_sales'])->toBe(1);

    // Profit report for seller A: cost-of-goods is hidden from sellers, so
    // profit ≡ revenue − expenses = 100 − 30 = 70. All cost-related fields
    // must come back as 0 to prevent reverse-calculating cost_price.
    $profitA = $this->actingAs($sellerA, 'sanctum')
        ->getJson("/api/v1/reports/profit?date_from={$today}&date_to={$today}")
        ->assertSuccessful()
        ->json('data');
    expect((float) $profitA['total_sales'])->toBe(100.0);
    expect((float) $profitA['cost_of_goods_sold'])->toBe(0.0);
    expect((float) $profitA['total_cost'])->toBe(0.0);
    expect((float) $profitA['expenses_total'])->toBe(30.0);
    expect((float) $profitA['profit'])->toBe(70.0);

    // Expenses report for seller A: only A expense.
    $expensesA = $this->actingAs($sellerA, 'sanctum')
        ->getJson("/api/v1/reports/expenses?date_from={$today}&date_to={$today}")
        ->assertSuccessful()
        ->json('data');
    expect((float) $expensesA['total_amount'])->toBe(30.0);
    expect((int) $expensesA['count'])->toBe(1);
});

test('deleting a sale restores stock net of an existing return', function () {
    // Start 10, sell 4 (→6), return 1 (→7). Deleting the sale must bring
    // stock back to 10 — NOT 11. The returned unit was already credited to
    // stock by the return, so the delete must only restore the still-out 3.
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

    $sale = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'paid' => 40,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'price' => 10]],
        ])
        ->assertSuccessful()
        ->json('data');
    expect((float) $product->fresh()->stock_quantity)->toBe(6.0);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSuccessful();
    expect((float) $product->fresh()->stock_quantity)->toBe(7.0);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/sales/{$sale['id']}")
        ->assertSuccessful();

    expect((float) $product->fresh()->stock_quantity)->toBe(10.0);
});

test('deleting a sale clears its returns from net sales reports and dashboard', function () {
    // Sale 600 with a 150 return nets to 450. Deleting the sale must leave
    // every figure at 0 — the orphaned refund must not keep subtracting from
    // net revenue (the old bug left the totals at -150).
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 50,
        'sale_price' => 150,
    ]);

    $sale = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'paid' => 600,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'price' => 150]],
        ])
        ->assertSuccessful()
        ->json('data');
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSuccessful();

    $today = now()->toDateString();

    $before = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/reports/sales?date_from={$today}&date_to={$today}")
        ->assertSuccessful()
        ->json('data');
    expect((float) $before['sales_gross'])->toBe(600.0);
    expect((float) $before['returns_total'])->toBe(150.0);
    expect((float) $before['total_amount'])->toBe(450.0);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/sales/{$sale['id']}")
        ->assertSuccessful();

    $report = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/reports/sales?date_from={$today}&date_to={$today}")
        ->assertSuccessful()
        ->json('data');
    expect((float) $report['sales_gross'])->toBe(0.0);
    expect((float) $report['returns_total'])->toBe(0.0);
    expect((float) $report['total_amount'])->toBe(0.0);
    expect($report['returns'])->toBe([]);

    $dashboard = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/dashboard?period=day')
        ->assertSuccessful()
        ->json('data');
    expect((float) $dashboard['period_sales_total'])->toBe(0.0);
    expect((float) $dashboard['period_returns_total'])->toBe(0.0);
    expect((float) $dashboard['period_profit'])->toBe(0.0);

    // Profit report must net to 0 too (no dangling COGS reversal either).
    $profit = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/reports/profit?date_from={$today}&date_to={$today}")
        ->assertSuccessful()
        ->json('data');
    expect((float) $profit['sales_total'])->toBe(0.0);
    expect((float) $profit['returns_total'])->toBe(0.0);
    expect((float) $profit['profit'])->toBe(0.0);
});

test('deleting an owner sale with a return keeps the original seller dashboard consistent', function () {
    // Seller rings up 600, owner refunds 150 → seller dashboard nets 450.
    // Owner then deletes the sale → seller dashboard must show 0, not -150.
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
        'created_by' => $owner->id,
        'stock_quantity' => 10,
        'cost_price' => 50,
        'sale_price' => 150,
    ]);

    $sale = $this->actingAs($seller, 'sanctum')
        ->postJson('/api/v1/sales', [
            'type' => 'product',
            'paid' => 600,
            'payment_type' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'price' => 150]],
        ])
        ->assertSuccessful()
        ->json('data');
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSuccessful();

    // Seller sees the net (Test 1 — owner-processed return attributed to seller).
    $sellerBefore = $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/dashboard?period=day')
        ->assertSuccessful()
        ->json('data');
    expect((float) $sellerBefore['period_sales_total'])->toBe(450.0);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/sales/{$sale['id']}")
        ->assertSuccessful();

    $sellerAfter = $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/dashboard?period=day')
        ->assertSuccessful()
        ->json('data');
    expect((float) $sellerAfter['period_sales_total'])->toBe(0.0);
    expect((float) $sellerAfter['period_returns_total'])->toBe(0.0);
});
