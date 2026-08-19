import { Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { DynamicIcon } from '@/components/dynamic-icon';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate } from '@/lib/format';
import { edit as editIncome } from '@/routes/income';
import type { Income, IncomeCategoryOption } from '@/types';

/**
 * A single row in the income list: category avatar, source + meta, amount.
 * The whole row links to the edit form; delete is a separate explicit control.
 *
 * The amount carries a leading `+`. Both ledgers are lists of positive
 * numbers, so without it a screenshot of one is indistinguishable from the
 * other.
 */
export function IncomeRow({
    income,
    category,
    currency,
    onDelete,
}: {
    income: Income;
    category: IncomeCategoryOption;
    currency: string;
    onDelete: (income: Income) => void;
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
                    href={editIncome(income.id)}
                    className="block truncate font-medium hover:underline"
                >
                    {income.source}
                </Link>

                <p className="truncate text-sm text-muted-foreground">
                    {category.label} · {formatDate(income.date)}
                </p>
            </div>

            <span className="shrink-0 font-semibold text-income tabular-nums">
                +{formatCurrency(income.amount, currency)}
            </span>

            <div className="flex shrink-0 items-center gap-1">
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="size-8 text-muted-foreground"
                >
                    <Link
                        href={editIncome(income.id)}
                        aria-label={`Edit ${income.source}`}
                    >
                        <Pencil className="size-4" />
                    </Link>
                </Button>

                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 text-muted-foreground hover:text-destructive"
                    onClick={() => onDelete(income)}
                    aria-label={`Delete ${income.source}`}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>
        </div>
    );
}
