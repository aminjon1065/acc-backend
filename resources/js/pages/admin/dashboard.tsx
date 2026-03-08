import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Props = {
    stats: {
        shops: number;
        users: number;
        products: number;
        sales_total: number;
        expenses_total: number;
        debts_total: number;
    };
    recentShops: Array<{ id: number; name: string; status: string; created_at: string }>;
    recentUsers: Array<{ id: number; name: string; email: string; role: string; shop_id: number | null; created_at: string }>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
];

export default function AdminDashboard({ stats, recentShops, recentUsers }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />

            <div className="space-y-6 p-4">
                <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <MetricCard label="Shops" value={stats.shops} />
                    <MetricCard label="Users" value={stats.users} />
                    <MetricCard label="Products" value={stats.products} />
                    <MetricCard label="Sales" value={stats.sales_total.toFixed(2)} />
                    <MetricCard label="Expenses" value={stats.expenses_total.toFixed(2)} />
                    <MetricCard label="Debts" value={stats.debts_total.toFixed(2)} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Recent Shops</CardTitle>
                            <Link href="/admin/shops" className="text-sm text-primary hover:underline">
                                Open all
                            </Link>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2 text-sm">
                                {recentShops.map((shop) => (
                                    <div key={shop.id} className="flex items-center justify-between rounded-md border p-2">
                                        <span className="font-medium">{shop.name}</span>
                                        <span className="text-muted-foreground">{shop.status}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Recent Users</CardTitle>
                            <Link href="/admin/users" className="text-sm text-primary hover:underline">
                                Open all
                            </Link>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2 text-sm">
                                {recentUsers.map((user) => (
                                    <div key={user.id} className="flex items-center justify-between rounded-md border p-2">
                                        <span className="font-medium">{user.name}</span>
                                        <span className="text-muted-foreground">{user.role}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Link href="/admin/reports" className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">
                        Reports
                    </Link>
                    <Link href="/admin/currencies" className="rounded-md border px-4 py-2 text-sm">
                        Currencies
                    </Link>
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
