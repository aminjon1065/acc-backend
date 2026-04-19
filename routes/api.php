<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DebtController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\ShopSettingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:mobile-login');

    Route::middleware(['auth:sanctum', 'active_shop', 'shop_scope'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);

        Route::get('dashboard', [DashboardController::class, 'show'])->middleware('api_ability:dashboard,view');

        Route::get('products', [ProductController::class, 'index'])->middleware('api_ability:products,viewAny');
        Route::post('products', [ProductController::class, 'store'])->middleware(['api_ability:products,create', 'throttle:mobile-writes']);
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('api_ability:products,view');
        Route::get('products/{product}/movements', [ProductController::class, 'movements'])->middleware('api_ability:products,view');
        Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->middleware('api_ability:products,update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('api_ability:products,delete');

        Route::get('expenses', [ExpenseController::class, 'index'])->middleware('api_ability:expenses,viewAny');
        Route::post('expenses', [ExpenseController::class, 'store'])->middleware(['api_ability:expenses,create', 'throttle:mobile-writes', 'idempotent']);
        Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->middleware('api_ability:expenses,view');
        Route::match(['put', 'patch'], 'expenses/{expense}', [ExpenseController::class, 'update'])->middleware(['api_ability:expenses,update', 'idempotent']);
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('api_ability:expenses,delete');

        Route::get('currencies', [CurrencyController::class, 'index'])->middleware('api_ability:currencies,viewAny');
        Route::get('currencies/{currency}', [CurrencyController::class, 'show'])->middleware('api_ability:currencies,view');
        Route::match(['put', 'patch'], 'currencies/{currency}', [CurrencyController::class, 'update'])->middleware('api_ability:currencies,update');

        Route::get('debts', [DebtController::class, 'index'])->middleware('api_ability:debts,viewAny');
        Route::post('debts', [DebtController::class, 'store'])->middleware(['api_ability:debts,create', 'throttle:mobile-writes']);
        Route::get('debts/{debt}', [DebtController::class, 'show'])->middleware('api_ability:debts,view');
        Route::post('debts/{debt}/transactions', [DebtController::class, 'storeTransaction'])->middleware(['api_ability:debts,update', 'throttle:mobile-writes']);
        Route::get('debts/{debt}/transactions', [DebtController::class, 'transactions'])->middleware('api_ability:debts,view');

        Route::get('purchases', [PurchaseController::class, 'index'])->middleware('api_ability:purchases,viewAny');
        Route::post('purchases', [PurchaseController::class, 'store'])->middleware(['api_ability:purchases,create', 'throttle:mobile-writes']);
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('api_ability:purchases,view');

        Route::get('sales', [SaleController::class, 'index'])->middleware('api_ability:sales,viewAny');
        Route::post('sales', [SaleController::class, 'store'])->middleware(['api_ability:sales,create', 'throttle:mobile-writes', 'idempotent']);
        Route::get('sales/{sale}', [SaleController::class, 'show'])->middleware('api_ability:sales,view');
<<<<<<< Updated upstream
        Route::match(['put', 'patch'], 'sales/{sale}', [SaleController::class, 'update'])->middleware(['api_ability:sales,update', 'idempotent']);
=======
        Route::post('sales/{sale}/return', [SaleController::class, 'return'])->middleware(['api_ability:sales,return', 'throttle:mobile-writes']);
>>>>>>> Stashed changes

        Route::get('shops', [ShopController::class, 'index'])->middleware('api_ability:shops,viewAny');
        Route::post('shops', [ShopController::class, 'store'])->middleware('api_ability:shops,create');
        Route::get('shops/{shop}', [ShopController::class, 'show'])->middleware('api_ability:shops,view');
        Route::match(['put', 'patch'], 'shops/{shop}', [ShopController::class, 'update'])->middleware('api_ability:shops,update');
        Route::delete('shops/{shop}', [ShopController::class, 'destroy'])->middleware('api_ability:shops,delete');

        Route::get('users', [UserController::class, 'index'])->middleware('api_ability:users,viewAny');
        Route::post('users', [UserController::class, 'store'])->middleware('api_ability:users,create');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('api_ability:users,view');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('api_ability:users,update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('api_ability:users,delete');

        Route::get('settings', [ShopSettingController::class, 'show'])->middleware('api_ability:settings,view');
        Route::match(['put', 'patch'], 'settings', [ShopSettingController::class, 'update'])->middleware('api_ability:settings,update');

        Route::prefix('reports')->group(function (): void {
            Route::get('sales', [ReportController::class, 'sales'])->middleware('api_ability:reports,view');
            Route::get('expenses', [ReportController::class, 'expenses'])->middleware('api_ability:reports,view');
            Route::get('profit', [ReportController::class, 'profit'])->middleware('api_ability:reports,view');
            Route::get('stock', [ReportController::class, 'stock'])->middleware('api_ability:reports,view');
        });

        Route::post('notifications/token', [NotificationController::class, 'store'])->middleware(['api_ability:notifications,create', 'throttle:mobile-writes']);
    });
});
