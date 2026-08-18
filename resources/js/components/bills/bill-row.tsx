import { Form, Link } from '@inertiajs/react';
import { Pencil, Trash2, Undo2, Wallet } from 'lucide-react';
import BillController from '@/actions/App/Http/Controllers/BillController';
import { DynamicIcon } from '@/components/dynamic-icon';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { edit as editBill } from '@/routes/bills';
import type { Bill, BillStatusValue, CategoryOption } from '@/types';

/**
 * A single row in the bills list: category avatar, name + due date, amount,
 * and the controls that matter — put money towards this month, or step the
 * last payment back.
 *
 * A month can be settled a bit at a time, so the row shows what has been paid
 * against what is left, and Undo removes one payment rather than the lot.
 * Everything here is a statement about the month being viewed, which is why
 * the month rides along with the request.
 */
export function BillRow({
    bill,
    category,
    currency,
    month,
    onPay,
    onDelete,
}: {
    bill: Bill;
    category: CategoryOption;
    currency: string;
    month: string;
    onPay: (bill: Bill) => void;
    onDelete: (bill: Bill) => void;
}) {
    const paid = bill.status === 'paid';

    return (
        <div className="group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/50">
            <span
                className="flex size-10 shrink-0 items-center justify-center rounded-full"
                style={{ backgroundColor: `${category.color}26` }}
                aria-hidden
            >
                <DynamicIcon
                    name={category.icon}
                    className="size-5"
                    style={{ color: category.color }}
                />
            </span>

            <div className="min-w-0 flex-1">
                <Link
                    href={editBill(bill.id, { query: { month } })}
                    className="block truncate font-medium hover:underline"
                >
                    {bill.name}
                </Link>

                <p className="flex items-center gap-2 truncate text-sm text-muted-foreground">
                    Due {formatDate(bill.due_on)}
                    <StatusBadge status={bill.status} />
                </p>

                {bill.paid_amount > 0 && bill.remaining > 0 && (
                    <p className="truncate text-sm text-muted-foreground tabular-nums">
                        {formatCurrency(bill.paid_amount, currency)} paid ·{' '}
                        {formatCurrency(bill.remaining, currency)} left
                    </p>
                )}
            </div>

            <span
                className={cn(
                    'shrink-0 font-semibold tabular-nums',
                    paid && 'text-muted-foreground',
                )}
            >
                {formatCurrency(bill.amount, currency)}
            </span>

            <div className="flex shrink-0 items-center gap-1">
                {!paid && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onPay(bill)}
                    >
                        <Wallet className="size-4" />
                        Pay
                    </Button>
                )}

                {bill.paid_amount > 0 && (
                    <Form
                        {...BillController.unpay.form(bill.id, {
                            query: { month },
                        })}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                disabled={processing}
                                className="text-muted-foreground"
                                title="Remove the last payment"
                            >
                                <Undo2 className="size-4" />
                                Undo
                            </Button>
                        )}
                    </Form>
                )}

                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="size-8 text-muted-foreground"
                >
                    <Link
                        href={editBill(bill.id, { query: { month } })}
                        aria-label={`Edit ${bill.name}`}
                    >
                        <Pencil className="size-4" />
                    </Link>
                </Button>

                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 text-muted-foreground hover:text-destructive"
                    onClick={() => onDelete(bill)}
                    aria-label={`Delete ${bill.name}`}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>
        </div>
    );
}

const BADGES: Record<BillStatusValue, { label: string; className: string }> = {
    partial: {
        label: 'Part paid',
        className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    },
    paid: {
        label: 'Paid',
        className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    },
    overdue: {
        label: 'Overdue',
        className: 'bg-destructive/15 text-destructive',
    },
    upcoming: {
        label: 'Upcoming',
        className: 'bg-muted text-muted-foreground',
    },
};

function StatusBadge({ status }: { status: BillStatusValue }) {
    const badge = BADGES[status];

    return (
        <span
            className={cn(
                'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                badge.className,
            )}
        >
            {badge.label}
        </span>
    );
}
