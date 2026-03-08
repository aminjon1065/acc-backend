<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::prefix('admin')
    ->as('admin.')
    ->middleware(['auth', 'verified', 'super_admin'])
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/shops', [AdminDashboardController::class, 'shops'])->name('shops');
        Route::patch('/shops/{shop}/status', [AdminDashboardController::class, 'updateShopStatus'])->name('shops.update-status');
        Route::get('/users', [AdminDashboardController::class, 'users'])->name('users');
        Route::get('/currencies', [AdminDashboardController::class, 'currencies'])->name('currencies');
        Route::patch('/currencies/{currency}', [AdminDashboardController::class, 'updateCurrency'])->name('currencies.update');
        Route::get('/reports', [AdminDashboardController::class, 'reports'])->name('reports');
    });

require __DIR__.'/settings.php';
