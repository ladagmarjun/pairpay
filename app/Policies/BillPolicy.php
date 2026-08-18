<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\User;

/**
 * Ownership rules for bills, matching {@see ExpensePolicy}: a user may only
 * ever touch their own rows.
 */
class BillPolicy
{
    public function view(User $user, Bill $bill): bool
    {
        return $user->id === $bill->user_id;
    }

    public function update(User $user, Bill $bill): bool
    {
        return $user->id === $bill->user_id;
    }

    public function delete(User $user, Bill $bill): bool
    {
        return $user->id === $bill->user_id;
    }
}
