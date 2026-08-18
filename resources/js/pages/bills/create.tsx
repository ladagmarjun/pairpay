import { Head } from '@inertiajs/react';
import BillController from '@/actions/App/Http/Controllers/BillController';
import { BillForm } from '@/components/bills/bill-form';
import Heading from '@/components/heading';
import { create as createBill, index as billsIndex } from '@/routes/bills';
import type { CategoryOption, PaymentMethodOption } from '@/types';

export default function CreateBill({
    categories,
    paymentMethods,
}: {
    categories: CategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    return (
        <>
            <Head title="Add bill" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Add bill"
                    description="Something you pay every month, and the date it falls due."
                />

                <BillForm
                    formProps={BillController.store.form()}
                    categories={categories}
                    paymentMethods={paymentMethods}
                    submitLabel="Add bill"
                />
            </div>
        </>
    );
}

CreateBill.layout = {
    breadcrumbs: [
        { title: 'Bills', href: billsIndex() },
        { title: 'Add bill', href: createBill() },
    ],
};
