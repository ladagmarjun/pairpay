<?php

namespace App\Actions\Expenses;

use App\Models\Expense;

class DeleteExpense
{
    public function handle(Expense $expense): void
    {
        $expense->delete();
    }
}
