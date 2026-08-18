import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { DynamicIcon } from '@/components/dynamic-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { todayIso } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as loansIndex } from '@/routes/loans';
import type { DirectionOption, Loan } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Create (when [loan] is omitted) or edit a loan. One shared form keeps the
 * two flows consistent, as with expenses and bills.
 *
 * Direction is picked first and drives the wording of everything below it —
 * "who you borrowed from" and "who you lent to" are different questions, and a
 * form that asks neither is how loans get entered backwards.
 */
export function LoanForm({
    formProps,
    loan,
    directions,
    submitLabel,
}: {
    formProps: RouteFormDefinition<'post'>;
    loan?: Loan;
    directions: DirectionOption[];
    submitLabel: string;
}) {
    const [direction, setDirection] = useState(
        loan?.direction ?? directions[0].value,
    );

    const selected =
        directions.find((option) => option.value === direction) ??
        directions[0];

    return (
        <Form {...formProps} className="max-w-2xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label>Direction</Label>

                        <input
                            type="hidden"
                            name="direction"
                            value={direction}
                        />

                        <div className="grid gap-2 sm:grid-cols-2">
                            {directions.map((option) => {
                                const active = direction === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() =>
                                            setDirection(option.value)
                                        }
                                        aria-pressed={active}
                                        disabled={processing}
                                        className={cn(
                                            'flex items-center gap-3 rounded-xl border p-4 text-left transition-colors disabled:opacity-50',
                                            active
                                                ? 'border-transparent text-white'
                                                : 'hover:bg-muted',
                                        )}
                                        style={
                                            active
                                                ? {
                                                      backgroundColor:
                                                          option.color,
                                                  }
                                                : undefined
                                        }
                                    >
                                        <DynamicIcon
                                            name={option.icon}
                                            className="size-5 shrink-0"
                                            style={
                                                active
                                                    ? undefined
                                                    : { color: option.color }
                                            }
                                        />

                                        <span>
                                            <span className="block font-medium">
                                                {option.label}
                                            </span>

                                            <span
                                                className={cn(
                                                    'block text-sm',
                                                    active
                                                        ? 'opacity-90'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {option.description}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        <InputError message={errors.direction} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="counterparty">
                            {selected.counterparty_label}
                        </Label>

                        <Input
                            id="counterparty"
                            name="counterparty"
                            required
                            autoFocus
                            maxLength={200}
                            defaultValue={loan?.counterparty}
                            placeholder="Who is this with?"
                            disabled={processing}
                        />

                        <InputError message={errors.counterparty} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="principal">Amount</Label>

                        <Input
                            id="principal"
                            name="principal"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0.01"
                            required
                            defaultValue={loan?.principal.toFixed(2)}
                            placeholder="0.00"
                            disabled={processing}
                        />

                        <p className="text-sm text-muted-foreground">
                            The full amount. Payments are recorded against it
                            afterwards, and the balance follows from them.
                        </p>

                        <InputError message={errors.principal} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="date">Date</Label>

                        <Input
                            id="date"
                            name="date"
                            type="date"
                            required
                            defaultValue={loan?.date ?? todayIso()}
                            disabled={processing}
                        />

                        <InputError message={errors.date} />
                    </div>

                    <fieldset className="grid gap-4 rounded-xl border p-4">
                        <legend className="px-1 text-sm font-medium">
                            Repayment plan (optional)
                        </legend>

                        <p className="text-sm text-muted-foreground">
                            Leave these blank for an informal loan paid back
                            whenever. Fill them in and the app works out what
                            each month costs, and tracks the months for you.
                        </p>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="term_months">
                                    Number of months
                                </Label>

                                <Input
                                    id="term_months"
                                    name="term_months"
                                    type="number"
                                    inputMode="numeric"
                                    min="1"
                                    max="600"
                                    defaultValue={loan?.term_months ?? ''}
                                    placeholder="e.g. 12"
                                    disabled={processing}
                                />

                                <InputError message={errors.term_months} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="first_due_date">
                                    First payment due
                                </Label>

                                <Input
                                    id="first_due_date"
                                    name="first_due_date"
                                    type="date"
                                    defaultValue={loan?.first_due_date ?? ''}
                                    disabled={processing}
                                />

                                <InputError message={errors.first_due_date} />
                            </div>
                        </div>
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes (optional)</Label>

                        <Textarea
                            id="notes"
                            name="notes"
                            rows={3}
                            maxLength={1000}
                            defaultValue={loan?.notes ?? ''}
                            disabled={processing}
                        />

                        <InputError message={errors.notes} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>

                        <Button asChild variant="ghost" disabled={processing}>
                            <Link href={loansIndex()}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
