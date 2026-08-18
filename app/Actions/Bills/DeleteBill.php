<?php

namespace App\Actions\Bills;

use App\Models\Bill;

class DeleteBill
{
    /**
     * The expenses this bill produced survive it — `bill_id` is nulled rather
     * than cascaded, so deleting a bill you no longer pay never rewrites the
     * months you did pay it.
     */
    public function handle(Bill $bill): void
    {
        $bill->delete();
    }
}
