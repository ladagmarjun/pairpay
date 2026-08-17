import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { DynamicIcon } from '@/components/dynamic-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { todayIso } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as expensesIndex } from '@/routes/expenses';
import type { CategoryOption, Expense, PaymentMethodOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Create (when [expense] is omitted) or edit an expense. One shared form keeps
 * the two flows consistent — the only difference is where it posts to.
 */
export function ExpenseForm({
    formProps,
    expense,
    categories,
    paymentMethods,
    submitLabel,
}: {
    formProps: RouteFormDefinition<'post'>;
    expense?: Expense;
    categories: CategoryOption[];
    paymentMethods: PaymentMethodOption[];
    submitLabel: string;
}) {
    const [category, setCategory] = useState(
        expense?.category ?? categories[0].value,
    );

    return (
        <Form {...formProps} className="max-w-2xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>

                        <Input
                            id="title"
                            name="title"
                            required
                            autoFocus
                            maxLength={200}
                            defaultValue={expense?.title}
                            placeholder="Groceries, rent, bus fare…"
                            disabled={processing}
                        />

                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="amount">Amount</Label>

                        <Input
                            id="amount"
                            name="amount"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0.01"
                            required
                            defaultValue={expense?.amount.toFixed(2)}
                            placeholder="0.00"
                            disabled={processing}
                        />

                        <InputError message={errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Category</Label>

                        {/* Chips carry the category's own color and icon, so the
                            choice reads the same way it does in the list. */}
                        <input type="hidden" name="category" value={category} />

                        <div className="flex flex-wrap gap-2">
                            {categories.map((option) => {
                                const selected = category === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() =>
                                            setCategory(option.value)
                                        }
                                        aria-pressed={selected}
                                        disabled={processing}
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors disabled:opacity-50',
                                            selected
                                                ? 'border-transparent text-white'
                                                : 'hover:bg-muted',
                                        )}
                                        style={
                                            selected
                                                ? {
                                                      backgroundColor:
                                                          option.color,
                                                  }
                                                : undefined
                                        }
                                    >
                                        <DynamicIcon
                                            name={option.icon}
                                            className="size-4"
                                            style={
                                                selected
                                                    ? undefined
                                                    : { color: option.color }
                                            }
                                        />
                                        {option.label}
                                    </button>
                                );
                            })}
                        </div>

                        <InputError message={errors.category} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="payment_method">Payment method</Label>

                        <Select
                            name="payment_method"
                            defaultValue={
                                expense?.payment_method ??
                                paymentMethods[0].value
                            }
                            disabled={processing}
                        >
                            <SelectTrigger
                                id="payment_method"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                {paymentMethods.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        <DynamicIcon
                                            name={option.icon}
                                            className="size-4"
                                        />
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <InputError message={errors.payment_method} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="date">Date</Label>

                        <Input
                            id="date"
                            name="date"
                            type="date"
                            required
                            defaultValue={expense?.date ?? todayIso()}
                            disabled={processing}
                        />

                        <InputError message={errors.date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes (optional)</Label>

                        <Textarea
                            id="notes"
                            name="notes"
                            rows={3}
                            maxLength={1000}
                            defaultValue={expense?.notes ?? ''}
                            disabled={processing}
                        />

                        <InputError message={errors.notes} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>

                        <Button asChild variant="ghost" disabled={processing}>
                            <Link href={expensesIndex()}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
