<?php

namespace App\Actions\Loans;

use App\Concerns\NormalizesLoanAttributes;
use App\Models\Loan;

class UpdateLoan
{
    use NormalizesLoanAttributes;

    /**
     * Payments are left alone. Lowering the principal below what has already
     * been paid settles the loan rather than making it owe a negative amount —
     * see `Loan::outstanding()`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Loan $loan, array $attributes): Loan
    {
        $loan->update($this->normalize($attributes));

        return $loan;
    }
}
