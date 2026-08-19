<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_charts_the_last_twelve_months(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->has('trend.points', 12)
                // Oldest first, ending on the month being viewed.
                ->where('trend.points.0.month', '2025-09')
                ->where('trend.points.11.month', '2026-08'));
    }

    public function test_it_totals_each_month_separately(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 300, 'date' => '2026-08-04']);
        Expense::factory()->for($user)->create(['amount' => 200.50, 'date' => '2026-08-27']);
        Expense::factory()->for($user)->create(['amount' => 1000, 'date' => '2026-07-15']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('trend.points.11.total', 500.5)
                ->where('trend.points.10.total', 1000)
                ->where('trend.total', 1500.5)
                ->where('trend.peak', 1000));
    }

    /**
     * A month with nothing in it is information — the bar has to be there and
     * flat, not missing, or the axis silently closes the gap up.
     */
    public function test_a_month_with_no_expenses_is_still_a_point(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 400, 'date' => '2026-08-10']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('trend.points', 12)
                ->where('trend.points.10.month', '2026-07')
                ->where('trend.points.10.total', 0));
    }

    public function test_the_trend_only_counts_the_signed_in_users_expenses(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 250, 'date' => '2026-08-10']);
        Expense::factory()->for($stranger)->create(['amount' => 9999, 'date' => '2026-08-10']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('trend.points.11.total', 250)
                ->where('trend.total', 250));
    }

    /**
     * Expenses either side of the window must not leak into the edge months.
     */
    public function test_it_ignores_expenses_outside_the_window(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 500, 'date' => '2025-08-31']);
        Expense::factory()->for($user)->create(['amount' => 700, 'date' => '2026-09-01']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('trend.total', 0)
                ->where('trend.peak', 0));
    }

    public function test_the_average_is_across_every_month_in_the_window(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 1200, 'date' => '2026-08-10']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('trend.average', 100));
    }

    /**
     * The window follows `?month=`, the same way every other month-scoped page
     * in the app does.
     */
    public function test_the_window_follows_the_month_in_the_query_string(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-03']))
            ->assertInertia(fn ($page) => $page
                ->where('trend.points.11.month', '2026-03')
                ->where('trend.points.0.month', '2025-04'));
    }

    public function test_it_charts_the_bills_over_the_same_window(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('bills.points', 12)
                ->where('bills.points.0.month', '2025-09')
                ->where('bills.points.11.month', '2026-08'));
    }

    /**
     * A bill is a template, not a row per month: it falls due every month from
     * the month it starts in, and in none of the ones before it.
     */
    public function test_a_bill_is_due_in_every_month_from_the_one_it_starts_in(): void
    {
        $user = User::factory()->create();

        Bill::factory()->for($user)->dueOn('2026-07-20')->create(['amount' => 1500]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('bills.points.9.due', 0)
                ->where('bills.points.10.due', 1500)
                ->where('bills.points.11.due', 1500));
    }

    public function test_paid_counts_only_the_expenses_a_bill_payment_wrote(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->dueOn('2026-08-05')->create(['amount' => 1000]);

        Expense::factory()->for($user)->create([
            'bill_id' => $bill->id,
            'amount' => 400,
            'date' => '2026-08-05',
        ]);

        // Groceries are spend, not a bill payment, so the bills chart leaves
        // them to the expense chart beside it.
        Expense::factory()->for($user)->create(['amount' => 900, 'date' => '2026-08-06']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('bills.points.11.due', 1000)
                ->where('bills.points.11.paid', 400)
                ->where('bills.paid', 400));
    }

    /**
     * Both series share one axis, so a month settled beyond what it owed still
     * has to fit inside the plot.
     */
    public function test_the_peak_takes_the_taller_of_the_two_series(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->dueOn('2026-08-05')->create(['amount' => 300]);

        Expense::factory()->for($user)->create([
            'bill_id' => $bill->id,
            'amount' => 800,
            'date' => '2026-08-05',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('bills.peak', 800));
    }

    public function test_the_bills_chart_only_counts_the_signed_in_users_bills(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        Bill::factory()->for($user)->dueOn('2026-08-01')->create(['amount' => 200]);
        Bill::factory()->for($stranger)->dueOn('2026-08-01')->create(['amount' => 9999]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('bills.points.11.due', 200));
    }

    public function test_the_dashboard_needs_a_signed_in_user(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
