import type { LucideIcon } from 'lucide-react';
import {
    Banknote,
    Car,
    ChartColumn,
    CreditCard,
    Ellipsis,
    Film,
    GraduationCap,
    HandCoins,
    Heart,
    Landmark,
    PiggyBank,
    ReceiptText,
    Shapes,
    ShoppingBag,
    Users,
    Utensils,
    Wallet,
} from 'lucide-react';
import type {
    CategoryOption,
    DirectionOption,
    PaymentMethodOption,
} from '@/types';

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
    'hand-coins': HandCoins,
    heart: Heart,
    landmark: Landmark,
    'piggy-bank': PiggyBank,
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
export function optionMap<
    T extends CategoryOption | PaymentMethodOption | DirectionOption,
>(options: T[]): Record<string, T> {
    return Object.fromEntries(options.map((option) => [option.value, option]));
}
