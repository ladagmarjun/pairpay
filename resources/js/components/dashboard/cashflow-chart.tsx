import { useState } from 'react';
import { axisTicks, chartYear } from '@/lib/chart';
import { formatCurrency, formatCurrencyCompact } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { CashflowTrend } from '@/types';

/**
 * What came in against what went out, month by month.
 *
 * The headline chart, because it answers the question the others only inform:
 * did the month keep anything. Two bars sharing a baseline rather than one
 * netted bar — a month that earned ₱40,000 and spent ₱39,000 is a different
 * month from one that earned ₱1,000 and spent nothing, and a single surplus
 * bar would draw them the same.
 *
 * The caption carries the netting instead, where it can say which way it went.
 */
export function CashflowChart({ cashflow }: { cashflow: CashflowTrend }) {
    const [active, setActive] = useState<number | null>(null);

    const ticks = axisTicks(cashflow.peak);
    const scaleMax = ticks[ticks.length - 1];

    const tracked = cashflow.peak > 0;
    const year = chartYear(cashflow.points);
    const widestTick = formatCurrencyCompact(scaleMax);
    const short = cashflow.surplus < 0;

    return (
        <figure className="rounded-xl border p-5">
            <figcaption className="mb-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <div>
                    <h2 className="font-medium">Cashflow</h2>
                    <p className="text-sm text-muted-foreground">
                        In and out across {year}
                    </p>
                </div>

                {tracked && (
                    <p className="text-sm text-muted-foreground">
                        <span
                            className={cn(
                                'font-medium tabular-nums',
                                short ? 'text-expense' : 'text-income',
                            )}
                        >
                            {formatCurrency(Math.abs(cashflow.surplus))}
                        </span>{' '}
                        {short ? 'more spent than earned' : 'kept so far'}
                    </p>
                )}
            </figcaption>

            {tracked ? (
                <>
                    <ul className="mb-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <li className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-xs bg-income" />
                            In
                        </li>
                        <li className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-xs bg-expense" />
                            Out
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
                                {cashflow.points.map((point, index) => {
                                    const kept = point.income - point.spent;
                                    const quiet =
                                        point.income === 0 && point.spent === 0;

                                    return (
                                        <li
                                            key={point.month}
                                            className="flex min-w-0 flex-1 flex-col items-center"
                                        >
                                            <div className="relative flex h-40 w-full items-end justify-center">
                                                <button
                                                    type="button"
                                                    aria-label={`${point.label}: ${formatCurrency(point.income)} in, ${formatCurrency(point.spent)} out`}
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
                                                            'flex h-full w-full max-w-6 items-end justify-center gap-px transition-opacity',
                                                            active !== null &&
                                                                active !==
                                                                    index &&
                                                                'opacity-40',
                                                        )}
                                                    >
                                                        <span
                                                            className="w-full rounded-t bg-income"
                                                            style={{
                                                                height: `${barHeight(point.income, scaleMax)}%`,
                                                            }}
                                                        />

                                                        <span
                                                            className="w-full rounded-t bg-expense"
                                                            style={{
                                                                height: `${barHeight(point.spent, scaleMax)}%`,
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

                                                        {quiet ? (
                                                            <p className="text-muted-foreground">
                                                                Nothing recorded
                                                            </p>
                                                        ) : (
                                                            <>
                                                                <p className="text-muted-foreground tabular-nums">
                                                                    {formatCurrency(
                                                                        point.income,
                                                                    )}{' '}
                                                                    in
                                                                </p>
                                                                <p className="text-muted-foreground tabular-nums">
                                                                    {formatCurrency(
                                                                        point.spent,
                                                                    )}{' '}
                                                                    out
                                                                </p>
                                                                <p
                                                                    className={cn(
                                                                        'tabular-nums',
                                                                        kept < 0
                                                                            ? 'text-expense'
                                                                            : 'text-income',
                                                                    )}
                                                                >
                                                                    {formatCurrency(
                                                                        Math.abs(
                                                                            kept,
                                                                        ),
                                                                    )}{' '}
                                                                    {kept < 0
                                                                        ? 'short'
                                                                        : 'kept'}
                                                                </p>
                                                            </>
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
                                        In
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-1 text-right font-medium"
                                    >
                                        Out
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-1 text-right font-medium"
                                    >
                                        Kept
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {cashflow.points.map((point) => {
                                    const kept = point.income - point.spent;

                                    return (
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
                                                {formatCurrency(point.income)}
                                            </td>
                                            <td className="py-1 text-right tabular-nums">
                                                {formatCurrency(point.spent)}
                                            </td>
                                            <td
                                                className={cn(
                                                    'py-1 text-right tabular-nums',
                                                    kept < 0 && 'text-expense',
                                                )}
                                            >
                                                {kept < 0 ? '−' : ''}
                                                {formatCurrency(Math.abs(kept))}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </details>
                </>
            ) : (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    Nothing recorded yet. Add income and expenses and this fills
                    in.
                </p>
            )}
        </figure>
    );
}

/**
 * A month with nothing on one side gets no bar there — a stub would read as a
 * small amount rather than none.
 */
function barHeight(amount: number, scaleMax: number): number {
    return amount > 0 ? Math.max((amount / scaleMax) * 100, 1.5) : 0;
}
