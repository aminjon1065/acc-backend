import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Props = {
    filters: { shop_id: number | null };
    shops: Array<{ id: number; name: string }>;
    summary: {
        sales_total: number;
        expenses_total: number;
        profit_estimate: number;
        purchases_total: number;
        debts_total: number;
        products_count: number;
        low_stock_count: number;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Reports', href: '/admin/reports' },
];

export default function AdminReportsPage({ filters, shops, summary }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Reports" />

            <div className="space-y-4 p-4">
                <div className="flex items-center gap-2">
                    <label htmlFor="shop-filter" className="text-sm text-muted-foreground">
                        Shop:
                    </label>
                    <select
                        id="shop-filter"
                        className="rounded-md border bg-background px-3 py-2 text-sm"
                        value={filters.shop_id ?? ''}
                        onChange={(event) => {
                            const value = event.target.value;
                            router.get(
                                '/admin/reports',
                                value ? { shop_id: Number(value) } : {},
                                { preserveState: true, preserveScroll: true },
                            );
                        }}
                    >
                        <option value="">All shops</option>
                        {shops.map((shop) => (
                            <option key={shop.id} value={shop.id}>
                                {shop.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <MetricCard label="Sales" value={summary.sales_total.toFixed(2)} />
                    <MetricCard label="Expenses" value={summary.expenses_total.toFixed(2)} />
                    <MetricCard label="Profit" value={summary.profit_estimate.toFixed(2)} />
                    <MetricCard label="Purchases" value={summary.purchases_total.toFixed(2)} />
                    <MetricCard label="Debts" value={summary.debts_total.toFixed(2)} />
                    <MetricCard label="Products" value={summary.products_count} />
                    <MetricCard label="Low stock" value={summary.low_stock_count} />
                </div>
            </div>
        </AppLayout>
    );
}

function MetricCard({ label, value }: { label: string; value: string | number }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm text-muted-foreground">{label}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-semibold">{value}</p>
            </CardContent>
        </Card>
    );
}
