<?php

namespace App\Actions\Income;

use App\Models\Income;

class DeleteIncome
{
    public function handle(Income $income): void
    {
        $income->delete();
    }
}
