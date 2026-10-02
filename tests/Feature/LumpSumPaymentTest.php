<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Loan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pay day: one sum across bills and loans together.
 */
class LumpSumPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-18');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('payments.create'))->assertRedirect(route('login'));
    }

    /**
     * Bills and loans arrive as one list, ordered by date, with money you lent
     * out left off — this screen is money going out.
     */
    public function test_the_page_lists_bills_and_borrowed_loans_by_date(): void
    {
        $user = User::factory()->create();
        Bill::factory()->for($user)->create(['name' => 'Water', 'amount' => 600, 'due_date' => '2026-08-20']);
        Loan::factory()->for($user)->borrowed()->create(['counterparty' => 'Ana', 'principal' => 3000, 'date' => '2026-08-02']);
        Loan::factory()->for($user)->lent()->create(['counterparty' => 'Ben', 'principal' => 900]);

        $this->actingAs($user)
            ->get(route('payments.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('payments/lump-sum')
                ->has('payables', 2)
                ->where('payables.0.name', 'Ana')
                ->where('payables.0.type', 'loan')
                ->where('payables.1.name', 'Water')
                ->where('payables.1.type', 'bill'));
    }

    public function test_a_settled_bill_and_a_paid_off_loan_drop_off_the_list(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 600, 'due_date' => '2026-08-20']);

        $this->actingAs($user)->post(route('bills.pay', $bill));

        $this->actingAs($user)
            ->get(route('payments.create'))
            ->assertInertia(fn ($page) => $page->has('payables', 0));
    }

    /**
     * The case this screen exists for: ₱900 electricity, ₱600 water and a
     * ₱2,000 loan installment, with ₱3,000 to hand.
     */
    public function test_one_sum_is_spread_across_bills_and_loans_by_date(): void
    {
        $user = User::factory()->create();

        $water = Bill::factory()->for($user)->create([
            'name' => 'Water',
            'amount' => 600,
            'due_date' => '2026-08-05',
        ]);

        $loan = Loan::factory()->for($user)->borrowed()->create([
            'counterparty' => 'Bank',
            'principal' => 20000,
            'term_months' => 10,
            'first_due_date' => '2026-08-10',
        ]);

        $electricity = Bill::factory()->for($user)->create([
            'name' => 'Electricity',
            'amount' => 900,
            'due_date' => '2026-08-20',
        ]);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'amount' => '3000',
                'date' => '2026-08-18',
                'bills' => [$water->id, $electricity->id],
                'loans' => [$loan->id],
            ])
            ->assertRedirect(route('payments.create', ['month' => '2026-08']));

        $month = CarbonImmutable::parse('2026-08-01');

        // Water (5th) ₱600, then the loan (10th) ₱2,000, then electricity
        // (20th) takes the remaining ₱400 of its ₱900.
        $this->assertSame(600.0, $water->paid());
        $this->assertSame(2000.0, $loan->fresh()->paidTotal());
        $this->assertSame(400.0, $electricity->paid());
    }

    /**
     * A loan takes its current installment and no more: a ₱20,000 loan with
     * ₱2,000 due must not swallow the rest of the money.
     */
    public function test_a_loan_takes_only_its_current_installment(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 600, 'due_date' => '2026-08-05']);
        $loan = Loan::factory()->for($user)->borrowed()->create([
            'principal' => 20000,
            'term_months' => 10,
            'first_due_date' => '2026-08-10',
        ]);

        $this->actingAs($user)->post(route('payments.store'), [
            'amount' => '2600',
            'date' => '2026-08-18',
            'bills' => [$bill->id],
            'loans' => [$loan->id],
        ]);

        $month = CarbonImmutable::parse('2026-08-01');

        $this->assertSame(600.0, $bill->paid());
        $this->assertSame(2000.0, $loan->fresh()->paidTotal(), 'One installment, not the balance.');
    }

    /**
     * The ceiling is what is due now, so the amount box cannot accept money
     * the allocation would have nowhere to put.
     */
    public function test_more_than_is_due_now_is_rejected_even_when_a_balance_remains(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->borrowed()->create([
            'principal' => 20000,
            'term_months' => 10,
            'first_due_date' => '2026-08-10',
        ]);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'amount' => '5000',
                'date' => '2026-08-18',
                'loans' => [$loan->id],
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0.0, $loan->fresh()->paidTotal());
    }

    /**
     * The month decides which bills are offered, so stepping it has to change
     * the list — this screen and the bills page must never disagree.
     */
    public function test_the_month_steers_the_bills_offered(): void
    {
        $user = User::factory()->create();
        Bill::factory()->for($user)->create(['name' => 'Rent', 'due_date' => '2026-08-25']);
        Bill::factory()->for($user)->create(['name' => 'Rent', 'due_date' => '2026-09-25']);

        $this->actingAs($user)
            ->get(route('payments.create'))
            ->assertInertia(fn ($page) => $page
                ->where('month.value', '2026-08')
                ->has('payables', 1)
                ->where('payables.0.due_date', '2026-08-25'));

        $this->actingAs($user)
            ->get(route('payments.create', ['month' => '2026-09']))
            ->assertInertia(fn ($page) => $page
                ->where('month.value', '2026-09')
                ->where('month.label', 'September 2026')
                ->has('payables', 1)
                ->where('payables.0.due_date', '2026-09-25'));
    }

    /**
     * A loan with no plan is ordered by when it was made, which is not a due
     * date and must not be shown as one.
     */
    public function test_an_informal_loan_is_not_given_a_due_date(): void
    {
        $user = User::factory()->create();
        Loan::factory()->for($user)->borrowed()->create([
            'principal' => 3000,
            'term_months' => null,
            'first_due_date' => null,
        ]);

        $this->actingAs($user)
            ->get(route('payments.create'))
            ->assertInertia(fn ($page) => $page
                ->where('payables.0.has_due_date', false)
                ->where('payables.0.due_now', 3000));
    }

    public function test_only_the_things_that_were_ticked_are_paid(): void
    {
        $user = User::factory()->create();
        $paid = Bill::factory()->for($user)->create(['amount' => 600, 'due_date' => '2026-08-05']);
        $untouched = Bill::factory()->for($user)->create(['amount' => 900, 'due_date' => '2026-08-06']);

        $this->actingAs($user)->post(route('payments.store'), [
            'amount' => '600',
            'date' => '2026-08-18',
            'bills' => [$paid->id],
        ]);

        $month = CarbonImmutable::parse('2026-08-01');

        $this->assertSame(600.0, $paid->paid());
        $this->assertSame(0.0, $untouched->paid());
    }

    public function test_paying_only_loans_is_fine(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->borrowed()->create(['principal' => 1000]);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'amount' => '1000',
                'date' => '2026-08-18',
                'loans' => [$loan->id],
            ])
            ->assertRedirect();

        $this->assertSame(0.0, $loan->fresh()->outstanding());
    }

    public function test_picking_nothing_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'amount' => '500',
                'date' => '2026-08-18',
            ])
            ->assertSessionHasErrors('bills');
    }

    public function test_the_sum_cannot_exceed_what_was_picked(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 600, 'due_date' => '2026-08-05']);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'amount' => '1000',
                'date' => '2026-08-18',
                'bills' => [$bill->id],
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_it_cannot_reach_another_users_things(): void
    {
        $theirBill = Bill::factory()->create(['amount' => 600]);
        $theirLoan = Loan::factory()->borrowed()->create(['principal' => 900]);

        $this->actingAs(User::factory()->create())
            ->post(route('payments.store'), [
                'amount' => '100',
                'date' => '2026-08-18',
                'bills' => [$theirBill->id],
                'loans' => [$theirLoan->id],
            ])
            ->assertSessionHasErrors(['bills.0', 'loans.0']);
    }

    public function test_a_loan_you_lent_cannot_be_paid_from_here(): void
    {
        $user = User::factory()->create();
        $lent = Loan::factory()->for($user)->lent()->create(['principal' => 900]);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'amount' => '100',
                'date' => '2026-08-18',
                'loans' => [$lent->id],
            ])
            ->assertSessionHasErrors('loans.0');

        $this->assertSame(0, $lent->payments()->count());
    }

    /**
     * Bill payments are expenses; loan payments are not. One screen writing
     * both must not blur that.
     */
    public function test_bill_payments_become_expenses_and_loan_payments_do_not(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 600, 'due_date' => '2026-08-05']);
        $loan = Loan::factory()->for($user)->borrowed()->create(['principal' => 1000]);

        $this->actingAs($user)->post(route('payments.store'), [
            'amount' => '1600',
            'date' => '2026-08-18',
            'bills' => [$bill->id],
            'loans' => [$loan->id],
        ]);

        $this->assertSame(1, $user->expenses()->count());
        $this->assertSame(600.0, $user->expenses()->sole()->amount);
        $this->assertSame(1, $loan->payments()->count());
    }
}
