<?php

namespace App\Actions\Loans;

use App\Models\Loan;
use App\Models\LoanPayment;

/**
 * Records a payment against a loan, in either direction — money you paid back,
 * or money that came back to you.
 */
class RecordLoanPayment
{
    /**
     * Nothing here recalculates a balance: the loan's outstanding amount is
     * the principal minus these rows, so writing the payment *is* the update.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Loan $loan, array $attributes): LoanPayment
    {
        $notes = trim((string) ($attributes['notes'] ?? ''));
        $attributes['notes'] = $notes === '' ? null : $notes;

        return $loan->payments()->create($attributes);
    }
}
