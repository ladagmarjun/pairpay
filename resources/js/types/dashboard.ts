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
