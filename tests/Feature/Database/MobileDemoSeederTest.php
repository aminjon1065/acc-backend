<?php

use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\UserRole;
use Database\Seeders\MobileDemoSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds mobile demo data for shops, users, operations, and reports', function () {
    $this->seed(MobileDemoSeeder::class);

    $shops = Shop::query()
        ->whereIn('name', ['Mobile Demo Alpha', 'Mobile Demo Beta', 'Mobile Demo Gamma'])
        ->orderBy('name')
        ->get();

    expect($shops)->toHaveCount(3);
    expect($shops->pluck('status')->all())->toBe(['active', 'active', 'suspended']);

    $users = User::query()
        ->where('email', 'like', '%@ck-accounting.test')
        ->where('email', '!=', 'admin@ck-accounting.test')
        ->orderBy('email')
        ->get();

    expect($users)->toHaveCount(8);
    expect($users->where('role', UserRole::Owner)->count())->toBe(3);
    expect($users->where('role', UserRole::Seller)->count())->toBe(5);
    expect($users->every(fn (User $user): bool => $user->shop_id !== null))->toBeTrue();
    expect($users->every(fn (User $user): bool => Hash::check('MobileTest123!', $user->password)))->toBeTrue();

    expect(Currency::query()->whereIn('code', ['TJS', 'USD', 'RUB'])->count())->toBe(3);
    expect(Currency::query()->where('code', 'TJS')->value('is_default'))->toBeTrue();
    expect(ShopSetting::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(3);

    expect(Product::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(10);
    expect(Purchase::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(5);
    expect(Sale::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(7);
    expect(Expense::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(7);
    expect(Debt::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(4);
    expect(AuditLog::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(5);

    $lowStockProduct = Product::query()->where('code', 'ALPHA-POWDER-3KG')->firstOrFail();
    $suspendedShopOwner = User::query()->where('email', 'owner.gamma@ck-accounting.test')->firstOrFail();

    expect((float) $lowStockProduct->stock_quantity)->toBe(11.0);
    expect((float) $lowStockProduct->low_stock_alert)->toBe(12.0);
    expect($suspendedShopOwner->role)->toBe(UserRole::Owner);
});

it('reseeds mobile demo data without duplicating records', function () {
    $this->seed(MobileDemoSeeder::class);
    $this->seed(MobileDemoSeeder::class);

    $shopIds = Shop::query()
        ->whereIn('name', ['Mobile Demo Alpha', 'Mobile Demo Beta', 'Mobile Demo Gamma'])
        ->pluck('id');

    expect(User::query()->where('email', 'like', '%@ck-accounting.test')->where('email', '!=', 'admin@ck-accounting.test')->count())->toBe(8);
    expect(Product::query()->whereIn('shop_id', $shopIds)->count())->toBe(10);
    expect(Purchase::query()->whereIn('shop_id', $shopIds)->count())->toBe(5);
    expect(Sale::query()->whereIn('shop_id', $shopIds)->count())->toBe(7);
    expect(Expense::query()->whereIn('shop_id', $shopIds)->count())->toBe(7);
    expect(Debt::query()->whereIn('shop_id', $shopIds)->count())->toBe(4);
    expect(AuditLog::query()->whereIn('shop_id', $shopIds)->count())->toBe(5);
    expect(ShopSetting::query()->whereIn('shop_id', $shopIds)->count())->toBe(3);
});
