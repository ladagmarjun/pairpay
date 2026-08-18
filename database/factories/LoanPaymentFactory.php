<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\LoanPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanPayment>
 */
class LoanPaymentFactory extends Factory
{
    protected $model = LoanPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'amount' => fake()->randomFloat(2, 100, 1000),
            'date' => fake()->dateTimeBetween('-6 months', 'now'),
            'notes' => null,
        ];
    }
}
