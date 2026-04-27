import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    BarChart2,
    CreditCard,
    Medal,
    Minus,
    Package,
    Receipt,
    ShoppingCart,
    TrendingDown,
    TrendingUp,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type MonthlyData = {
    month: string;
    sales: number;
    expenses: number;
    profit: number;
};

type TopShop = {
    shop_name: string;
    total_sales: number;
    sales_count: number;
};

type Props = {
    filters: {
        shop_id: number | null;
        date_from: string;
        date_to: string;
    };
    shops: Array<{ id: number; name: string }>;
    summary: {
        sales_total: number;
        expenses_total: number;
        profit_estimate: number;
        purchases_total: number;
        debts_total: number;
        products_count: number;
        low_stock_count: number;
        sales_count: number;
        expenses_count: number;
    };
    monthlyBreakdown: MonthlyData[];
    topShops: TopShop[];
};

type QuickRangePreset = 'today' | 'week' | 'month' | 'year';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Reports', href: '/admin/reports' },
];

const quickRangeLabels: Record<QuickRangePreset, string> = {
    today: 'Today',
    week: 'This Week',
    month: 'This Month',
    year: 'This Year',
};

function formatDateInput(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatMoney(value: number): string {
    return Number(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatDate(iso: string): string {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function getQuickRange(preset: QuickRangePreset): {
    date_from: string;
    date_to: string;
} {
    const today = new Date();
    const from = new Date(today);

    if (preset === 'today') {
        return {
            date_from: formatDateInput(today),
            date_to: formatDateInput(today),
        };
    }

    if (preset === 'week') {
        const day = today.getDay();
        const daysSinceMonday = day === 0 ? 6 : day - 1;
        from.setDate(today.getDate() - daysSinceMonday);

        return {
            date_from: formatDateInput(from),
            date_to: formatDateInput(today),
        };
    }

    if (preset === 'month') {
        from.setDate(1);

        return {
            date_from: formatDateInput(from),
            date_to: formatDateInput(today),
        };
    }

    from.setMonth(0, 1);

    return {
        date_from: formatDateInput(from),
        date_to: formatDateInput(today),
    };
}

function MetricCard({
    label,
    value,
    icon: Icon,
    iconClassName,
    circleClassName,
    subtext,
}: {
    label: string;
    value: string | number;
    icon: LucideIcon;
    iconClassName: string;
    circleClassName: string;
    subtext?: string;
}) {
    return (
        <Card>
            <CardContent className="pt-5">
                <div className="flex items-start gap-3">
                    <div
                        className={cn(
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                            circleClassName,
                        )}
                    >
                        <Icon className={cn('h-5 w-5', iconClassName)} />
                    </div>
                    <div className="min-w-0">
                        <p className="text-sm text-muted-foreground">{label}</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">
                            {value}
                        </p>
                        {subtext && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                {subtext}
                            </p>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function getRankClass(index: number): string {
    if (index === 0) return 'bg-yellow-100 text-yellow-700';
    if (index === 1) return 'bg-slate-100 text-slate-600';
    if (index === 2) return 'bg-orange-100 text-orange-700';

    return 'bg-muted text-muted-foreground';
}

export default function AdminReportsPage({
    filters,
    shops,
    summary,
    monthlyBreakdown,
    topShops,
}: Props) {
    const selectedShop = shops.find((shop) => shop.id === filters.shop_id);
    const year = new Date().getFullYear();
    const monthRange = getQuickRange('month');
    const maxChartValue = Math.max(
        ...monthlyBreakdown.map((month) =>
            Math.max(month.sales, month.expenses),
        ),
        0,
    );
    const hasMonthlyData = maxChartValue > 0;

    function updateFilters(updates: Partial<Props['filters']>) {
        router.get(
            '/admin/reports',
            { ...filters, ...updates },
            { preserveState: true, preserveScroll: true },
        );
    }

    function clearShop() {
        router.get(
            '/admin/reports',
            { date_from: filters.date_from, date_to: filters.date_to },
            { preserveState: true, preserveScroll: true },
        );
    }

    function clearDateRange() {
        router.get(
            '/admin/reports',
            {
                shop_id: filters.shop_id,
                date_from: monthRange.date_from,
                date_to: monthRange.date_to,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin — Reports" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Financial Reports
                        </h1>
                        <p className="text-muted-foreground">
                            System-wide financial analytics
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {selectedShop && (
                            <Badge variant="secondary" className="gap-1 pr-1">
                                {selectedShop.name}
                                <button
                                    type="button"
                                    className="hover:text-foreground"
                                    onClick={clearShop}
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        )}
                        <Badge variant="secondary" className="gap-1 pr-1">
                            {formatDate(filters.date_from)} –{' '}
                            {formatDate(filters.date_to)}
                            <button
                                type="button"
                                className="hover:text-foreground"
                                onClick={clearDateRange}
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </Badge>
                    </div>
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-end gap-4 py-4">
                        <label className="space-y-1">
                            <span className="text-sm text-muted-foreground">
                                Shop
                            </span>
                            <select
                                value={filters.shop_id ?? ''}
                                className="h-9 min-w-44 rounded-md border border-input bg-background px-3 text-sm"
                                onChange={(event) =>
                                    updateFilters({
                                        shop_id: event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    })
                                }
                            >
                                <option value="">All Shops</option>
                                {shops.map((shop) => (
                                    <option key={shop.id} value={shop.id}>
                                        {shop.name}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="space-y-1">
                            <span className="text-sm text-muted-foreground">
                                Date From
                            </span>
                            <input
                                type="date"
                                value={filters.date_from}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                onChange={(event) =>
                                    updateFilters({
                                        date_from: event.target.value,
                                    })
                                }
                            />
                        </label>

                        <label className="space-y-1">
                            <span className="text-sm text-muted-foreground">
                                Date To
                            </span>
                            <input
                                type="date"
                                value={filters.date_to}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                onChange={(event) =>
                                    updateFilters({
                                        date_to: event.target.value,
                                    })
                                }
                            />
                        </label>

                        <div className="flex flex-wrap gap-2">
                            {(
                                Object.keys(
                                    quickRangeLabels,
                                ) as QuickRangePreset[]
                            ).map((preset) => (
                                <Button
                                    key={preset}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        updateFilters(getQuickRange(preset))
                                    }
                                >
                                    {quickRangeLabels[preset]}
                                </Button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <MetricCard
                        label="Total Sales"
                        value={formatMoney(summary.sales_total)}
                        icon={TrendingUp}
                        circleClassName="bg-emerald-100"
                        iconClassName="text-emerald-600"
                        subtext={`${summary.sales_count} transactions`}
                    />
                    <MetricCard
                        label="Total Expenses"
                        value={formatMoney(summary.expenses_total)}
                        icon={Receipt}
                        circleClassName="bg-orange-100"
                        iconClassName="text-orange-600"
                        subtext={`${summary.expenses_count} entries`}
                    />
                    <MetricCard
                        label="Net Profit"
                        value={formatMoney(summary.profit_estimate)}
                        icon={
                            summary.profit_estimate < 0
                                ? TrendingDown
                                : TrendingUp
                        }
                        circleClassName={cn(
                            summary.profit_estimate > 0 && 'bg-green-100',
                            summary.profit_estimate < 0 && 'bg-red-100',
                            summary.profit_estimate === 0 && 'bg-slate-100',
                        )}
                        iconClassName={cn(
                            summary.profit_estimate > 0 && 'text-green-600',
                            summary.profit_estimate < 0 && 'text-red-600',
                            summary.profit_estimate === 0 && 'text-slate-500',
                        )}
                    />
                    <MetricCard
                        label="Total Purchases"
                        value={formatMoney(summary.purchases_total)}
                        icon={ShoppingCart}
                        circleClassName="bg-purple-100"
                        iconClassName="text-purple-600"
                    />
                </div>

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <MetricCard
                        label="Total Debts"
                        value={formatMoney(summary.debts_total)}
                        icon={CreditCard}
                        circleClassName="bg-red-100"
                        iconClassName="text-red-600"
                    />
                    <MetricCard
                        label="Total Products"
                        value={summary.products_count}
                        icon={Package}
                        circleClassName="bg-slate-100"
                        iconClassName="text-slate-600"
                    />
                    <MetricCard
                        label="Low Stock"
                        value={summary.low_stock_count}
                        icon={AlertTriangle}
                        circleClassName={
                            summary.low_stock_count > 0
                                ? 'bg-amber-100'
                                : 'bg-slate-100'
                        }
                        iconClassName={
                            summary.low_stock_count > 0
                                ? 'text-amber-600'
                                : 'text-slate-500'
                        }
                    />
                    <MetricCard
                        label="Sales Transactions"
                        value={summary.sales_count}
                        icon={BarChart2}
                        circleClassName="bg-blue-100"
                        iconClassName="text-blue-600"
                    />
                </div>

                <div
                    className={cn(
                        'flex items-center gap-3 rounded-lg border p-4',
                        summary.profit_estimate > 0 &&
                            'border-emerald-200 bg-emerald-50',
                        summary.profit_estimate < 0 &&
                            'border-red-200 bg-red-50',
                        summary.profit_estimate === 0 &&
                            'border-slate-200 bg-slate-50',
                    )}
                >
                    {summary.profit_estimate > 0 && (
                        <TrendingUp className="h-6 w-6 text-emerald-600" />
                    )}
                    {summary.profit_estimate < 0 && (
                        <TrendingDown className="h-6 w-6 text-red-600" />
                    )}
                    {summary.profit_estimate === 0 && (
                        <Minus className="h-6 w-6 text-slate-500" />
                    )}
                    <div>
                        <p
                            className={cn(
                                'font-semibold',
                                summary.profit_estimate > 0 &&
                                    'text-emerald-700',
                                summary.profit_estimate < 0 && 'text-red-700',
                                summary.profit_estimate === 0 &&
                                    'text-slate-600',
                            )}
                        >
                            {summary.profit_estimate > 0 && 'Profitable period'}
                            {summary.profit_estimate < 0 &&
                                'Operating at a loss'}
                            {summary.profit_estimate === 0 && 'Break even'}
                        </p>
                        <p
                            className={cn(
                                'text-2xl font-bold tabular-nums',
                                summary.profit_estimate > 0 &&
                                    'text-emerald-600',
                                summary.profit_estimate < 0 && 'text-red-600',
                                summary.profit_estimate === 0 &&
                                    'text-slate-600',
                            )}
                        >
                            {formatMoney(Math.abs(summary.profit_estimate))}
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Monthly Overview ({year})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {!hasMonthlyData ? (
                            <div className="py-12 text-center text-muted-foreground">
                                No data for this period
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <div className="flex h-48 items-end gap-2 overflow-x-auto pb-2">
                                    {monthlyBreakdown.map((month) => {
                                        const salesHeight = Math.max(
                                            (month.sales / maxChartValue) * 160,
                                            month.sales > 0 ? 2 : 0,
                                        );
                                        const expensesHeight = Math.max(
                                            (month.expenses / maxChartValue) *
                                                160,
                                            month.expenses > 0 ? 2 : 0,
                                        );

                                        return (
                                            <div
                                                key={month.month}
                                                className="group relative flex min-w-14 flex-1 flex-col items-center gap-2"
                                            >
                                                <div className="flex h-40 items-end gap-1">
                                                    <div
                                                        className="w-4 rounded-t bg-emerald-500"
                                                        style={{
                                                            height: `${salesHeight}px`,
                                                        }}
                                                        title={`${month.month}: Sales ${formatMoney(month.sales)}, Expenses ${formatMoney(
                                                            month.expenses,
                                                        )}, Profit ${formatMoney(month.profit)}`}
                                                    />
                                                    <div
                                                        className="w-4 rounded-t bg-orange-500"
                                                        style={{
                                                            height: `${expensesHeight}px`,
                                                        }}
                                                        title={`${month.month}: Sales ${formatMoney(month.sales)}, Expenses ${formatMoney(
                                                            month.expenses,
                                                        )}, Profit ${formatMoney(month.profit)}`}
                                                    />
                                                </div>
                                                <span className="text-xs text-muted-foreground">
                                                    {month.month}
                                                </span>
                                                <div className="absolute bottom-12 z-10 hidden rounded-md bg-black px-2 py-1 text-xs whitespace-nowrap text-white group-hover:block">
                                                    {month.month}: Sales{' '}
                                                    {formatMoney(month.sales)},
                                                    Expenses{' '}
                                                    {formatMoney(
                                                        month.expenses,
                                                    )}
                                                    , Profit{' '}
                                                    {formatMoney(month.profit)}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                                <div className="flex items-center justify-center gap-4">
                                    <div className="flex items-center gap-1">
                                        <span className="h-3 w-3 rounded bg-emerald-500" />
                                        <span className="text-xs text-muted-foreground">
                                            Sales
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <span className="h-3 w-3 rounded bg-orange-500" />
                                        <span className="text-xs text-muted-foreground">
                                            Expenses
                                        </span>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {!filters.shop_id && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Top Shops by Sales</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {topShops.length === 0 ? (
                                    <div className="py-8 text-center text-muted-foreground">
                                        No sales data for this period.
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead className="text-left text-muted-foreground">
                                                <tr>
                                                    <th className="pb-2 font-medium">
                                                        Rank
                                                    </th>
                                                    <th className="pb-2 font-medium">
                                                        Shop
                                                    </th>
                                                    <th className="pb-2 text-right font-medium">
                                                        Total Sales
                                                    </th>
                                                    <th className="pb-2 text-right font-medium">
                                                        Transactions
                                                    </th>
                                                    <th className="pb-2 text-right font-medium">
                                                        Avg per Sale
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {topShops.map((shop, index) => (
                                                    <tr
                                                        key={`${shop.shop_name}-${index}`}
                                                        className="border-t even:bg-muted/30"
                                                    >
                                                        <td className="py-3">
                                                            <span
                                                                className={cn(
                                                                    'inline-flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-xs font-bold',
                                                                    getRankClass(
                                                                        index,
                                                                    ),
                                                                )}
                                                            >
                                                                {index < 3 && (
                                                                    <Medal className="mr-1 h-3 w-3" />
                                                                )}
                                                                {index + 1}
                                                            </span>
                                                        </td>
                                                        <td className="py-3 font-medium">
                                                            {shop.shop_name}
                                                        </td>
                                                        <td className="py-3 text-right tabular-nums">
                                                            {formatMoney(
                                                                shop.total_sales,
                                                            )}
                                                        </td>
                                                        <td className="py-3 text-right text-muted-foreground tabular-nums">
                                                            {shop.sales_count}
                                                        </td>
                                                        <td className="py-3 text-right text-muted-foreground tabular-nums">
                                                            {shop.sales_count >
                                                            0
                                                                ? formatMoney(
                                                                      shop.total_sales /
                                                                          shop.sales_count,
                                                                  )
                                                                : '—'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Summary</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <tbody>
                                    <tr className="even:bg-muted/30">
                                        <td className="py-3 text-muted-foreground">
                                            Gross Sales
                                        </td>
                                        <td className="py-3 text-right font-medium tabular-nums">
                                            {formatMoney(summary.sales_total)}
                                        </td>
                                    </tr>
                                    <tr className="even:bg-muted/30">
                                        <td className="py-3 text-muted-foreground">
                                            Total Purchases
                                        </td>
                                        <td className="py-3 text-right font-medium tabular-nums">
                                            {formatMoney(
                                                summary.purchases_total,
                                            )}
                                        </td>
                                    </tr>
                                    <tr className="even:bg-muted/30">
                                        <td className="py-3 text-muted-foreground">
                                            Total Expenses
                                        </td>
                                        <td className="py-3 text-right font-medium tabular-nums">
                                            {formatMoney(
                                                summary.expenses_total,
                                            )}
                                        </td>
                                    </tr>
                                    <tr className="font-bold even:bg-muted/30">
                                        <td className="py-3">Net Profit</td>
                                        <td
                                            className={cn(
                                                'py-3 text-right tabular-nums',
                                                summary.profit_estimate > 0 &&
                                                    'text-green-600',
                                                summary.profit_estimate < 0 &&
                                                    'text-red-600',
                                                summary.profit_estimate === 0 &&
                                                    'text-slate-600',
                                            )}
                                        >
                                            {formatMoney(
                                                summary.profit_estimate,
                                            )}
                                        </td>
                                    </tr>
                                    <tr className="even:bg-muted/30">
                                        <td className="py-3 text-muted-foreground">
                                            Outstanding Debts
                                        </td>
                                        <td className="py-3 text-right font-medium tabular-nums">
                                            {formatMoney(summary.debts_total)}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p className="mt-4 text-xs text-muted-foreground">
                                Print this page (Ctrl+P) to export as PDF.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
