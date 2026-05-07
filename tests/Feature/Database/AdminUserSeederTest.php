<?php

use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

// Constants mirror the values in `database/seeders/AdminUserSeeder.php`. Keep
// them in sync with the seeder; if the seeder's email/name/password rotates,
// update both sides together.
const SEEDED_ADMIN_EMAIL = 'admin@ck.top';
const SEEDED_ADMIN_NAME = 'CK Top Admin';
const SEEDED_ADMIN_PASSWORD = 'Demo12345!';

it('seeds the super admin user with expected credentials', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', SEEDED_ADMIN_EMAIL)->first();

    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe(SEEDED_ADMIN_NAME);
    expect($admin->shop_id)->toBeNull();
    expect($admin->role)->toBe(UserRole::SuperAdmin);
    expect($admin->email_verified_at)->not->toBeNull();
    expect(Hash::check(SEEDED_ADMIN_PASSWORD, $admin->password))->toBeTrue();
});

it('updates existing admin user with the seeded values', function () {
    $shop = Shop::factory()->create();

    User::factory()->create([
        'name' => 'Old Admin',
        'email' => SEEDED_ADMIN_EMAIL,
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
        'password' => Hash::make('old-password'),
        'email_verified_at' => null,
    ]);

    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', SEEDED_ADMIN_EMAIL)->firstOrFail();

    expect($admin->name)->toBe(SEEDED_ADMIN_NAME);
    expect($admin->shop_id)->toBeNull();
    expect($admin->role)->toBe(UserRole::SuperAdmin);
    expect($admin->email_verified_at)->not->toBeNull();
    expect(Hash::check(SEEDED_ADMIN_PASSWORD, $admin->password))->toBeTrue();
    expect(User::query()->where('email', SEEDED_ADMIN_EMAIL)->count())->toBe(1);
});
