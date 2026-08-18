import { formatCurrency } from '@/lib/format';
import type { LoansSummary } from '@/types';

/**
 * The two figures at the top of the loans page.
 *
 * They sit side by side and are never netted off: owing ₱5,000 while being
 * owed ₱5,000 is not the same position as owing nothing, and a single "net"
 * number would hide both debts.
 */
export function LoansSummaryCard({
    summary,
    currency,
}: {
    summary: LoansSummary;
    currency: string;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="rounded-xl bg-primary-container p-5 text-primary-container-foreground">
                <h2 className="font-medium">You owe</h2>

                <p className="mt-1 text-3xl font-bold tabular-nums">
                    {formatCurrency(summary.owed_by_you, currency)}
                </p>
            </div>

            <div className="rounded-xl border p-5">
                <h2 className="font-medium">You are owed</h2>

                <p className="mt-1 text-3xl font-bold tabular-nums">
                    {formatCurrency(summary.owed_to_you, currency)}
                </p>
            </div>
        </div>
    );
}
