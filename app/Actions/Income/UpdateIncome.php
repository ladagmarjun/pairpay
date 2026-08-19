<?php

namespace App\Actions\Income;

use App\Concerns\NormalizesIncomeAttributes;
use App\Models\Income;

class UpdateIncome
{
    use NormalizesIncomeAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Income $income, array $attributes): Income
    {
        $income->update($this->normalize($attributes));

        return $income;
    }
}
