/**
 * Currency and date formatting. All money in PairPay is stored as a number in
 * the user's currency; formatting is done here so we never scatter
 * `Intl.NumberFormat` calls through the components.
 */

const LOCALE = 'en-US';

export function formatCurrency(amount: number, currency = 'PHP'): string {
    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency,
    }).format(amount);
}

/** Compact currency for dashboards, e.g. `₱1.2K`. */
export function formatCurrencyCompact(
    amount: number,
    currency = 'PHP',
): string {
    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency,
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(amount);
}

/** `2026-08-03` → `Aug 3, 2026`. */
export function formatDate(date: string): string {
    return new Intl.DateTimeFormat(LOCALE, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(parseIsoDate(date));
}

/** `2026-08` → `August 2026`. */
export function formatMonthYear(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);

    return new Intl.DateTimeFormat(LOCALE, {
        year: 'numeric',
        month: 'long',
    }).format(new Date(year, monthNumber - 1, 1));
}

/** Today as `YYYY-MM-DD`, in the browser's timezone — the date input's default. */
export function todayIso(): string {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60_000;

    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

/**
 * Parse a `YYYY-MM-DD` string as a *local* date. `new Date('2026-08-03')`
 * parses as UTC midnight, which renders as the previous day in any timezone
 * behind UTC — this avoids that off-by-one.
 */
function parseIsoDate(date: string): Date {
    const [year, month, day] = date.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day);
}
