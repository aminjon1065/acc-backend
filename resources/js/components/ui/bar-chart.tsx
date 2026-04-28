// resources/js/components/ui/bar-chart.tsx
//
// Zero-dependency SVG bar chart. No external libraries needed.
//
// Usage:
//   <BarChart
//     data={monthlyBreakdown}
//     keys={['sales', 'expenses', 'profit']}
//     colors={['#2563EB', '#F59E0B', '#059669']}
//     labels={{ sales: 'Sales', expenses: 'Expenses', profit: 'Profit' }}
//     height={180}
//   />
//
// Each item in `data` must have a `month` string + numeric keys matching `keys`.

'use client';

import { useState } from 'react';
import { cn } from '@/lib/utils';

interface BarChartProps {
    data: Record<string, string | number>[];
    keys: string[];
    colors?: string[];
    labels?: Record<string, string>;
    height?: number;
    className?: string;
    formatY?: (v: number) => string;
}

const DEFAULT_COLORS = ['#2563EB', '#F59E0B', '#059669', '#7C3AED', '#DC2626'];

export function BarChart({
    data,
    keys,
    colors = DEFAULT_COLORS,
    labels = {},
    height = 180,
    className,
    formatY = (v) => (v >= 1000 ? `${(v / 1000).toFixed(0)}k` : String(v)),
}: BarChartProps) {
    const [hoveredIndex, setHoveredIndex] = useState<number | null>(null);

    const WIDTH = 560;
    const pad = { top: 12, right: 8, bottom: 28, left: 46 };
    const W = WIDTH - pad.left - pad.right;
    const H = height - pad.top - pad.bottom;

    const maxVal =
        Math.max(
            ...data.flatMap((d) =>
                keys.map((k) => (typeof d[k] === 'number' ? (d[k] as number) : 0)),
            ),
        ) || 1;

    const barGroupW = W / data.length;
    const barW = Math.min((barGroupW - 8) / keys.length, 22);
    const gridLines = [0, 0.25, 0.5, 0.75, 1];

    return (
        <div className={cn('w-full', className)}>
            {/* Legend */}
            <div className="mb-3 flex flex-wrap gap-4">
                {keys.map((k, i) => (
                    <div key={k} className="flex items-center gap-1.5">
                        <span
                            className="inline-block size-2.5 rounded-[3px]"
                            style={{ background: colors[i] ?? DEFAULT_COLORS[i] }}
                        />
                        <span className="text-[11px] font-medium text-muted-foreground">
                            {labels[k] ?? k}
                        </span>
                    </div>
                ))}
            </div>

            {/* SVG */}
            <svg
                width="100%"
                viewBox={`0 0 ${WIDTH} ${height}`}
                style={{ overflow: 'visible' }}
                aria-label="Bar chart"
            >
                {/* Grid lines + Y axis labels */}
                {gridLines.map((t) => {
                    const y = pad.top + H * (1 - t);
                    return (
                        <g key={t}>
                            <line
                                x1={pad.left}
                                x2={pad.left + W}
                                y1={y}
                                y2={y}
                                stroke="oklch(0.905 0.013 255.5)"
                                strokeWidth={1}
                            />
                            <text
                                x={pad.left - 6}
                                y={y + 4}
                                textAnchor="end"
                                fontSize={9}
                                fill="oklch(0.63 0.02 255)"
                            >
                                {t > 0 ? formatY(maxVal * t) : '0'}
                            </text>
                        </g>
                    );
                })}

                {/* Bars */}
                {data.map((d, i) => {
                    const groupX =
                        pad.left +
                        i * barGroupW +
                        barGroupW / 2 -
                        (keys.length * (barW + 2)) / 2;
                    const isHovered = hoveredIndex === i;

                    return (
                        <g
                            key={i}
                            onMouseEnter={() => setHoveredIndex(i)}
                            onMouseLeave={() => setHoveredIndex(null)}
                        >
                            {/* Hover highlight */}
                            {isHovered && (
                                <rect
                                    x={pad.left + i * barGroupW + 2}
                                    y={pad.top}
                                    width={barGroupW - 4}
                                    height={H}
                                    fill="oklch(0.967 0.007 247.9)"
                                    rx={4}
                                />
                            )}

                            {keys.map((k, ki) => {
                                const val =
                                    typeof d[k] === 'number' ? (d[k] as number) : 0;
                                const bh = (val / maxVal) * H;
                                const bx = groupX + ki * (barW + 2);
                                const by = pad.top + H - bh;
                                const clr = colors[ki] ?? DEFAULT_COLORS[ki];

                                return (
                                    <rect
                                        key={ki}
                                        x={bx}
                                        y={by}
                                        width={barW}
                                        height={Math.max(bh, 2)}
                                        fill={isHovered ? clr : clr + 'CC'}
                                        rx={3}
                                        style={{ transition: 'fill .15s' }}
                                    />
                                );
                            })}

                            {/* X axis label */}
                            <text
                                x={pad.left + i * barGroupW + barGroupW / 2}
                                y={pad.top + H + 16}
                                textAnchor="middle"
                                fontSize={9}
                                fill="oklch(0.63 0.02 255)"
                            >
                                {String(d.month ?? d.label ?? i + 1)}
                            </text>
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}
