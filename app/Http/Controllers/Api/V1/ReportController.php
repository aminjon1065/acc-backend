<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Api\V1\DashboardCacheVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function __construct(
        private readonly DashboardCacheVersion $cacheVersion,
    ) {}

    public function sales(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $user->isSuperAdmin() ? 'sa_'.($request->shop_id ?? 'all') : $user->shop_id;
        $version = $this->cacheVersion->versionForShop(is_int($shopId) ? $shopId : null);
        $cacheKey = "reports:sales:shop_{$shopId}:v{$version}:from_{$request->date_from}:to_{$request->date_to}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            $sales = $this->scopeByDate($this->scopeSales($user, $request), $request)
                ->get(['id', 'total', 'payment_type', 'created_at']);

            $salesTotal = (float) $sales->sum('total');
            $salesCount = $sales->count();
            $cashTotal = (float) $sales
                ->filter(fn (Sale $sale) => ($sale->payment_type?->value ?? $sale->payment_type) === 'cash')
                ->sum('total');
            $cardTotal = (float) $sales
                ->filter(fn (Sale $sale) => ($sale->payment_type?->value ?? $sale->payment_type) === 'card')
                ->sum('total');
            $transferTotal = (float) $sales
                ->filter(fn (Sale $sale) => ($sale->payment_type?->value ?? $sale->payment_type) === 'transfer')
                ->sum('total');
            $dailyData = $sales
                ->groupBy(fn (Sale $sale) => $sale->created_at?->toDateString() ?? '')
                ->filter(fn ($items, string $date) => $date !== '')
                ->map(fn ($items, string $date) => [
                    'date' => $date,
                    'count' => $items->count(),
                    'amount' => (float) $items->sum('total'),
                ])
                ->sortByDesc('date')
                ->values()
                ->all();

            return [
                'total_sales' => $salesCount,
                'total_amount' => $salesTotal,
                'cash' => $cashTotal,
                'card' => $cardTotal,
                'transfer' => $transferTotal,
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'data' => $dailyData,
                'sales_total' => $salesTotal,
                'sales_count' => $salesCount,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $user->isSuperAdmin() ? 'sa_'.($request->shop_id ?? 'all') : $user->shop_id;
        $version = $this->cacheVersion->versionForShop(is_int($shopId) ? $shopId : null);
        $cacheKey = "reports:expenses:shop_{$shopId}:v{$version}:from_{$request->date_from}:to_{$request->date_to}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            $expenses = $this->scopeByDate($this->scopeExpenses($user, $request), $request)
                ->get(['id', 'total', 'created_at']);

            $expenseTotal = (float) $expenses->sum('total');
            $expenseCount = $expenses->count();
            $dailyData = $expenses
                ->groupBy(fn (Expense $expense) => $expense->created_at?->toDateString() ?? '')
                ->filter(fn ($items, string $date) => $date !== '')
                ->map(fn ($items, string $date) => [
                    'date' => $date,
                    'count' => $items->count(),
                    'amount' => (float) $items->sum('total'),
                ])
                ->sortByDesc('date')
                ->values()
                ->all();

            return [
                'total_amount' => $expenseTotal,
                'count' => $expenseCount,
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'data' => $dailyData,
                'expenses_total' => $expenseTotal,
                'expenses_count' => $expenseCount,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }

    public function profit(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $user->isSuperAdmin() ? 'sa_'.($request->shop_id ?? 'all') : $user->shop_id;
        $version = $this->cacheVersion->versionForShop(is_int($shopId) ? $shopId : null);
        $cacheKey = "reports:profit:shop_{$shopId}:v{$version}:from_{$request->date_from}:to_{$request->date_to}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            $salesQuery = $this->scopeByDate($this->scopeSales($user, $request), $request);
            $saleItemsQuery = $this->scopeByDate($this->scopeSaleItems($user, $request), $request, column: 'sales.created_at');
            $expensesQuery = $this->scopeByDate($this->scopeExpenses($user, $request), $request);

            $salesTotal = (float) $salesQuery->sum('total');
            $costOfGoodsSold = (float) $saleItemsQuery
                ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.cost_price), 0) as cogs')
                ->value('cogs');
            $expensesTotal = (float) $expensesQuery->sum('total');
            $profit = $salesTotal - $costOfGoodsSold - $expensesTotal;

            return [
                'total_sales' => $salesTotal,
                'total_expenses' => $expensesTotal,
                'total_cost' => $costOfGoodsSold,
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'profit' => $profit,
                'sales_total' => $salesTotal,
                'cost_of_goods_sold' => $costOfGoodsSold,
                'expenses_total' => $expensesTotal,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }

    public function stock(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $user->isSuperAdmin() ? 'sa_'.($request->shop_id ?? 'all') : $user->shop_id;
        $version = $this->cacheVersion->versionForShop(is_int($shopId) ? $shopId : null);
        $cacheKey = "reports:stock:v{$version}:shop_{$shopId}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            $products = $this->scopeProducts($user, $request);

            $totalProducts = (int) (clone $products)->count();
            $totalStockQuantity = (float) (clone $products)->sum('stock_quantity');
            $totalValue = (float) (clone $products)->selectRaw('COALESCE(SUM(stock_quantity * sale_price), 0) as total_value')->value('total_value');
            $lowStockCount = (int) (clone $products)
                ->where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_alert')
                ->count();
            $outOfStockCount = (int) (clone $products)->where('stock_quantity', '<=', 0)->count();
            $productRows = (clone $products)
                ->select(['id', 'name', 'stock_quantity', 'sale_price'])
                ->orderByRaw('(stock_quantity * sale_price) desc')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock_quantity' => (float) $product->stock_quantity,
                    'sale_price' => (float) $product->sale_price,
                    'value' => (float) $product->stock_quantity * (float) $product->sale_price,
                ])
                ->values()
                ->all();

            return [
                'total_products' => $totalProducts,
                'total_value' => $totalValue,
                'low_stock' => $lowStockCount,
                'out_of_stock' => $outOfStockCount,
                'data' => $productRows,
                'products_count' => $totalProducts,
                'stock_quantity_total' => $totalStockQuantity,
                'low_stock_products_count' => $lowStockCount,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopeByDate(Builder $query, Request $request, string $column = 'created_at'): Builder
    {
        if ($request->filled('date_from')) {
            $query->whereDate($column, '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate($column, '<=', $request->date('date_to'));
        }

        return $query;
    }

    /**
     * @return Builder<Sale>
     */
    private function scopeSales(User $user, Request $request): Builder
    {
        $query = Sale::query();
        $this->applyShopScope($query, $user, $request);

        return $query;
    }

    /**
     * @return Builder<SaleItem>
     */
    private function scopeSaleItems(User $user, Request $request): Builder
    {
        $query = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id');
        $this->applyShopScope($query, $user, $request, table: 'sales');

        return $query;
    }

    /**
     * @return Builder<Expense>
     */
    private function scopeExpenses(User $user, Request $request): Builder
    {
        $query = Expense::query();
        $this->applyShopScope($query, $user, $request);

        return $query;
    }

    /**
     * @return Builder<Product>
     */
    private function scopeProducts(User $user, Request $request): Builder
    {
        $query = Product::query();
        $this->applyShopScope($query, $user, $request);

        return $query;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyShopScope(Builder $query, User $user, Request $request, string $table = ''): void
    {
        $column = ($table !== '' ? $table.'.' : '').'shop_id';

        if (! $user->isSuperAdmin()) {
            $query->where($column, $user->shop_id);

            return;
        }

        if ($request->filled('shop_id')) {
            $query->where($column, $request->integer('shop_id'));
        }
    }
}
