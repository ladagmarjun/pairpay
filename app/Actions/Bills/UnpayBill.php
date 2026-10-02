<?php

namespace App\Actions\Bills;

use App\Models\Bill;

/**
 * Undoes the last payment made towards a month — the way back from a
 * mis-click, since the expense a payment wrote is otherwise
 * indistinguishable from one entered by hand.
 *
 * One step at a time, rather than clearing the month: a month settled with two
 * payments should not lose both because the second was a typo.
 */
class UnpayBill
{
    public function handle(Bill $bill): void
    {
        $bill->payments()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first()
            ?->delete();
    }
}
