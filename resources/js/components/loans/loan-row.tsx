import { Link } from '@inertiajs/react';
import { DynamicIcon } from '@/components/dynamic-icon';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { formatCurrency, formatDate } from '@/lib/format';
import { show as showLoan } from '@/routes/loans';
import type { DirectionOption, Loan } from '@/types';

/**
 * A single row in the loans list: who it is with, what is left to pay, and how
 * far through it is. The whole row links to the loan, where the payments are.
 *
 * The amount shown is the *outstanding* one, because that is the live question
 * — the principal is history the moment the first payment lands.
 */
export function LoanRow({
    loan,
    direction,
    currency,
}: {
    loan: Loan;
    direction: DirectionOption;
    currency: string;
}) {
    const settled = loan.status === 'settled';

    return (
        <div className="group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/50">
            <span
                className="flex size-10 shrink-0 items-center justify-center rounded-full"
                style={{ backgroundColor: `${direction.color}26` }}
                aria-hidden
            >
                <DynamicIcon
                    name={direction.icon}
                    className="size-5"
                    style={{ color: direction.color }}
                />
            </span>

            <div className="min-w-0 flex-1">
                <Link
                    href={showLoan(loan.id)}
                    className="block truncate font-medium hover:underline"
                >
                    {loan.counterparty}
                </Link>

                <p className="flex items-center gap-2 truncate text-sm text-muted-foreground">
                    {/* On a loan with a plan, when the next month falls due is
                        more use than when the loan was made. */}
                    {loan.next_due_date
                        ? `Next due ${formatDate(loan.next_due_date)}`
                        : `${direction.label} ${formatDate(loan.date)}`}
                    <LoanStatusBadge status={loan.status} />
                </p>
            </div>

            <div className="shrink-0 text-right">
                <p
                    className="font-semibold tabular-nums"
                    style={settled ? undefined : { color: direction.color }}
                >
                    {formatCurrency(
                        settled ? loan.principal : loan.outstanding,
                        currency,
                    )}
                </p>

                <p className="text-sm text-muted-foreground tabular-nums">
                    {settled
                        ? 'paid in full'
                        : `of ${formatCurrency(loan.principal, currency)}`}
                </p>
            </div>
        </div>
    );
}
