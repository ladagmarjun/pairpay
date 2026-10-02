import { formatCurrency, formatDate } from '@/lib/format';
import type { BillBreakdown } from '@/types';

/**
 * The month being viewed, bill by bill: what each one owed, and how much of it
 * was settled.
 *
 * One month rather than a year. A bill costs the same every month, so twelve
 * columns of the same height said little; which bills are still open this
 * month is the question worth answering.
 *
 * Each bill is a track at its amount with the paid part filled in front of it.
 * Every track shares one scale, so a large bill reads as large beside a small
 * one, and a bill paid beyond what it owed overshoots its track rather than
 * being quietly clipped at full.
 */
export function BillPaymentChart({ bills }: { bills: BillBreakdown }) {
    const tracked = bills.bills.length > 0;
    const scaleMax = bills.peak > 0 ? bills.peak : 1;

    return (
        <figure className="rounded-xl border p-5">
            <figcaption className="mb-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <div>
                    <h2 className="font-medium">Bills</h2>
                    <p className="text-sm text-muted-foreground">
                        Due and paid in {bills.label}
                    </p>
                </div>

                {tracked && (
                    <p className="text-sm text-muted-foreground">
                        <span className="font-medium text-foreground tabular-nums">
                            {formatCurrency(bills.paid)}
                        </span>{' '}
                        paid of {formatCurrency(bills.due)}
                    </p>
                )}
            </figcaption>

            {tracked ? (
                <>
                    <ul className="mb-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <li className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-xs bg-chart-series" />
                            Paid
                        </li>
                        <li className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-xs bg-muted" />
                            Still due
                        </li>
                    </ul>

                    <ul className="flex flex-col gap-4">
                        {bills.bills.map((bill) => {
                            const due = (bill.due / scaleMax) * 100;
                            const paid = (bill.paid / scaleMax) * 100;
                            const outstanding = Math.max(
                                0,
                                bill.due - bill.paid,
                            );

                            return (
                                <li key={bill.id}>
                                    <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                        <span className="min-w-0 truncate">
                                            {bill.name}
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                Due {formatDate(bill.due_on)}
                                            </span>
                                        </span>

                                        <span className="shrink-0 text-muted-foreground tabular-nums">
                                            {outstanding > 0
                                                ? `${formatCurrency(outstanding)} left`
                                                : 'Paid'}
                                        </span>
                                    </div>

                                    <div
                                        role="img"
                                        aria-label={`${bill.name}: ${formatCurrency(bill.paid)} paid of ${formatCurrency(bill.due)} due`}
                                        className="relative h-3"
                                    >
                                        <span
                                            className="absolute inset-y-0 left-0 rounded-sm bg-muted"
                                            style={{
                                                width: `${Math.max(due, bill.due > 0 ? 1 : 0)}%`,
                                            }}
                                        />
                                        <span
                                            className="absolute inset-y-0 left-0 rounded-sm bg-chart-series"
                                            style={{
                                                width: `${Math.max(paid, bill.paid > 0 ? 1 : 0)}%`,
                                            }}
                                        />
                                    </div>
                                </li>
                            );
                        })}
                    </ul>

                    <details className="mt-6">
                        <summary className="cursor-pointer text-sm text-muted-foreground hover:text-foreground">
                            View as table
                        </summary>

                        <table className="mt-3 w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th
                                        scope="col"
                                        className="py-1 font-medium"
                                    >
                                        Bill
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-1 text-right font-medium"
                                    >
                                        Due
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-1 text-right font-medium"
                                    >
                                        Paid
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {bills.bills.map((bill) => (
                                    <tr
                                        key={bill.id}
                                        className="border-b last:border-0"
                                    >
                                        <th
                                            scope="row"
                                            className="py-1 font-normal"
                                        >
                                            {bill.name}
                                        </th>
                                        <td className="py-1 text-right tabular-nums">
                                            {formatCurrency(bill.due)}
                                        </td>
                                        <td className="py-1 text-right tabular-nums">
                                            {formatCurrency(bill.paid)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </details>
                </>
            ) : (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    No bills due in {bills.label}. Add one and this fills in.
                </p>
            )}
        </figure>
    );
}
