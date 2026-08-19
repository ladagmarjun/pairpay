import { Head } from '@inertiajs/react';
import IncomeController from '@/actions/App/Http/Controllers/IncomeController';
import Heading from '@/components/heading';
import { IncomeForm } from '@/components/income/income-form';
import { index as incomeIndex } from '@/routes/income';
import type {
    Income,
    IncomeCategoryOption,
    PaymentMethodOption,
} from '@/types';

export default function EditIncome({
    income,
    categories,
    paymentMethods,
}: {
    income: Income;
    categories: IncomeCategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    return (
        <>
            <Head title="Edit income" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit income"
                    description="Update the details of this payment."
                />

                <IncomeForm
                    formProps={IncomeController.update.form(income.id)}
                    income={income}
                    categories={categories}
                    paymentMethods={paymentMethods}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditIncome.layout = {
    breadcrumbs: [
        { title: 'Income', href: incomeIndex() },
        { title: 'Edit income', href: incomeIndex() },
    ],
};
