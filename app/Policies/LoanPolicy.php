<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

/**
 * Ownership rules for loans, matching {@see ExpensePolicy} and
 * {@see BillPolicy}: a user may only ever touch their own rows. Payments have
 * no policy of their own — they are reached through their loan, and authorized
 * against it.
 */
class LoanPolicy
{
    public function view(User $user, Loan $loan): bool
    {
        return $user->id === $loan->user_id;
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->id === $loan->user_id;
    }

    public function delete(User $user, Loan $loan): bool
    {
        return $user->id === $loan->user_id;
    }
}
