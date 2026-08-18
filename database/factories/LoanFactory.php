<?php

namespace Database\Factories;

use App\Enums\LoanDirection;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'counterparty' => fake()->name(),
            'direction' => fake()->randomElement(LoanDirection::cases()),
            'principal' => fake()->randomFloat(2, 500, 50000),
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'notes' => null,
        ];
    }

    public function borrowed(): static
    {
        return $this->state(fn (): array => ['direction' => LoanDirection::Borrowed]);
    }

    public function lent(): static
    {
        return $this->state(fn (): array => ['direction' => LoanDirection::Lent]);
    }
}
