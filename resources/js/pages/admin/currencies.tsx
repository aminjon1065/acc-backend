import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type CurrencyRow = {
    id: number;
    code: string;
    name: string;
    rate: number;
    is_default: boolean;
};

type Props = {
    currencies: CurrencyRow[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Currencies', href: '/admin/currencies' },
];

export default function AdminCurrenciesPage({ currencies }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Currencies" />

            <div className="p-4">
                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="px-3 py-2">Code</th>
                                <th className="px-3 py-2">Name</th>
                                <th className="px-3 py-2">Rate</th>
                                <th className="px-3 py-2">Default</th>
                                <th className="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {currencies.map((currency) => (
                                <tr key={currency.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">{currency.code}</td>
                                    <td className="px-3 py-2">{currency.name}</td>
                                    <td className="px-3 py-2">{currency.rate}</td>
                                    <td className="px-3 py-2">{currency.is_default ? 'yes' : 'no'}</td>
                                    <td className="px-3 py-2">
                                        {!currency.is_default && (
                                            <button
                                                type="button"
                                                className="rounded border px-2 py-1 text-xs"
                                                onClick={() =>
                                                    router.patch(`/admin/currencies/${currency.id}`, {
                                                        rate: currency.rate,
                                                        is_default: true,
                                                    })
                                                }
                                            >
                                                Set default
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
