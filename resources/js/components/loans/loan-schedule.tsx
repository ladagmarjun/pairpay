import { formatCurrency, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { InstallmentStatusValue, LoanInstallment } from '@/types';

const BADGES: Record<
    InstallmentStatusValue,
    { label: string; className: string }
> = {
    paid: {
        label: 'Paid',
        className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    },
    partial: {
        label: 'Part paid',
        className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    },
    overdue: {
        label: 'Overdue',
        className: 'bg-destructive/15 text-destructive',
    },
    upcoming: {
        label: 'Upcoming',
        className: 'bg-muted text-muted-foreground',
    },
};

/**
 * The month-by-month plan.
 *
 * Payments fill the earliest unpaid month first, so a month paid short stays
 * owed rather than being carried forward — which is why a row can read
 * "₱500 of ₱2,000" and still be Overdue.
 */
export function LoanSchedule({
    installments,
    currency,
}: {
    installments: LoanInstallment[];
    currency: string;
}) {
    return (
        <div className="flex flex-col gap-2">
            <h2 className="font-medium">Schedule</h2>

            <div className="divide-y overflow-hidden rounded-xl border">
                {installments.map((installment) => (
                    <div
                        key={installment.number}
                        className="flex items-center gap-4 px-4 py-3"
                    >
                        <span
                            className="w-8 shrink-0 text-sm text-muted-foreground tabular-nums"
                            aria-hidden
                        >
                            {installment.number}
                        </span>

                        <div className="min-w-0 flex-1">
                            <p className="flex items-center gap-2 font-medium">
                                {formatDate(installment.due_date)}
                                <StatusBadge status={installment.status} />
                            </p>

                            {installment.paid > 0 &&
                                installment.outstanding > 0 && (
                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {formatCurrency(
                                            installment.paid,
                                            currency,
                                        )}{' '}
                                        paid ·{' '}
                                        {formatCurrency(
                                            installment.outstanding,
                                            currency,
                                        )}{' '}
                                        left
                                    </p>
                                )}
                        </div>

                        <span
                            className={cn(
                                'shrink-0 font-semibold tabular-nums',
                                installment.status === 'paid' &&
                                    'text-muted-foreground line-through',
                            )}
                        >
                            {formatCurrency(installment.amount, currency)}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function StatusBadge({ status }: { status: InstallmentStatusValue }) {
    const badge = BADGES[status];

    return (
        <span
            className={cn(
                'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                badge.className,
            )}
        >
            {badge.label}
        </span>
    );
}
