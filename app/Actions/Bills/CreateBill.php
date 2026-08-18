<?php

namespace App\Actions\Bills;

use App\Concerns\NormalizesBillAttributes;
use App\Models\Bill;
use App\Models\User;

/**
 * Records a new recurring bill. Writes go through an action rather than the
 * controller so the same logic is reachable from a console command, an import,
 * or a future API without duplicating it.
 */
class CreateBill
{
    use NormalizesBillAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): Bill
    {
        return $user->bills()->create($this->normalize($attributes));
    }
}
