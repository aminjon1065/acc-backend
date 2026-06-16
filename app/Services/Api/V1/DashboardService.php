<?php

namespace App\Services\Api\V1;

use App\Models\Debt;
use App\Models\DebtTransaction;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters): array
    {
        [$from, $to, $period] = $this->resolvePeriod($filters);
        $shopIds = $this->resolveShopIdFilter($user, $filters);
        $shopId = $this->resolveShopId($user, $filters);
        $sellerId = $this->resolveSellerId($user);

        $salesQuery = $this->scopeByShopAndPeriod(Sale::query(), $shopIds, $from, $to);
        $expensesQuery = $this->scopeByShopAndPeriod(Expense::query(), $shopIds, $from, $to);
        $productsQuery = Product::query();
        // Only count debts with an outstanding balance. The schema keeps
        // `balance >= 0` as an invariant — when a transaction would push it
        // negative, DebtService flips `direction` and stores ABS. So a
        // settled debt has balance = 0; anything > 0 is still active.
        $debtsQuery = Debt::query()->where('balance', '>', 0);

        if ($shopIds !== null) {
            $productsQuery->whereIn('shop_id', $shopIds);
            $debtsQuery->whereIn('shop_id', $shopIds);
        }

        if ($sellerId !== null) {
            $salesQuery->where('user_id', $sellerId);
            $expensesQuery->where('user_id', $sellerId);
            $debtsQuery->where('user_id', $sellerId);
        }

        $salesTotal = (float) (clone $salesQuery)->sum('total');
        $expensesTotal = (float) (clone $expensesQuery)->sum('total');

        // Exclude soft-deleted sales: the raw join bypasses the Sale model's
        // SoftDeletes global scope, so without this a deleted sale's items
        // keep inflating COGS even though its gross revenue (Eloquent) is
        // already gone — pinning period_profit at -COGS forever.
        $saleItemsQuery = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.created_at', [$from, $to]);

        if ($shopIds !== null) {
            $saleItemsQuery->whereIn('sales.shop_id', $shopIds);
        }

        if ($sellerId !== null) {
            $saleItemsQuery->where('sales.user_id', $sellerId);
        }

        $costOfGoodsSold = (float) (clone $saleItemsQuery)
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.cost_price), 0) as cogs')
            ->value('cogs');

        // Returns processed in the same window pull money OUT of the period
        // (refund) and PUT goods BACK on the shelf. Both effects need to
        // land in the financials, otherwise the dashboard overstates both
        // revenue and profit. We total the refund amount and the cost of
        // the returned goods so the period_profit math nets out cleanly
        // even when a sale and its return straddle the report window.
        $returnsAggregate = $this->returnsTotalsForPeriod($shopIds, $sellerId, $from, $to);
        $returnsTotal = $returnsAggregate['total'];
        $returnsCogs = $returnsAggregate['cogs'];
        // Bucket by `direction`. The balance is always stored as a positive
        // magnitude (see invariant above); the direction column carries the
        // sign. Don't switch this to balance-sign-based bucketing — it
        // would split the receivable/payable totals wrong.
        $debtsStats = (clone $debtsQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'receivable' THEN balance ELSE 0 END), 0) as receivable,
                COALESCE(SUM(CASE WHEN direction = 'payable' THEN balance ELSE 0 END), 0) as payable
            ")
            ->first();

        $receivable = (float) ($debtsStats->receivable ?? 0);
        $payable = (float) ($debtsStats->payable ?? 0);

        $productStats = (clone $productsQuery)
            ->selectRaw('
                COALESCE(SUM(stock_quantity), 0) as stock_total_qty,
                COALESCE(SUM(stock_quantity * cost_price), 0) as stock_total_cost,
                COALESCE(SUM(stock_quantity * sale_price), 0) as stock_total_sales_value,
                SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_alert THEN 1 ELSE 0 END) as low_stock_count
            ')
            ->first();

        // Net sales = gross - returns. Net COGS = sold cost - returned cost
        // (returned goods went back on the shelf, the original cost is
        // reversed). Period profit is net revenue minus net cost minus
        // expenses.
        $netSalesTotal = $salesTotal - $returnsTotal;
        $netCostOfGoodsSold = $costOfGoodsSold - $returnsCogs;

        return [
            'period' => $period,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'shop_id' => $shopId,
            'period_sales_total' => $netSalesTotal,
            'period_sales_gross' => $salesTotal,
            'period_returns_total' => $returnsTotal,
            'period_expenses_total' => $expensesTotal,
            'period_profit' => $netSalesTotal - $netCostOfGoodsSold - $expensesTotal,
            'period_cogs' => $netCostOfGoodsSold,
            'debts_receivable' => $receivable,
            'debts_payable' => $payable,
            'debts_net' => $receivable - $payable,
            'stock_total_qty' => (float) ($productStats->stock_total_qty ?? 0),
            'stock_total_cost' => (float) ($productStats->stock_total_cost ?? 0),
            'stock_total_sales_value' => (float) ($productStats->stock_total_sales_value ?? 0),
            'low_stock_count' => (int) ($productStats->low_stock_count ?? 0),
            'recent_sales' => $this->recentSales($salesQuery),
            'recent_expenses' => $this->recentExpenses($expensesQuery),
            'recent_debt_transactions' => $this->recentDebtTransactions($shopIds, $sellerId, $from, $to),
            'low_stock_products' => $this->lowStockProducts($productsQuery),
            'unpaid_debts' => $this->unpaidDebts($debtsQuery),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolvePeriod(array $filters): array
    {
        $period = (string) ($filters['period'] ?? 'day');

        if ($period === 'custom') {
            return [
                CarbonImmutable::parse((string) $filters['date_from'])->startOfDay(),
                CarbonImmutable::parse((string) $filters['date_to'])->endOfDay(),
                $period,
            ];
        }

        $anchor = array_key_exists('date', $filters)
            ? CarbonImmutable::parse((string) $filters['date'])
            : CarbonImmutable::now();

        return match ($period) {
            // "Week" = rolling last 7 days (today and the 6 days before it),
            // not the calendar week — so a sale from a few days ago still
            // shows even when it falls in the previous Mon–Sun week.
            'week' => [$anchor->subDays(6)->startOfDay(), $anchor->endOfDay(), $period],
            'month' => [$anchor->startOfMonth()->startOfDay(), $anchor->endOfMonth()->endOfDay(), $period],
            'year' => [$anchor->startOfYear()->startOfDay(), $anchor->endOfYear()->endOfDay(), $period],
            default => [$anchor->startOfDay(), $anchor->endOfDay(), 'day'],
        };
    }

    /**
     * Backwards-compat shim: returns the single shop_id if the resolved
     * scope is exactly one shop, otherwise null. Used for the cache key
     * and any UI-facing fields that expect a scalar. The query scoping
     * uses `resolveShopIdFilter` which returns the full array.
     *
     * @param  array<string, mixed>  $filters
     */
    private function resolveShopId(User $user, array $filters): ?int
    {
        $ids = $this->resolveShopIdFilter($user, $filters);
        if ($ids !== null && count($ids) === 1) {
            return (int) $ids[0];
        }

        return null;
    }

    /**
     * Shop scope to apply to every WHERE.
     *
     *   • null: super_admin with no explicit filter — query across all shops
     *   • []  : explicit filter for a shop the user can't access (empty result)
     *   • [N] : single-shop scope (specific filter, or seller's only shop)
     *   • [N,M,...]: owner across all owned shops without explicit filter
     *
     * @param  array<string, mixed>  $filters
     * @return list<int>|null
     */
    private function resolveShopIdFilter(User $user, array $filters): ?array
    {
        if (array_key_exists('shop_id', $filters) && $filters['shop_id'] !== null) {
            $requested = (int) $filters['shop_id'];
            $accessible = $user->accessibleShopIds();
            if ($accessible !== null && ! in_array($requested, $accessible, true)) {
                // User explicitly asked for a shop they can't see — return
                // empty array so queries return no rows. Better than throwing
                // because the dashboard is a read-only aggregation surface.
                return [];
            }

            return [$requested];
        }

        return $user->accessibleShopIds();
    }

    private function resolveSellerId(User $user): ?int
    {
        if ($user->role === UserRole::Seller) {
            return (int) $user->id;
        }

        return null;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>|null  $shopIds  null = no shop filter (super_admin all)
     * @return Builder<TModel>
     */
    private function scopeByShopAndPeriod(Builder $query, ?array $shopIds, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        if ($shopIds !== null) {
            $query->whereIn('shop_id', $shopIds);
        }

        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * @param  Builder<Sale>  $query
     * @return array<int, array<string, mixed>>
     */
    private function recentSales(Builder $query): array
    {
        return (clone $query)
            ->with(['user'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'customer_name' => $sale->customer_name,
                'total' => (float) $sale->total,
                'paid' => (float) $sale->paid,
                'debt' => (float) $sale->debt,
                'payment_type' => $sale->payment_type,
                'actor_name' => $sale->user?->name,
                'created_at' => $sale->created_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * @param  Builder<Expense>  $query
     * @return array<int, array<string, mixed>>
     */
    private function recentExpenses(Builder $query): array
    {
        return (clone $query)
            ->with(['user'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'name' => $expense->name,
                'total' => (float) $expense->total,
                'actor_name' => $expense->user?->name,
                'created_at' => $expense->created_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * @param  list<int>|null  $shopIds
     * @return array<int, array<string, mixed>>
     */
    private function recentDebtTransactions(?array $shopIds, ?int $sellerId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DebtTransaction::query()
            ->with(['debt', 'user'])
            ->when($shopIds !== null, fn (Builder $query) => $query->whereIn('shop_id', $shopIds))
            ->when($sellerId !== null, fn (Builder $query) => $query->where('user_id', $sellerId))
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (DebtTransaction $transaction): array => [
                'id' => $transaction->id,
                'debt_id' => $transaction->debt_id,
                'person_name' => $transaction->debt?->person_name,
                'direction' => $transaction->debt?->direction,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'actor_name' => $transaction->user?->name,
                'created_at' => $transaction->created_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * @param  Builder<Product>  $query
     * @return array<int, array<string, mixed>>
     */
    private function lowStockProducts(Builder $query): array
    {
        return (clone $query)
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'low_stock_alert')
            ->orderByRaw('(low_stock_alert - stock_quantity) desc')
            ->limit(5)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'unit' => $product->unit,
                'stock_quantity' => (float) $product->stock_quantity,
                'low_stock_alert' => (float) $product->low_stock_alert,
            ])
            ->all();
    }

    /**
     * @param  Builder<Debt>  $query
     * @return array<int, array<string, mixed>>
     */
    private function unpaidDebts(Builder $query): array
    {
        return (clone $query)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Debt $debt): array => [
                'id' => $debt->id,
                'person_name' => $debt->person_name,
                'direction' => $debt->direction,
                'balance' => (float) $debt->balance,
                'updated_at' => $debt->updated_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * Aggregate refund totals + reversed COGS for sale returns processed
     * inside the given window, scoped to the same shop / seller filters
     * as the parent report.
     *
     * `total` — money refunded to customers (reduces net sales).
     * `cogs`  — original cost of the returned units (reverses COGS).
     *
     * The COGS reversal joins sale_return_items back to the parent
     * sale's sale_items via (sale_id, product_id) to pick up the
     * snapshotted cost_price at the time of sale. The same product
     * appearing multiple times in one sale shares the same cost_price
     * (snapshot), so MAX is a safe deduplication.
     *
     * @param  array<int>|null  $shopIds
     * @return array{total: float, cogs: float}
     */
    private function returnsTotalsForPeriod(
        ?array $shopIds,
        ?int $sellerId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $totalsQuery = SaleReturn::query()
            ->whereBetween('sale_returns.created_at', [$from, $to]);

        if ($shopIds !== null) {
            $totalsQuery->whereIn('sale_returns.shop_id', $shopIds);
        }

        /**
         * Sellers must see refunds against THEIR sales, regardless of who
         * pressed the "return" button. Filtering on `sale_returns.user_id`
         * (the actor who processed the refund) would hide every owner-driven
         * return from the seller's dashboard, even though the sale itself
         * belongs to the seller. Join the parent sale and filter on
         * `sales.user_id` — same contract `ReportController` already uses.
         */
        if ($sellerId !== null) {
            $totalsQuery
                ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
                ->where('sales.user_id', $sellerId)
                ->select('sale_returns.*');
        }

        $returnsTotal = (float) (clone $totalsQuery)->sum('sale_returns.total');

        $cogsQuery = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->joinSub(
                DB::table('sale_items')
                    ->select('sale_id', 'product_id', DB::raw('MAX(cost_price) as cost_price'))
                    ->whereNotNull('product_id')
                    ->groupBy('sale_id', 'product_id'),
                'si',
                fn ($join) => $join
                    ->on('si.sale_id', '=', 'sale_returns.sale_id')
                    ->on('si.product_id', '=', 'sale_return_items.product_id'),
            )
            ->whereBetween('sale_returns.created_at', [$from, $to]);

        if ($shopIds !== null) {
            $cogsQuery->whereIn('sale_returns.shop_id', $shopIds);
        }

        if ($sellerId !== null) {
            $cogsQuery
                ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
                ->where('sales.user_id', $sellerId);
        }

        $returnsCogs = (float) $cogsQuery
            ->selectRaw('COALESCE(SUM(sale_return_items.quantity * si.cost_price), 0) as cogs')
            ->value('cogs');

        return ['total' => $returnsTotal, 'cogs' => $returnsCogs];
    }
}
