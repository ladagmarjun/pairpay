<?php

namespace App\Actions\Loans;

use App\Models\Loan;

class DeleteLoan
{
    /**
     * The payments go with it — a payment against a loan that no longer
     * exists is not a record of anything, which is why `loan_payments`
     * cascades where `expenses.bill_id` nulls.
     */
    public function handle(Loan $loan): void
    {
        $loan->delete();
    }
}
