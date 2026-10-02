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
            // Carbon's clock rather than Faker's relative strings, which read
            // the real time and ignore a test's travelTo()/setTestNow().
            'due_date' => fake()->dateTimeBetween(now()->subMonths(6), now()),
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
