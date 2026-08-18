<?php

namespace Tests\Feature;

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

    public function test_the_dashboard_needs_a_signed_in_user(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
