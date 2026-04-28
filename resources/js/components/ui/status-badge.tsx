// resources/js/components/ui/status-badge.tsx
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface StatusBadgeProps {
    status: 'active' | 'suspended' | string;
    className?: string;
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
    return (
        <Badge
            className={cn(
                'text-[11px] font-semibold px-2 py-0.5 border',
                status === 'active'
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-50'
                    : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-50',
                className,
            )}
        >
            {status === 'active' ? 'Active' : 'Suspended'}
        </Badge>
    );
}
