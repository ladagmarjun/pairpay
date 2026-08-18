<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    protected $model = Bill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'due_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'category' => ExpenseCategory::Bills,
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'notes' => null,
        ];
    }

    /**
     * A bill anchored on an exact date — used to pin the day of the month,
     * including days that not every month has.
     */
    public function dueOn(string $date): static
    {
        return $this->state(fn (): array => ['due_date' => $date]);
    }
}
