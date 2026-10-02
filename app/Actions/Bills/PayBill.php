<?php

namespace App\Actions\Bills;

use App\Models\Bill;
use App\Models\Expense;

/**
 * Puts money towards a bill for a month by writing the expense it becomes.
 */
class PayBill
{
    /**
     * A bill can be settled a bit at a time: pass an amount to pay part of
     * it, or leave it out to clear whatever is left. Each call writes its own
     * expense, so the expense list shows what actually left your pocket and
     * when — ₱500 now and ₱400 later is two payments, not one of ₱900.
     *
     * The expense is dated on the bill's due day rather than today, so paying
     * early or late still counts towards the bill's month, and the monthly
     * summary adds up the way the person expects.
     *
     * Never writes more than is left, so a double submit or a slip of the
     * keyboard cannot overpay the bill. A bill with nothing left returns
     * null rather than a zero-value expense.
     */
    public function handle(Bill $bill, ?float $amount = null): ?Expense
    {
        $remaining = $bill->remaining();

        if ($remaining <= 0.0) {
            return null;
        }

        $paying = round(min($amount ?? $remaining, $remaining), 2);

        if ($paying <= 0.0) {
            return null;
        }

        $expense = new Expense([
            'title' => $bill->name,
            'category' => $bill->category,
            'amount' => $paying,
            'date' => $bill->due_date,
            'payment_method' => $bill->payment_method,
            'notes' => $bill->notes,
        ]);

        // Neither key is mass assignable: the owner is not the form's to choose,
        // and the link to the bill is ours to make.
        $expense->forceFill([
            'user_id' => $bill->user_id,
            'bill_id' => $bill->id,
        ])->save();

        return $expense;
    }
}
