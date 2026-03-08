<?php

use App\Models\Currency;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('redirects guests from admin dashboard', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('forbids non super admin users from admin routes', function () {
    $owner = User::factory()->create([
        'role' => UserRole::Owner->value,
    ]);

    $this->actingAs($owner)
        ->get('/admin')
        ->assertForbidden();

    $this->actingAs($owner)
        ->get('/admin/shops')
        ->assertForbidden();
});

it('allows super admin to open admin pages', function () {
    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->actingAs($admin)->get('/admin/shops')->assertOk();
    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/currencies')->assertOk();
    $this->actingAs($admin)->get('/admin/reports')->assertOk();
});

it('allows super admin to change shop status', function () {
    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $shop = Shop::factory()->create([
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->patch("/admin/shops/{$shop->id}/status", [
            'status' => 'suspended',
        ])
        ->assertRedirect();

    expect($shop->fresh()->status)->toBe('suspended');
});

it('allows super admin to change currency settings', function () {
    $admin = User::factory()->create([
        'shop_id' => null,
        'role' => UserRole::SuperAdmin->value,
    ]);

    $currencyA = Currency::factory()->create([
        'is_default' => true,
    ]);

    $currencyB = Currency::factory()->create([
        'is_default' => false,
    ]);

    $this->actingAs($admin)
        ->patch("/admin/currencies/{$currencyB->id}", [
            'rate' => 12.5,
            'is_default' => true,
        ])
        ->assertRedirect();

    expect((float) $currencyB->fresh()->rate)->toBe(12.5);
    expect($currencyB->fresh()->is_default)->toBeTrue();
    expect($currencyA->fresh()->is_default)->toBeFalse();
});
