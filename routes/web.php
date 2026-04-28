<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Landing')->name('home');

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
        Route::post('/users', [AdminDashboardController::class, 'storeUser'])->name('users.store');
        Route::patch('/users/{user}', [AdminDashboardController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminDashboardController::class, 'destroyUser'])->name('users.destroy');
        Route::get('/currencies', [AdminDashboardController::class, 'currencies'])->name('currencies');
        Route::post('/currencies', [AdminDashboardController::class, 'storeCurrency'])->name('currencies.store');
        Route::patch('/currencies/{currency}', [AdminDashboardController::class, 'updateCurrency'])->name('currencies.update');
        Route::delete('/currencies/{currency}', [AdminDashboardController::class, 'destroyCurrency'])->name('currencies.destroy');
        Route::post('/shops', [AdminDashboardController::class, 'storeShop'])->name('shops.store');
        Route::patch('/shops/{shop}', [AdminDashboardController::class, 'updateShop'])->name('shops.update');
        Route::delete('/shops/{shop}', [AdminDashboardController::class, 'destroyShop'])->name('shops.destroy');
        Route::get('/reports', [AdminDashboardController::class, 'reports'])->name('reports');
    });

require __DIR__.'/settings.php';
