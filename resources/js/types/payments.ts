/**
 * Wire types for the pay-day screen, mirroring
 * `LumpSumPaymentController::payables()` on the server.
 */

/**
 * One thing that wants paying. Bills and loans are flattened into a single
 * shape so the list can order them by date without caring which is which.
 */
export type Payable = {
    type: 'bill' | 'loan';
    id: number;
    name: string;
    /** The category for a bill, or which installment for a loan. */
    detail: string;
    /** Used for ordering; only a real due date when `has_due_date`. */
    due_date: string;
    /**
     * False for a loan with no repayment plan — it is ordered by when it was
     * made, which is not a date anything falls due on.
     */
    has_due_date: boolean;
    /**
     * The only figure this screen pays against: a bill's remainder for the
     * month, a loan's current installment. Nothing here runs ahead.
     */
    due_now: number;
    /** The loan's whole balance, shown for context. Never allocated against. */
    outstanding: number;
};

export type PayMonth = {
    value: string;
    label: string;
    previous: string;
    next: string;
};
