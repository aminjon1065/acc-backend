<?php

use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;

test('app:db-prepare-production wipes tenant data and leaves only super_admin users', function () {
    // Seed a realistic mess: shop with owner + seller, a sale with an item,
    // an expense and a stray super_admin from a previous environment.
    $shop = Shop::factory()->create();
    $owner = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
        'email' => 'owner@example.com',
    ]);
    $seller = User::factory()->create([
        'shop_id' => $shop->id,
        'role' => UserRole::Seller->value,
        'email' => 'seller@example.com',
    ]);
    User::factory()->create([
        'role' => UserRole::SuperAdmin->value,
        'email' => 'leftover-admin@example.com',
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'created_by' => $owner->id,
    ]);
    $sale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
    ]);
    Expense::factory()->create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
    ]);

    // Issue a Sanctum token for the seller — the command must clean these up
    // so we don't leave orphan auth tokens behind.
    $seller->createToken('mobile');

    $this->artisan('app:db-prepare-production', ['--force' => true])
        ->assertSuccessful();

    // Every tenant table must be empty.
    expect(DB::table('shops')->count())->toBe(0);
    expect(DB::table('products')->count())->toBe(0);
    expect(DB::table('sales')->count())->toBe(0);
    expect(DB::table('sale_items')->count())->toBe(0);
    expect(DB::table('expenses')->count())->toBe(0);

    // Only super_admins survive. Owners + sellers are gone.
    $remaining = User::query()->orderBy('email')->get(['email', 'role']);
    expect($remaining->pluck('role')->unique()->values()->all())
        ->toBe([UserRole::SuperAdmin]);
    expect($remaining->pluck('email')->all())->toContain('admin@ck.top');
    expect($remaining->pluck('email')->all())->not->toContain('owner@example.com');
    expect($remaining->pluck('email')->all())->not->toContain('seller@example.com');

    // Sanctum tokens for the deleted seller must be gone.
    expect(DB::table('personal_access_tokens')->count())->toBe(0);

    // Surviving super_admins have shop_id cleared so they don't dangle to a
    // shop that no longer exists.
    expect(User::query()->whereNotNull('shop_id')->count())->toBe(0);
});

test('app:db-prepare-production is idempotent', function () {
    $this->artisan('app:db-prepare-production', ['--force' => true])->assertSuccessful();
    $this->artisan('app:db-prepare-production', ['--force' => true])->assertSuccessful();

    expect(User::query()->where('email', 'admin@ck.top')->count())->toBe(1);
});
