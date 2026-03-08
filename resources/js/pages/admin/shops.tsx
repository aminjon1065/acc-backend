import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ShopRow = {
    id: number;
    name: string;
    owner_name: string | null;
    email: string | null;
    status: 'active' | 'suspended';
    users_count: number;
    products_count: number;
    sales_count: number;
    expenses_count: number;
    debts_count: number;
};

type Props = {
    shops: {
        data: ShopRow[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Shops', href: '/admin/shops' },
];

export default function AdminShopsPage({ shops }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Shops" />

            <div className="p-4">
                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="px-3 py-2">Shop</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Users</th>
                                <th className="px-3 py-2">Products</th>
                                <th className="px-3 py-2">Sales</th>
                                <th className="px-3 py-2">Expenses</th>
                                <th className="px-3 py-2">Debts</th>
                                <th className="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shops.data.map((shop) => (
                                <tr key={shop.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <p className="font-medium">{shop.name}</p>
                                        <p className="text-xs text-muted-foreground">{shop.email ?? '-'}</p>
                                    </td>
                                    <td className="px-3 py-2">{shop.status}</td>
                                    <td className="px-3 py-2">{shop.users_count}</td>
                                    <td className="px-3 py-2">{shop.products_count}</td>
                                    <td className="px-3 py-2">{shop.sales_count}</td>
                                    <td className="px-3 py-2">{shop.expenses_count}</td>
                                    <td className="px-3 py-2">{shop.debts_count}</td>
                                    <td className="px-3 py-2">
                                        {shop.status === 'active' ? (
                                            <button
                                                type="button"
                                                className="rounded border px-2 py-1 text-xs"
                                                onClick={() =>
                                                    router.patch(`/admin/shops/${shop.id}/status`, { status: 'suspended' })
                                                }
                                            >
                                                Suspend
                                            </button>
                                        ) : (
                                            <button
                                                type="button"
                                                className="rounded border px-2 py-1 text-xs"
                                                onClick={() =>
                                                    router.patch(`/admin/shops/${shop.id}/status`, { status: 'active' })
                                                }
                                            >
                                                Activate
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
