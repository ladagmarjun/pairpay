import { useState } from 'react';
import { axisTicks } from '@/lib/chart';
import { formatCurrency, formatCurrencyCompact } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { MonthlyTrend } from '@/types';

/**
 * Spend per month across the last year.
 *
 * Built from divs rather than SVG so the type stays at real pixel sizes at
 * every width — an SVG scaled to fit would scale its labels with it, and the
 * axis is mostly text.
 *
 * One series, so there is no legend: the heading says what is plotted. Values
 * are labelled selectively — the biggest month only — with the axis, the
 * tooltip, and the table view carrying the rest.
 *
 * Each column owns its own axis label rather than there being a second row of
 * them beside the bars. A separate row cannot be kept in step: `flex-1` items
 * will not shrink below their own text, so on a narrow screen the bars would
 * compress while the labels held their width, and the two would walk apart.
 */
export function MonthlyExpenseChart({ trend }: { trend: MonthlyTrend }) {
    const [active, setActive] = useState<number | null>(null);

    const ticks = axisTicks(trend.peak);
    const scaleMax = ticks[ticks.length - 1];
    const peakIndex = trend.points.reduce(
        (best, point, index) =>
            point.total > trend.points[best].total ? index : best,
        0,
    );

    const spent = trend.total > 0;
    const widestTick = formatCurrencyCompact(scaleMax);

    return (
        <figure className="rounded-xl border p-5">
            <figcaption className="mb-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <div>
                    <h2 className="font-medium">Monthly expenses</h2>
                    <p className="text-sm text-muted-foreground">
                        The last {trend.points.length} months
                    </p>
                </div>

                {spent && (
                    <p className="text-sm text-muted-foreground">
                        <span className="font-medium text-foreground tabular-nums">
                            {formatCurrency(trend.average)}
                        </span>{' '}
                        a month on average
                    </p>
                )}
            </figcaption>

            {spent ? (
                <>
                    <div className="flex gap-3">
                        {/* The invisible copy of the widest tick is what gives
                            this column its width; the real ticks are placed
                            against the gridlines they name, so the two cannot
                            drift the way evenly-spaced boxes would. */}
                        <div
                            aria-hidden="true"
                            className="relative h-40 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                        >
                            <span className="invisible">{widestTick}</span>

                            {ticks.map((tick, index) => (
                                <span
                                    key={tick}
                                    className="absolute right-0 translate-y-1/2 leading-none"
                                    style={{
                                        bottom: `${(index / (ticks.length - 1)) * 100}%`,
                                    }}
                                >
                                    {formatCurrencyCompact(tick)}
                                </span>
                            ))}
                        </div>

                        <div className="relative min-w-0 flex-1">
                            {/* Hairline, solid, one step off the surface: the
                                grid is there to be measured against, not read. */}
                            <div
                                aria-hidden="true"
                                className="absolute inset-x-0 top-0 h-40"
                            >
                                {ticks.map((tick, index) => (
                                    <span
                                        key={tick}
                                        className="absolute inset-x-0 h-px bg-border"
                                        style={{
                                            bottom: `${(index / (ticks.length - 1)) * 100}%`,
                                        }}
                                    />
                                ))}
                            </div>

                            <ol className="relative flex gap-1 sm:gap-2">
                                {trend.points.map((point, index) => {
                                    const height =
                                        (point.total / scaleMax) * 100;
                                    const isPeak =
                                        index === peakIndex && point.total > 0;

                                    return (
                                        <li
                                            key={point.month}
                                            className="flex min-w-0 flex-1 flex-col items-center"
                                        >
                                            <div className="relative flex h-40 w-full items-end justify-center">
                                                {/* The value rides the cap of
                                                    the tallest column only. One
                                                    on every column would be
                                                    noise, and the tooltip
                                                    covers the rest. */}
                                                {isPeak && (
                                                    <span
                                                        className="pointer-events-none absolute text-xs font-medium text-foreground tabular-nums"
                                                        style={{
                                                            bottom: `calc(${height}% + 6px)`,
                                                        }}
                                                    >
                                                        {formatCurrencyCompact(
                                                            point.total,
                                                        )}
                                                    </span>
                                                )}

                                                {/* A button, not a bare div:
                                                    a phone has no hover, so the
                                                    tooltip has to be reachable
                                                    by tap and by keyboard. */}
                                                <button
                                                    type="button"
                                                    aria-label={`${point.label}: ${formatCurrency(point.total)}`}
                                                    className="flex h-full w-full items-end justify-center rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    onMouseEnter={() =>
                                                        setActive(index)
                                                    }
                                                    onMouseLeave={() =>
                                                        setActive(null)
                                                    }
                                                    onFocus={() =>
                                                        setActive(index)
                                                    }
                                                    onBlur={() =>
                                                        setActive(null)
                                                    }
                                                    onClick={() =>
                                                        setActive(
                                                            active === index
                                                                ? null
                                                                : index,
                                                        )
                                                    }
                                                >
                                                    <span
                                                        className={cn(
                                                            'w-full max-w-6 rounded-t bg-chart-series transition-opacity',
                                                            active !== null &&
                                                                active !==
                                                                    index &&
                                                                'opacity-40',
                                                        )}
                                                        style={{
                                                            height: `${Math.max(height, point.total > 0 ? 1.5 : 0)}%`,
                                                        }}
                                                    />
                                                </button>

                                                {active === index && (
                                                    <div
                                                        role="tooltip"
                                                        className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 w-max -translate-x-1/2 rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-md"
                                                    >
                                                        <p className="font-medium">
                                                            {point.label}
                                                        </p>
                                                        <p className="text-muted-foreground tabular-nums">
                                                            {formatCurrency(
                                                                point.total,
                                                            )}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>

                                            {/* Twelve three-letter months do
                                                not fit a phone. Every third is
                                                kept, counted back from the
                                                newest so the month you are in
                                                is always one of them; the rest
                                                stay in the layout, just unseen,
                                                so nothing shifts at `sm`. */}
                                            <span
                                                aria-hidden="true"
                                                className={cn(
                                                    'mt-2 text-xs',
                                                    index === peakIndex
                                                        ? 'text-foreground'
                                                        : 'text-muted-foreground',
                                                    (trend.points.length -
                                                        1 -
                                                        index) %
                                                        3 !==
                                                        0 &&
                                                        'invisible sm:visible',
                                                )}
                                            >
                                                {point.short_label}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ol>
                        </div>
                    </div>

                    {/* Everything the chart shows, for anyone who would rather
                        read the numbers than poke at the bars. */}
                    <details className="mt-6">
                        <summary className="cursor-pointer text-sm text-muted-foreground hover:text-foreground">
                            View as table
                        </summary>

                        <table className="mt-3 w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th
                                        scope="col"
                                        className="py-1 font-medium"
                                    >
                                        Month
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-1 text-right font-medium"
                                    >
                                        Spent
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {trend.points.map((point) => (
                                    <tr
                                        key={point.month}
                                        className="border-b last:border-0"
                                    >
                                        <th
                                            scope="row"
                                            className="py-1 font-normal"
                                        >
                                            {point.label}
                                        </th>
                                        <td className="py-1 text-right tabular-nums">
                                            {formatCurrency(point.total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </details>
                </>
            ) : (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    No expenses recorded yet. Add one and this fills in.
                </p>
            )}
        </figure>
    );
}
