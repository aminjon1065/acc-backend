import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    DollarSign,
    Info,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Star,
    Trash2,
    TrendingUp,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type CurrencyRow = {
    id: number;
    code: string;
    name: string;
    rate: number;
    is_default: boolean;
    created_at: string;
    updated_at?: string;
};

type Props = {
    currencies: CurrencyRow[];
    filters: { search?: string };
};

type CurrencyFormData = {
    code: string;
    name: string;
    rate: string;
    is_default: boolean;
};

type PageProps = {
    flash?: { success?: string };
    errors?: { error?: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Currencies', href: '/admin/currencies' },
];

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
}

function formatRate(rate: number): string {
    return Number(rate)
        .toFixed(6)
        .replace(/\.?0+$/, '');
}

function getCurrencyBgClass(code: string): string {
    const firstLetter = code.trim().toUpperCase().charAt(0);

    if (firstLetter >= 'A' && firstLetter <= 'E') return 'bg-blue-500';
    if (firstLetter >= 'F' && firstLetter <= 'J') return 'bg-green-500';
    if (firstLetter >= 'K' && firstLetter <= 'O') return 'bg-purple-500';
    if (firstLetter >= 'P' && firstLetter <= 'T') return 'bg-orange-500';
    if (firstLetter >= 'U' && firstLetter <= 'Z') return 'bg-red-500';

    return 'bg-slate-500';
}

