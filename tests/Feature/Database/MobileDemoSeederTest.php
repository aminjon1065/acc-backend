<?php

use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Database\Seeders\MobileDemoSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds demo shops with sellers for mobile testing', function () {
    $this->seed(MobileDemoSeeder::class);

    $alphaShop = Shop::query()->where('name', 'Mobile Demo Alpha')->first();
    $betaShop = Shop::query()->where('name', 'Mobile Demo Beta')->first();

    expect($alphaShop)->not->toBeNull();
    expect($betaShop)->not->toBeNull();

    $sellers = User::query()
        ->whereIn('email', [
            'seller.alpha.1@ck-accounting.test',
            'seller.alpha.2@ck-accounting.test',
            'seller.beta.1@ck-accounting.test',
            'seller.beta.2@ck-accounting.test',
        ])
        ->get();

    expect($sellers)->toHaveCount(4);
    expect($sellers->every(fn (User $user): bool => $user->role === UserRole::Seller))->toBeTrue();
    expect($sellers->every(fn (User $user): bool => $user->shop_id !== null))->toBeTrue();
    expect($sellers->every(fn (User $user): bool => Hash::check('MobileTest123!', $user->password)))->toBeTrue();
});
