/**
 * Wire types for the bills feature. These mirror the shapes produced by
 * `BillController::present()` and `BillController::summarize()` on the server —
 * change one, change the other.
 */

import type { ExpenseCategoryValue, PaymentMethodValue } from './expenses';

/**
 * Where a bill stands in the month being viewed. Derived on the server from
 * whether an expense points back at the bill, so it is never stale.
 */
export type BillStatusValue = 'paid' | 'partial' | 'overdue' | 'upcoming';

export type Bill = {
    id: number;
    name: string;
    amount: number;
    /**
     * When it falls due in the month being viewed, `YYYY-MM-DD`. The only date
     * the front end ever sees: the stored anchor's month and year mean
     * nothing, since only its day recurs.
     */
    due_on: string;
    category: ExpenseCategoryValue;
    payment_method: PaymentMethodValue;
    notes: string | null;
    status: BillStatusValue;
    /** Everything paid towards this month, across however many payments. */
    paid_amount: number;
    /** What is still owed this month, floored at zero. */
    remaining: number;
};

export type BillsSummary = {
    /** `YYYY-MM`. */
    month: string;
    month_label: string;
    previous_month: string;
    next_month: string;
    count: number;
    paid_count: number;
    total: number;
    paid_total: number;
    remaining: number;
};
