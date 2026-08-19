import type { LucideIcon } from 'lucide-react';
import {
    Banknote,
    Briefcase,
    Car,
    ChartColumn,
    CreditCard,
    Ellipsis,
    Film,
    Gift,
    GraduationCap,
    HandCoins,
    Heart,
    Landmark,
    Laptop,
    PiggyBank,
    ReceiptText,
    Shapes,
    ShoppingBag,
    Store,
    TrendingUp,
    Undo2,
    Users,
    Utensils,
    Wallet,
} from 'lucide-react';
import type {
    CategoryOption,
    DirectionOption,
    IncomeCategoryOption,
    PaymentMethodOption,
} from '@/types';

/**
 * The server sends icons by name (`ExpenseCategory::icon()`), so the enum stays
 * the single source of truth for a category's label, color, and icon. This maps
 * those names onto the actual components.
 */
const ICONS: Record<string, LucideIcon> = {
    banknote: Banknote,
    briefcase: Briefcase,
    car: Car,
    'chart-column': ChartColumn,
    'credit-card': CreditCard,
    ellipsis: Ellipsis,
    film: Film,
    gift: Gift,
    'graduation-cap': GraduationCap,
    'hand-coins': HandCoins,
    heart: Heart,
    landmark: Landmark,
    laptop: Laptop,
    'piggy-bank': PiggyBank,
    'receipt-text': ReceiptText,
    shapes: Shapes,
    'shopping-bag': ShoppingBag,
    store: Store,
    'trending-up': TrendingUp,
    'undo-2': Undo2,
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
    T extends
        | CategoryOption
        | IncomeCategoryOption
        | PaymentMethodOption
        | DirectionOption,
>(options: T[]): Record<string, T> {
    return Object.fromEntries(options.map((option) => [option.value, option]));
}
