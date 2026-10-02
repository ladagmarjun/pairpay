<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bills used to recur every month from their first due date. The migration
 * spreads each one into a bill per month, so nothing that showed before goes
 * missing and past payments stay on the month they paid.
 */
class SplitRecurringBillsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-08-19 12:00:00');
    }

    public function test_a_recurring_bill_becomes_one_bill_per_month_up_to_now(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->dueOn('2026-05-31')->create(['name' => 'Rent', 'amount' => 12000]);

        $this->migrate();

        $this->assertSame(
            ['2026-05-31', '2026-06-30', '2026-07-31', '2026-08-31'],
            $user->bills()->orderBy('due_date')->pluck('due_date')->map->toDateString()->all(),
        );

        // The original row keeps the first month.
        $this->assertSame('2026-05-31', $bill->fresh()->due_date->toDateString());
        $this->assertSame(4, $user->bills()->where('name', 'Rent')->where('amount', 12000)->count());
    }

    public function test_each_payment_moves_to_the_bill_for_its_month(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->dueOn('2026-06-10')->create(['amount' => 2000]);

        $june = Expense::factory()->for($user)->create(['bill_id' => $bill->id, 'amount' => 2000, 'date' => '2026-06-10']);
        $july = Expense::factory()->for($user)->create(['bill_id' => $bill->id, 'amount' => 500, 'date' => '2026-07-10']);

        $this->migrate();

        $this->assertSame($bill->id, $june->fresh()->bill_id);

        $julyBill = $user->bills()->whereDate('due_date', '2026-07-10')->sole();
        $this->assertSame($julyBill->id, $july->fresh()->bill_id);
        $this->assertSame(500.0, $julyBill->paid());

        $this->assertSame(0.0, $user->bills()->whereDate('due_date', '2026-08-10')->sole()->paid());
    }

    public function test_a_bill_starting_this_month_is_left_alone(): void
    {
        $user = User::factory()->create();
        Bill::factory()->for($user)->dueOn('2026-08-25')->create();

        $this->migrate();

        $this->assertSame(1, $user->bills()->count());
    }

    private function migrate(): void
    {
        $migration = require database_path('migrations/2026_10_02_000000_split_recurring_bills_into_months.php');

        $migration->up();
    }
}
