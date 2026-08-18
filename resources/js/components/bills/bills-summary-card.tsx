import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/format';
import { index as billsIndex } from '@/routes/bills';
import type { BillsSummary } from '@/types';

/**
 * Header card on the bills page: what the month costs in fixed expenses, how
 * much of it is still owed, and a month stepper.
 *
 * The stepper is a link rather than local state — the month lives in the query
 * string, so the view is shareable and survives a refresh, exactly as on the
 * expenses page.
 */
export function BillsSummaryCard({
    summary,
    currency,
}: {
    summary: BillsSummary;
    currency: string;
}) {
    const settled = summary.count > 0 && summary.paid_count === summary.count;

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
                        href={billsIndex({
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
                        href={billsIndex({
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
                {formatCurrency(summary.remaining, currency)}
            </p>

            <p className="text-sm opacity-80">
                {settled
                    ? 'Everything is paid this month'
                    : `still to pay of ${formatCurrency(summary.total, currency)}`}
            </p>

            {summary.count > 0 && (
                <>
                    <span className="mt-4 flex h-2 overflow-hidden rounded-full bg-current/15">
                        <span
                            className="block h-full rounded-full bg-current transition-[width]"
                            style={{
                                width: `${(summary.paid_count / summary.count) * 100}%`,
                            }}
                        />
                    </span>

                    <p className="mt-2 text-sm opacity-80">
                        {summary.paid_count} of {summary.count}{' '}
                        {summary.count === 1 ? 'bill' : 'bills'} paid
                    </p>
                </>
            )}
        </div>
    );
}
