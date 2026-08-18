<?php

namespace App\Actions\Loans;

use App\Models\LoanPayment;

/**
 * Removes a payment — the way back from a mistyped amount, since the balance
 * is derived and there is nothing else to correct.
 */
class DeleteLoanPayment
{
    public function handle(LoanPayment $payment): void
    {
        $payment->delete();
    }
}
