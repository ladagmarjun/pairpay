/**
 * Wire types for the loans feature. These mirror the shapes produced by
 * `LoanController::present()`, `::presentPayment()` and `::summarize()` on the
 * server — change one, change the other.
 */

export type LoanDirectionValue = 'borrowed' | 'lent';

/** Derived on the server from the payments, so it is never stale. */
export type LoanStatusValue = 'open' | 'partial' | 'settled';

/** Where one month of a repayment plan stands. Also derived. */
export type InstallmentStatusValue =
    'paid' | 'partial' | 'overdue' | 'upcoming';

/** A direction as sent by `LoanDirection::options()`. */
export type DirectionOption = {
    value: LoanDirectionValue;
    label: string;
    description: string;
    /** What to call the other party — "Lender" or "Borrower". */
    counterparty_label: string;
    color: string;
    icon: string;
};

export type Loan = {
    id: number;
    counterparty: string;
    direction: LoanDirectionValue;
    principal: number;
    paid: number;
    /** Principal minus payments, floored at zero. */
    outstanding: number;
    /**
     * What the loan wants paying right now — the next month on a plan, the
     * whole balance without one. The lump-sum split works from this.
     */
    due_now: number;
    status: LoanStatusValue;
    /** ISO date, `YYYY-MM-DD`. */
    date: string;
    /** Length of the repayment plan, or null for an informal loan. */
    term_months: number | null;
    first_due_date: string | null;
    /** What one month costs; the last one differs by the rounding. */
    installment_amount: number | null;
    /** The next month with anything left on it, null once fully paid. */
    next_due_date: string | null;
    notes: string | null;
};

/**
 * One month of the plan. Worked out on the server from the principal, the
 * term and the payments — there is no installments table.
 */
export type LoanInstallment = {
    number: number;
    due_date: string;
    amount: number;
    paid: number;
    outstanding: number;
    status: InstallmentStatusValue;
};

export type LoanPayment = {
    id: number;
    amount: number;
    date: string;
    notes: string | null;
};

/**
 * The two figures are never netted off against each other — owing and being
 * owed the same amount is not the same position as owing nothing.
 */
export type LoansSummary = {
    owed_by_you: number;
    owed_to_you: number;
    open_count: number;
    settled_count: number;
};
