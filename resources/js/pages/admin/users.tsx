import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    shop: { id: number; name: string } | null;
};

type Props = {
    users: {
        data: UserRow[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
];

export default function AdminUsersPage({ users }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Users" />

            <div className="p-4">
                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="px-3 py-2">Name</th>
                                <th className="px-3 py-2">Email</th>
                                <th className="px-3 py-2">Role</th>
                                <th className="px-3 py-2">Shop</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">{user.name}</td>
                                    <td className="px-3 py-2">{user.email}</td>
                                    <td className="px-3 py-2">{user.role}</td>
                                    <td className="px-3 py-2">{user.shop?.name ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
