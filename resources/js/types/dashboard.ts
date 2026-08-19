/**
 * Wire types for the dashboard. These mirror `MonthlyTrend::toArray()` on the
 * server — change one, change the other.
 */

/** One month's total spend. */
export type TrendPoint = {
    /** `YYYY-MM`. */
    month: string;
    /** `August 2026` — what the tooltip and the table view say. */
    label: string;
    /** `Aug` — short enough that twelve of them fit on the axis. */
    short_label: string;
    total: number;
};

export type MonthlyTrend = {
    /** Oldest first, one entry per month in the window, gaps included as zero. */
    points: TrendPoint[];
    total: number;
    average: number;
    /** The largest single month, which is what the chart scales to. */
    peak: number;
};

/** One month of fixed bills: what fell due, and what was paid against it. */
export type BillTrendPoint = {
    /** `YYYY-MM`. */
    month: string;
    label: string;
    short_label: string;
    /** Every bill that had started by this month, at what it costs today. */
    due: number;
    /** What the bill payments in this month actually came to. */
    paid: number;
};

/** Mirrors `BillTrend::toArray()`. */
export type BillTrend = {
    /** Oldest first, one entry per month in the window. */
    points: BillTrendPoint[];
    due: number;
    paid: number;
    /** The taller of the two series, which is what both scale to. */
    peak: number;
};

/** What was still owed as one month closed, in both directions. */
export type LoanTrendPoint = {
    /** `YYYY-MM`. */
    month: string;
    label: string;
    short_label: string;
    /** Outstanding on what you borrowed. */
    borrowed: number;
    /** Outstanding on what you lent. */
    lent: number;
};

/**
 * Mirrors `LoanTrend::toArray()`. A balance rather than a flow, so the two
 * headline figures are where the window ends — not a sum across it — and the
 * directions are never netted off against each other.
 */
export type LoanTrend = {
    /** Oldest first, one entry per month in the window. */
    points: LoanTrendPoint[];
    owed_by_you: number;
    owed_to_you: number;
    /** The tallest bar of either direction, which both scale to. */
    peak: number;
};

/** One month of the two ledgers side by side. */
export type CashflowPoint = {
    /** `YYYY-MM`. */
    month: string;
    label: string;
    short_label: string;
    /** Everything received that month. */
    income: number;
    /** Everything spent, bill payments included — they are expenses. */
    spent: number;
};

/**
 * Mirrors `CashflowTrend::toArray()`. Loan movements are deliberately absent
 * from both sides; see the class.
 */
export type CashflowTrend = {
    /** Oldest first, one entry per month in the window. */
    points: CashflowPoint[];
    income: number;
    spent: number;
    /** Income less spending. Negative when the year has spent more than it earned. */
    surplus: number;
    /** The tallest bar of either side, which both scale to. */
    peak: number;
};
