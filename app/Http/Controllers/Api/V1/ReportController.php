<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $user->isSuperAdmin() ? 'sa_'.($request->shop_id ?? 'all') : $user->shop_id;
        $cacheKey = "reports:sales:shop_{$shopId}:from_{$request->date_from}:to_{$request->date_to}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            $salesTotal = (float) $this->scopeByDate($this->scopeSales($user, $request), $request)->sum('total');
            $salesCount = (int) $this->scopeByDate($this->scopeSales($user, $request), $request)->count();

            return [
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
        $cacheKey = "reports:expenses:shop_{$shopId}:from_{$request->date_from}:to_{$request->date_to}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            $expenseTotal = (float) $this->scopeByDate($this->scopeExpenses($user, $request), $request)->sum('total');
            $expenseCount = (int) $this->scopeByDate($this->scopeExpenses($user, $request), $request)->count();

            return [
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
        $cacheKey = "reports:profit:shop_{$shopId}:from_{$request->date_from}:to_{$request->date_to}";

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
                'sales_total' => $salesTotal,
                'cost_of_goods_sold' => $costOfGoodsSold,
                'expenses_total' => $expensesTotal,
                'profit' => $profit,
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
        $cacheKey = "reports:stock:shop_{$shopId}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            $products = $this->scopeProducts($user, $request);

            $totalProducts = (int) (clone $products)->count();
            $totalStockQuantity = (float) (clone $products)->sum('stock_quantity');
            $lowStockCount = (int) (clone $products)->whereColumn('stock_quantity', '<=', 'low_stock_alert')->count();

            return [
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
