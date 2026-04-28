<?php

namespace App\Services\Api\V1;

use App\Models\Debt;
use App\Models\DebtTransaction;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters): array
    {
        [$from, $to, $period] = $this->resolvePeriod($filters);
        $shopId = $this->resolveShopId($user, $filters);
        $sellerId = $this->resolveSellerId($user);

        $salesQuery = $this->scopeByShopAndPeriod(Sale::query(), $shopId, $from, $to);
        $expensesQuery = $this->scopeByShopAndPeriod(Expense::query(), $shopId, $from, $to);
        $productsQuery = Product::query();
        $debtsQuery = Debt::query()->where('balance', '>', 0);

        if ($shopId !== null) {
            $productsQuery->where('shop_id', $shopId);
            $debtsQuery->where('shop_id', $shopId);
        }

        if ($sellerId !== null) {
            $salesQuery->where('user_id', $sellerId);
            $expensesQuery->where('user_id', $sellerId);
            $debtsQuery->where('user_id', $sellerId);
        }

        $salesTotal = (float) (clone $salesQuery)->sum('total');
        $expensesTotal = (float) (clone $expensesQuery)->sum('total');

        $saleItemsQuery = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.created_at', [$from, $to]);

        if ($shopId !== null) {
            $saleItemsQuery->where('sales.shop_id', $shopId);
        }

        if ($sellerId !== null) {
            $saleItemsQuery->where('sales.user_id', $sellerId);
        }

        $costOfGoodsSold = (float) (clone $saleItemsQuery)
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.cost_price), 0) as cogs')
            ->value('cogs');
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

        return [
            'period' => $period,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'shop_id' => $shopId,
            'period_sales_total' => $salesTotal,
            'period_expenses_total' => $expensesTotal,
            'period_profit' => $salesTotal - $costOfGoodsSold - $expensesTotal,
            'period_cogs' => $costOfGoodsSold,
            'debts_receivable' => $receivable,
            'debts_payable' => $payable,
            'debts_net' => $receivable - $payable,
            'stock_total_qty' => (float) ($productStats->stock_total_qty ?? 0),
            'stock_total_cost' => (float) ($productStats->stock_total_cost ?? 0),
            'stock_total_sales_value' => (float) ($productStats->stock_total_sales_value ?? 0),
            'low_stock_count' => (int) ($productStats->low_stock_count ?? 0),
            'recent_sales' => $this->recentSales($salesQuery),
            'recent_expenses' => $this->recentExpenses($expensesQuery),
            'recent_debt_transactions' => $this->recentDebtTransactions($shopId, $sellerId, $from, $to),
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
            'week' => [$anchor->startOfWeek()->startOfDay(), $anchor->endOfWeek()->endOfDay(), $period],
            'month' => [$anchor->startOfMonth()->startOfDay(), $anchor->endOfMonth()->endOfDay(), $period],
            'year' => [$anchor->startOfYear()->startOfDay(), $anchor->endOfYear()->endOfDay(), $period],
            default => [$anchor->startOfDay(), $anchor->endOfDay(), 'day'],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveShopId(User $user, array $filters): ?int
    {
        if (! $user->isSuperAdmin()) {
            return (int) $user->shop_id;
        }

        if (! array_key_exists('shop_id', $filters) || $filters['shop_id'] === null) {
            return null;
        }

        return (int) $filters['shop_id'];
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
     * @return Builder<TModel>
     */
    private function scopeByShopAndPeriod(Builder $query, ?int $shopId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
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
     * @return array<int, array<string, mixed>>
     */
    private function recentDebtTransactions(?int $shopId, ?int $sellerId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DebtTransaction::query()
            ->with(['debt', 'user'])
            ->when($shopId !== null, fn (Builder $query) => $query->where('shop_id', $shopId))
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
}
