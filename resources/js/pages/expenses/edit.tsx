import { Head } from '@inertiajs/react';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import { ExpenseForm } from '@/components/expenses/expense-form';
import Heading from '@/components/heading';
import { index as expensesIndex } from '@/routes/expenses';
import type { CategoryOption, Expense, PaymentMethodOption } from '@/types';

export default function EditExpense({
    expense,
    categories,
    paymentMethods,
}: {
    expense: Expense;
    categories: CategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    return (
        <>
            <Head title="Edit expense" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit expense"
                    description="Update the details of this expense."
                />

                <ExpenseForm
                    formProps={ExpenseController.update.form(expense.id)}
                    expense={expense}
                    categories={categories}
                    paymentMethods={paymentMethods}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditExpense.layout = {
    breadcrumbs: [
        { title: 'Expenses', href: expensesIndex() },
        { title: 'Edit expense', href: expensesIndex() },
    ],
};
