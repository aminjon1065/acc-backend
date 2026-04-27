import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    EyeOff,
    Pencil,
    Search,
    Trash2,
    UserPlus,
    Users as UsersIcon,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    shop: { id: number; name: string } | null;
    created_at: string;
};

type ShopOption = { id: number; name: string };

type PaginationLinks = Array<{
    url: string | null;
    label: string;
    active: boolean;
}>;

type Props = {
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLinks;
    };
    shops: ShopOption[];
    filters: { search?: string; role?: string; shop_id?: number };
};

type UserFormData = {
    name: string;
    email: string;
    password: string;
    role: string;
    shop_id: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function getRoleLabel(role: string): string {
    switch (role) {
        case 'super_admin':
            return 'Super Admin';
        case 'owner':
            return 'Owner';
        case 'seller':
            return 'Seller';
        default:
            return role;
    }
}

function getRoleBadgeClasses(role: string): string {
    switch (role) {
        case 'super_admin':
            return 'bg-purple-500 text-white';
        case 'owner':
            return 'bg-blue-500 text-white';
        case 'seller':
            return 'bg-slate-500 text-white';
        default:
            return 'bg-gray-500 text-white';
    }
}

function getAvatarClasses(role: string): string {
    switch (role) {
        case 'super_admin':
            return 'bg-purple-100 text-purple-700';
        case 'owner':
            return 'bg-blue-100 text-blue-700';
        case 'seller':
            return 'bg-slate-100 text-slate-600';
        default:
            return 'bg-gray-100 text-gray-600';
    }
}

function getPasswordStrength(password: string): 'weak' | 'medium' | 'strong' {
    if (password.length < 8) return 'weak';
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);
    const score = [hasUpper, hasLower, hasNumber, hasSpecial].filter(
        Boolean,
    ).length;
    if (score >= 3 && password.length >= 12) return 'strong';
    if (score >= 2) return 'medium';
    return 'weak';
}

function getVisiblePages(current: number, last: number): (number | '...')[] {
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const pages: (number | '...')[] = [1];
    if (current > 3) pages.push('...');
    for (
        let i = Math.max(2, current - 1);
        i <= Math.min(current + 1, last - 1);
        i++
    ) {
        pages.push(i);
    }
    if (current < last - 2) pages.push('...');
    pages.push(last);
    return pages;
}

