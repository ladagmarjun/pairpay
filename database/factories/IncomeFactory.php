<?php

namespace Database\Factories;

use App\Enums\IncomeCategory;
use App\Enums\PaymentMethod;
use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 */
class IncomeFactory extends Factory
{
    protected $model = Income::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source' => fake()->company(),
            'category' => fake()->randomElement(IncomeCategory::cases()),
            'amount' => fake()->randomFloat(2, 500, 50000),
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'notes' => null,
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
        ];
    }

    /**
     * The recurring kind — a wage, which is what most of a month's income is.
     */
    public function salary(): static
    {
        return $this->state(fn (): array => [
            'category' => IncomeCategory::Salary,
            'payment_method' => PaymentMethod::BankTransfer,
        ]);
    }
}
