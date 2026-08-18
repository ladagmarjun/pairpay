import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import LoanPaymentController from '@/actions/App/Http/Controllers/LoanPaymentController';
import { DynamicIcon } from '@/components/dynamic-icon';
import { DeleteLoanDialog } from '@/components/loans/delete-loan-dialog';
import { LoanPaymentForm } from '@/components/loans/loan-payment-form';
import { LoanSchedule } from '@/components/loans/loan-schedule';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { Button } from '@/components/ui/button';
import { useCurrency } from '@/hooks/use-currency';
import { optionMap } from '@/lib/expense-meta';
import { formatCurrency, formatDate } from '@/lib/format';
import { edit as editLoan, index as loansIndex } from '@/routes/loans';
import type {
    DirectionOption,
    Loan,
    LoanInstallment,
    LoanPayment,
} from '@/types';

export default function ShowLoan({
    loan,
    payments,
    installments,
    nextInstallment,
    directions,
}: {
    loan: Loan;
    payments: LoanPayment[];
    installments: LoanInstallment[];
    nextInstallment: LoanInstallment | null;
    directions: DirectionOption[];
}) {
    const currency = useCurrency();
    const direction = optionMap(directions)[loan.direction];
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const progress =
        loan.principal > 0 ? Math.min(loan.paid / loan.principal, 1) : 0;

    return (
        <>
            <Head title={loan.counterparty} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <span
                            className="flex size-12 shrink-0 items-center justify-center rounded-full"
                            style={{ backgroundColor: `${direction.color}26` }}
                            aria-hidden
                        >
                            <DynamicIcon
                                name={direction.icon}
                                className="size-6"
                                style={{ color: direction.color }}
                            />
                        </span>

                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">
                                {loan.counterparty}
                            </h1>

                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                {direction.description} ·{' '}
                                {formatDate(loan.date)}
                                <LoanStatusBadge status={loan.status} />
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={editLoan(loan.id)}>
                                <Pencil className="size-4" />
                                Edit
                            </Link>
                        </Button>

                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-muted-foreground hover:text-destructive"
                            onClick={() => setConfirmingDelete(true)}
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                <div className="rounded-xl bg-primary-container p-5 text-primary-container-foreground">
                    <h2 className="font-medium">
                        {loan.status === 'settled'
                            ? 'Settled in full'
                            : 'Left to pay'}
                    </h2>

                    <p className="mt-1 text-3xl font-bold tabular-nums">
                        {formatCurrency(loan.outstanding, currency)}
                    </p>

                    <span className="mt-4 flex h-2 overflow-hidden rounded-full bg-current/15">
                        <span
                            className="block h-full rounded-full bg-current"
                            style={{ width: `${progress * 100}%` }}
                        />
                    </span>

                    <p className="mt-2 text-sm opacity-80">
                        {formatCurrency(loan.paid, currency)} paid of{' '}
                        {formatCurrency(loan.principal, currency)}
                        {loan.term_months !== null &&
                            loan.installment_amount !== null &&
                            ` · ${formatCurrency(loan.installment_amount, currency)} × ${loan.term_months} months`}
                    </p>
                </div>

                {loan.notes && (
                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                        {loan.notes}
                    </p>
                )}

                {loan.status !== 'settled' && (
                    <LoanPaymentForm
                        loan={loan}
                        nextInstallment={nextInstallment}
                        currency={currency}
                    />
                )}

                {installments.length > 0 && (
                    <LoanSchedule
                        installments={installments}
                        currency={currency}
                    />
                )}

                <div className="flex flex-col gap-2">
                    <h2 className="font-medium">Payments</h2>

                    {payments.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                            Nothing paid yet.
                        </p>
                    ) : (
                        <div className="divide-y overflow-hidden rounded-xl border">
                            {payments.map((payment) => (
                                <PaymentRow
                                    key={payment.id}
                                    loan={loan}
                                    payment={payment}
                                    currency={currency}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <DeleteLoanDialog
                loan={confirmingDelete ? loan : null}
                onClose={() => setConfirmingDelete(false)}
            />
        </>
    );
}

/**
 * Removing a payment is the only correction available — the balance is derived
 * from these rows, so there is nothing else to edit.
 */
function PaymentRow({
    loan,
    payment,
    currency,
}: {
    loan: Loan;
    payment: LoanPayment;
    currency: string;
}) {
    return (
        <div className="flex items-center gap-4 px-4 py-3">
            <div className="min-w-0 flex-1">
                <p className="font-medium tabular-nums">
                    {formatCurrency(payment.amount, currency)}
                </p>

                <p className="truncate text-sm text-muted-foreground">
                    {formatDate(payment.date)}
                    {payment.notes ? ` · ${payment.notes}` : ''}
                </p>
            </div>

            <Form
                {...LoanPaymentController.destroy.form([loan.id, payment.id])}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="ghost"
                        size="icon"
                        className="size-8 text-muted-foreground hover:text-destructive"
                        disabled={processing}
                        aria-label={`Remove payment of ${payment.amount}`}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                )}
            </Form>
        </div>
    );
}

ShowLoan.layout = {
    breadcrumbs: [
        { title: 'Loans', href: loansIndex() },
        { title: 'Loan', href: loansIndex() },
    ],
};
