<?php

namespace App\Actions\Expenses;

use App\Concerns\NormalizesExpenseAttributes;
use App\Models\Expense;

class UpdateExpense
{
    use NormalizesExpenseAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Expense $expense, array $attributes): Expense
    {
        $expense->update($this->normalize($attributes));

        return $expense;
    }
}
