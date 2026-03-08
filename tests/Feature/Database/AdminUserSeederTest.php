<?php

use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds the super admin user with expected credentials', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@ck-accounting.test')->first();

    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe('Super Admin');
    expect($admin->shop_id)->toBeNull();
    expect($admin->role)->toBe(UserRole::SuperAdmin);
    expect($admin->email_verified_at)->not->toBeNull();
    expect(Hash::check('Momajon115877!', $admin->password))->toBeTrue();
});

it('updates existing admin user with the seeded values', function () {
    $shop = Shop::factory()->create();

    User::factory()->create([
        'name' => 'Old Admin',
        'email' => 'admin@ck-accounting.test',
        'shop_id' => $shop->id,
        'role' => UserRole::Owner->value,
        'password' => Hash::make('old-password'),
        'email_verified_at' => null,
    ]);

    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@ck-accounting.test')->firstOrFail();

    expect($admin->name)->toBe('Super Admin');
    expect($admin->shop_id)->toBeNull();
    expect($admin->role)->toBe(UserRole::SuperAdmin);
    expect($admin->email_verified_at)->not->toBeNull();
    expect(Hash::check('Momajon115877!', $admin->password))->toBeTrue();
    expect(User::query()->where('email', 'admin@ck-accounting.test')->count())->toBe(1);
});
