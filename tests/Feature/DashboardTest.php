<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard charts a calendar year — January at the left, December at the
 * right — for whichever year the month being viewed falls in. Index 0 is
 * therefore January and index 11 December, which is what the offsets below
 * are counting.
 *
 * The months the year has not reached yet are drawn empty rather than filled
 * in, so a chart never shows a figure that has not happened.
 *
 * Which months those are depends on today, so the clock is pinned to
 * mid-August 2026: January through August have happened, September through
 * December have not.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-08-19 12:00:00');
    }

    public function test_the_dashboard_charts_the_calendar_year(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->has('trend.points', 12)
                ->where('trend.points.0.month', '2026-01')
                ->where('trend.points.11.month', '2026-12'));
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
                ->where('trend.points.7.total', 500.5)
                ->where('trend.points.6.total', 1000)
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
                ->where('trend.points.6.month', '2026-07')
                ->where('trend.points.6.total', 0));
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
                ->where('trend.points.7.total', 250)
                ->where('trend.total', 250));
    }

    /**
     * Expenses either side of the year must not leak into the edge months.
     */
    public function test_it_ignores_expenses_outside_the_year(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 500, 'date' => '2025-12-31']);
        Expense::factory()->for($user)->create(['amount' => 700, 'date' => '2027-01-01']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('trend.total', 0)
                ->where('trend.peak', 0));
    }

    /**
     * The year runs to December, so for most of it the chart reaches into
     * months that have not happened. Those stay empty — including when
     * something has been dated into them, which is a plan rather than a cost.
     */
    public function test_months_the_year_has_not_reached_are_left_empty(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 900, 'date' => '2026-09-01']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('trend.points.8.month', '2026-09')
                ->where('trend.points.8.total', 0)
                ->where('trend.total', 0));
    }

    /**
     * Dividing by twelve would drag every average down by the months still to
     * come, so it is the months behind us that count.
     */
    public function test_the_average_divides_by_the_months_that_have_happened(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 1200, 'date' => '2026-08-10']);

        // January through August: eight months, not twelve.
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('trend.average', 150));
    }

    /**
     * The year follows `?month=`, the same way every other month-scoped page
     * in the app does — and a year that is fully behind us is drawn whole.
     */
    public function test_the_year_follows_the_month_in_the_query_string(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 640, 'date' => '2025-12-20']);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2025-03']))
            ->assertInertia(fn ($page) => $page
                ->where('trend.points.0.month', '2025-01')
                ->where('trend.points.11.month', '2025-12')
                ->where('trend.points.11.total', 640)
                ->where('trend.average', 53.33));
    }

    public function test_the_bills_chart_covers_only_the_month_being_viewed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page
                ->where('bills.month', '2026-08')
                ->where('bills.label', 'August 2026')
                ->has('bills.bills', 0));
    }

    /**
     * A bill belongs to the month it falls due in, and to no other.
     */
    public function test_a_bill_is_listed_only_in_its_own_month(): void
    {
        $user = User::factory()->create();

        Bill::factory()->for($user)->dueOn('2026-07-20')->create(['amount' => 1500]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page->has('bills.bills', 0)->where('bills.due', 0));

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-07']))
            ->assertInertia(fn ($page) => $page
                ->has('bills.bills', 1)
                ->where('bills.bills.0.due', 1500)
                ->where('bills.bills.0.due_on', '2026-07-20'));
    }

    public function test_paid_counts_only_the_bills_own_payments(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->dueOn('2026-08-05')->create(['amount' => 1000]);

        Expense::factory()->for($user)->create([
            'bill_id' => $bill->id,
            'amount' => 400,
            'date' => '2026-08-05',
        ]);

        // Groceries are spend, not a bill payment, so they do not show
        // against the bill.
        Expense::factory()->for($user)->create(['amount' => 900, 'date' => '2026-08-06']);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page
                ->where('bills.bills.0.due', 1000)
                ->where('bills.bills.0.paid', 400)
                ->where('bills.due', 1000)
                ->where('bills.paid', 400));
    }

    /**
     * Every bar shares one scale, so a bill settled beyond what it owed still
     * has to fit.
     */
    public function test_the_peak_takes_the_larger_of_due_and_paid(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->dueOn('2026-08-05')->create(['amount' => 300]);

        Expense::factory()->for($user)->create([
            'bill_id' => $bill->id,
            'amount' => 800,
            'date' => '2026-08-05',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page->where('bills.peak', 800));
    }

    public function test_the_bills_chart_only_counts_the_signed_in_users_bills(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        Bill::factory()->for($user)->dueOn('2026-08-01')->create(['amount' => 200]);
        Bill::factory()->for($stranger)->dueOn('2026-08-01')->create(['amount' => 9999]);

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page
                ->has('bills.bills', 1)
                ->where('bills.due', 200));
    }

    /**
     * A balance carries: a loan taken out once shows up in every month after
     * it, not only the one the money changed hands in.
     */
    public function test_a_loan_stays_outstanding_until_it_is_paid_off(): void
    {
        $user = User::factory()->create();

        Loan::factory()->for($user)->borrowed()->create([
            'principal' => 5000,
            'date' => '2026-06-10',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('loans.points', 12)
                ->where('loans.points.4.borrowed', 0)
                ->where('loans.points.5.borrowed', 5000)
                ->where('loans.points.7.borrowed', 5000)
                ->where('loans.owed_by_you', 5000));
    }

    /**
     * Carrying today's balance into the rest of the year would be drawing a
     * guess as though it had happened.
     */
    public function test_a_balance_is_not_carried_into_months_that_have_not_happened(): void
    {
        $user = User::factory()->create();

        Loan::factory()->for($user)->borrowed()->create([
            'principal' => 5000,
            'date' => '2026-06-10',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('loans.points.8.borrowed', 0)
                ->where('loans.points.11.borrowed', 0)
                // The caption still reads where the balance actually stands.
                ->where('loans.owed_by_you', 5000));
    }

    public function test_payments_bring_the_balance_down_from_the_month_they_land(): void
    {
        $user = User::factory()->create();

        $loan = Loan::factory()->for($user)->borrowed()->create([
            'principal' => 5000,
            'date' => '2026-06-10',
        ]);

        LoanPayment::factory()->for($loan)->create(['amount' => 2000, 'date' => '2026-07-01']);
        LoanPayment::factory()->for($loan)->create(['amount' => 3000, 'date' => '2026-08-01']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('loans.points.5.borrowed', 5000)
                ->where('loans.points.6.borrowed', 3000)
                ->where('loans.points.7.borrowed', 0)
                ->where('loans.owed_by_you', 0));
    }

    /**
     * Paying a loan down before the year opens must still be counted, or
     * January would show a debt that was already part settled.
     */
    public function test_payments_from_before_the_year_still_count(): void
    {
        $user = User::factory()->create();

        $loan = Loan::factory()->for($user)->borrowed()->create([
            'principal' => 4000,
            'date' => '2025-01-15',
        ]);

        LoanPayment::factory()->for($loan)->create(['amount' => 1500, 'date' => '2025-03-02']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('loans.points.0.borrowed', 2500)
                ->where('loans.points.7.borrowed', 2500));
    }

    /**
     * The two directions are never netted off: owing 5,000 while being owed
     * 5,000 is not the position of owing nothing.
     */
    public function test_the_two_directions_are_kept_apart(): void
    {
        $user = User::factory()->create();

        Loan::factory()->for($user)->borrowed()->create(['principal' => 5000, 'date' => '2026-08-01']);
        Loan::factory()->for($user)->lent()->create(['principal' => 5000, 'date' => '2026-08-01']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('loans.points.7.borrowed', 5000)
                ->where('loans.points.7.lent', 5000)
                ->where('loans.owed_by_you', 5000)
                ->where('loans.owed_to_you', 5000)
                ->where('loans.peak', 5000));
    }

    public function test_the_loan_chart_only_counts_the_signed_in_users_loans(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        Loan::factory()->for($user)->borrowed()->create(['principal' => 300, 'date' => '2026-08-01']);
        Loan::factory()->for($stranger)->borrowed()->create(['principal' => 9999, 'date' => '2026-08-01']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('loans.points.7.borrowed', 300));
    }

    public function test_it_charts_cashflow_over_the_same_year(): void
    {
        $user = User::factory()->create();

        Income::factory()->for($user)->create(['amount' => 30000, 'date' => '2026-08-15']);
        Expense::factory()->for($user)->create(['amount' => 12000, 'date' => '2026-08-20']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('cashflow.points', 12)
                ->where('cashflow.points.0.month', '2026-01')
                ->where('cashflow.points.7.income', 30000)
                ->where('cashflow.points.7.spent', 12000)
                ->where('cashflow.surplus', 18000)
                ->where('cashflow.peak', 30000));
    }

    /**
     * Spending more than came in is the thing the chart is for, so the surplus
     * goes negative rather than being floored at zero.
     */
    public function test_a_month_that_overspends_shows_a_negative_surplus(): void
    {
        $user = User::factory()->create();

        Income::factory()->for($user)->create(['amount' => 1000, 'date' => '2026-08-15']);
        Expense::factory()->for($user)->create(['amount' => 2500, 'date' => '2026-08-20']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('cashflow.surplus', -1500));
    }

    /**
     * Borrowing is not earning, and a repayment coming back on a loan you made
     * is your own money returning. Neither belongs on the cashflow chart, or a
     * month you were paid back would read as a month you earned.
     */
    public function test_loan_money_is_never_counted_as_cashflow(): void
    {
        $user = User::factory()->create();

        $loan = Loan::factory()->for($user)->lent()->create([
            'principal' => 5000,
            'date' => '2026-08-01',
        ]);

        LoanPayment::factory()->for($loan)->create(['amount' => 5000, 'date' => '2026-08-10']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('cashflow.points.7.income', 0)
                ->where('cashflow.points.7.spent', 0)
                ->where('cashflow.income', 0));
    }

    /**
     * Paying a bill writes an expense, so it is already on the spending side —
     * counting the bill as well would double it.
     */
    public function test_a_bill_payment_is_counted_once_as_spending(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->dueOn('2026-08-05')->create(['amount' => 1000]);

        Expense::factory()->for($user)->create([
            'bill_id' => $bill->id,
            'amount' => 1000,
            'date' => '2026-08-05',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('cashflow.points.7.spent', 1000));
    }

    public function test_cashflow_only_counts_the_signed_in_users_money(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        Income::factory()->for($user)->create(['amount' => 700, 'date' => '2026-08-15']);
        Income::factory()->for($stranger)->create(['amount' => 9999, 'date' => '2026-08-15']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('cashflow.points.7.income', 700));
    }

    public function test_the_dashboard_needs_a_signed_in_user(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
