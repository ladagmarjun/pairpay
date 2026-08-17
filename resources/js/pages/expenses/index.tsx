import { Head, Link } from '@inertiajs/react';
import { Plus, ReceiptText, SearchX } from 'lucide-react';
import { useState } from 'react';
import { DeleteExpenseDialog } from '@/components/expenses/delete-expense-dialog';
import { ExpenseFilterBar } from '@/components/expenses/expense-filters';
import { ExpenseRow } from '@/components/expenses/expense-row';
import { MonthlySummaryCard } from '@/components/expenses/monthly-summary-card';
import { Button } from '@/components/ui/button';
import { useCurrency } from '@/hooks/use-currency';
import { optionMap } from '@/lib/expense-meta';
import {
    create as createExpense,
    index as expensesIndex,
} from '@/routes/expenses';
import type {
    CategoryOption,
    Expense,
    ExpenseFilters,
    MonthlySummary,
    Paginated,
    PaymentMethodOption,
} from '@/types';

export default function ExpensesIndex({
    expenses,
    filters,
    summary,
    categories,
}: {
    expenses: Paginated<Expense>;
    filters: ExpenseFilters;
    summary: MonthlySummary;
    categories: CategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    const currency = useCurrency();
    const categoriesByValue = optionMap(categories);
    const [pendingDelete, setPendingDelete] = useState<Expense | null>(null);

    return (
        <>
            <Head title="Expenses" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Expenses
                    </h1>

                    <Button asChild>
                        <Link href={createExpense()}>
                            <Plus className="size-4" />
                            Add expense
                        </Link>
                    </Button>
                </div>

                <MonthlySummaryCard summary={summary} currency={currency} />

                <ExpenseFilterBar filters={filters} categories={categories} />

                {expenses.data.length === 0 ? (
                    <EmptyState hasFilter={filters.is_active} />
                ) : (
                    <div className="divide-y overflow-hidden rounded-xl border">
                        {expenses.data.map((expense) => (
                            <ExpenseRow
                                key={expense.id}
                                expense={expense}
                                category={categoriesByValue[expense.category]}
                                currency={currency}
                                onDelete={setPendingDelete}
                            />
                        ))}
                    </div>
                )}

                {expenses.last_page > 1 && (
                    <div className="flex items-center justify-between gap-4">
                        <p className="text-sm text-muted-foreground">
                            Showing {expenses.from}–{expenses.to} of{' '}
                            {expenses.total}
                        </p>

                        {/* The paginator's own URLs already carry the active
                            filters, thanks to `withQueryString()`. */}
                        <div className="flex gap-2">
                            {expenses.prev_page_url ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={expenses.prev_page_url}
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

                            {expenses.next_page_url ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={expenses.next_page_url}
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

            <DeleteExpenseDialog
                expense={pendingDelete}
                onClose={() => setPendingDelete(null)}
            />
        </>
    );
}

function EmptyState({ hasFilter }: { hasFilter: boolean }) {
    const Icon = hasFilter ? SearchX : ReceiptText;

    return (
        <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed p-12">
            <div className="flex max-w-sm flex-col items-center text-center">
                <Icon
                    className="size-14 text-muted-foreground/60"
                    strokeWidth={1.5}
                />

                <p className="mt-4 font-medium">
                    {hasFilter ? 'No matching expenses' : 'No expenses yet'}
                </p>

                <p className="mt-1 text-sm text-muted-foreground">
                    {hasFilter
                        ? 'Try adjusting your search or filters.'
                        : 'Select “Add expense” to record your first one.'}
                </p>
            </div>
        </div>
    );
}

ExpensesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Expenses',
            href: expensesIndex(),
        },
    ],
};
