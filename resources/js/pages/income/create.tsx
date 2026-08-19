import { Head } from '@inertiajs/react';
import IncomeController from '@/actions/App/Http/Controllers/IncomeController';
import Heading from '@/components/heading';
import { IncomeForm } from '@/components/income/income-form';
import { create as createIncome, index as incomeIndex } from '@/routes/income';
import type { IncomeCategoryOption, PaymentMethodOption } from '@/types';

export default function CreateIncome({
    categories,
    paymentMethods,
}: {
    categories: IncomeCategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    return (
        <>
            <Head title="Add income" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Add income"
                    description="Record money you received and where it came from."
                />

                <IncomeForm
                    formProps={IncomeController.store.form()}
                    categories={categories}
                    paymentMethods={paymentMethods}
                    submitLabel="Add income"
                />
            </div>
        </>
    );
}

CreateIncome.layout = {
    breadcrumbs: [
        { title: 'Income', href: incomeIndex() },
        { title: 'Add income', href: createIncome() },
    ],
};
