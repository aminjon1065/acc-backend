<?php

use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;

// ─── Helper ───────────────────────────────────────────────────────────────────

function makeTwoShopsWithOwners(): array
{
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $ownerA = User::factory()->create(['shop_id' => $shopA->id, 'role' => UserRole::Owner->value]);
    $ownerB = User::factory()->create(['shop_id' => $shopB->id, 'role' => UserRole::Owner->value]);

    return [$shopA, $shopB, $ownerA, $ownerB];
}

// ─── Products isolation ───────────────────────────────────────────────────────

test('owner cannot view product from another shop', function () {
    [$shopA, $shopB, $ownerA] = makeTwoShopsWithOwners();

    $product = Product::factory()->create(['shop_id' => $shopB->id]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson("/api/v1/products/{$product->id}")
        ->assertNotFound();
});

test('owner cannot update product from another shop', function () {
    [$shopA, $shopB, $ownerA] = makeTwoShopsWithOwners();

    $product = Product::factory()->create(['shop_id' => $shopB->id]);

    $this->actingAs($ownerA, 'sanctum')
        ->putJson("/api/v1/products/{$product->id}", ['name' => 'Hacked'])
        ->assertNotFound();
});

test('seller cannot update or delete products', function () {
    $shop = Shop::factory()->create();
    $seller = User::factory()->create(['shop_id' => $shop->id, 'role' => UserRole::Seller->value]);
    $product = Product::factory()->create(['shop_id' => $shop->id]);

    $this->actingAs($seller, 'sanctum')
        ->putJson("/api/v1/products/{$product->id}", ['name' => 'Hacked'])
        ->assertForbidden();

    $this->actingAs($seller, 'sanctum')
        ->deleteJson("/api/v1/products/{$product->id}")
        ->assertForbidden();
});

test('product listing only returns products for own shop', function () {
    [$shopA, $shopB, $ownerA] = makeTwoShopsWithOwners();

    $myProduct = Product::factory()->create(['shop_id' => $shopA->id, 'name' => 'My Product']);
    Product::factory()->create(['shop_id' => $shopB->id, 'name' => 'Foreign Product']);

    $response = $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('My Product')
        ->and($names)->not->toContain('Foreign Product');
});

// ─── Sales isolation ──────────────────────────────────────────────────────────

test('owner cannot view sale from another shop', function () {
    [$shopA, $shopB, $ownerA, $ownerB] = makeTwoShopsWithOwners();

    $sale = Sale::factory()->create(['shop_id' => $shopB->id, 'user_id' => $ownerB->id]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson("/api/v1/sales/{$sale->id}")
        ->assertNotFound();
});

test('sale listing only returns sales for own shop', function () {
    [$shopA, $shopB, $ownerA, $ownerB] = makeTwoShopsWithOwners();

    Sale::factory()->create(['shop_id' => $shopA->id, 'user_id' => $ownerA->id]);
    Sale::factory()->create(['shop_id' => $shopB->id, 'user_id' => $ownerB->id]);

    $response = $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/sales')
        ->assertOk();

    $shopIds = collect($response->json('data'))->pluck('shop_id')->unique();
    expect($shopIds->toArray())->toEqual([$shopA->id]);
});

// ─── Purchases isolation ──────────────────────────────────────────────────────

test('owner cannot view purchase from another shop', function () {
    [$shopA, $shopB, $ownerA, $ownerB] = makeTwoShopsWithOwners();

    $product = Product::factory()->create(['shop_id' => $shopB->id]);
    $purchase = Purchase::factory()->create(['shop_id' => $shopB->id, 'user_id' => $ownerB->id]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson("/api/v1/purchases/{$purchase->id}")
        ->assertNotFound();
});

test('purchase listing only returns purchases for own shop', function () {
    [$shopA, $shopB, $ownerA, $ownerB] = makeTwoShopsWithOwners();

    Purchase::factory()->create(['shop_id' => $shopA->id, 'user_id' => $ownerA->id]);
    Purchase::factory()->create(['shop_id' => $shopB->id, 'user_id' => $ownerB->id]);

    $response = $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/purchases')
        ->assertOk();

    $shopIds = collect($response->json('data'))->pluck('shop_id')->unique();
    expect($shopIds->toArray())->toEqual([$shopA->id]);
});

// ─── Expenses isolation ───────────────────────────────────────────────────────

test('owner cannot view expense from another shop', function () {
    [$shopA, $shopB, $ownerA, $ownerB] = makeTwoShopsWithOwners();

    $expense = Expense::factory()->create(['shop_id' => $shopB->id, 'user_id' => $ownerB->id]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson("/api/v1/expenses/{$expense->id}")
        ->assertNotFound();
});

test('expense listing only returns expenses for own shop', function () {
    [$shopA, $shopB, $ownerA, $ownerB] = makeTwoShopsWithOwners();

    Expense::factory()->create(['shop_id' => $shopA->id, 'user_id' => $ownerA->id]);
    Expense::factory()->create(['shop_id' => $shopB->id, 'user_id' => $ownerB->id]);

    $response = $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/expenses')
        ->assertOk();

    $shopIds = collect($response->json('data'))->pluck('shop_id')->unique();
    expect($shopIds->toArray())->toEqual([$shopA->id]);
});

// ─── Debts isolation ─────────────────────────────────────────────────────────

test('owner cannot view debt from another shop', function () {
    [$shopA, $shopB, $ownerA, $ownerB] = makeTwoShopsWithOwners();

    $debt = Debt::factory()->create(['shop_id' => $shopB->id, 'user_id' => $ownerB->id]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson("/api/v1/debts/{$debt->id}")
        ->assertNotFound();
});

test('debt listing only returns debts for own shop', function () {
    [$shopA, $shopB, $ownerA, $ownerB] = makeTwoShopsWithOwners();

    Debt::factory()->create(['shop_id' => $shopA->id, 'user_id' => $ownerA->id]);
    Debt::factory()->create(['shop_id' => $shopB->id, 'user_id' => $ownerB->id]);

    $response = $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/debts')
        ->assertOk();

    $shopIds = collect($response->json('data'))->pluck('shop_id')->unique();
    expect($shopIds->toArray())->toEqual([$shopA->id]);
});

// ─── Super admin bypass ───────────────────────────────────────────────────────

test('super admin can view resources from any shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $superAdmin = User::factory()->create(['shop_id' => null, 'role' => UserRole::SuperAdmin->value]);
    $ownerB = User::factory()->create(['shop_id' => $shopB->id, 'role' => UserRole::Owner->value]);

    Product::factory()->create(['shop_id' => $shopA->id]);
    Product::factory()->create(['shop_id' => $shopB->id]);
    Sale::factory()->create(['shop_id' => $shopA->id, 'user_id' => $ownerB->id]);
    Sale::factory()->create(['shop_id' => $shopB->id, 'user_id' => $ownerB->id]);

    $productCount = $this->actingAs($superAdmin, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertOk()
        ->json('meta.total');

    // Super admin sees products from all shops (total >= 2)
    expect($productCount)->toBeGreaterThanOrEqual(2);
});
