import { Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { DynamicIcon } from '@/components/dynamic-icon';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate } from '@/lib/format';
import { edit as editExpense } from '@/routes/expenses';
import type { CategoryOption, Expense } from '@/types';

/**
 * A single row in the expense list: category avatar, title + meta, amount.
 * The whole row links to the edit form; delete is a separate explicit control.
 */
export function ExpenseRow({
    expense,
    category,
    currency,
    onDelete,
}: {
    expense: Expense;
    category: CategoryOption;
    currency: string;
    onDelete: (expense: Expense) => void;
}) {
    return (
        <div className="group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/50">
            <span
                className="flex size-10 shrink-0 items-center justify-center rounded-full"
                style={{ backgroundColor: `${category.color}26` }}
                aria-hidden
            >
                <DynamicIcon
                    name={category.icon}
                    className="size-5"
                    style={{ color: category.color }}
                />
            </span>

            <div className="min-w-0 flex-1">
                <Link
                    href={editExpense(expense.id)}
                    className="block truncate font-medium hover:underline"
                >
                    {expense.title}
                </Link>

                <p className="truncate text-sm text-muted-foreground">
                    {category.label} · {formatDate(expense.date)}
                </p>
            </div>

            <span className="shrink-0 font-semibold tabular-nums">
                {formatCurrency(expense.amount, currency)}
            </span>

            <div className="flex shrink-0 items-center gap-1">
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="size-8 text-muted-foreground"
                >
                    <Link
                        href={editExpense(expense.id)}
                        aria-label={`Edit ${expense.title}`}
                    >
                        <Pencil className="size-4" />
                    </Link>
                </Button>

                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 text-muted-foreground hover:text-destructive"
                    onClick={() => onDelete(expense)}
                    aria-label={`Delete ${expense.title}`}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>
        </div>
    );
}
