import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import BillController from '@/actions/App/Http/Controllers/BillController';
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
import { formatCurrency } from '@/lib/format';
import type { Bill } from '@/types';

/**
 * Splits one lump sum across several bills.
 *
 * The split shown here is computed the same way the server does it — oldest
 * due first, each bill taking what it still needs until the money runs out —
 * so what you confirm is what gets written. The server is still the authority;
 * this is a preview, not the decision.
 */
export function BillAllocationDialog({
    open,
    bills,
    month,
    currency,
    onClose,
}: {
    open: boolean;
    bills: Bill[];
    month: string;
    currency: string;
    onClose: () => void;
}) {
    const unpaid = useMemo(
        () => bills.filter((bill) => bill.remaining > 0),
        [bills],
    );

    const [amount, setAmount] = useState('');
    const [picked, setPicked] = useState<number[]>(() =>
        unpaid.map((bill) => bill.id),
    );

    const split = useMemo(() => {
        const total = Number(amount) || 0;

        // Oldest due first, so the most pressing bill clears before anything
        // is put towards the next one.
        const ordered = unpaid
            .filter((bill) => picked.includes(bill.id))
            .slice()
            .sort(
                (a, b) =>
                    a.due_on.localeCompare(b.due_on) ||
                    a.name.localeCompare(b.name),
            );

        // Each row is worked out from what the rows before it already took,
        // rather than a running total mutated along the way.
        return ordered.reduce<{ bill: Bill; paying: number }[]>(
            (rows, bill) => {
                const spent = rows.reduce((sum, row) => sum + row.paying, 0);
                const paying =
                    Math.round(
                        Math.min(bill.remaining, Math.max(total - spent, 0)) *
                            100,
                    ) / 100;

                return [...rows, { bill, paying }];
            },
            [],
        );
    }, [amount, picked, unpaid]);

    const owed = split.reduce((total, row) => total + row.bill.remaining, 0);
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
                    {...BillController.allocate.form({ query: { month } })}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Pay a lump sum</DialogTitle>

                                <DialogDescription>
                                    Enter what you have. It goes to the bills
                                    due soonest first, and at most one ends up
                                    part paid.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4 py-2">
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
                                    <Label>Bills</Label>

                                    <div className="divide-y overflow-hidden rounded-xl border">
                                        {split.length === 0 && (
                                            <p className="p-4 text-sm text-muted-foreground">
                                                Pick at least one bill.
                                            </p>
                                        )}

                                        {unpaid.map((bill) => {
                                            const row = split.find(
                                                (entry) =>
                                                    entry.bill.id === bill.id,
                                            );

                                            return (
                                                <label
                                                    key={bill.id}
                                                    className="flex items-center gap-3 px-4 py-3"
                                                >
                                                    <Checkbox
                                                        checked={picked.includes(
                                                            bill.id,
                                                        )}
                                                        onCheckedChange={() =>
                                                            toggle(bill.id)
                                                        }
                                                        disabled={processing}
                                                    />

                                                    {picked.includes(
                                                        bill.id,
                                                    ) && (
                                                        <input
                                                            type="hidden"
                                                            name="bills[]"
                                                            value={bill.id}
                                                        />
                                                    )}

                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate font-medium">
                                                            {bill.name}
                                                        </span>

                                                        <span className="block text-sm text-muted-foreground">
                                                            {formatCurrency(
                                                                bill.remaining,
                                                                currency,
                                                            )}{' '}
                                                            owed
                                                        </span>
                                                    </span>

                                                    {row && row.paying > 0 && (
                                                        <span className="shrink-0 text-right text-sm font-medium tabular-nums">
                                                            {formatCurrency(
                                                                row.paying,
                                                                currency,
                                                            )}
                                                            {row.paying <
                                                                bill.remaining && (
                                                                <span className="block text-xs font-normal text-muted-foreground">
                                                                    part
                                                                </span>
                                                            )}
                                                        </span>
                                                    )}
                                                </label>
                                            );
                                        })}
                                    </div>

                                    <InputError message={errors.bills} />

                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {formatCurrency(covers, currency)} of{' '}
                                        {formatCurrency(owed, currency)} owed
                                        {over &&
                                            ' — that is more than these bills need.'}
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
