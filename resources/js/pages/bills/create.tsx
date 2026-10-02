import { Head } from '@inertiajs/react';
import BillController from '@/actions/App/Http/Controllers/BillController';
import { BillForm } from '@/components/bills/bill-form';
import Heading from '@/components/heading';
import { todayIso } from '@/lib/format';
import { create as createBill, index as billsIndex } from '@/routes/bills';
import type { CategoryOption, PaymentMethodOption } from '@/types';

export default function CreateBill({
    month,
    categories,
    paymentMethods,
}: {
    /** `YYYY-MM` — the month the bill is being added to. */
    month: string;
    categories: CategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    // Today when adding to the current month, otherwise the 1st of the month
    // being viewed — so a bill added from September's list lands in September.
    const today = todayIso();
    const defaultDueDate = today.startsWith(month) ? today : `${month}-01`;

    return (
        <>
            <Head title="Add bill" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Add bill"
                    description="One month’s bill: what it costs this time, and when it is due."
                />

                <BillForm
                    formProps={BillController.store.form()}
                    categories={categories}
                    paymentMethods={paymentMethods}
                    submitLabel="Add bill"
                    defaultDueDate={defaultDueDate}
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
