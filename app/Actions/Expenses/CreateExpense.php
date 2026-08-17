<?php

namespace App\Actions\Expenses;

use App\Concerns\NormalizesExpenseAttributes;
use App\Models\Expense;
use App\Models\User;

/**
 * Records a new expense for a user. Writes go through an action rather than the
 * controller so the same logic is reachable from a console command, an import,
 * or a future API without duplicating it.
 */
class CreateExpense
{
    use NormalizesExpenseAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Expense
    {
        return $user->expenses()->create($this->normalize($attributes));
    }
}
