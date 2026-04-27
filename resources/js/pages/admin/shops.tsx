import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Search, X, Store, Pencil, Power, Trash2, ChevronLeft, ChevronRight } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type ShopRow = {
    id: number;
    name: string;
    owner_name: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    status: 'active' | 'suspended';
    users_count: number;
    products_count: number;
    sales_count: number;
    expenses_count: number;
    debts_count: number;
    created_at: string;
};

type PaginationLinks = Array<{ url: string | null; label: string; active: boolean }>;

type Props = {
    shops: {
        data: ShopRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLinks;
    };
    filters: { search?: string; status?: string };
};

type ShopFormData = {
    name: string;
    owner_name: string;
    phone: string;
    email: string;
    address: string;
    status: 'active' | 'suspended';
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Shops', href: '/admin/shops' },
];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function getStatusClasses(status: string): string {
    return status === 'active'
        ? 'bg-green-100 text-green-700'
        : 'bg-red-100 text-red-700';
}

function getVisiblePages(current: number, last: number): (number | '...')[] {
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const pages: (number | '...')[] = [1];
    if (current > 3) pages.push('...');
    for (let i = Math.max(2, current - 1); i <= Math.min(current + 1, last - 1); i++) {
        pages.push(i);
    }
    if (current < last - 2) pages.push('...');
    pages.push(last);
    return pages;
}

