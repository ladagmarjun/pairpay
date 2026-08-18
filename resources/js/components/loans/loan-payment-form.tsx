import { Form } from '@inertiajs/react';
import LoanPaymentController from '@/actions/App/Http/Controllers/LoanPaymentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency, formatDate, todayIso } from '@/lib/format';
import type { Loan, LoanInstallment } from '@/types';

/**
 * Records a payment against a loan.
 *
 * On a loan with a plan the amount and date are pre-filled from the next month
 * owed, so paying an installment is one click — but both stay editable, since
 * what was actually paid is the thing being recorded, not what was due.
 *
 * The amount is capped at what is left on the loan, here and on the server.
 * Overpaying is a typo, and the balance has nowhere to put the difference.
 */
export function LoanPaymentForm({
    loan,
    nextInstallment,
    currency,
}: {
    loan: Loan;
    nextInstallment: LoanInstallment | null;
    currency: string;
}) {
    return (
        <Form
            {...LoanPaymentController.store.form(loan.id)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="flex flex-wrap items-end gap-3 rounded-xl border p-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid min-w-40 flex-1 gap-2">
                        <Label htmlFor="amount">Amount</Label>

                        <Input
                            id="amount"
                            name="amount"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0.01"
                            max={loan.outstanding}
                            required
                            defaultValue={nextInstallment?.outstanding.toFixed(
                                2,
                            )}
                            placeholder="0.00"
                            disabled={processing}
                        />

                        <InputError message={errors.amount} />
                    </div>

                    <div className="grid min-w-40 flex-1 gap-2">
                        <Label htmlFor="date">Date</Label>

                        <Input
                            id="date"
                            name="date"
                            type="date"
                            required
                            defaultValue={todayIso()}
                            disabled={processing}
                        />

                        <InputError message={errors.date} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        Record payment
                    </Button>

                    <p className="w-full text-sm text-muted-foreground">
                        {nextInstallment
                            ? `Next due ${formatDate(nextInstallment.due_date)} · ${formatCurrency(nextInstallment.outstanding, currency)}. ${formatCurrency(loan.outstanding, currency)} left in total.`
                            : `${formatCurrency(loan.outstanding, currency)} left to pay.`}
                    </p>
                </>
            )}
        </Form>
    );
}
