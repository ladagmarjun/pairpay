<?php

namespace App\Actions\Bills;

use App\Concerns\NormalizesBillAttributes;
use App\Models\Bill;

class UpdateBill
{
    use NormalizesBillAttributes;

    /**
     * Changing a bill changes what future payments will cost. Expenses already
     * written for past months keep the amount that was actually paid — they are
     * a record of what happened, not a view onto the template.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Bill $bill, array $attributes): Bill
    {
        $bill->update($this->normalize($attributes));

        return $bill;
    }
}
