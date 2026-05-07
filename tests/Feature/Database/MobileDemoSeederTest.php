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

// Constants mirror the values in `database/seeders/MobileDemoSeeder.php`.
// Keep in sync — if seeded shop names, email domain, or password change,
// update both sides together.
const DEMO_ALPHA_SHOP = 'Сомон Маркет';
const DEMO_BETA_SHOP = 'Сугд Минимаркет';
const DEMO_GAMMA_SHOP = 'Орзу Канцтовары';
const DEMO_GAMMA_OWNER_EMAIL = 'mohira@ck.top';
const DEMO_PASSWORD = 'Demo12345!';
const DEMO_EMAIL_DOMAIN = '%@ck.top';
const ADMIN_EMAIL = 'admin@ck.top';

it('seeds mobile demo data for shops, users, operations, and reports', function () {
    $this->seed(MobileDemoSeeder::class);

    $shops = Shop::query()
        ->whereIn('name', [DEMO_ALPHA_SHOP, DEMO_BETA_SHOP, DEMO_GAMMA_SHOP])
        ->get()
        ->keyBy('name');

    expect($shops)->toHaveCount(3);
    expect($shops[DEMO_ALPHA_SHOP]->status)->toBe('active');
    expect($shops[DEMO_BETA_SHOP]->status)->toBe('active');
    expect($shops[DEMO_GAMMA_SHOP]->status)->toBe('suspended');

    // Demo users excluding the seeded super-admin. The seeder creates 8
    // (3 owners + 5 sellers); the admin is created by AdminUserSeeder.
    $users = User::query()
        ->where('email', 'like', DEMO_EMAIL_DOMAIN)
        ->where('email', '!=', ADMIN_EMAIL)
        ->orderBy('email')
        ->get();

    expect($users)->toHaveCount(8);
    expect($users->where('role', UserRole::Owner)->count())->toBe(3);
    expect($users->where('role', UserRole::Seller)->count())->toBe(5);
    expect($users->every(fn (User $user): bool => $user->shop_id !== null))->toBeTrue();
    expect($users->every(fn (User $user): bool => Hash::check(DEMO_PASSWORD, $user->password)))->toBeTrue();

    expect(Currency::query()->whereIn('code', ['TJS', 'USD', 'RUB'])->count())->toBe(3);
    expect(Currency::query()->where('code', 'TJS')->value('is_default'))->toBeTrue();
    expect(ShopSetting::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(3);

    expect(Product::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(10);
    expect(Purchase::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(5);
    expect(Sale::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(7);
    expect(Expense::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(7);
    expect(Debt::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(4);
    expect(AuditLog::query()->whereIn('shop_id', $shops->pluck('id'))->count())->toBe(5);

    // ALPHA-POWDER-3KG is intentionally seeded below its low-stock threshold
    // so the dashboard low-stock widget has data to render. The seeder
    // bypasses the inventory service, so stock_quantity is the literal
    // initial value (11), independent of the purchase/sale rows below.
    $lowStockProduct = Product::query()->where('code', 'ALPHA-POWDER-3KG')->firstOrFail();
    expect((float) $lowStockProduct->stock_quantity)->toBe(11.0);
    expect((float) $lowStockProduct->low_stock_alert)->toBe(12.0);

    $suspendedShopOwner = User::query()->where('email', DEMO_GAMMA_OWNER_EMAIL)->firstOrFail();
    expect($suspendedShopOwner->role)->toBe(UserRole::Owner);
});

it('reseeds mobile demo data without duplicating records', function () {
    $this->seed(MobileDemoSeeder::class);
    $this->seed(MobileDemoSeeder::class);

    $shopIds = Shop::query()
        ->whereIn('name', [DEMO_ALPHA_SHOP, DEMO_BETA_SHOP, DEMO_GAMMA_SHOP])
        ->pluck('id');

    expect(User::query()->where('email', 'like', DEMO_EMAIL_DOMAIN)->where('email', '!=', ADMIN_EMAIL)->count())->toBe(8);
    expect(Product::query()->whereIn('shop_id', $shopIds)->count())->toBe(10);
    expect(Purchase::query()->whereIn('shop_id', $shopIds)->count())->toBe(5);
    expect(Sale::query()->whereIn('shop_id', $shopIds)->count())->toBe(7);
    expect(Expense::query()->whereIn('shop_id', $shopIds)->count())->toBe(7);
    expect(Debt::query()->whereIn('shop_id', $shopIds)->count())->toBe(4);
    expect(AuditLog::query()->whereIn('shop_id', $shopIds)->count())->toBe(5);
    expect(ShopSetting::query()->whereIn('shop_id', $shopIds)->count())->toBe(3);
});
