<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'amount' => fake()->randomFloat(2, 1, 2000),
            'date' => fake()->dateTimeBetween('-6 months', 'now'),
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
        ];
    }

    /**
     * An expense dated inside the given month.
     */
    public function inMonth(int $year, int $month): static
    {
        return $this->state(fn (): array => [
            'date' => fake()->dateTimeBetween(
                sprintf('%04d-%02d-01', $year, $month),
                sprintf('%04d-%02d-28', $year, $month),
            ),
        ]);
    }
}
