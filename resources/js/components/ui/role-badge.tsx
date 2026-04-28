// resources/js/components/ui/role-badge.tsx
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Role = 'super_admin' | 'owner' | 'seller' | string;

const roleConfig: Record<string, { label: string; className: string }> = {
    super_admin: {
        label: 'Super Admin',
        className: 'bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-50',
    },
    owner: {
        label: 'Owner',
        className: 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-50',
    },
    seller: {
        label: 'Seller',
        className: 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-100',
    },
};

interface RoleBadgeProps {
    role: Role;
    className?: string;
}

export function RoleBadge({ role, className }: RoleBadgeProps) {
    const config = roleConfig[role] ?? {
        label: role,
        className: 'bg-slate-100 text-slate-600 border-slate-200',
    };

    return (
        <Badge
            className={cn(
                'text-[11px] font-semibold px-2 py-0.5 border',
                config.className,
                className,
            )}
        >
            {config.label}
        </Badge>
    );
}