export default function AdminShopsPage({ shops, filters }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [localSearch, setLocalSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [editingShop, setEditingShop] = useState<ShopRow | null>(null);
    const [showSuccess, setShowSuccess] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const createForm = useForm<ShopFormData>({
        name: '',
        owner_name: '',
        phone: '',
        email: '',
        address: '',
        status: 'active',
    });

    const editForm = useForm<ShopFormData>({
        name: '',
        owner_name: '',
        phone: '',
        email: '',
        address: '',
        status: 'active',
    });

    // Show flash message
    useEffect(() => {
        if (flash?.success) {
            setShowSuccess(true);
            const timer = setTimeout(() => setShowSuccess(false), 3000);
            return () => clearTimeout(timer);
        }
    }, [flash?.success]);

    // Debounced search
    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            router.get('/admin/shops', { search: localSearch, status: statusFilter }, { preserveState: true, preserveScroll: true });
        }, 400);
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
    }, [localSearch, statusFilter]);

    function handleStatusToggle(shop: ShopRow) {
        const newStatus = shop.status === 'active' ? 'suspended' : 'active';
        if (!window.confirm(`Are you sure you want to ${newStatus === 'suspended' ? 'suspend' : 'activate'} "${shop.name}"?`)) return;
        router.patch(`/admin/shops/${shop.id}/status`, { status: newStatus });
    }

    function handleDelete(shop: ShopRow) {
        if (!window.confirm(`Are you sure you want to delete "${shop.name}"? This action cannot be undone.`)) return;
        router.delete(`/admin/shops/${shop.id}`);
    }

    function openEditModal(shop: ShopRow) {
        setEditingShop(shop);
        editForm.setData({
            name: shop.name,
            owner_name: shop.owner_name ?? '',
            phone: shop.phone ?? '',
            email: shop.email ?? '',
            address: shop.address ?? '',
            status: shop.status,
        });
    }

    function closeEditModal() {
        setEditingShop(null);
        editForm.reset();
    }

    function handleCreateSubmit() {
        createForm.post('/admin/shops', {
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
            },
        });
    }

    function handleEditSubmit() {
        if (!editingShop) return;
        editForm.patch(`/admin/shops/${editingShop.id}`, {
            onSuccess: () => {
                setEditingShop(null);
                editForm.reset();
            },
        });
    }

    function clearSearch() {
        setLocalSearch('');
        router.get('/admin/shops', { status: statusFilter }, { preserveState: true, preserveScroll: true });
    }

    function clearStatus() {
        setStatusFilter('');
        router.get('/admin/shops', { search: localSearch }, { preserveState: true, preserveScroll: true });
    }

    function handlePageChange(page: number) {
        router.get('/admin/shops', { ...filters, page }, { preserveState: true });
    }

    const visiblePages = getVisiblePages(shops.current_page, shops.last_page);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin — Shops" />

            <div className="p-4 md:p-6 space-y-6">
                {/* Flash Success */}
                {showSuccess && flash?.success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between">
                        <span>{flash.success}</span>
                        <button onClick={() => setShowSuccess(false)} className="text-green-500 hover:text-green-700">
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                )}

                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Shops</h1>
                        <p className="text-muted-foreground">Manage all shops in the system</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant="secondary">{shops.total} shops total</Badge>
                        <Button onClick={() => setCreateOpen(true)} className="gap-2">
                            <Plus className="h-4 w-4" />
                            Add Shop
                        </Button>
                    </div>
                </div>

                {/* Search & Filter */}
                <div className="flex flex-col sm:flex-row gap-3">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder="Search by name, email or owner…"
                            value={localSearch}
                            onChange={(e) => setLocalSearch(e.target.value)}
                            className="pl-10 pr-10"
                        />
                        {localSearch && (
                            <button
                                onClick={clearSearch}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                {/* Active Filters */}
                {(localSearch || statusFilter) && (
                    <div className="flex flex-wrap gap-2">
                        {localSearch && (
                            <Badge variant="secondary" className="gap-1 pr-1">
                                Search: {localSearch}
                                <button onClick={clearSearch} className="ml-1 hover:text-foreground">
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        )}
                        {statusFilter && (
                            <Badge variant="secondary" className="gap-1 pr-1">
                                Status: {statusFilter}
                                <button onClick={clearStatus} className="ml-1 hover:text-foreground">
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        )}
                    </div>
                )}

                {/* Table */}
                <Card className="overflow-hidden">
                    {shops.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                            <Store className="mb-3 h-12 w-12" />
                            <p className="text-lg font-medium">No shops found</p>
                            <p className="text-sm">Try adjusting your search or filters</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Shop</th>
                                        <th className="px-4 py-3 font-medium text-center">Status</th>
                                        <th className="px-4 py-3 font-medium text-center">Users</th>
                                        <th className="px-4 py-3 font-medium text-center">Products</th>
                                        <th className="px-4 py-3 font-medium text-center">Sales</th>
                                        <th className="px-4 py-3 font-medium text-center">Expenses</th>
                                        <th className="px-4 py-3 font-medium text-center">Debts</th>
                                        <th className="px-4 py-3 font-medium">Created</th>
                                        <th className="px-4 py-3 font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {shops.data.map((shop) => (
                                        <tr key={shop.id} className="border-t hover:bg-muted/40 transition-colors">
                                            <td className="px-4 py-3">
                                                <p className="font-semibold">{shop.name}</p>
                                                {shop.email && <p className="text-xs text-muted-foreground">{shop.email}</p>}
                                                {shop.owner_name && <p className="text-xs text-muted-foreground">Owner: {shop.owner_name}</p>}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge className={getStatusClasses(shop.status)}>
                                                    {shop.status}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">{shop.users_count}</td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">{shop.products_count}</td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">{shop.sales_count}</td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">{shop.expenses_count}</td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">{shop.debts_count}</td>
                                            <td className="px-4 py-3 text-muted-foreground">{formatDate(shop.created_at)}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1">
                                                    <Button variant="ghost" size="sm" onClick={() => openEditModal(shop)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="sm" onClick={() => handleStatusToggle(shop)}>
                                                        <Power className="h-4 w-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="sm" className="text-red-500 hover:text-red-600" onClick={() => handleDelete(shop)}>
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                {/* Pagination */}
                {shops.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={shops.current_page === 1}
                            onClick={() => handlePageChange(shops.current_page - 1)}
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        {visiblePages.map((page, idx) =>
                            page === '...' ? (
                                <span key={`ellipsis-${idx}`} className="px-2 text-muted-foreground">…</span>
                            ) : (
                                <Button
                                    key={page}
                                    variant={shops.current_page === page ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => handlePageChange(page as number)}
                                >
                                    {page}
                                </Button>
                            )
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={shops.current_page === shops.last_page}
                            onClick={() => handlePageChange(shops.current_page + 1)}
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                )}
            </div>

            {/* Create Modal */}
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create New Shop</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={(e) => { e.preventDefault(); handleCreateSubmit(); }} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="sm:col-span-2">
                                <Label htmlFor="name">Name *</Label>
                                <Input id="name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} />
                                {createForm.errors.name && <p className="text-red-500 text-xs mt-1">{createForm.errors.name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="owner_name">Owner Name</Label>
                                <Input id="owner_name" value={createForm.data.owner_name} onChange={(e) => createForm.setData('owner_name', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="phone">Phone</Label>
                                <Input id="phone" type="tel" value={createForm.data.phone} onChange={(e) => createForm.setData('phone', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="status">Status</Label>
                                <select
                                    id="status"
                                    value={createForm.data.status}
                                    onChange={(e) => createForm.setData('status', e.target.value as 'active' | 'suspended')}
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div className="sm:col-span-2">
                                <Label htmlFor="address">Address</Label>
                                <Input id="address" value={createForm.data.address} onChange={(e) => createForm.setData('address', e.target.value)} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => { setCreateOpen(false); createForm.reset(); }}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                Create Shop
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Modal */}
            <Dialog open={!!editingShop} onOpenChange={(open) => !open && closeEditModal()}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Shop — {editingShop?.name}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={(e) => { e.preventDefault(); handleEditSubmit(); }} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="sm:col-span-2">
                                <Label htmlFor="edit-name">Name *</Label>
                                <Input id="edit-name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                                {editForm.errors.name && <p className="text-red-500 text-xs mt-1">{editForm.errors.name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="edit-owner_name">Owner Name</Label>
                                <Input id="edit-owner_name" value={editForm.data.owner_name} onChange={(e) => editForm.setData('owner_name', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="edit-phone">Phone</Label>
                                <Input id="edit-phone" type="tel" value={editForm.data.phone} onChange={(e) => editForm.setData('phone', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="edit-email">Email</Label>
                                <Input id="edit-email" type="email" value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="edit-status">Status</Label>
                                <select
                                    id="edit-status"
                                    value={editForm.data.status}
                                    onChange={(e) => editForm.setData('status', e.target.value as 'active' | 'suspended')}
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div className="sm:col-span-2">
                                <Label htmlFor="edit-address">Address</Label>
                                <Input id="edit-address" value={editForm.data.address} onChange={(e) => editForm.setData('address', e.target.value)} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeEditModal}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                Save Changes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}