export default function AdminCurrenciesPage({ currencies, filters }: Props) {
    const { flash, errors } = usePage().props as PageProps;
    const [localSearch, setLocalSearch] = useState(filters.search ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [editingCurrency, setEditingCurrency] = useState<CurrencyRow | null>(
        null,
    );
    const [inlineRateId, setInlineRateId] = useState<number | null>(null);
    const [inlineRate, setInlineRate] = useState('');
    const [savingInlineRate, setSavingInlineRate] = useState<number | null>(
        null,
    );
    const [dismissedSuccess, setDismissedSuccess] = useState<string | null>(
        null,
    );
    const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const defaultCurrency = useMemo(
        () => currencies.find((currency) => currency.is_default),
        [currencies],
    );
    const successMessage = flash?.success;
    const showSuccess = Boolean(
        successMessage && dismissedSuccess !== successMessage,
    );

    const createForm = useForm<CurrencyFormData>({
        code: '',
        name: '',
        rate: '',
        is_default: false,
    });

    const editForm = useForm<CurrencyFormData>({
        code: '',
        name: '',
        rate: '',
        is_default: false,
    });

    useEffect(() => {
        if (!successMessage || dismissedSuccess === successMessage) return;

        const timer = setTimeout(
            () => setDismissedSuccess(successMessage),
            3000,
        );

        return () => clearTimeout(timer);
    }, [dismissedSuccess, successMessage]);

    useEffect(() => {
        return () => {
            if (searchTimeoutRef.current)
                clearTimeout(searchTimeoutRef.current);
        };
    }, []);

    function handleSearchChange(value: string) {
        setLocalSearch(value);

        if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
        searchTimeoutRef.current = setTimeout(() => {
            router.get(
                '/admin/currencies',
                value.trim() ? { search: value.trim() } : {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 400);
    }

    function clearSearch() {
        setLocalSearch('');
        if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
        router.get(
            '/admin/currencies',
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function openCreateModal() {
        createForm.reset();
        createForm.clearErrors();
        setCreateOpen(true);
    }

    function openEditModal(currency: CurrencyRow) {
        editForm.clearErrors();
        editForm.setData({
            code: currency.code,
            name: currency.name,
            rate: String(currency.rate),
            is_default: currency.is_default,
        });
        setEditingCurrency(currency);
    }

    function closeEditModal() {
        setEditingCurrency(null);
        editForm.reset();
        editForm.clearErrors();
    }

    function handleCreateSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        createForm.post('/admin/currencies', {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
            },
        });
    }

    function handleEditSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editingCurrency) return;

        editForm.patch(`/admin/currencies/${editingCurrency.id}`, {
            preserveScroll: true,
            onSuccess: closeEditModal,
        });
    }

    function handleSetDefault(currency: CurrencyRow) {
        if (
            !window.confirm(
                `Set ${currency.code} as default currency? This affects all shops.`,
            )
        )
            return;

        router.patch(
            `/admin/currencies/${currency.id}`,
            {
                rate: currency.rate,
                is_default: true,
            },
            { preserveScroll: true },
        );
    }

    function handleDelete(currency: CurrencyRow) {
        if (currency.is_default) return;
        if (!window.confirm(`Delete ${currency.code}? This cannot be undone.`))
            return;

        router.delete(`/admin/currencies/${currency.id}`, {
            preserveScroll: true,
        });
    }

    function openInlineRate(currency: CurrencyRow) {
        setInlineRateId(currency.id);
        setInlineRate(String(currency.rate));
    }

    function closeInlineRate() {
        setInlineRateId(null);
        setInlineRate('');
    }

    function saveInlineRate(currency: CurrencyRow) {
        const nextRate = inlineRate.trim();

        if (
            !nextRate ||
            Number(nextRate) <= 0 ||
            Number(nextRate) === Number(currency.rate)
        ) {
            closeInlineRate();
            return;
        }

        setSavingInlineRate(currency.id);
        router.patch(
            `/admin/currencies/${currency.id}`,
            {
                rate: nextRate,
                is_default: currency.is_default,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSavingInlineRate(null);
                    closeInlineRate();
                },
            },
        );
    }

    function handleInlineRateKeyDown(
        event: KeyboardEvent<HTMLInputElement>,
        currency: CurrencyRow,
    ) {
        if (event.key === 'Enter') {
            event.preventDefault();
            saveInlineRate(currency);
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeInlineRate();
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin — Currencies" />

            <div className="space-y-6 p-4 md:p-6">
                {showSuccess && successMessage && (
                    <Alert className="border-green-200 bg-green-50 text-green-800">
                        <Info className="h-4 w-4" />
                        <AlertDescription className="text-green-800">
                            <div className="flex w-full items-center justify-between gap-3">
                                <span>{successMessage}</span>
                                <button
                                    type="button"
                                    className="text-green-600 hover:text-green-800"
                                    onClick={() =>
                                        setDismissedSuccess(successMessage)
                                    }
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                {errors?.error && (
                    <Alert className="border-red-200 bg-red-50 text-red-800">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription className="text-red-800">
                            {errors.error}
                        </AlertDescription>
                    </Alert>
                )}

                {currencies.length > 0 && (
                    <Alert
                        className={cn(
                            defaultCurrency
                                ? 'border-blue-200 bg-blue-50 text-blue-800'
                                : 'border-amber-200 bg-amber-50 text-amber-800',
                        )}
                    >
                        {defaultCurrency ? (
                            <Info className="h-4 w-4" />
                        ) : (
                            <AlertTriangle className="h-4 w-4" />
                        )}
                        <AlertDescription
                            className={
                                defaultCurrency
                                    ? 'text-blue-800'
                                    : 'text-amber-800'
                            }
                        >
                            {defaultCurrency ? (
                                <span>
                                    Default currency is used as the base for all
                                    exchange rate calculations. Currently:{' '}
                                    <strong>
                                        {defaultCurrency.code} —{' '}
                                        {defaultCurrency.name}
                                    </strong>
                                </span>
                            ) : (
                                <span>
                                    No default currency set. Please set one.
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Currencies</h1>
                        <p className="text-muted-foreground">
                            Manage exchange rates and system currencies
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant="secondary">
                            {currencies.length} currencies
                        </Badge>
                        <Button
                            type="button"
                            className="gap-2"
                            onClick={openCreateModal}
                        >
                            <Plus className="h-4 w-4" />
                            Add Currency
                        </Button>
                    </div>
                </div>

                <div className="relative max-w-md">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={localSearch}
                        onChange={(event) =>
                            handleSearchChange(event.target.value)
                        }
                        placeholder="Search by code or name…"
                        className="pr-10 pl-9"
                    />
                    {localSearch && (
                        <button
                            type="button"
                            className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            onClick={clearSearch}
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>System currencies</CardTitle>
                    </CardHeader>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-6 py-3 font-medium">
                                            Currency
                                        </th>
                                        <th className="px-6 py-3 font-medium">
                                            Code
                                        </th>
                                        <th className="px-6 py-3 font-medium">
                                            Rate
                                        </th>
                                        <th className="px-6 py-3 font-medium">
                                            Default
                                        </th>
                                        <th className="px-6 py-3 font-medium">
                                            Updated
                                        </th>
                                        <th className="px-6 py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {currencies.length > 0 ? (
                                        currencies.map((currency) => (
                                            <tr
                                                key={currency.id}
                                                className="border-t transition-colors hover:bg-muted/40"
                                            >
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div
                                                            className={cn(
                                                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-xs font-bold text-white',
                                                                getCurrencyBgClass(
                                                                    currency.code,
                                                                ),
                                                            )}
                                                        >
                                                            {currency.code.slice(
                                                                0,
                                                                2,
                                                            )}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="font-medium">
                                                                {currency.name}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {currency.code}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <Badge
                                                        variant="secondary"
                                                        className="font-mono font-medium"
                                                    >
                                                        {currency.code}
                                                    </Badge>
                                                </td>
                                                <td className="group/rate px-6 py-4">
                                                    {inlineRateId ===
                                                    currency.id ? (
                                                        <Input
                                                            autoFocus
                                                            type="number"
                                                            min="0.000001"
                                                            step="0.000001"
                                                            value={inlineRate}
                                                            className="h-8 w-32"
                                                            onChange={(event) =>
                                                                setInlineRate(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            onBlur={() =>
                                                                saveInlineRate(
                                                                    currency,
                                                                )
                                                            }
                                                            onKeyDown={(
                                                                event,
                                                            ) =>
                                                                handleInlineRateKeyDown(
                                                                    event,
                                                                    currency,
                                                                )
                                                            }
                                                        />
                                                    ) : (
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium tabular-nums">
                                                                {formatRate(
                                                                    Number(
                                                                        currency.rate,
                                                                    ),
                                                                )}
                                                            </span>
                                                            <TrendingUp className="h-3.5 w-3.5 text-muted-foreground" />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-7 px-2 opacity-0 transition-opacity group-hover/rate:opacity-100"
                                                                disabled={
                                                                    savingInlineRate ===
                                                                    currency.id
                                                                }
                                                                onClick={() =>
                                                                    openInlineRate(
                                                                        currency,
                                                                    )
                                                                }
                                                            >
                                                                <RefreshCw className="h-3.5 w-3.5" />
                                                            </Button>
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4">
                                                    {currency.is_default && (
                                                        <Badge className="border-green-200 bg-green-100 text-green-700">
                                                            <Star className="h-3 w-3 fill-current" />
                                                            Default
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-muted-foreground">
                                                    {formatDate(
                                                        currency.updated_at ??
                                                            currency.created_at,
                                                    )}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0"
                                                            onClick={() =>
                                                                openEditModal(
                                                                    currency,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        {!currency.is_default && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0 text-amber-600 hover:text-amber-700"
                                                                onClick={() =>
                                                                    handleSetDefault(
                                                                        currency,
                                                                    )
                                                                }
                                                            >
                                                                <Star className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {!currency.is_default && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0 text-red-600 hover:text-red-700"
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        currency,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-6 py-14 text-center"
                                            >
                                                <div className="flex flex-col items-center gap-3 text-muted-foreground">
                                                    <DollarSign className="h-10 w-10" />
                                                    <div className="font-medium">
                                                        No currencies found
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Add New Currency</DialogTitle>
                        </DialogHeader>
                        <form
                            onSubmit={handleCreateSubmit}
                            className="space-y-4"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="create-code">
                                    Currency Code*
                                </Label>
                                <Input
                                    id="create-code"
                                    value={createForm.data.code}
                                    maxLength={10}
                                    placeholder="USD"
                                    onChange={(event) =>
                                        createForm.setData(
                                            'code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                />
                                {createForm.errors.code && (
                                    <p className="text-sm text-red-600">
                                        {createForm.errors.code}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="create-name">
                                    Currency Name*
                                </Label>
                                <Input
                                    id="create-name"
                                    value={createForm.data.name}
                                    placeholder="US Dollar"
                                    onChange={(event) =>
                                        createForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                                {createForm.errors.name && (
                                    <p className="text-sm text-red-600">
                                        {createForm.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="create-rate">
                                    Exchange Rate*
                                </Label>
                                <Input
                                    id="create-rate"
                                    type="number"
                                    step="0.000001"
                                    min="0.000001"
                                    value={createForm.data.rate}
                                    placeholder="1.000000"
                                    onChange={(event) =>
                                        createForm.setData(
                                            'rate',
                                            event.target.value,
                                        )
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Rate relative to base currency. Set to 1.0
                                    for the base currency.
                                </p>
                                {createForm.errors.rate && (
                                    <p className="text-sm text-red-600">
                                        {createForm.errors.rate}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="create-default"
                                    checked={createForm.data.is_default}
                                    onCheckedChange={(checked) =>
                                        createForm.setData(
                                            'is_default',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="create-default">
                                    Make this the default currency
                                </Label>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setCreateOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={createForm.processing}
                                >
                                    Add Currency
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={editingCurrency !== null}
                    onOpenChange={(open) =>
                        !open ? closeEditModal() : undefined
                    }
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Edit Currency — {editingCurrency?.code}
                            </DialogTitle>
                        </DialogHeader>
                        <form onSubmit={handleEditSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="edit-code">Currency Code</Label>
                                <Input
                                    id="edit-code"
                                    value={editForm.data.code}
                                    disabled
                                    className="text-muted-foreground"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="edit-name">Currency Name</Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                                {editForm.errors.name && (
                                    <p className="text-sm text-red-600">
                                        {editForm.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="edit-rate">
                                    Exchange Rate*
                                </Label>
                                <Input
                                    id="edit-rate"
                                    type="number"
                                    step="0.000001"
                                    min="0.000001"
                                    value={editForm.data.rate}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'rate',
                                            event.target.value,
                                        )
                                    }
                                />
                                {editForm.errors.rate && (
                                    <p className="text-sm text-red-600">
                                        {editForm.errors.rate}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="edit-default"
                                    checked={editForm.data.is_default}
                                    disabled={editingCurrency?.is_default}
                                    onCheckedChange={(checked) =>
                                        editForm.setData(
                                            'is_default',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="edit-default">
                                    Make this the default currency
                                </Label>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={closeEditModal}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={editForm.processing}
                                >
                                    Save Changes
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
