<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCurrencyRequest;
use App\Http\Requests\Admin\StoreShopRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateCurrencyRequest;
use App\Http\Requests\Admin\UpdateShopRequest;
use App\Http\Requests\Admin\UpdateShopStatusRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        $recentShops = Shop::query()->latest('id')->limit(8)->get(['id', 'name', 'status', 'created_at']);
        $recentUsers = User::query()->latest('id')->limit(8)->get(['id', 'shop_id', 'name', 'email', 'role', 'created_at']);

        // Recent activity: merge shops and users, sorted by created_at desc
        $shopActivities = $recentShops->map(fn (Shop $s) => [
            'type' => 'shop',
            'name' => $s->name,
            'created_at' => $s->created_at->toIso8601String(),
        ]);
        $userActivities = $recentUsers->map(fn (User $u) => [
            'type' => 'user',
            'name' => $u->name,
            'created_at' => $u->created_at->toIso8601String(),
        ]);
        $recentActivity = $shopActivities->concat($userActivities)
            ->sortByDesc(fn ($a) => $a['created_at'])
            ->take(10)
            ->values()
            ->all();

        // Monthly stats: last 6 months
        $monthlyStats = collect(range(0, 5))->map(function ($i) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            $monthName = $date->format('M');

            $shops = Shop::query()
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $users = User::query()
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            return [
                'month' => $monthName,
                'shops' => $shops,
                'users' => $users,
            ];
        })->reverse()->values()->all();

        $totalRevenue = (float) Sale::query()->sum('total');
        $totalExpenses = (float) Expense::query()->sum('total');

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'shops' => Shop::query()->count(),
                'users' => User::query()->count(),
                'products' => Product::query()->count(),
                'sales_total' => (float) Sale::query()->sum('total'),
                'expenses_total' => $totalExpenses,
                'debts_total' => (float) Debt::query()->sum('balance'),
            ],
            'systemHealth' => [
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_profit' => $totalRevenue - $totalExpenses,
                'suspended_shops' => Shop::query()->where('status', 'suspended')->count(),
                'low_stock_items' => Product::query()->whereColumn('stock_quantity', '<=', 'low_stock_alert')->count(),
            ],
            'recentShops' => $recentShops,
            'recentUsers' => $recentUsers,
            'recentActivity' => $recentActivity,
        ]);
    }

    public function shops(Request $request): Response
    {
        $query = Shop::query()
            ->withCount(['users', 'products', 'sales', 'expenses', 'debts'])
            ->latest('id');

        if ($search = $request->string('search')->trim()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->trim()) {
            $query->where('status', $status);
        }

        $shops = $query->paginate($request->integer('per_page', 15))->withQueryString();

        return Inertia::render('admin/shops', [
            'shops' => $shops,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function storeShop(StoreShopRequest $request): RedirectResponse
    {
        Shop::query()->create($request->validated() + ['status' => $request->validated('status', 'active')]);

        return back()->with('success', 'Shop created successfully.');
    }

    public function updateShop(UpdateShopRequest $request, Shop $shop): RedirectResponse
    {
        $shop->update($request->validated());

        return back()->with('success', 'Shop updated successfully.');
    }

    public function destroyShop(Shop $shop): RedirectResponse
    {
        $shop->delete();

        return back()->with('success', 'Shop deleted.');
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
        $query = User::query()->with('shop:id,name')->latest('id');

        if ($search = $request->string('search')->trim()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->string('role')->trim()) {
            $query->where('role', $role);
        }

        if ($shopId = $request->integer('shop_id')) {
            $query->where('shop_id', $shopId);
        }

        $users = $query->paginate($request->integer('per_page', 20))->withQueryString();

        return Inertia::render('admin/users', [
            'users' => $users,
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'role', 'shop_id'),
        ]);
    }

    public function storeUser(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create($request->validated());

        return back()->with('success', 'User created successfully.');
    }

    public function updateUser(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);

        return back()->with('success', 'User updated successfully.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }
        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    public function currencies(Request $request): Response
    {
        $query = Currency::query()->orderByDesc('is_default')->orderBy('code');

        if ($search = $request->string('search')->trim()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return Inertia::render('admin/currencies', [
            'currencies' => $query->get(),
            'filters' => $request->only('search'),
        ]);
    }

    public function storeCurrency(StoreCurrencyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->boolean('is_default')) {
            Currency::query()->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        Currency::query()->create($data);

        return back()->with('success', 'Currency added successfully.');
    }

    public function updateCurrency(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $currency->fill($request->validated());

        if ($request->boolean('is_default')) {
            Currency::query()->whereKeyNot($currency->id)->update(['is_default' => false]);
            $currency->is_default = true;
        }

        $currency->save();

        return back()->with('success', 'Currency updated successfully.');
    }

    public function destroyCurrency(Currency $currency): RedirectResponse
    {
        if ($currency->is_default) {
            return back()->withErrors(['error' => 'Cannot delete the default currency.']);
        }

        $currency->delete();

        return back()->with('success', 'Currency deleted.');
    }

    public function reports(Request $request): Response
    {
        $shopId = $request->integer('shop_id');
        $dateFrom = $request->string('date_from')->trim() ?: now()->startOfMonth()->toDateString();
        $dateTo = $request->string('date_to')->trim() ?: now()->toDateString();

        $applyShop = fn ($query) => $shopId > 0 ? $query->where('shop_id', $shopId) : $query;
        $applyDate = fn ($query, $col = 'created_at') => $query
            ->whereDate($col, '>=', $dateFrom)
            ->whereDate($col, '<=', $dateTo);

        $salesQuery = $applyDate($applyShop(Sale::query()), 'created_at');
        $expensesQuery = $applyDate($applyShop(Expense::query()), 'created_at');
        $purchasesQuery = $applyDate($applyShop(Purchase::query()), 'created_at');
        $debtsQuery = $applyShop(Debt::query());
        $productsQuery = $applyShop(Product::query());

        $salesTotal = (float) $salesQuery->sum('total');
        $expensesTotal = (float) $expensesQuery->sum('total');
        $purchasesTotal = (float) $purchasesQuery->sum('total_amount');

        $monthlyBreakdown = collect(range(1, 12))->map(function (int $m) use ($shopId): array {
            $year = now()->year;
            $from = Carbon::create($year, $m, 1)->startOfMonth();
            $to = Carbon::create($year, $m, 1)->endOfMonth();

            $monthSales = (float) Sale::query()
                ->when($shopId > 0, fn ($q) => $q->where('shop_id', $shopId))
                ->whereBetween('created_at', [$from, $to])
                ->sum('total');
            $monthExpenses = (float) Expense::query()
                ->when($shopId > 0, fn ($q) => $q->where('shop_id', $shopId))
                ->whereBetween('created_at', [$from, $to])
                ->sum('total');

            return [
                'month' => $from->format('M'),
                'sales' => $monthSales,
                'expenses' => $monthExpenses,
                'profit' => $monthSales - $monthExpenses,
            ];
        })->toArray();

        $topShops = Sale::query()
            ->selectRaw('shop_id, SUM(total) as total_sales, COUNT(*) as sales_count')
            ->when($shopId > 0, fn ($q) => $q->where('shop_id', $shopId))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->groupBy('shop_id')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->with('shop:id,name')
            ->get()
            ->map(fn ($row) => [
                'shop_name' => $row->shop?->name ?? 'Unknown',
                'total_sales' => (float) $row->total_sales,
                'sales_count' => (int) $row->sales_count,
            ])
            ->toArray();

        return Inertia::render('admin/reports', [
            'filters' => [
                'shop_id' => $shopId > 0 ? $shopId : null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name']),
            'summary' => [
                'sales_total' => $salesTotal,
                'expenses_total' => $expensesTotal,
                'profit_estimate' => $salesTotal - $expensesTotal,
                'purchases_total' => $purchasesTotal,
                'debts_total' => (float) $debtsQuery->sum('balance'),
                'products_count' => (int) $productsQuery->count(),
                'low_stock_count' => (int) $productsQuery->whereColumn('stock_quantity', '<=', 'low_stock_alert')->count(),
                'sales_count' => (int) $salesQuery->count(),
                'expenses_count' => (int) $expensesQuery->count(),
            ],
            'monthlyBreakdown' => $monthlyBreakdown,
            'topShops' => $topShops,
        ]);
    }
}
