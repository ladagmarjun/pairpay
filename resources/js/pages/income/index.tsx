import { Head, Link } from '@inertiajs/react';
import { Plus, SearchX, Wallet } from 'lucide-react';
import { useState } from 'react';
import { DeleteIncomeDialog } from '@/components/income/delete-income-dialog';
import { IncomeFilterBar } from '@/components/income/income-filters';
import { IncomeRow } from '@/components/income/income-row';
import { MonthlyIncomeCard } from '@/components/income/monthly-income-card';
import { Button } from '@/components/ui/button';
import { useCurrency } from '@/hooks/use-currency';
import { optionMap } from '@/lib/expense-meta';
import { create as createIncome, index as incomeIndex } from '@/routes/income';
import type {
    Income,
    IncomeCategoryOption,
    IncomeFilters,
    MonthlyIncomeSummary,
    Paginated,
    PaymentMethodOption,
} from '@/types';

export default function IncomeIndex({
    income,
    filters,
    summary,
    categories,
}: {
    income: Paginated<Income>;
    filters: IncomeFilters;
    summary: MonthlyIncomeSummary;
    categories: IncomeCategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    const currency = useCurrency();
    const categoriesByValue = optionMap(categories);
    const [pendingDelete, setPendingDelete] = useState<Income | null>(null);

    return (
        <>
            <Head title="Income" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Income
                    </h1>

                    <Button asChild>
                        <Link href={createIncome()}>
                            <Plus className="size-4" />
                            Add income
                        </Link>
                    </Button>
                </div>

                <MonthlyIncomeCard summary={summary} currency={currency} />

                <IncomeFilterBar filters={filters} categories={categories} />

                {income.data.length === 0 ? (
                    <EmptyState hasFilter={filters.is_active} />
                ) : (
                    <div className="divide-y overflow-hidden rounded-xl border">
                        {income.data.map((entry) => (
                            <IncomeRow
                                key={entry.id}
                                income={entry}
                                category={categoriesByValue[entry.category]}
                                currency={currency}
                                onDelete={setPendingDelete}
                            />
                        ))}
                    </div>
                )}

                {income.last_page > 1 && (
                    <div className="flex items-center justify-between gap-4">
                        <p className="text-sm text-muted-foreground">
                            Showing {income.from}–{income.to} of {income.total}
                        </p>

                        {/* The paginator's own URLs already carry the active
                            filters, thanks to `withQueryString()`. */}
                        <div className="flex gap-2">
                            {income.prev_page_url ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={income.prev_page_url}
                                        preserveScroll
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Previous
                                </Button>
                            )}

                            {income.next_page_url ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={income.next_page_url}
                                        preserveScroll
                                    >
                                        Next
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Next
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <DeleteIncomeDialog
                income={pendingDelete}
                onClose={() => setPendingDelete(null)}
            />
        </>
    );
}

function EmptyState({ hasFilter }: { hasFilter: boolean }) {
    const Icon = hasFilter ? SearchX : Wallet;

    return (
        <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed p-12">
            <div className="flex max-w-sm flex-col items-center text-center">
                <Icon
                    className="size-14 text-muted-foreground/60"
                    strokeWidth={1.5}
                />

                <p className="mt-4 font-medium">
                    {hasFilter ? 'No matching income' : 'No income yet'}
                </p>

                <p className="mt-1 text-sm text-muted-foreground">
                    {hasFilter
                        ? 'Try adjusting your search or filters.'
                        : 'Select “Add income” to record your first payment.'}
                </p>
            </div>
        </div>
    );
}

IncomeIndex.layout = {
    breadcrumbs: [
        {
            title: 'Income',
            href: incomeIndex(),
        },
    ],
};
