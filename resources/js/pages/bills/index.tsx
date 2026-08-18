import { Head, Link } from '@inertiajs/react';
import { Plus, ReceiptText, Wallet } from 'lucide-react';
import { useState } from 'react';
import { BillAllocationDialog } from '@/components/bills/bill-allocation-dialog';
import { BillPaymentDialog } from '@/components/bills/bill-payment-dialog';
import { BillRow } from '@/components/bills/bill-row';
import { BillsSummaryCard } from '@/components/bills/bills-summary-card';
import { DeleteBillDialog } from '@/components/bills/delete-bill-dialog';
import { Button } from '@/components/ui/button';
import { useCurrency } from '@/hooks/use-currency';
import { optionMap } from '@/lib/expense-meta';
import { create as createBill, index as billsIndex } from '@/routes/bills';
import type {
    Bill,
    BillsSummary,
    CategoryOption,
    PaymentMethodOption,
} from '@/types';

export default function BillsIndex({
    bills,
    summary,
    categories,
}: {
    bills: Bill[];
    summary: BillsSummary;
    categories: CategoryOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    const currency = useCurrency();
    const categoriesByValue = optionMap(categories);
    const [pendingDelete, setPendingDelete] = useState<Bill | null>(null);
    const [pendingPay, setPendingPay] = useState<Bill | null>(null);
    const [splitting, setSplitting] = useState(false);

    const anythingOwed = bills.some((bill) => bill.remaining > 0);

    return (
        <>
            <Head title="Bills" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Bills
                    </h1>

                    <div className="flex items-center gap-2">
                        {/* Only worth offering when there is something to
                            split it across. */}
                        {anythingOwed && (
                            <Button
                                variant="outline"
                                onClick={() => setSplitting(true)}
                            >
                                <Wallet className="size-4" />
                                Pay a lump sum
                            </Button>
                        )}

                        <Button asChild>
                            <Link href={createBill()}>
                                <Plus className="size-4" />
                                Add bill
                            </Link>
                        </Button>
                    </div>
                </div>

                <BillsSummaryCard summary={summary} currency={currency} />

                {bills.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="divide-y overflow-hidden rounded-xl border">
                        {bills.map((bill) => (
                            <BillRow
                                key={bill.id}
                                bill={bill}
                                category={categoriesByValue[bill.category]}
                                currency={currency}
                                month={summary.month}
                                onPay={setPendingPay}
                                onDelete={setPendingDelete}
                            />
                        ))}
                    </div>
                )}
            </div>

            <BillPaymentDialog
                bill={pendingPay}
                month={summary.month}
                currency={currency}
                onClose={() => setPendingPay(null)}
            />

            <BillAllocationDialog
                open={splitting}
                bills={bills}
                month={summary.month}
                currency={currency}
                onClose={() => setSplitting(false)}
            />

            <DeleteBillDialog
                bill={pendingDelete}
                onClose={() => setPendingDelete(null)}
            />
        </>
    );
}

/**
 * Bills are not filtered, so unlike the expenses list there is only one way to
 * arrive here empty: nothing has been set up yet.
 */
function EmptyState() {
    return (
        <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed p-12">
            <div className="flex max-w-sm flex-col items-center text-center">
                <ReceiptText
                    className="size-14 text-muted-foreground/60"
                    strokeWidth={1.5}
                />

                <p className="mt-4 font-medium">No bills yet</p>

                <p className="mt-1 text-sm text-muted-foreground">
                    Add the things you pay every month — rent, power, internet —
                    and mark them paid as they go out.
                </p>
            </div>
        </div>
    );
}

BillsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Bills',
            href: billsIndex(),
        },
    ],
};
