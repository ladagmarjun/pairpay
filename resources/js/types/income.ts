/**
 * Wire types for the income feature. These mirror the shapes produced by
 * `IncomeController::present()`, `IncomeFilter::toArray()`, and
 * `MonthlyIncomeSummary::toArray()` on the server — change one, change the
 * other.
 *
 * Deliberately its own set rather than reusing the expense types: the two
 * ledgers carry different category lists, and one shared type would let a
 * `'food'` income through the compiler. The payment methods are shared,
 * though: cash is cash whichever way it is moving.
 */

import type { PaymentMethodValue } from './expenses';

export type IncomeCategoryValue =
    | 'salary'
    | 'freelance'
    | 'business'
    | 'investment'
    | 'gift'
    | 'refund'
    | 'other';

/** A category as sent by `IncomeCategory::options()` — carries its own label, color, and icon. */
export type IncomeCategoryOption = {
    value: IncomeCategoryValue;
    label: string;
    color: string;
    icon: string;
};

export type Income = {
    id: number;
    /** Who it came from — the employer, the client, the platform. */
    source: string;
    category: IncomeCategoryValue;
    amount: number;
    /** ISO date, `YYYY-MM-DD`. */
    date: string;
    notes: string | null;
    payment_method: PaymentMethodValue;
};

export type IncomeFilters = {
    search: string | null;
    category: IncomeCategoryValue | null;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
};

export type IncomeBreakdown = IncomeCategoryOption & {
    category: IncomeCategoryValue;
    total: number;
    /** This category's share of the month's income, 0–1. */
    fraction: number;
};

export type MonthlyIncomeSummary = {
    /** `YYYY-MM`. */
    month: string;
    month_label: string;
    previous_month: string;
    next_month: string;
    total: number;
    entry_count: number;
    by_category: IncomeBreakdown[];
};
