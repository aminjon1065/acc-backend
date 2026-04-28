// resources/js/components/ui/metric-card.tsx
//
// Usage:
//   <MetricCard
//     label="Total Shops"
//     value={stats.shops}
//     icon={Store}
//     color="blue"
//     trend={12}   // optional: % vs last period (positive = up, negative = down)
//     sub="active tenants"  // optional subtitle
//   />

import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { TrendingUp, TrendingDown } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

type ColorKey = 'blue' | 'emerald' | 'amber' | 'red' | 'purple' | 'slate';

const colorMap: Record<ColorKey, { icon: string; text: string }> = {
    blue:    { icon: 'bg-blue-50 text-blue-600',    text: 'text-blue-600' },
    emerald: { icon: 'bg-emerald-50 text-emerald-600', text: 'text-emerald-600' },
    amber:   { icon: 'bg-amber-50 text-amber-600',  text: 'text-amber-600' },
    red:     { icon: 'bg-red-50 text-red-600',      text: 'text-red-600' },
    purple:  { icon: 'bg-purple-50 text-purple-600', text: 'text-purple-600' },
    slate:   { icon: 'bg-slate-100 text-slate-500', text: 'text-slate-500' },
};

interface MetricCardProps {
    label: string;
    value: string | number;
    icon: LucideIcon;
    color?: ColorKey;
    trend?: number;
    sub?: string;
    className?: string;
}

export function MetricCard({
    label,
    value,
    icon: Icon,
    color = 'blue',
    trend,
    sub,
    className,
}: MetricCardProps) {
    const c = colorMap[color] ?? colorMap.blue;
    const trendPositive = trend !== undefined && trend >= 0;

    return (
        <Card className={cn('overflow-hidden', className)}>
            <CardContent className="pt-5 pb-4 px-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            {label}
                        </p>
                        <p className="mt-1.5 text-[22px] font-extrabold leading-none tracking-tight text-foreground">
                            {value}
                        </p>
                        {sub && (
                            <p className="mt-1 text-[11px] text-muted-foreground">
                                {sub}
                            </p>
                        )}
                    </div>
                    <div
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-xl',
                            c.icon,
                        )}
                    >
                        <Icon className="size-[18px]" />
                    </div>
                </div>

                {trend !== undefined && (
                    <div className="mt-3 flex items-center gap-1.5">
                        {trendPositive ? (
                            <TrendingUp className="size-3 text-emerald-600" />
                        ) : (
                            <TrendingDown className="size-3 text-red-500" />
                        )}
                        <span
                            className={cn(
                                'text-[11px] font-semibold',
                                trendPositive ? 'text-emerald-600' : 'text-red-500',
                            )}
                        >
                            {trendPositive ? '+' : ''}{trend}%
                        </span>
                        <span className="text-[11px] text-muted-foreground">
                            vs last month
                        </span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
