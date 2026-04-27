import { Head, Link } from '@inertiajs/react';
import {
    Store,
    Users,
    Package,
    TrendingUp,
    Receipt,
    CreditCard,
    DollarSign,
    ShieldAlert,
    AlertTriangle,
    Clock,
    BarChart2,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type MonthStat = { month: string; shops: number; users: number };

type Props = {
    stats: {
        shops: number;
        users: number;
        products: number;
        sales_total: number;
        expenses_total: number;
        debts_total: number;
    };
    systemHealth: {
        total_revenue: number;
        total_expenses: number;
        net_profit: number;
        suspended_shops: number;
        low_stock_items: number;
    };
    recentShops: Array<{ id: number; name: string; status: string; created_at: string }>;
    recentUsers: Array<{ id: number; name: string; email: string; role: string; shop_id: number | null; created_at: string }>;
    recentActivity: Array<{ type: 'shop' | 'user'; name: string; created_at: string }>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatMoney(value: number): string {
    return value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function timeAgo(iso: string): string {
    const now = Date.now();
    const then = new Date(iso).getTime();
    const diff = Math.floor((now - then) / 1000);

    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
    return formatDate(iso);
}

function getRoleLabel(role: string): string {
    switch (role) {
        case 'super_admin': return 'Super Admin';
        case 'owner': return 'Owner';
        case 'seller': return 'Seller';
        default: return role;
    }
}

function getAvatarClasses(role: string): string {
    switch (role) {
        case 'super_admin': return 'bg-purple-100 text-purple-700';
        case 'owner': return 'bg-blue-100 text-blue-700';
        case 'seller': return 'bg-slate-100 text-slate-600';
        default: return 'bg-gray-100 text-gray-600';
    }
}

function MetricCard({
    label,
    value,
    icon: Icon,
    accentClass,
}: {
    label: string;
    value: string | number;
    icon: React.ElementType;
    accentClass: string;
}) {
    return (
        <Card className="overflow-hidden">
            <CardContent className="flex flex-row items-center gap-4 pt-4">
                <div className={cn('flex h-10 w-10 items-center justify-center rounded-full bg-opacity-10', accentClass)}>
                    <Icon className={cn('h-5 w-5', accentClass.replace('bg-', 'text-'))} />
                </div>
                <div>
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="text-2xl font-bold">{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function HealthCard({
    label,
    value,
    icon: Icon,
    accentClass,
    linkHref,
    linkLabel,
}: {
    label: string;
    value: string | number;
    icon: React.ElementType;
    accentClass: string;
    linkHref?: string;
    linkLabel?: string;
}) {
    return (
        <Card className="overflow-hidden">
            <CardContent className="flex flex-row items-center justify-between pt-4">
                <div className="flex flex-row items-center gap-4">
                    <div className={cn('flex h-10 w-10 items-center justify-center rounded-full bg-opacity-10', accentClass)}>
                        <Icon className={cn('h-5 w-5', accentClass.replace('bg-', 'text-'))} />
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">{label}</p>
                        <p className={cn('text-2xl font-bold', accentClass.includes('text-red') || accentClass.includes('text-amber') ? accentClass.replace('bg-', 'text-') : '')}>{value}</p>
                    </div>
                </div>
                {linkHref && linkLabel && (
                    <Link href={linkHref} className="text-sm font-medium text-primary hover:underline">
                        {linkLabel}
                    </Link>
                )}
            </CardContent>
        </Card>
    );
}

export default function AdminDashboard({ stats, systemHealth, recentShops, recentUsers, recentActivity }: Props) {
    const today = new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />

            <div className="space-y-6 p-6">
                {/* Page Header */}
                <div>
                    <h1 className="text-3xl font-bold">Admin Dashboard</h1>
                    <p className="text-muted-foreground mt-1">System overview — {today}</p>
                </div>

                {/* Primary Metric Cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <MetricCard label="Shops" value={stats.shops} icon={Store} accentClass="bg-blue-500 text-blue-500" />
                    <MetricCard label="Users" value={stats.users} icon={Users} accentClass="bg-indigo-500 text-indigo-500" />
                    <MetricCard label="Products" value={stats.products} icon={Package} accentClass="bg-green-500 text-green-500" />
                    <MetricCard label="Total Sales" value={formatMoney(stats.sales_total)} icon={TrendingUp} accentClass="bg-emerald-500 text-emerald-500" />
                    <MetricCard label="Expenses" value={formatMoney(stats.expenses_total)} icon={Receipt} accentClass="bg-orange-500 text-orange-500" />
                    <MetricCard label="Total Debts" value={formatMoney(stats.debts_total)} icon={CreditCard} accentClass="bg-red-500 text-red-500" />
                </div>

                {/* System Health Row */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <HealthCard
                        label="Net Profit"
                        value={formatMoney(systemHealth.net_profit)}
                        icon={DollarSign}
                        accentClass={cn(
                            'bg-',
                            systemHealth.net_profit > 0 ? 'bg-green-500 text-green-500' : systemHealth.net_profit < 0 ? 'bg-red-500 text-red-500' : 'bg-gray-500 text-gray-500'
                        )}
                    />
                    <HealthCard
                        label="Suspended Shops"
                        value={systemHealth.suspended_shops}
                        icon={ShieldAlert}
                        accentClass="bg-amber-500 text-amber-500"
                        linkHref="/admin/shops"
                        linkLabel="Manage →"
                    />
                    <HealthCard
                        label="Low Stock Items"
                        value={systemHealth.low_stock_items}
                        icon={AlertTriangle}
                        accentClass={systemHealth.low_stock_items > 0 ? 'bg-red-500 text-red-500' : 'bg-gray-500 text-gray-500'}
                    />
                </div>

                {/* Recent Sections Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Recent Shops */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <CardTitle>Recent Shops</CardTitle>
                            <Link href="/admin/shops" className="text-sm font-medium text-primary hover:underline">
                                View all →
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentShops.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                                    <Store className="mb-2 h-8 w-8" />
                                    <p>No shops registered yet</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {recentShops.map((shop) => (
                                        <div key={shop.id} className="flex items-center justify-between rounded-md border p-3">
                                            <span className="font-semibold">{shop.name}</span>
                                            <div className="flex items-center gap-3">
                                                <Badge className={shop.status === 'active' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}>
                                                    {shop.status}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">{formatDate(shop.created_at)}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Users */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <CardTitle>Recent Users</CardTitle>
                            <Link href="/admin/users" className="text-sm font-medium text-primary hover:underline">
                                View all →
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentUsers.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                                    <Users className="mb-2 h-8 w-8" />
                                    <p>No users registered yet</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {recentUsers.map((user) => (
                                        <div key={user.id} className="flex items-center gap-3 rounded-md border p-3">
                                            <div className={cn('flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold', getAvatarClasses(user.role))}>
                                                {user.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div className="flex-1">
                                                <p className="font-semibold">{user.name}</p>
                                                <p className="text-xs text-muted-foreground">{user.email}</p>
                                            </div>
                                            <Badge className={cn(
                                                user.role === 'super_admin' ? 'bg-purple-500 text-white' : user.role === 'owner' ? 'bg-blue-500 text-white' : 'bg-slate-500 text-white'
                                            )}>
                                                {getRoleLabel(user.role)}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">{formatDate(user.created_at)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Activity Feed */}
                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 pb-3">
                        <Clock className="h-5 w-5 text-muted-foreground" />
                        <CardTitle>Recent Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentActivity.length === 0 ? (
                            <p className="text-center text-muted-foreground py-4">No recent activity</p>
                        ) : (
                            <div className="space-y-3">
                                {recentActivity.map((item, index) => (
                                    <div key={index} className="flex items-center gap-3">
                                        <div className={cn('h-2 w-2 rounded-full', item.type === 'shop' ? 'bg-blue-500' : 'bg-indigo-500')} />
                                        <p className="flex-1 text-sm">
                                            New {item.type === 'shop' ? 'shop' : 'user'} registered: <span className="font-medium">{item.name}</span>
                                        </p>
                                        <span className="text-xs text-muted-foreground">{timeAgo(item.created_at)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Quick Actions */}
                <div className="flex flex-wrap gap-3">
                    <Link href="/admin/reports">
                        <Button className="gap-2">
                            <BarChart2 className="h-4 w-4" />
                            View Reports
                        </Button>
                    </Link>
                    <Link href="/admin/currencies">
                        <Button variant="outline" className="gap-2">
                            <DollarSign className="h-4 w-4" />
                            Currencies
                        </Button>
                    </Link>
                    <Link href="/admin/shops">
                        <Button variant="outline" className="gap-2">
                            <Store className="h-4 w-4" />
                            Manage Shops
                        </Button>
                    </Link>
                    <Link href="/admin/users">
                        <Button variant="outline" className="gap-2">
                            <Users className="h-4 w-4" />
                            Manage Users
                        </Button>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}