<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\Api\V1\DashboardCacheVersion;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(
        private readonly DashboardCacheVersion $cacheVersion,
    ) {}

    public function sales(Request $request): JsonResponse
    {
        $user = $request->user();
        // Cache key must distinguish per-user scope: owners with different
        // owned-shop sets must NOT share cache slots even when both query
        // without an explicit shop_id. Encoding user_id alongside the
        // resolved shop scope guarantees that.
        $shopId = $user->isSuperAdmin()
            ? 'sa_'.($request->shop_id ?? 'all')
            : 'user_'.$user->id.($request->filled('shop_id') ? '_shop_'.$request->integer('shop_id') : '');
        $version = $this->cacheVersion->versionForShop(is_int($shopId) ? $shopId : null);
        $cacheKey = "reports:sales:shop_{$shopId}:v{$version}:from_{$request->date_from}:to_{$request->date_to}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            // Eager-load items (+ their product for the canonical name)
            // and the seller so receipt blocks in the PDF can render
            // without a second request. The `sale_items` table stores
            // the line label in `name` — for product lines we prefer
            // the current product name, falling back to the stored
            // value (matches SaleResource's resolution).
            $sales = $this->scopeByDate($this->scopeSales($user, $request), $request)
                ->with([
                    'items:id,sale_id,product_id,name,unit,quantity,price,total,cost_price',
                    'items.product:id,name',
                    'user:id,name',
                ])
                ->get(['id', 'shop_id', 'user_id', 'customer_name', 'type', 'total', 'discount', 'paid', 'debt', 'payment_type', 'created_at']);

            $salesGross = (float) $sales->sum('total');
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

            // Returns processed in the same window subtract from net
            // revenue. We can't reduce per-row totals because the return
            // and the sale can straddle the report period; instead we
            // expose returns as a top-level deduction.
            $returnsTotal = $this->returnsTotalForPeriod($user, $request);
            $netSalesTotal = $salesGross - $returnsTotal;
            // Per-item breakdown for the PDF export "Возвраты" table.
            // Skipped when the period has no refunds to keep the payload
            // light. Same row-cap reasoning as the expenses report:
            // bounded by the date window so unbounded growth is unlikely.
            $returnsItems = $returnsTotal > 0
                ? $this->returnsItemsForPeriod($user, $request)
                : [];

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

            // Per-sale receipts (items inline) — feeds the "Чеки" section
            // of the PDF export. Sellers must not see `cost_price`; the
            // resource-style filter is applied here so a downstream
            // consumer can't reconstruct it.
            $isSeller = $user->role === \App\UserRole::Seller;
            $receipts = $sales
                ->sortByDesc('created_at')
                ->map(fn (Sale $sale) => [
                    'sale_id' => (string) $sale->id,
                    'created_at' => $sale->created_at?->toISOString(),
                    'customer_name' => $sale->customer_name,
                    'seller_name' => $sale->user?->name,
                    'payment_type' => $sale->payment_type?->value ?? $sale->payment_type,
                    'type' => $sale->type?->value ?? $sale->type,
                    'total' => (float) $sale->total,
                    'discount' => (float) $sale->discount,
                    'paid' => (float) $sale->paid,
                    'debt' => (float) $sale->debt,
                    'items' => $sale->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name ?? $item->name,
                        'unit' => $item->unit,
                        'quantity' => (float) $item->quantity,
                        'price' => (float) $item->price,
                        'total' => (float) $item->total,
                        // Sellers see null — backend policy mirror.
                        'cost_price' => $isSeller ? null : (float) $item->cost_price,
                    ])->values()->all(),
                ])
                ->values()
                ->all();

            return [
                'total_sales' => $salesCount,
                // `total_amount` stays NET so callers that already
                // subtract returns externally don't double-count. The
                // gross-only number is exposed under `sales_gross` for
                // the UI to render an explicit "минус возвраты" line.
                'total_amount' => $netSalesTotal,
                'sales_gross' => $salesGross,
                'returns_total' => $returnsTotal,
                'returns' => $returnsItems,
                'receipts' => $receipts,
                'cash' => $cashTotal,
                'card' => $cardTotal,
                'transfer' => $transferTotal,
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'data' => $dailyData,
                'sales_total' => $netSalesTotal,
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
        // Cache key must distinguish per-user scope: owners with different
        // owned-shop sets must NOT share cache slots even when both query
        // without an explicit shop_id. Encoding user_id alongside the
        // resolved shop scope guarantees that.
        $shopId = $user->isSuperAdmin()
            ? 'sa_'.($request->shop_id ?? 'all')
            : 'user_'.$user->id.($request->filled('shop_id') ? '_shop_'.$request->integer('shop_id') : '');
        $version = $this->cacheVersion->versionForShop(is_int($shopId) ? $shopId : null);
        $cacheKey = "reports:expenses:shop_{$shopId}:v{$version}:from_{$request->date_from}:to_{$request->date_to}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            // Fetch the full row set so we can both aggregate (totals,
            // daily breakdown) and emit a per-item table for the export
            // PDF without a second request. Capped implicitly by the
            // date window — a single shop's expenses for a month rarely
            // exceed a few hundred rows.
            $expenses = $this->scopeByDate($this->scopeExpenses($user, $request), $request)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'name', 'unit', 'quantity', 'price', 'total', 'note', 'created_at']);

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

            $items = $expenses->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'name' => $expense->name,
                'unit' => $expense->unit ?? null,
                'quantity' => (float) $expense->quantity,
                'price' => (float) $expense->price,
                'total' => (float) $expense->total,
                'note' => $expense->note ?? null,
                'created_at' => $expense->created_at?->toISOString(),
            ])->all();

            return [
                'total_amount' => $expenseTotal,
                'count' => $expenseCount,
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'data' => $dailyData,
                'items' => $items,
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
        // Sellers must never see cost-of-goods figures (it would let them
        // back-calculate `cost_price` per product, which their role explicitly
        // hides). The cache key segregates seller responses so the cost-less
        // payload never leaks into an owner's slot and vice-versa.
        $isSeller = $user->role === UserRole::Seller;
        $shopId = $user->isSuperAdmin()
            ? 'sa_'.($request->shop_id ?? 'all')
            : 'user_'.$user->id.($request->filled('shop_id') ? '_shop_'.$request->integer('shop_id') : '');
        $version = $this->cacheVersion->versionForShop(is_int($shopId) ? $shopId : null);
        $roleSuffix = $isSeller ? ':seller' : '';
        $cacheKey = "reports:profit:shop_{$shopId}:v{$version}:from_{$request->date_from}:to_{$request->date_to}{$roleSuffix}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request, $isSeller) {
            $salesQuery = $this->scopeByDate($this->scopeSales($user, $request), $request);
            $expensesQuery = $this->scopeByDate($this->scopeExpenses($user, $request), $request);

            $salesGross = (float) $salesQuery->sum('total');
            $expensesTotal = (float) $expensesQuery->sum('total');

            // Returns reverse revenue (and COGS, for non-seller roles).
            $returnsTotal = $this->returnsTotalForPeriod($user, $request);
            $netSalesTotal = $salesGross - $returnsTotal;

            if ($isSeller) {
                // Seller-facing payload: profit ≡ revenue − expenses.
                // We deliberately drop every cost-related field so a seller
                // can't reverse-engineer cost_price from the response body.
                $profit = $netSalesTotal - $expensesTotal;

                return [
                    'total_sales' => $netSalesTotal,
                    'total_expenses' => $expensesTotal,
                    'total_cost' => 0,
                    'date_from' => $request->string('date_from')->toString(),
                    'date_to' => $request->string('date_to')->toString(),
                    'profit' => $profit,
                    'sales_total' => $netSalesTotal,
                    'sales_gross' => $salesGross,
                    'returns_total' => $returnsTotal,
                    'returns_cogs' => 0,
                    'cost_of_goods_sold' => 0,
                    'cost_of_goods_sold_gross' => 0,
                    'expenses_total' => $expensesTotal,
                ];
            }

            $saleItemsQuery = $this->scopeByDate($this->scopeSaleItems($user, $request), $request, column: 'sales.created_at');
            $costOfGoodsSold = (float) $saleItemsQuery
                ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.cost_price), 0) as cogs')
                ->value('cogs');

            // Returns processed in the window reverse both revenue and
            // the matching COGS — the goods went back on the shelf, the
            // cost we'd booked against them is no longer real. Without
            // this, a fully returned sale leaves period_profit pinned
            // at -COGS forever.
            $returnsCogs = $this->returnsCogsForPeriod($user, $request);

            $netCostOfGoodsSold = $costOfGoodsSold - $returnsCogs;
            $profit = $netSalesTotal - $netCostOfGoodsSold - $expensesTotal;

            return [
                'total_sales' => $netSalesTotal,
                'total_expenses' => $expensesTotal,
                'total_cost' => $netCostOfGoodsSold,
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'profit' => $profit,
                'sales_total' => $netSalesTotal,
                'sales_gross' => $salesGross,
                'returns_total' => $returnsTotal,
                'returns_cogs' => $returnsCogs,
                'cost_of_goods_sold' => $netCostOfGoodsSold,
                'cost_of_goods_sold_gross' => $costOfGoodsSold,
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
        $shopId = $user->isSuperAdmin()
            ? 'sa_'.($request->shop_id ?? 'all')
            : 'user_'.$user->id.($request->filled('shop_id') ? '_shop_'.$request->integer('shop_id') : '');
        $version = $this->cacheVersion->versionForShop(is_int($shopId) ? $shopId : null);

        // Period mode is opted into by sending both date_from and date_to.
        // The cache key must distinguish the two modes — a "now" snapshot
        // shares no rows with an A→B balance report, and mixing them
        // would serve stale or wrong data to the other view.
        $isPeriod = $request->filled('date_from') && $request->filled('date_to');
        $cacheKey = $isPeriod
            ? "reports:stock:v{$version}:shop_{$shopId}:period:{$request->date_from}:{$request->date_to}"
            : "reports:stock:v{$version}:shop_{$shopId}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $request, $isPeriod) {
            return $isPeriod
                ? $this->stockPeriodBalance($user, $request)
                : $this->stockSnapshotNow($user, $request);
        });

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }

    /**
     * "Right now" stock snapshot — original behaviour preserved for the
     * default report view (no dates supplied).
     *
     * @return array<string, mixed>
     */
    private function stockSnapshotNow(User $user, Request $request): array
    {
        $products = $this->scopeProducts($user, $request);

        $totalProducts = (int) (clone $products)->count();
        $totalStockQuantity = (float) (clone $products)->sum('stock_quantity');
        $totalValue = (float) (clone $products)
            ->selectRaw('COALESCE(SUM(stock_quantity * sale_price), 0) as total_value')
            ->value('total_value');
        $totalCostValue = (float) (clone $products)
            ->selectRaw('COALESCE(SUM(stock_quantity * cost_price), 0) as total_cost_value')
            ->value('total_cost_value');
        $lowStockCount = (int) (clone $products)
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'low_stock_alert')
            ->count();
        $outOfStockCount = (int) (clone $products)->where('stock_quantity', '<=', 0)->count();
        $productRows = (clone $products)
            ->select(['id', 'name', 'code', 'unit', 'stock_quantity', 'low_stock_alert', 'sale_price', 'cost_price'])
            ->orderByRaw('(stock_quantity * sale_price) desc')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'unit' => $product->unit,
                'stock_quantity' => (float) $product->stock_quantity,
                'low_stock_alert' => (float) $product->low_stock_alert,
                'sale_price' => (float) $product->sale_price,
                'cost_price' => (float) $product->cost_price,
                'value' => (float) $product->stock_quantity * (float) $product->sale_price,
                'cost_value' => (float) $product->stock_quantity * (float) $product->cost_price,
            ])
            ->values()
            ->all();

        return [
            'mode' => 'snapshot',
            'total_products' => $totalProducts,
            'total_value' => $totalValue,
            'total_cost_value' => $totalCostValue,
            'low_stock' => $lowStockCount,
            'out_of_stock' => $outOfStockCount,
            'data' => $productRows,
            'products_count' => $totalProducts,
            'stock_quantity_total' => $totalStockQuantity,
            'low_stock_products_count' => $lowStockCount,
        ];
    }

    /**
     * Period-balance stock report. For each product computes:
     *   opening  — stock_quantity AT date_from (reconstructed)
     *   incoming — purchases inside (date_from, date_to]
     *   outgoing — sales inside (date_from, date_to]
     *   returned — sale-returns inside (date_from, date_to]
     *   closing  — stock_quantity AT date_to (reconstructed)
     *
     * Reconstruction rule (T = some past moment):
     *   stock_at_T = current_stock
     *              - purchases_after_T
     *              + sales_after_T
     *              - returns_after_T
     *
     * Purchases increased stock, so subtract their post-T arrivals to
     * walk back. Sales decreased stock, so add post-T departures back.
     * Returns increased stock (goods back on shelf), so subtract them.
     *
     * @return array<string, mixed>
     */
    private function stockPeriodBalance(User $user, Request $request): array
    {
        $dateFrom = $request->date('date_from')->startOfDay();
        $dateTo = $request->date('date_to')->endOfDay();

        $products = $this->scopeProducts($user, $request)
            ->select(['id', 'name', 'code', 'unit', 'shop_id', 'stock_quantity', 'low_stock_alert', 'sale_price', 'cost_price'])
            ->get();

        // Movements grouped per product, split into in-period (after_from
        // AND <= date_to) and after-period (> date_to). One pass per
        // movement table; downstream code derives the post-`date_from`
        // total as `in_period + after_to`.
        $purchases = $this->aggregateMovements(
            DB::table('purchase_items')
                ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id'),
            $user,
            $request,
            'purchases',
            'purchase_items',
            $dateFrom,
            $dateTo,
            softDeletes: true,
        );

        $sales = $this->aggregateMovements(
            DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id'),
            $user,
            $request,
            'sales',
            'sale_items',
            $dateFrom,
            $dateTo,
            softDeletes: true,
        );

        $returns = $this->aggregateMovements(
            DB::table('sale_return_items')
                ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id'),
            $user,
            $request,
            'sale_returns',
            'sale_return_items',
            $dateFrom,
            $dateTo,
            // sale_returns table is append-only (no soft-delete column).
            softDeletes: false,
        );

        $rows = [];
        $totalOpening = 0.0;
        $totalIncoming = 0.0;
        $totalOutgoing = 0.0;
        $totalReturned = 0.0;
        $totalClosing = 0.0;
        $totalClosingValue = 0.0;
        $totalClosingCostValue = 0.0;

        foreach ($products as $product) {
            $pId = (string) $product->id;
            $current = (float) $product->stock_quantity;

            $purchasesAfterFrom = ($purchases[$pId]['in_period'] ?? 0) + ($purchases[$pId]['after_to'] ?? 0);
            $purchasesAfterTo = (float) ($purchases[$pId]['after_to'] ?? 0);
            $salesAfterFrom = ($sales[$pId]['in_period'] ?? 0) + ($sales[$pId]['after_to'] ?? 0);
            $salesAfterTo = (float) ($sales[$pId]['after_to'] ?? 0);
            $returnsAfterFrom = ($returns[$pId]['in_period'] ?? 0) + ($returns[$pId]['after_to'] ?? 0);
            $returnsAfterTo = (float) ($returns[$pId]['after_to'] ?? 0);

            $opening = $current - $purchasesAfterFrom + $salesAfterFrom - $returnsAfterFrom;
            $closing = $current - $purchasesAfterTo + $salesAfterTo - $returnsAfterTo;
            $incoming = (float) ($purchases[$pId]['in_period'] ?? 0);
            $outgoing = (float) ($sales[$pId]['in_period'] ?? 0);
            $returned = (float) ($returns[$pId]['in_period'] ?? 0);

            $rows[] = [
                'id' => $pId,
                'name' => $product->name,
                'code' => $product->code,
                'unit' => $product->unit,
                'low_stock_alert' => (float) $product->low_stock_alert,
                'opening_qty' => $opening,
                'incoming_qty' => $incoming,
                'outgoing_qty' => $outgoing,
                'returned_qty' => $returned,
                'closing_qty' => $closing,
                'sale_price' => (float) $product->sale_price,
                'cost_price' => (float) $product->cost_price,
                'closing_value' => $closing * (float) $product->sale_price,
                'closing_cost_value' => $closing * (float) $product->cost_price,
                // Keep `stock_quantity` for backward compatibility — older
                // mobile clients render this as the right-hand column.
                'stock_quantity' => $closing,
                'value' => $closing * (float) $product->sale_price,
                'cost_value' => $closing * (float) $product->cost_price,
            ];

            $totalOpening += $opening;
            $totalIncoming += $incoming;
            $totalOutgoing += $outgoing;
            $totalReturned += $returned;
            $totalClosing += $closing;
            $totalClosingValue += $closing * (float) $product->sale_price;
            $totalClosingCostValue += $closing * (float) $product->cost_price;
        }

        // Sort by absolute period activity desc — products with no
        // movements in the window fall to the bottom, those with the
        // most churn float to the top.
        usort($rows, function (array $a, array $b): int {
            $activity = fn (array $r): float => $r['incoming_qty'] + $r['outgoing_qty'] + $r['returned_qty'];

            return $activity($b) <=> $activity($a);
        });

        $lowStockCount = 0;
        $outOfStockCount = 0;
        foreach ($rows as $r) {
            if ($r['closing_qty'] <= 0) {
                $outOfStockCount++;
            }
        }

        return [
            'mode' => 'period',
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'total_products' => count($rows),
            'total_value' => $totalClosingValue,
            'total_cost_value' => $totalClosingCostValue,
            'opening_qty' => $totalOpening,
            'incoming_qty' => $totalIncoming,
            'outgoing_qty' => $totalOutgoing,
            'returned_qty' => $totalReturned,
            'closing_qty' => $totalClosing,
            'low_stock' => $lowStockCount,
            'out_of_stock' => $outOfStockCount,
            'data' => $rows,
            'products_count' => count($rows),
            'stock_quantity_total' => $totalClosing,
            'low_stock_products_count' => $lowStockCount,
        ];
    }

    /**
     * Aggregate `quantity` from a joined movement table, grouped by
     * product_id and split into in-period vs after-period buckets.
     * Shared by purchases / sales / sale-returns since they all follow
     * the same shape (header table has created_at + shop_id, lines
     * table has product_id + quantity).
     *
     * Returns `[product_id => ['in_period' => float, 'after_to' => float]]`.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array<string, array{in_period: float, after_to: float}>
     */
    private function aggregateMovements(
        $query,
        User $user,
        Request $request,
        string $headerTable,
        string $linesTable,
        \Carbon\CarbonInterface $dateFrom,
        \Carbon\CarbonInterface $dateTo,
        bool $softDeletes = true,
    ): array {
        $createdAtCol = "{$headerTable}.created_at";
        $shopCol = "{$headerTable}.shop_id";
        $productCol = "{$linesTable}.product_id";
        $qtyCol = "{$linesTable}.quantity";

        // Skip rows that don't reference a product — service-line sales
        // have product_id = NULL and shouldn't affect stock math.
        $query->whereNotNull($productCol);

        // Only need rows after date_from; older ones contribute nothing
        // to either bucket.
        $query->where($createdAtCol, '>', $dateFrom);

        // Shop scope mirrors `applyShopScope` but operates on raw query
        // builder (`applyShopScope` only knows Eloquent).
        $accessibleShopIds = $user->accessibleShopIds();
        if ($request->filled('shop_id')) {
            $requested = $request->integer('shop_id');
            if ($accessibleShopIds === null || in_array($requested, $accessibleShopIds, true)) {
                $query->where($shopCol, $requested);
            } else {
                return [];
            }
        } elseif ($accessibleShopIds !== null) {
            $query->whereIn($shopCol, $accessibleShopIds);
        }

        // Exclude soft-deleted parent rows so a cancelled purchase /
        // sale doesn't show up as ghost stock activity. The sale_returns
        // table is append-only and has no deleted_at column — caller
        // sets softDeletes=false to skip the filter.
        if ($softDeletes) {
            $query->whereNull("{$headerTable}.deleted_at");
        }

        $rows = $query
            ->groupBy($productCol)
            ->selectRaw("{$productCol} as product_id, "
                ."COALESCE(SUM(CASE WHEN {$createdAtCol} > ? THEN {$qtyCol} ELSE 0 END), 0) as after_to, "
                ."COALESCE(SUM(CASE WHEN {$createdAtCol} > ? AND {$createdAtCol} <= ? THEN {$qtyCol} ELSE 0 END), 0) as in_period",
                [$dateTo, $dateFrom, $dateTo])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->product_id] = [
                'in_period' => (float) $row->in_period,
                'after_to' => (float) $row->after_to,
            ];
        }

        return $result;
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
        // Sellers see their own receipts only — owners/admins see
        // everything in the shop scope.
        $this->applySellerOwnership($query, $user, 'sales.user_id');

        return $query;
    }

    /**
     * @return Builder<SaleItem>
     */
    private function scopeSaleItems(User $user, Request $request): Builder
    {
        $query = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id');
        $this->applyShopScope($query, $user, $request, table: 'sales');
        $this->applySellerOwnership($query, $user, 'sales.user_id');

        return $query;
    }

    /**
     * @return Builder<Expense>
     */
    private function scopeExpenses(User $user, Request $request): Builder
    {
        $query = Expense::query();
        $this->applyShopScope($query, $user, $request);
        $this->applySellerOwnership($query, $user, 'expenses.user_id');

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
     * Flat per-line breakdown of returns inside the report window for the
     * PDF "Возвраты" table on mobile. One row per `sale_return_items`
     * line, enriched with parent return metadata (actor, reason, refund
     * method) so a single table render covers everything the export
     * needs without grouping client-side.
     *
     * Scoping mirrors `returnsTotalForPeriod` — sellers see only refunds
     * processed against their own sales, owners/admins see everything in
     * the shop scope.
     *
     * @return list<array<string, mixed>>
     */
    private function returnsItemsForPeriod(User $user, Request $request): array
    {
        $query = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->leftJoin('users', 'users.id', '=', 'sale_returns.user_id');

        if ($request->filled('date_from')) {
            $query->whereDate('sale_returns.created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_returns.created_at', '<=', $request->date('date_to'));
        }

        $accessibleShopIds = $user->accessibleShopIds();
        if ($request->filled('shop_id')) {
            $requested = $request->integer('shop_id');
            if ($accessibleShopIds === null || in_array($requested, $accessibleShopIds, true)) {
                $query->where('sale_returns.shop_id', $requested);
            } else {
                return [];
            }
        } elseif ($accessibleShopIds !== null) {
            $query->whereIn('sale_returns.shop_id', $accessibleShopIds);
        }

        if ($user->role === UserRole::Seller) {
            $query
                ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
                ->where('sales.user_id', $user->id);
        }

        return $query
            ->orderByDesc('sale_returns.created_at')
            ->orderBy('sale_return_items.id')
            ->get([
                'sale_returns.id as sale_return_id',
                'sale_returns.sale_id',
                'sale_returns.created_at',
                'sale_returns.reason',
                'sale_returns.refund_method',
                'users.name as seller_name',
                'sale_return_items.name as product_name',
                'sale_return_items.unit',
                'sale_return_items.quantity',
                'sale_return_items.price',
                'sale_return_items.total',
            ])
            ->map(fn ($row) => [
                'sale_return_id' => (string) $row->sale_return_id,
                'sale_id' => (string) $row->sale_id,
                'created_at' => $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->toISOString()
                    : null,
                'reason' => $row->reason,
                'refund_method' => $row->refund_method,
                'seller_name' => $row->seller_name,
                'product_name' => $row->product_name,
                'unit' => $row->unit,
                'quantity' => (float) $row->quantity,
                'price' => (float) $row->price,
                'total' => (float) $row->total,
            ])
            ->all();
    }

    /**
     * Sum of refund amounts (sale_returns.total) inside the report
     * window. Subtracted from sales totals so the report reflects net
     * revenue rather than gross sales.
     */
    private function returnsTotalForPeriod(User $user, Request $request): float
    {
        $query = SaleReturn::query();

        // Shop scope — qualified column to stay unambiguous after the
        // optional seller-join below.
        $accessibleShopIds = $user->accessibleShopIds();
        if ($request->filled('shop_id')) {
            $requested = $request->integer('shop_id');
            if ($accessibleShopIds === null || in_array($requested, $accessibleShopIds, true)) {
                $query->where('sale_returns.shop_id', $requested);
            } else {
                return 0.0;
            }
        } elseif ($accessibleShopIds !== null) {
            $query->whereIn('sale_returns.shop_id', $accessibleShopIds);
        }

        // Date scope — qualified to avoid ambiguity once `sales` joins in.
        if ($request->filled('date_from')) {
            $query->whereDate('sale_returns.created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_returns.created_at', '<=', $request->date('date_to'));
        }

        // Sellers see only returns against THEIR sales — join to the
        // parent sale and filter by sales.user_id. Without this, a
        // seller's report would include refunds processed for sales
        // recorded by other staff, distorting their net revenue.
        if ($user->role === UserRole::Seller) {
            $query
                ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
                ->where('sales.user_id', $user->id)
                ->select('sale_returns.*');
        }

        return (float) $query->sum('sale_returns.total');
    }

    /**
     * Sum of (returned_quantity × original cost_price) inside the
     * report window. Joins sale_return_items back to the parent sale's
     * sale_items by (sale_id, product_id) to pick up the cost_price
     * snapshotted at the time of sale. MAX(cost_price) is a safe
     * dedup — the same product appearing multiple times in one sale
     * shares the snapshotted cost.
     */
    private function returnsCogsForPeriod(User $user, Request $request): float
    {
        $query = DB::table('sale_return_items')
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
            );

        // Date + shop scoping mirrors the rest of the controller; the
        // `applyShopScope` helper only knows Eloquent builders, so we
        // inline the equivalent query-builder filter here.
        if ($request->filled('date_from')) {
            $query->whereDate('sale_returns.created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_returns.created_at', '<=', $request->date('date_to'));
        }

        $accessibleShopIds = $user->accessibleShopIds();
        if ($request->filled('shop_id')) {
            $requested = $request->integer('shop_id');
            if ($accessibleShopIds === null || in_array($requested, $accessibleShopIds, true)) {
                $query->where('sale_returns.shop_id', $requested);
            } else {
                return 0.0;
            }
        } elseif ($accessibleShopIds !== null) {
            $query->whereIn('sale_returns.shop_id', $accessibleShopIds);
        }

        // Seller scope: only count returns against this seller's own
        // sales. Joins the parent sale and filters by sales.user_id.
        if ($user->role === UserRole::Seller) {
            $query
                ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
                ->where('sales.user_id', $user->id);
        }

        return (float) $query
            ->selectRaw('COALESCE(SUM(sale_return_items.quantity * si.cost_price), 0) as cogs')
            ->value('cogs');
    }

    /**
     * Narrow a query to rows owned by the actor when they're a seller.
     * No-op for owners and super_admins — they see everything in their
     * accessible shop set. Pass the fully-qualified column when the
     * query has joined tables (e.g. `sales.user_id`); otherwise the
     * default `user_id` works on the base table.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applySellerOwnership(Builder $query, User $user, string $column = 'user_id'): void
    {
        if ($user->role === UserRole::Seller) {
            $query->where($column, $user->id);
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyShopScope(Builder $query, User $user, Request $request, string $table = ''): void
    {
        $column = ($table !== '' ? $table.'.' : '').'shop_id';
        $accessibleShopIds = $user->accessibleShopIds();

        // Explicit `shop_id` filter from the request — but only honored if
        // the user can actually see that shop. For owner/seller a
        // non-accessible shop_id collapses to "no rows" (empty whereIn).
        if ($request->filled('shop_id')) {
            $requested = $request->integer('shop_id');
            if ($accessibleShopIds === null || in_array($requested, $accessibleShopIds, true)) {
                $query->where($column, $requested);

                return;
            }
            $query->whereRaw('1 = 0');

            return;
        }

        // No explicit filter — fall back to the user's accessible scope.
        if ($accessibleShopIds !== null) {
            $query->whereIn($column, $accessibleShopIds);
        }
    }
}
