import { Form, Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Landmark,
    ReceiptText,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import LumpSumPaymentController from '@/actions/App/Http/Controllers/LumpSumPaymentController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrency } from '@/hooks/use-currency';
import { formatCurrency, formatDate, todayIso } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create as payDay } from '@/routes/payments';
import type { Payable, PayMonth } from '@/types';

/**
 * Pay day: one sum against everything that wants paying.
 *
 * Bills and loans sit in one list ordered by date, because when the money has
 * to be split between them the date is the only thing they have in common.
 *
 * Every figure on this screen is *what is due now* — a bill's remainder for the
 * month, a loan's current installment. Nothing runs ahead, which is what keeps
 * the total meaningful: a ₱20,000 loan with ₱2,000 due this month contributes
 * ₱2,000 here, not ₱20,000.
 */
export default function LumpSum({
    payables,
    month,
}: {
    payables: Payable[];
    month: PayMonth;
}) {
    const currency = useCurrency();

    const [amount, setAmount] = useState('');
    const [picked, setPicked] = useState<string[]>(() => payables.map(keyOf));

    const split = useMemo(
        () =>
            planSplit(
                payables.filter((payable) => picked.includes(keyOf(payable))),
                Number(amount) || 0,
            ),
        [amount, picked, payables],
    );

    const due = split.reduce((total, row) => total + row.payable.due_now, 0);
    const covers = split.reduce((total, row) => total + row.paying, 0);
    const over = (Number(amount) || 0) > due;

    function toggle(key: string) {
        setPicked((current) =>
            current.includes(key)
                ? current.filter((value) => value !== key)
                : [...current, key],
        );
    }

    return (
        <>
            <Head title="Pay" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Pay"
                        description="Everything due, in one payment."
                    />

                    {/* The month decides every bill's due date, so it is shown
                        and steerable here rather than assumed. */}
                    <div className="flex items-center gap-1">
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="size-8"
                        >
                            <Link
                                href={payDay({
                                    query: { month: month.previous },
                                })}
                                aria-label="Previous month"
                            >
                                <ChevronLeft className="size-5" />
                            </Link>
                        </Button>

                        <span className="min-w-32 text-center font-medium">
                            {month.label}
                        </span>

                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="size-8"
                        >
                            <Link
                                href={payDay({ query: { month: month.next } })}
                                aria-label="Next month"
                            >
                                <ChevronRight className="size-5" />
                            </Link>
                        </Button>
                    </div>
                </div>

                {payables.length === 0 ? (
                    <EmptyState month={month.label} />
                ) : (
                    <Form
                        {...LumpSumPaymentController.store.form()}
                        className="flex max-w-3xl flex-col gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="month"
                                    value={month.value}
                                />

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="amount">
                                            Amount to pay
                                        </Label>

                                        <Input
                                            id="amount"
                                            name="amount"
                                            type="number"
                                            inputMode="decimal"
                                            step="0.01"
                                            min="0.01"
                                            max={due}
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
                                </div>

                                <div className="flex flex-col gap-2">
                                    <div className="flex items-baseline justify-between gap-4">
                                        <Label>What it covers</Label>

                                        <button
                                            type="button"
                                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                                            onClick={() =>
                                                setAmount(due.toFixed(2))
                                            }
                                            disabled={processing || due <= 0}
                                        >
                                            Pay everything due (
                                            {formatCurrency(due, currency)})
                                        </button>
                                    </div>

                                    <div className="divide-y overflow-hidden rounded-xl border">
                                        {payables.map((payable) => {
                                            const key = keyOf(payable);
                                            const row = split.find(
                                                (entry) =>
                                                    keyOf(entry.payable) ===
                                                    key,
                                            );
                                            const on = picked.includes(key);

                                            return (
                                                <label
                                                    key={key}
                                                    className="flex items-center gap-3 px-4 py-3"
                                                >
                                                    <Checkbox
                                                        checked={on}
                                                        onCheckedChange={() =>
                                                            toggle(key)
                                                        }
                                                        disabled={processing}
                                                    />

                                                    {on && (
                                                        <input
                                                            type="hidden"
                                                            name={
                                                                payable.type ===
                                                                'bill'
                                                                    ? 'bills[]'
                                                                    : 'loans[]'
                                                            }
                                                            value={payable.id}
                                                        />
                                                    )}

                                                    {payable.type === 'bill' ? (
                                                        <ReceiptText className="size-4 shrink-0 text-muted-foreground" />
                                                    ) : (
                                                        <Landmark className="size-4 shrink-0 text-muted-foreground" />
                                                    )}

                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate font-medium">
                                                            {payable.name}
                                                        </span>

                                                        <span className="block truncate text-sm text-muted-foreground">
                                                            {payable.detail}
                                                            {payable.has_due_date
                                                                ? ` · due ${formatDate(payable.due_date)}`
                                                                : ' · no fixed date'}
                                                            {payable.type ===
                                                                'loan' &&
                                                                ` · ${formatCurrency(payable.outstanding, currency)} balance`}
                                                        </span>
                                                    </span>

                                                    <span className="shrink-0 text-right tabular-nums">
                                                        <span className="block text-sm font-medium">
                                                            {formatCurrency(
                                                                row?.paying ??
                                                                    0,
                                                                currency,
                                                            )}
                                                        </span>

                                                        <span className="block text-xs text-muted-foreground">
                                                            of{' '}
                                                            {formatCurrency(
                                                                payable.due_now,
                                                                currency,
                                                            )}{' '}
                                                            due
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>

                                    <InputError message={errors.bills} />
                                    <InputError message={errors.loans} />

                                    <p
                                        className={cn(
                                            'text-sm tabular-nums',
                                            over
                                                ? 'text-destructive'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {formatCurrency(covers, currency)} of{' '}
                                        {formatCurrency(due, currency)} due
                                        {over &&
                                            ' — that is more than these have due now.'}
                                    </p>
                                </div>

                                <div>
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || covers <= 0 || over
                                        }
                                    >
                                        Pay {formatCurrency(covers, currency)}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

function EmptyState({ month }: { month: string }) {
    return (
        <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed p-12">
            <div className="flex max-w-sm flex-col items-center text-center">
                <CheckCircle2
                    className="size-14 text-muted-foreground/60"
                    strokeWidth={1.5}
                />

                <p className="mt-4 font-medium">Nothing due in {month}</p>

                <p className="mt-1 text-sm text-muted-foreground">
                    Every bill for this month is settled and no loan has an
                    installment waiting.
                </p>
            </div>
        </div>
    );
}

function keyOf(payable: Payable): string {
    return `${payable.type}:${payable.id}`;
}

/**
 * The same single pass the server makes, kept as a plain function so the
 * preview and `AllocateLumpSum` can never disagree: soonest due first, each
 * thing taking what it owes now and no more.
 */
function planSplit(
    payables: Payable[],
    total: number,
): { payable: Payable; paying: number }[] {
    const ordered = payables
        .slice()
        .sort(
            (a, b) =>
                a.due_date.localeCompare(b.due_date) ||
                a.name.localeCompare(b.name),
        );

    const paying = new Map<string, number>();
    let left = total;

    for (const payable of ordered) {
        if (left <= 0) {
            break;
        }

        const take = Math.min(payable.due_now, left);

        if (take <= 0) {
            continue;
        }

        paying.set(keyOf(payable), round(take));
        left = round(left - take);
    }

    return ordered.map((payable) => ({
        payable,
        paying: paying.get(keyOf(payable)) ?? 0,
    }));
}

function round(value: number): number {
    return Math.round(value * 100) / 100;
}

LumpSum.layout = {
    breadcrumbs: [{ title: 'Pay', href: payDay() }],
};
