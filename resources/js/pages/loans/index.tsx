import { Head, Link } from '@inertiajs/react';
import { Landmark, Plus, Wallet } from 'lucide-react';
import { useState } from 'react';
import { LoanAllocationDialog } from '@/components/loans/loan-allocation-dialog';
import { LoanRow } from '@/components/loans/loan-row';
import { LoansSummaryCard } from '@/components/loans/loans-summary-card';
import { Button } from '@/components/ui/button';
import { useCurrency } from '@/hooks/use-currency';
import { optionMap } from '@/lib/expense-meta';
import { create as createLoan, index as loansIndex } from '@/routes/loans';
import type { DirectionOption, Loan, LoansSummary } from '@/types';

export default function LoansIndex({
    loans,
    summary,
    directions,
}: {
    loans: Loan[];
    summary: LoansSummary;
    directions: DirectionOption[];
}) {
    const currency = useCurrency();
    const directionsByValue = optionMap(directions);
    const [splitting, setSplitting] = useState(false);

    // Only money you owe can be paid off in a lump sum.
    const owesAnything = loans.some(
        (loan) => loan.direction === 'borrowed' && loan.outstanding > 0,
    );

    return (
        <>
            <Head title="Loans" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Loans
                    </h1>

                    <div className="flex items-center gap-2">
                        {owesAnything && (
                            <Button
                                variant="outline"
                                onClick={() => setSplitting(true)}
                            >
                                <Wallet className="size-4" />
                                Pay a lump sum
                            </Button>
                        )}

                        <Button asChild>
                            <Link href={createLoan()}>
                                <Plus className="size-4" />
                                Add loan
                            </Link>
                        </Button>
                    </div>
                </div>

                <LoansSummaryCard summary={summary} currency={currency} />

                {loans.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="divide-y overflow-hidden rounded-xl border">
                        {loans.map((loan) => (
                            <LoanRow
                                key={loan.id}
                                loan={loan}
                                direction={directionsByValue[loan.direction]}
                                currency={currency}
                            />
                        ))}
                    </div>
                )}
            </div>

            <LoanAllocationDialog
                open={splitting}
                loans={loans}
                currency={currency}
                onClose={() => setSplitting(false)}
            />
        </>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed p-12">
            <div className="flex max-w-sm flex-col items-center text-center">
                <Landmark
                    className="size-14 text-muted-foreground/60"
                    strokeWidth={1.5}
                />

                <p className="mt-4 font-medium">No loans yet</p>

                <p className="mt-1 text-sm text-muted-foreground">
                    Track money you borrowed and money you lent, and record the
                    payments against each as they happen.
                </p>
            </div>
        </div>
    );
}

LoansIndex.layout = {
    breadcrumbs: [
        {
            title: 'Loans',
            href: loansIndex(),
        },
    ],
};
