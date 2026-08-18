<?php

namespace App\Actions\Loans;

use App\Concerns\NormalizesLoanAttributes;
use App\Models\Loan;
use App\Models\User;

/**
 * Records a new loan. Writes go through an action rather than the controller
 * so the same logic is reachable from a console command, an import, or a
 * future API without duplicating it.
 */
class CreateLoan
{
    use NormalizesLoanAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Loan
    {
        return $user->loans()->create($this->normalize($attributes));
    }
}
