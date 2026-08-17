import type { LucideProps } from 'lucide-react';
import { createElement } from 'react';
import { iconFor } from '@/lib/expense-meta';

/**
 * Renders a Lucide icon chosen by name at runtime — the names come from the
 * server (`ExpenseCategory::icon()`, `PaymentMethod::icon()`, and the
 * coming-soon routes), so the PHP enums stay the single source of truth for a
 * category's label, color, and icon.
 */
export function DynamicIcon({
    name,
    ...props
}: { name: string } & LucideProps) {
    return createElement(iconFor(name), props);
}
