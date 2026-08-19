import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/format';
import { index as incomeIndex } from '@/routes/income';
import type { MonthlyIncomeSummary } from '@/types';

/**
 * Header card on the income page: total received in the selected month, a
 * month stepper, and where the biggest share came from.
 *
 * The stepper is a link rather than local state — the month lives in the query
 * string, so the view is shareable and survives a refresh. `mergeQuery` keeps
 * any active search/filter in place while stepping.
 */
export function MonthlyIncomeCard({
    summary,
    currency,
}: {
    summary: MonthlyIncomeSummary;
    currency: string;
}) {
    const top = summary.by_category.slice(0, 3);

    return (
        <div className="rounded-xl bg-primary-container p-5 text-primary-container-foreground">
            <div className="flex items-center gap-1">
                <h2 className="flex-1 font-medium">{summary.month_label}</h2>

                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="size-8 hover:bg-black/5 dark:hover:bg-white/10"
                >
                    <Link
                        href={incomeIndex({
                            mergeQuery: { month: summary.previous_month },
                        })}
                        preserveScroll
                        aria-label="Previous month"
                    >
                        <ChevronLeft className="size-5" />
                    </Link>
                </Button>

                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="size-8 hover:bg-black/5 dark:hover:bg-white/10"
                >
                    <Link
                        href={incomeIndex({
                            mergeQuery: { month: summary.next_month },
                        })}
                        preserveScroll
                        aria-label="Next month"
                    >
                        <ChevronRight className="size-5" />
                    </Link>
                </Button>
            </div>

            <p className="mt-1 text-3xl font-bold tabular-nums">
                {formatCurrency(summary.total, currency)}
            </p>

            <p className="text-sm opacity-80">
                {summary.entry_count}{' '}
                {summary.entry_count === 1 ? 'payment' : 'payments'} this month
            </p>

            {top.length > 0 && (
                <ul className="mt-4 space-y-2">
                    {top.map((entry) => (
                        <li
                            key={entry.category}
                            className="flex items-center gap-3 text-sm"
                        >
                            <span className="w-24 shrink-0 truncate">
                                {entry.label}
                            </span>

                            <span className="h-2 flex-1 overflow-hidden rounded-full bg-current/15">
                                <span
                                    className="block h-full rounded-full"
                                    style={{
                                        width: `${Math.min(entry.fraction, 1) * 100}%`,
                                        backgroundColor: entry.color,
                                    }}
                                />
                            </span>

                            <span className="shrink-0 tabular-nums">
                                {formatCurrency(entry.total, currency)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
