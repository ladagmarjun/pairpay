import type { LucideIcon } from 'lucide-react';
import {
    Banknote,
    Car,
    ChartColumn,
    CreditCard,
    Ellipsis,
    Film,
    GraduationCap,
    Heart,
    Landmark,
    ReceiptText,
    Shapes,
    ShoppingBag,
    Users,
    Utensils,
    Wallet,
} from 'lucide-react';
import type { CategoryOption, PaymentMethodOption } from '@/types';

/**
 * The server sends icons by name (`ExpenseCategory::icon()`), so the enum stays
 * the single source of truth for a category's label, color, and icon. This maps
 * those names onto the actual components.
 */
const ICONS: Record<string, LucideIcon> = {
    banknote: Banknote,
    car: Car,
    'chart-column': ChartColumn,
    'credit-card': CreditCard,
    ellipsis: Ellipsis,
    film: Film,
    'graduation-cap': GraduationCap,
    heart: Heart,
    landmark: Landmark,
    'receipt-text': ReceiptText,
    shapes: Shapes,
    'shopping-bag': ShoppingBag,
    users: Users,
    utensils: Utensils,
    wallet: Wallet,
};

export function iconFor(name: string): LucideIcon {
    return ICONS[name] ?? Shapes;
}

/**
 * Turn an options array into a `value → option` lookup, so a row holding only
 * `category: 'food'` can still render the right label, color, and icon.
 */
export function optionMap<T extends CategoryOption | PaymentMethodOption>(
    options: T[],
): Record<string, T> {
    return Object.fromEntries(options.map((option) => [option.value, option]));
}
