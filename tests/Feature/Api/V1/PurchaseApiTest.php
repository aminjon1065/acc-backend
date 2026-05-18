<?php

use App\Enums\PricingMode;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

test('owner can create purchase and stock increases', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 5,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'supplier_name' => 'Supplier A',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'price' => 3,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.total_amount', 30)
        ->assertJsonPath('data.items.0.product_id', $product->id);

    expect((float) $product->fresh()->stock_quantity)->toBe(15.0);
});

test('purchase markup promotes product to markup pricing mode', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'pricing_mode' => 'fixed',
        'markup_percent' => null,
        'cost_price' => 5,
        'sale_price' => 7,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10,
                    'markup_percent' => 40,
                ],
            ],
        ])
        ->assertSuccessful();

    $product->refresh();

    expect($product->pricing_mode)->toBe(PricingMode::Markup);
    expect((float) $product->markup_percent)->toBe(40.0);
    expect((float) $product->sale_price)->toBe(14.0);
});

test('owner cannot create purchase with product from another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);
    $productB = Product::factory()->create([
        'shop_id' => $shopB->id,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                [
                    'product_id' => $productB->id,
                    'quantity' => 1,
                    'price' => 100,
                ],
            ],
        ])
        ->assertNotFound();
});

test('owner can update purchase supplier without touching items', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 5,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'supplier_name' => 'Supplier A',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'price' => 10],
            ],
        ])
        ->assertSuccessful();
    $purchaseId = $createResponse->json('data.id');

    expect((float) $product->fresh()->stock_quantity)->toBe(8.0);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/purchases/{$purchaseId}", [
            'supplier_name' => 'Supplier B',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.supplier_name', 'Supplier B')
        ->assertJsonPath('data.total_amount', 30);

    // Items untouched, stock untouched.
    expect((float) $product->fresh()->stock_quantity)->toBe(8.0);
});

test('owner can replace purchase items and stock is adjusted', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $productA = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 0,
    ]);
    $productB = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 0,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 5, 'price' => 4],
            ],
        ])
        ->assertSuccessful();
    $purchaseId = $createResponse->json('data.id');

    expect((float) $productA->fresh()->stock_quantity)->toBe(5.0);

    // Replace 5×A with 2×B at price 7 — A is rolled back, B added.
    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/purchases/{$purchaseId}", [
            'items' => [
                ['product_id' => $productB->id, 'quantity' => 2, 'price' => 7],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.total_amount', 14);

    expect((float) $productA->fresh()->stock_quantity)->toBe(0.0);
    expect((float) $productB->fresh()->stock_quantity)->toBe(2.0);
});

test('owner can delete purchase and stock is rolled back', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
        'stock_quantity' => 2,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4, 'price' => 5],
            ],
        ])
        ->assertSuccessful();
    $purchaseId = $createResponse->json('data.id');

    expect((float) $product->fresh()->stock_quantity)->toBe(6.0);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/purchases/{$purchaseId}")
        ->assertSuccessful();

    // Stock returned to pre-purchase level.
    expect((float) $product->fresh()->stock_quantity)->toBe(2.0);

    // Soft-deleted — show returns 404.
    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/purchases/{$purchaseId}")
        ->assertNotFound();
});

test('seller cannot update or delete purchase', function () {
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
        'stock_quantity' => 0,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 1]],
        ])
        ->assertSuccessful();
    $purchaseId = $createResponse->json('data.id');

    $this->actingAs($seller, 'sanctum')
        ->patchJson("/api/v1/purchases/{$purchaseId}", ['supplier_name' => 'Hack'])
        ->assertForbidden();

    $this->actingAs($seller, 'sanctum')
        ->deleteJson("/api/v1/purchases/{$purchaseId}")
        ->assertForbidden();
});

test('purchase update rejects stale version with 409', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'stock_quantity' => 0,
    ]);

    $createResponse = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 5]],
        ])
        ->assertSuccessful();
    $purchaseId = $createResponse->json('data.id');
    $currentVersion = (int) $createResponse->json('data.version');

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/purchases/{$purchaseId}", [
            'version' => $currentVersion - 1,
            'supplier_name' => 'Stale',
        ])
        ->assertStatus(409);
});

test('super admin must provide shop_id when creating purchase', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
    ]);

    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/purchases', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 1,
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shop_id');
});
