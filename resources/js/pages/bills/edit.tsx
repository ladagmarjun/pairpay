import { Head } from '@inertiajs/react';
import BillController from '@/actions/App/Http/Controllers/BillController';
import { BillForm } from '@/components/bills/bill-form';
import Heading from '@/components/heading';
import { index as billsIndex } from '@/routes/bills';
import type { Bill, CategoryOption, PaymentMethodOption } from '@/types';

export default function EditBill({
    bill,
    categories,
    paymentMethods,
}: {
    bill: Bill;
    categories: CategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    return (
        <>
            <Head title="Edit bill" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit bill"
                    description="Changes apply to this bill only. Payments already recorded keep what they cost."
                />

                <BillForm
                    formProps={BillController.update.form(bill.id)}
                    bill={bill}
                    categories={categories}
                    paymentMethods={paymentMethods}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditBill.layout = {
    breadcrumbs: [
        { title: 'Bills', href: billsIndex() },
        { title: 'Edit bill', href: billsIndex() },
    ],
};