export default function AdminUsersPage({ users, shops, filters }: Props) {
    const { auth, flash } = usePage().props as {
        auth?: { user?: { id: number } };
        flash?: { success?: string };
    };
    const [localSearch, setLocalSearch] = useState(filters.search ?? '');
    const [roleFilter, setRoleFilter] = useState(filters.role ?? '');
    const [shopFilter, setShopFilter] = useState(
        filters.shop_id ? String(filters.shop_id) : '',
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);
    const [dismissedSuccess, setDismissedSuccess] = useState<string | null>(
        null,
    );
    const [showPassword, setShowPassword] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const successMessage = flash?.success;
    const showSuccess = Boolean(
        successMessage && dismissedSuccess !== successMessage,
    );

    const createForm = useForm<UserFormData>({
        name: '',
        email: '',
        password: '',
        role: 'seller',
        shop_id: '',
    });

    const editForm = useForm<UserFormData>({
        name: '',
        email: '',
        password: '',
        role: 'seller',
        shop_id: '',
    });

    // Show flash message
    useEffect(() => {
        if (!successMessage || dismissedSuccess === successMessage) return;

        const timer = setTimeout(
            () => setDismissedSuccess(successMessage),
            3000,
        );

        return () => clearTimeout(timer);
    }, [dismissedSuccess, successMessage]);

    // Debounced search
    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            router.get(
                '/admin/users',
                {
                    search: localSearch,
                    role: roleFilter || undefined,
                    shop_id: shopFilter || undefined,
                },
                { preserveState: true, preserveScroll: true },
            );
        }, 400);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [localSearch, roleFilter, shopFilter]);

    function handleDelete(user: UserRow) {
        if (
            !window.confirm(
                `Delete user "${user.name}"? This action cannot be undone.`,
            )
        )
            return;
        router.delete(`/admin/users/${user.id}`);
    }

    function openEditModal(user: UserRow) {
        setEditingUser(user);
        editForm.setData({
            name: user.name,
            email: user.email,
            password: '',
            role: user.role,
            shop_id: user.shop ? String(user.shop.id) : '',
        });
    }

    function closeEditModal() {
        setEditingUser(null);
        editForm.reset();
        setShowPassword(false);
    }

    function handleCreateSubmit() {
        const data = {
            ...createForm.data,
            shop_id:
                createForm.data.role === 'super_admin'
                    ? ''
                    : createForm.data.shop_id,
        };
        createForm.transform(() => data);
        createForm.post('/admin/users', {
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
            },
        });
    }

    function handleEditSubmit() {
        if (!editingUser) return;
        const data = {
            ...editForm.data,
            shop_id:
                editForm.data.role === 'super_admin'
                    ? ''
                    : editForm.data.shop_id,
        };
        editForm.transform(() => data);
        editForm.patch(`/admin/users/${editingUser.id}`, {
            onSuccess: () => {
                setEditingUser(null);
                editForm.reset();
                setShowPassword(false);
            },
        });
    }

    function clearSearch() {
        setLocalSearch('');
        router.get(
            '/admin/users',
            { role: roleFilter, shop_id: shopFilter || undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    function clearRole() {
        setRoleFilter('');
        router.get(
            '/admin/users',
            { search: localSearch, shop_id: shopFilter || undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    function clearShop() {
        setShopFilter('');
        router.get(
            '/admin/users',
            { search: localSearch, role: roleFilter || undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    function handlePageChange(page: number) {
        router.get(
            '/admin/users',
            { ...filters, page },
            { preserveState: true },
        );
    }

    function handleRoleChange(
        role: string,
        form: typeof createForm | typeof editForm,
        setter: (v: string) => void,
    ) {
        setter(role);
        if (role === 'super_admin') {
            form.setData('shop_id', '');
        }
    }

    const visiblePages = getVisiblePages(users.current_page, users.last_page);
    const currentUserId = auth?.user?.id;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin — Users" />

            <div className="space-y-6 p-4 md:p-6">
                {/* Flash Success */}
                {showSuccess && successMessage && (
                    <div className="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
                        <span>{successMessage}</span>
                        <button
                            onClick={() => setDismissedSuccess(successMessage)}
                            className="text-green-500 hover:text-green-700"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                )}

                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Users</h1>
                        <p className="text-muted-foreground">
                            Manage all system users
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant="secondary">
                            {users.total} users total
                        </Badge>
                        <Button
                            onClick={() => setCreateOpen(true)}
                            className="gap-2"
                        >
                            <UserPlus className="h-4 w-4" />
                            Add User
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative max-w-sm flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder="Search by name or email…"
                            value={localSearch}
                            onChange={(e) => setLocalSearch(e.target.value)}
                            className="pr-10 pl-10"
                        />
                        {localSearch && (
                            <button
                                onClick={clearSearch}
                                className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                    <select
                        value={roleFilter}
                        onChange={(e) => setRoleFilter(e.target.value)}
                        className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All roles</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="owner">Owner</option>
                        <option value="seller">Seller</option>
                    </select>
                    <select
                        value={shopFilter}
                        onChange={(e) => setShopFilter(e.target.value)}
                        className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All shops</option>
                        {shops.map((shop) => (
                            <option key={shop.id} value={String(shop.id)}>
                                {shop.name}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Active Filters */}
                {(localSearch || roleFilter || shopFilter) && (
                    <div className="flex flex-wrap gap-2">
                        {localSearch && (
                            <Badge variant="secondary" className="gap-1 pr-1">
                                Search: {localSearch}
                                <button
                                    onClick={clearSearch}
                                    className="ml-1 hover:text-foreground"
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        )}
                        {roleFilter && (
                            <Badge variant="secondary" className="gap-1 pr-1">
                                Role: {getRoleLabel(roleFilter)}
                                <button
                                    onClick={clearRole}
                                    className="ml-1 hover:text-foreground"
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        )}
                        {shopFilter && (
                            <Badge variant="secondary" className="gap-1 pr-1">
                                Shop:{' '}
                                {shops.find((s) => String(s.id) === shopFilter)
                                    ?.name ?? shopFilter}
                                <button
                                    onClick={clearShop}
                                    className="ml-1 hover:text-foreground"
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        )}
                    </div>
                )}

                {/* Table */}
                <Card className="overflow-hidden">
                    {users.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                            <UsersIcon className="mb-3 h-12 w-12" />
                            <p className="text-lg font-medium">
                                No users found
                            </p>
                            <p className="text-sm">Try adjusting filters</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            User
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Role
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Shop
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Joined
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="border-t transition-colors hover:bg-muted/40"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <div
                                                        className={cn(
                                                            'flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold',
                                                            getAvatarClasses(
                                                                user.role,
                                                            ),
                                                        )}
                                                    >
                                                        {user.name
                                                            .charAt(0)
                                                            .toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <p className="font-semibold">
                                                            {user.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {user.email}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    className={getRoleBadgeClasses(
                                                        user.role,
                                                    )}
                                                >
                                                    {getRoleLabel(user.role)}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {user.shop?.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDate(user.created_at)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEditModal(user)
                                                        }
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    {user.id !==
                                                        currentUserId && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-red-500 hover:text-red-600"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    user,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
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
                {users.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={users.current_page === 1}
                            onClick={() =>
                                handlePageChange(users.current_page - 1)
                            }
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        {visiblePages.map((page, idx) =>
                            page === '...' ? (
                                <span
                                    key={`ellipsis-${idx}`}
                                    className="px-2 text-muted-foreground"
                                >
                                    …
                                </span>
                            ) : (
                                <Button
                                    key={page}
                                    variant={
                                        users.current_page === page
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="sm"
                                    onClick={() =>
                                        handlePageChange(page as number)
                                    }
                                >
                                    {page}
                                </Button>
                            ),
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={users.current_page === users.last_page}
                            onClick={() =>
                                handlePageChange(users.current_page + 1)
                            }
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
                        <DialogTitle>Create New User</DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleCreateSubmit();
                        }}
                        className="space-y-4"
                    >
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label htmlFor="name">Name *</Label>
                                <Input
                                    id="name"
                                    value={createForm.data.name}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                />
                                {createForm.errors.name && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {createForm.errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="sm:col-span-2">
                                <Label htmlFor="email">Email *</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={createForm.data.email}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                />
                                {createForm.errors.email && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {createForm.errors.email}
                                    </p>
                                )}
                            </div>
                            <div className="relative sm:col-span-2">
                                <Label htmlFor="password">Password *</Label>
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    value={createForm.data.password}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    className="pr-10"
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowPassword(!showPassword)
                                    }
                                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-4 w-4" />
                                    ) : (
                                        <Eye className="h-4 w-4" />
                                    )}
                                </button>
                                {createForm.data.password && (
                                    <p
                                        className={cn(
                                            'mt-1 text-xs',
                                            getPasswordStrength(
                                                createForm.data.password,
                                            ) === 'weak'
                                                ? 'text-red-500'
                                                : getPasswordStrength(
                                                        createForm.data
                                                            .password,
                                                    ) === 'medium'
                                                  ? 'text-yellow-500'
                                                  : 'text-green-500',
                                        )}
                                    >
                                        Strength:{' '}
                                        {getPasswordStrength(
                                            createForm.data.password,
                                        )}
                                    </p>
                                )}
                                {createForm.errors.password && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {createForm.errors.password}
                                    </p>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="role">Role *</Label>
                                <select
                                    id="role"
                                    value={createForm.data.role}
                                    onChange={(e) =>
                                        handleRoleChange(
                                            e.target.value,
                                            createForm,
                                            (v) =>
                                                createForm.setData('role', v),
                                        )
                                    }
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="super_admin">
                                        Super Admin
                                    </option>
                                    <option value="owner">Owner</option>
                                    <option value="seller">Seller</option>
                                </select>
                                {createForm.errors.role && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {createForm.errors.role}
                                    </p>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="shop_id">Shop</Label>
                                <select
                                    id="shop_id"
                                    value={createForm.data.shop_id}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'shop_id',
                                            e.target.value,
                                        )
                                    }
                                    disabled={
                                        createForm.data.role === 'super_admin'
                                    }
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-50"
                                >
                                    <option value="">No shop</option>
                                    {shops.map((shop) => (
                                        <option
                                            key={shop.id}
                                            value={String(shop.id)}
                                        >
                                            {shop.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setCreateOpen(false);
                                    createForm.reset();
                                    setShowPassword(false);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                            >
                                Create User
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Modal */}
            <Dialog
                open={!!editingUser}
                onOpenChange={(open) => !open && closeEditModal()}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Edit User — {editingUser?.name}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleEditSubmit();
                        }}
                        className="space-y-4"
                    >
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label htmlFor="edit-name">Name *</Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(e) =>
                                        editForm.setData('name', e.target.value)
                                    }
                                />
                                {editForm.errors.name && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {editForm.errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="sm:col-span-2">
                                <Label htmlFor="edit-email">Email *</Label>
                                <Input
                                    id="edit-email"
                                    type="email"
                                    value={editForm.data.email}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                />
                                {editForm.errors.email && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {editForm.errors.email}
                                    </p>
                                )}
                            </div>
                            <div className="relative sm:col-span-2">
                                <Label htmlFor="edit-password">Password</Label>
                                <Input
                                    id="edit-password"
                                    type={showPassword ? 'text' : 'password'}
                                    value={editForm.data.password}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Leave blank to keep current password"
                                    className="pr-10"
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowPassword(!showPassword)
                                    }
                                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-4 w-4" />
                                    ) : (
                                        <Eye className="h-4 w-4" />
                                    )}
                                </button>
                                {editForm.data.password && (
                                    <p
                                        className={cn(
                                            'mt-1 text-xs',
                                            getPasswordStrength(
                                                editForm.data.password,
                                            ) === 'weak'
                                                ? 'text-red-500'
                                                : getPasswordStrength(
                                                        editForm.data.password,
                                                    ) === 'medium'
                                                  ? 'text-yellow-500'
                                                  : 'text-green-500',
                                        )}
                                    >
                                        Strength:{' '}
                                        {getPasswordStrength(
                                            editForm.data.password,
                                        )}
                                    </p>
                                )}
                                {editForm.errors.password && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {editForm.errors.password}
                                    </p>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="edit-role">Role *</Label>
                                <select
                                    id="edit-role"
                                    value={editForm.data.role}
                                    onChange={(e) =>
                                        handleRoleChange(
                                            e.target.value,
                                            editForm,
                                            (v) => editForm.setData('role', v),
                                        )
                                    }
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="super_admin">
                                        Super Admin
                                    </option>
                                    <option value="owner">Owner</option>
                                    <option value="seller">Seller</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="edit-shop_id">Shop</Label>
                                <select
                                    id="edit-shop_id"
                                    value={editForm.data.shop_id}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'shop_id',
                                            e.target.value,
                                        )
                                    }
                                    disabled={
                                        editForm.data.role === 'super_admin'
                                    }
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-50"
                                >
                                    <option value="">No shop</option>
                                    {shops.map((shop) => (
                                        <option
                                            key={shop.id}
                                            value={String(shop.id)}
                                        >
                                            {shop.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
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
        </AppLayout>
    );
}
