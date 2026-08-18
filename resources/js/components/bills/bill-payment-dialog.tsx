import { Form } from '@inertiajs/react';
import BillController from '@/actions/App/Http/Controllers/BillController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/format';
import type { Bill } from '@/types';

/**
 * Puts money towards one bill.
 *
 * The amount is pre-filled with what is still owed, so settling a month in
 * full is one click — but it is editable, because a month can be paid a bit at
 * a time. Pay ₱500 of ₱900 and the bill reads "part paid" with ₱400 left.
 */
export function BillPaymentDialog({
    bill,
    month,
    currency,
    onClose,
}: {
    bill: Bill | null;
    month: string;
    currency: string;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={bill !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {bill && (
                    <Form
                        {...BillController.pay.form(bill.id, {
                            query: { month },
                        })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>Pay {bill.name}</DialogTitle>

                                    <DialogDescription>
                                        {bill.paid_amount > 0
                                            ? `${formatCurrency(bill.paid_amount, currency)} paid so far · ${formatCurrency(bill.remaining, currency)} still owed.`
                                            : `${formatCurrency(bill.remaining, currency)} due this month.`}
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="grid gap-2 py-2">
                                    <Label htmlFor="amount">Amount</Label>

                                    <Input
                                        id="amount"
                                        name="amount"
                                        type="number"
                                        inputMode="decimal"
                                        step="0.01"
                                        min="0.01"
                                        max={bill.remaining}
                                        required
                                        autoFocus
                                        defaultValue={bill.remaining.toFixed(2)}
                                        disabled={processing}
                                    />

                                    <InputError message={errors.amount} />

                                    <p className="text-sm text-muted-foreground">
                                        Pay less than this and the rest stays
                                        owed on the month.
                                    </p>
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={onClose}
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>

                                    <Button type="submit" disabled={processing}>
                                        Record payment
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
