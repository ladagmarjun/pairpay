import { useState } from 'react';
import { axisTicks, chartYear } from '@/lib/chart';
import { formatCurrency, formatCurrencyCompact } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { LoanTrend } from '@/types';

/**
 * What was still owed at the end of each month, both ways round.
 *
 * The charts beside this one plot flows — what the month cost, what was paid.
 * This plots a balance, so a flat run of months is not a quiet spell: it is a
 * debt that has not moved.
 *
 * Two bars per month rather than one stacked one. Owing ₱5,000 while being
 * owed ₱5,000 is not the position of owing nothing, so the two are never added
 * together; they share an axis only so their sizes can be compared.
 */
export function LoanBalanceChart({ loans }: { loans: LoanTrend }) {
    const [active, setActive] = useState<number | null>(null);

    const ticks = axisTicks(loans.peak);
    const scaleMax = ticks[ticks.length - 1];

    const lending = loans.peak > 0;
    const year = chartYear(loans.points);
    const widestTick = formatCurrencyCompact(scaleMax);

    return (
        <figure className="rounded-xl border p-5">
            <figcaption className="mb-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <div>
                    <h2 className="font-medium">Loan balances</h2>
                    <p className="text-sm text-muted-foreground">
                        Outstanding at each month end in {year}
                    </p>
                </div>

                {lending && (
                    <p className="text-sm text-muted-foreground">
                        <span className="font-medium text-foreground tabular-nums">
                            {formatCurrency(loans.owed_by_you)}
                        </span>{' '}
                        owed now
                    </p>
                )}
            </figcaption>

            {lending ? (
                <>
                    <ul className="mb-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <li className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-xs bg-owed" />
                            You owe
                        </li>
                        <li className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-xs bg-lent" />
                            You are owed
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
                                {loans.points.map((point, index) => {
                                    const settled =
                                        point.borrowed === 0 &&
                                        point.lent === 0;

                                    return (
                                        <li
                                            key={point.month}
                                            className="flex min-w-0 flex-1 flex-col items-center"
                                        >
                                            <div className="relative flex h-40 w-full items-end justify-center">
                                                <button
                                                    type="button"
                                                    aria-label={`${point.label}: ${formatCurrency(point.borrowed)} owed by you, ${formatCurrency(point.lent)} owed to you`}
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
                                                    {/* The pair is centred as
                                                        one unit, so a month
                                                        owing nothing still
                                                        lines up with its own
                                                        axis label. */}
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
                                                            className="w-full rounded-t bg-owed"
                                                            style={{
                                                                height: `${barHeight(point.borrowed, scaleMax)}%`,
                                                            }}
                                                        />

                                                        <span
                                                            className="w-full rounded-t bg-lent"
                                                            style={{
                                                                height: `${barHeight(point.lent, scaleMax)}%`,
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

                                                        {settled ? (
                                                            <p className="text-muted-foreground">
                                                                Nothing
                                                                outstanding
                                                            </p>
                                                        ) : (
                                                            <>
                                                                <p className="text-muted-foreground tabular-nums">
                                                                    {formatCurrency(
                                                                        point.borrowed,
                                                                    )}{' '}
                                                                    owed by you
                                                                </p>
                                                                <p className="text-muted-foreground tabular-nums">
                                                                    {formatCurrency(
                                                                        point.lent,
                                                                    )}{' '}
                                                                    owed to you
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
                                        You owe
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-1 text-right font-medium"
                                    >
                                        You are owed
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {loans.points.map((point) => (
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
                                            {formatCurrency(point.borrowed)}
                                        </td>
                                        <td className="py-1 text-right tabular-nums">
                                            {formatCurrency(point.lent)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </details>
                </>
            ) : (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    Nothing outstanding. Loans you record show their balance
                    here.
                </p>
            )}
        </figure>
    );
}

/**
 * A settled month gets no bar at all rather than the hairline the other charts
 * give a zero: here zero is the good outcome, and drawing a stub would read as
 * a debt that is nearly, but not quite, gone.
 */
function barHeight(amount: number, scaleMax: number): number {
    return amount > 0 ? Math.max((amount / scaleMax) * 100, 1.5) : 0;
}
