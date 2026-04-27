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
