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
