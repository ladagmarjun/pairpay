import { Head } from '@inertiajs/react';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import { ExpenseForm } from '@/components/expenses/expense-form';
import Heading from '@/components/heading';
import {
    create as createExpense,
    index as expensesIndex,
} from '@/routes/expenses';
import type { CategoryOption, PaymentMethodOption } from '@/types';

export default function CreateExpense({
    categories,
    paymentMethods,
}: {
    categories: CategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    return (
        <>
            <Head title="Add expense" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Add expense"
                    description="Record what you spent and where it went."
                />

                <ExpenseForm
                    formProps={ExpenseController.store.form()}
                    categories={categories}
                    paymentMethods={paymentMethods}
                    submitLabel="Add expense"
                />
            </div>
        </>
    );
}

CreateExpense.layout = {
    breadcrumbs: [
        { title: 'Expenses', href: expensesIndex() },
        { title: 'Add expense', href: createExpense() },
    ],
};
