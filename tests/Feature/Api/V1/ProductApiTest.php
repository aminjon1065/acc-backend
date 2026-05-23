<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('shop owner can create and list own products', function () {
    Storage::fake('public');

    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $image = UploadedFile::fake()->image('coffee.jpg');

    $this->actingAs($owner, 'sanctum')
        ->post('/api/v1/products', [
            'name' => 'Coffee',
            'code' => 'COF-001',
            'unit' => 'piece',
            'cost_price' => 5,
            'sale_price' => 10,
            'pricing_mode' => 'fixed',
            'bulk_price' => 8,
            'bulk_threshold' => 12,
            'stock_quantity' => 100,
            'low_stock_alert' => 5,
            'image' => $image,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Coffee')
        ->assertJsonPath('data.shop_id', $shop->id)
        ->assertJsonPath('data.pricing_mode', 'fixed')
        ->assertJsonPath('data.bulk_price', 8)
        ->assertJsonPath('data.bulk_threshold', 12)
        ->assertJsonPath('data.image_path', fn (?string $path) => is_string($path) && str_starts_with($path, "products/{$shop->id}/"))
        ->assertJsonPath('data.image_url', fn (?string $url) => is_string($url) && str_contains($url, '/storage/products/'.$shop->id.'/'))
        ->assertJsonPath('data.photo_url', fn (?string $url) => is_string($url) && str_contains($url, '/storage/products/'.$shop->id.'/'));

    $product = Product::query()->firstOrFail();

    expect($product->image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($product->image_path);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('shop owner can create markup-based product and sale price is derived from cost price', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/products', [
            'name' => 'Markup Product',
            'cost_price' => 20,
            'pricing_mode' => 'markup',
            'markup_percent' => 25,
            'stock_quantity' => 15,
        ])
        ->assertCreated()
        ->assertJsonPath('data.pricing_mode', 'markup')
        ->assertJsonPath('data.markup_percent', 25)
        ->assertJsonPath('data.sale_price', 25);
});

test('shop owner can create product image from mobile photo field', function () {
    Storage::fake('public');

    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->post('/api/v1/products', [
            'name' => 'Mobile Photo Product',
            'cost_price' => 5,
            'sale_price' => 10,
            'stock_quantity' => 3,
            'photo' => UploadedFile::fake()->image('mobile-photo.jpg'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.image_path', fn (?string $path) => is_string($path) && str_starts_with($path, "products/{$shop->id}/"))
        ->assertJsonPath('data.photo_url', fn (?string $url) => is_string($url) && str_contains($url, '/storage/products/'.$shop->id.'/'));

    $product = Product::query()->where('name', 'Mobile Photo Product')->firstOrFail();

    expect($product->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($product->image_path);
});

test('shop owner can replace product image', function () {
    Storage::fake('public');

    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'image_path' => UploadedFile::fake()->image('old.jpg')->store("products/{$shop->id}", 'public'),
    ]);
    $oldImagePath = $product->image_path;

    $newImage = UploadedFile::fake()->image('new.jpg');

    $this->actingAs($owner, 'sanctum')
        ->post('/api/v1/products/'.$product->id, [
            '_method' => 'PATCH',
            'image' => $newImage,
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.image_path', fn (?string $path) => is_string($path) && $path !== $oldImagePath);

    $product->refresh();

    Storage::disk('public')->assertMissing($oldImagePath);
    Storage::disk('public')->assertExists($product->image_path);
});

test('shop owner can replace product image from mobile photo field', function () {
    Storage::fake('public');

    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'image_path' => UploadedFile::fake()->image('old-mobile.jpg')->store("products/{$shop->id}", 'public'),
    ]);
    $oldImagePath = $product->image_path;

    $this->actingAs($owner, 'sanctum')
        ->post('/api/v1/products/'.$product->id, [
            '_method' => 'PATCH',
            'photo' => UploadedFile::fake()->image('new-mobile.jpg'),
        ])
        ->assertOk()
        ->assertJsonPath('data.image_path', fn (?string $path) => is_string($path) && $path !== $oldImagePath);

    $product->refresh();

    Storage::disk('public')->assertMissing($oldImagePath);
    Storage::disk('public')->assertExists($product->image_path);
});

test('owner cannot access product from another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $ownerA = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);

    $productInShopB = Product::factory()->create([
        'shop_id' => $shopB->id,
    ]);

    $this->actingAs($ownerA, 'sanctum')
        ->getJson('/api/v1/products/'.$productInShopB->id)
        ->assertNotFound();
});

test('super admin can access products from all shops', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    Product::factory()->count(2)->create([
        'shop_id' => $shopA->id,
    ]);

    Product::factory()->count(3)->create([
        'shop_id' => $shopB->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/products?limit=10')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

test('multi-shop owner can narrow product list to one shop via shop_id', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shopA->id,
        'role' => UserRole::Owner->value,
    ]);
    // Make `accessibleShopIds()` return [A, B] — the owner owns both shops.
    $shopA->update(['owner_id' => $owner->id]);
    $shopB->update(['owner_id' => $owner->id]);

    Product::factory()->count(2)->create(['shop_id' => $shopA->id]);
    Product::factory()->count(3)->create(['shop_id' => $shopB->id]);

    // Without shop_id: sees all 5 across both owned shops.
    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products?limit=10')
        ->assertOk()
        ->assertJsonCount(5, 'data');

    // With shop_id=B: narrows to that shop's catalog. Regression for the
    // create-purchase 404 — the picker must only show SKUs from the
    // shop the user is buying for.
    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products?limit=10&shop_id='.$shopB->id)
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('products ids endpoint returns id+updated_at scoped to actor', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => UserRole::Owner->value]);
    $shop->update(['owner_id' => $owner->id]);

    $a = Product::factory()->create(['shop_id' => $shop->id]);
    $b = Product::factory()->create(['shop_id' => $shop->id]);

    // Product in another shop — out of scope.
    $otherShop = Shop::factory()->create();
    Product::factory()->create(['shop_id' => $otherShop->id]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/products/ids')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($a->id)->toContain($b->id);
    expect(count($ids))->toBe(2);
    expect($response->json('data.0'))->toHaveKey('updated_at');
});

test('deleting a product with no history hard-deletes the row and frees its code for reuse', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);

    $original = Product::factory()->create([
        'shop_id' => $shop->id,
        'code' => '2400',
        'name' => 'Old name',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/products/{$original->id}")
        ->assertOk();

    expect(Product::query()->withTrashed()->where('shop_id', $shop->id)->where('code', '2400')->count())->toBe(0);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/products', [
            'name' => 'Пастел',
            'code' => '2400',
            'unit' => 'шт',
            'cost_price' => 100,
            'sale_price' => 150,
            'pricing_mode' => 'fixed',
            'stock_quantity' => 10,
            'low_stock_alert' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Пастел')
        ->assertJsonPath('data.code', '2400');

    expect(Product::query()->where('shop_id', $shop->id)->where('code', '2400')->count())->toBe(1);
});

test('products list hides soft-deleted rows by default and exposes them only with include_trashed', function () {
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
    ]);
    $alive = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Alive']);
    $gone = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Gone']);
    $gone->delete();

    $idsDefault = collect(
        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/products')
            ->assertSuccessful()
            ->json('data')
    )->pluck('id')->all();

    expect($idsDefault)->toContain($alive->id);
    expect($idsDefault)->not->toContain($gone->id);

    $idsWithTrashed = collect(
        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/products?include_trashed=1')
            ->assertSuccessful()
            ->json('data')
    )->pluck('id')->all();

    expect($idsWithTrashed)->toContain($alive->id)->toContain($gone->id);
});
