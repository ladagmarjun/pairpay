<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * Ownership rules for expenses — the server-side equivalent of the Firestore
 * security rules the mobile app relied on: a user may only ever touch their
 * own rows.
 */
class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return $user->id === $expense->user_id;
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->id === $expense->user_id;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->id === $expense->user_id;
    }
}
