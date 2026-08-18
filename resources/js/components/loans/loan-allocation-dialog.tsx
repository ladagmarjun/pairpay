import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import LoanController from '@/actions/App/Http/Controllers/LoanController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatCurrency, todayIso } from '@/lib/format';
import type { Loan } from '@/types';

/**
 * Splits one lump sum across the loans you owe.
 *
 * The preview follows the server exactly: most pressing first, covering what
 * each loan needs *now* before anything goes against the balances. Only
 * borrowed loans appear — paying a lump sum is money going out, and a
 * repayment coming back on a loan you made is recorded on that loan.
 */
export function LoanAllocationDialog({
    open,
    loans,
    currency,
    onClose,
}: {
    open: boolean;
    loans: Loan[];
    currency: string;
    onClose: () => void;
}) {
    const owing = useMemo(
        () =>
            loans.filter(
                (loan) => loan.direction === 'borrowed' && loan.outstanding > 0,
            ),
        [loans],
    );

    const [amount, setAmount] = useState('');
    const [picked, setPicked] = useState<number[]>(() =>
        owing.map((loan) => loan.id),
    );

    const split = useMemo(
        () =>
            planSplit(
                owing.filter((loan) => picked.includes(loan.id)),
                Number(amount) || 0,
            ),
        [amount, picked, owing],
    );

    const owed = split.reduce((total, row) => total + row.loan.outstanding, 0);
    const covers = split.reduce((total, row) => total + row.paying, 0);
    const over = (Number(amount) || 0) > owed;

    function toggle(id: number) {
        setPicked((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <Form
                    {...LoanController.allocate.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Pay a lump sum</DialogTitle>

                                <DialogDescription>
                                    Enter what you have. It covers what each
                                    loan owes right now, most pressing first,
                                    then goes against the balances.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4 py-2">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="allocation-amount">
                                            Amount to pay
                                        </Label>

                                        <Input
                                            id="allocation-amount"
                                            name="amount"
                                            type="number"
                                            inputMode="decimal"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            autoFocus
                                            value={amount}
                                            onChange={(event) =>
                                                setAmount(event.target.value)
                                            }
                                            placeholder="0.00"
                                            disabled={processing}
                                        />

                                        <InputError message={errors.amount} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="allocation-date">
                                            Date
                                        </Label>

                                        <Input
                                            id="allocation-date"
                                            name="date"
                                            type="date"
                                            required
                                            defaultValue={todayIso()}
                                            disabled={processing}
                                        />

                                        <InputError message={errors.date} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label>Loans you owe</Label>

                                    <div className="divide-y overflow-hidden rounded-xl border">
                                        {owing.length === 0 && (
                                            <p className="p-4 text-sm text-muted-foreground">
                                                Nothing outstanding to pay.
                                            </p>
                                        )}

                                        {owing.map((loan) => {
                                            const row = split.find(
                                                (entry) =>
                                                    entry.loan.id === loan.id,
                                            );

                                            return (
                                                <label
                                                    key={loan.id}
                                                    className="flex items-center gap-3 px-4 py-3"
                                                >
                                                    <Checkbox
                                                        checked={picked.includes(
                                                            loan.id,
                                                        )}
                                                        onCheckedChange={() =>
                                                            toggle(loan.id)
                                                        }
                                                        disabled={processing}
                                                    />

                                                    {picked.includes(
                                                        loan.id,
                                                    ) && (
                                                        <input
                                                            type="hidden"
                                                            name="loans[]"
                                                            value={loan.id}
                                                        />
                                                    )}

                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate font-medium">
                                                            {loan.counterparty}
                                                        </span>

                                                        <span className="block text-sm text-muted-foreground">
                                                            {formatCurrency(
                                                                loan.due_now,
                                                                currency,
                                                            )}{' '}
                                                            due now ·{' '}
                                                            {formatCurrency(
                                                                loan.outstanding,
                                                                currency,
                                                            )}{' '}
                                                            in total
                                                        </span>
                                                    </span>

                                                    {row && row.paying > 0 && (
                                                        <span className="shrink-0 text-right text-sm font-medium tabular-nums">
                                                            {formatCurrency(
                                                                row.paying,
                                                                currency,
                                                            )}
                                                        </span>
                                                    )}
                                                </label>
                                            );
                                        })}
                                    </div>

                                    <InputError message={errors.loans} />

                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {formatCurrency(covers, currency)} of{' '}
                                        {formatCurrency(owed, currency)} owed
                                        {over &&
                                            ' — that is more than these loans need.'}
                                    </p>
                                </div>
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

                                <Button
                                    type="submit"
                                    disabled={processing || covers <= 0 || over}
                                >
                                    Pay {formatCurrency(covers, currency)}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The same two passes the server makes, kept as a plain function so the
 * preview and `AllocateLoanPayments` can never disagree: cover what each loan
 * needs now, most pressing first, then put anything left against the balances
 * in the same order.
 */
function planSplit(
    loans: Loan[],
    total: number,
): { loan: Loan; paying: number }[] {
    const ordered = loans
        .slice()
        .sort((a, b) =>
            (a.next_due_date ?? a.date).localeCompare(
                b.next_due_date ?? b.date,
            ),
        );

    const paying = new Map<number, number>();
    let left = total;

    for (const loan of ordered) {
        if (left <= 0) {
            break;
        }

        const take = Math.min(loan.due_now, left);

        if (take <= 0) {
            continue;
        }

        paying.set(loan.id, round(take));
        left = round(left - take);
    }

    for (const loan of ordered) {
        if (left <= 0) {
            break;
        }

        const already = paying.get(loan.id) ?? 0;
        const take = Math.min(loan.outstanding - already, left);

        if (take <= 0) {
            continue;
        }

        paying.set(loan.id, round(already + take));
        left = round(left - take);
    }

    return ordered.map((loan) => ({
        loan,
        paying: paying.get(loan.id) ?? 0,
    }));
}

function round(value: number): number {
    return Math.round(value * 100) / 100;
}
