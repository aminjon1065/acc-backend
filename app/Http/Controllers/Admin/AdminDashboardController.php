<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCurrencyRequest;
use App\Http\Requests\Admin\UpdateShopStatusRequest;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'shops' => Shop::query()->count(),
                'users' => User::query()->count(),
                'products' => Product::query()->count(),
                'sales_total' => (float) Sale::query()->sum('total'),
                'expenses_total' => (float) Expense::query()->sum('total'),
                'debts_total' => (float) Debt::query()->sum('balance'),
            ],
            'recentShops' => Shop::query()->latest('id')->limit(8)->get(),
            'recentUsers' => User::query()->latest('id')->limit(8)->get(['id', 'shop_id', 'name', 'email', 'role', 'created_at']),
        ]);
    }

    public function shops(Request $request): Response
    {
        $shops = Shop::query()
            ->withCount(['users', 'products', 'sales', 'expenses', 'debts'])
            ->latest('id')
            ->paginate($request->integer('limit', 20))
            ->withQueryString();

        return Inertia::render('admin/shops', [
            'shops' => $shops,
        ]);
    }

    public function updateShopStatus(UpdateShopStatusRequest $request, Shop $shop): RedirectResponse
    {
        $shop->update([
            'status' => $request->validated('status'),
        ]);

        return back();
    }

    public function users(Request $request): Response
    {
        $users = User::query()
            ->with('shop:id,name')
            ->latest('id')
            ->paginate($request->integer('limit', 25))
            ->withQueryString();

        return Inertia::render('admin/users', [
            'users' => $users,
        ]);
    }

    public function currencies(): Response
    {
        return Inertia::render('admin/currencies', [
            'currencies' => Currency::query()->orderByDesc('is_default')->orderBy('code')->get(),
        ]);
    }

    public function updateCurrency(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $currency->fill($request->validated());

        if ($request->boolean('is_default')) {
            Currency::query()->whereKeyNot($currency->id)->update(['is_default' => false]);
            $currency->is_default = true;
        }

        $currency->save();

        return back();
    }

    public function reports(Request $request): Response
    {
        $shopId = $request->integer('shop_id');

        $salesQuery = Sale::query();
        $expensesQuery = Expense::query();
        $productsQuery = Product::query();
        $purchasesQuery = Purchase::query();
        $debtsQuery = Debt::query();

        if ($shopId > 0) {
            $salesQuery->where('shop_id', $shopId);
            $expensesQuery->where('shop_id', $shopId);
            $productsQuery->where('shop_id', $shopId);
            $purchasesQuery->where('shop_id', $shopId);
            $debtsQuery->where('shop_id', $shopId);
        }

        $salesTotal = (float) $salesQuery->sum('total');
        $expensesTotal = (float) $expensesQuery->sum('total');

        return Inertia::render('admin/reports', [
            'filters' => [
                'shop_id' => $shopId > 0 ? $shopId : null,
            ],
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name']),
            'summary' => [
                'sales_total' => $salesTotal,
                'expenses_total' => $expensesTotal,
                'profit_estimate' => $salesTotal - $expensesTotal,
                'purchases_total' => (float) $purchasesQuery->sum('total_amount'),
                'debts_total' => (float) $debtsQuery->sum('balance'),
                'products_count' => (int) $productsQuery->count(),
                'low_stock_count' => (int) $productsQuery->whereColumn('stock_quantity', '<=', 'low_stock_alert')->count(),
            ],
        ]);
    }
}
