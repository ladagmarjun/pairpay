<?php

namespace App\Actions\Income;

use App\Concerns\NormalizesIncomeAttributes;
use App\Models\Income;
use App\Models\User;

/**
 * Records money received. Writes go through an action rather than the
 * controller so the same logic is reachable from a console command, an import,
 * or a future API without duplicating it.
 */
class CreateIncome
{
    use NormalizesIncomeAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Income
    {
        return $user->incomes()->create($this->normalize($attributes));
    }
}
