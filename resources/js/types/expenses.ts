/**
 * Wire types for the expenses feature. These mirror the shapes produced by
 * `ExpenseController::present()`, `ExpenseFilter::toArray()`, and
 * `MonthlySummary::toArray()` on the server — change one, change the other.
 */

export type ExpenseCategoryValue =
    | 'food'
    | 'transportation'
    | 'bills'
    | 'shopping'
    | 'health'
    | 'entertainment'
    | 'education'
    | 'other';

export type PaymentMethodValue =
    'cash' | 'card' | 'bank_transfer' | 'e_wallet' | 'other';

/** A category as sent by `ExpenseCategory::options()` — carries its own label, color, and icon. */
export type CategoryOption = {
    value: ExpenseCategoryValue;
    label: string;
    color: string;
    icon: string;
};

/** A payment method as sent by `PaymentMethod::options()`. */
export type PaymentMethodOption = {
    value: PaymentMethodValue;
    label: string;
    icon: string;
};

export type Expense = {
    id: number;
    title: string;
    category: ExpenseCategoryValue;
    amount: number;
    /** ISO date, `YYYY-MM-DD`. */
    date: string;
    notes: string | null;
    payment_method: PaymentMethodValue;
};

export type ExpenseFilters = {
    search: string | null;
    category: ExpenseCategoryValue | null;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
};

export type CategoryBreakdown = CategoryOption & {
    category: ExpenseCategoryValue;
    total: number;
    /** This category's share of the month's total, 0–1. */
    fraction: number;
};

export type MonthlySummary = {
    /** `YYYY-MM`. */
    month: string;
    month_label: string;
    previous_month: string;
    next_month: string;
    total: number;
    transaction_count: number;
    by_category: CategoryBreakdown[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
