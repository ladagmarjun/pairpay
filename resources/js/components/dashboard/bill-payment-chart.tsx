import { useState } from 'react';
import { axisTicks, chartYear } from '@/lib/chart';
import { formatCurrency, formatCurrencyCompact } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BillTrend } from '@/types';

/**
 * What the bills came to each month, and how much of it was settled.
 *
 * Two series on one column rather than two columns side by side: paid is a
 * part of due, not a rival to it, and drawing it inside the same bar says so
 * while keeping twelve months legible on a phone. A month paid beyond what it
 * owed — a bill edited downwards after the fact — overshoots the track, which
 * is the honest picture rather than a bar quietly clipped at full.
 *
 * Built from divs and laid out the same way as the expense chart beside it,
 * for the reasons given there.
 */
export function BillPaymentChart({ bills }: { bills: BillTrend }) {
    const [active, setActive] = useState<number | null>(null);

    const ticks = axisTicks(bills.peak);
    const scaleMax = ticks[ticks.length - 1];

    const tracked = bills.due > 0 || bills.paid > 0;
    const year = chartYear(bills.points);
    const widestTick = formatCurrencyCompact(scaleMax);

    return (
        <figure className="rounded-xl border p-5">
            <figcaption className="mb-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <div>
                    <h2 className="font-medium">Bills</h2>
                    <p className="text-sm text-muted-foreground">
                        Due and paid in {year}
                    </p>
                </div>

                {tracked && (
                    <p className="text-sm text-muted-foreground">
                        <span className="font-medium text-foreground tabular-nums">
                            {formatCurrency(bills.paid)}
                        </span>{' '}
                        paid of {formatCurrency(bills.due)}
                    </p>
                )}
            </figcaption>

            {tracked ? (
                <>
                    {/* Two series, so unlike the expense chart beside it this
                        one has to name them. */}
                    <ul className="mb-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <li className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-xs bg-chart-series" />
                            Paid
                        </li>
                        <li className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-xs bg-muted" />
                            Still due
                        </li>
                    </ul>

                    <div className="flex gap-3">
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
                                {bills.points.map((point, index) => {
                                    const due = (point.due / scaleMax) * 100;
                                    const paid = (point.paid / scaleMax) * 100;
                                    const outstanding = Math.max(
                                        0,
                                        point.due - point.paid,
                                    );

                                    return (
                                        <li
                                            key={point.month}
                                            className="flex min-w-0 flex-1 flex-col items-center"
                                        >
                                            <div className="relative flex h-40 w-full items-end justify-center">
                                                <button
                                                    type="button"
                                                    aria-label={`${point.label}: ${formatCurrency(point.paid)} paid of ${formatCurrency(point.due)} due`}
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
                                                    {/* The track is what the
                                                        month owed; the fill in
                                                        front of it is the part
                                                        that was paid. */}
                                                    <span
                                                        className={cn(
                                                            'relative h-full w-full max-w-6 transition-opacity',
                                                            active !== null &&
                                                                active !==
                                                                    index &&
                                                                'opacity-40',
                                                        )}
                                                    >
                                                        <span
                                                            className="absolute inset-x-0 bottom-0 rounded-t bg-muted"
                                                            style={{
                                                                height: `${Math.max(due, point.due > 0 ? 1.5 : 0)}%`,
                                                            }}
                                                        />

                                                        <span
                                                            className="absolute inset-x-0 bottom-0 rounded-t bg-chart-series"
                                                            style={{
                                                                height: `${Math.max(paid, point.paid > 0 ? 1.5 : 0)}%`,
                                                            }}
                                                        />
                                                    </span>
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
                                                                point.paid,
                                                            )}{' '}
                                                            paid of{' '}
                                                            {formatCurrency(
                                                                point.due,
                                                            )}
                                                        </p>
                                                        {outstanding > 0 && (
                                                            <p className="text-muted-foreground tabular-nums">
                                                                {formatCurrency(
                                                                    outstanding,
                                                                )}{' '}
                                                                still due
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </div>

                                            <span
                                                aria-hidden="true"
                                                className={cn(
                                                    'mt-2 text-xs text-muted-foreground',
                                                    index % 3 !== 0 &&
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
                                        Due
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-1 text-right font-medium"
                                    >
                                        Paid
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {bills.points.map((point) => (
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
                                            {formatCurrency(point.due)}
                                        </td>
                                        <td className="py-1 text-right tabular-nums">
                                            {formatCurrency(point.paid)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </details>
                </>
            ) : (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    No bills yet. Add one and this fills in.
                </p>
            )}
        </figure>
    );
}
