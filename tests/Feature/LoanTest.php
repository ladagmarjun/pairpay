<?php

namespace Tests\Feature;

use App\Enums\LoanDirection;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Overdue is decided against today, so the clock has to stand still
        // for the schedule assertions to mean anything.
        Carbon::setTestNow('2026-08-18');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('loans.index'))->assertRedirect(route('login'));
    }

    public function test_the_list_only_shows_the_signed_in_users_loans(): void
    {
        $user = User::factory()->create();
        $mine = Loan::factory()->for($user)->create(['counterparty' => 'My cousin']);
        Loan::factory()->create(['counterparty' => 'Someone else cousin']);

        $response = $this->actingAs($user)->get(route('loans.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('loans/index')
            ->has('loans', 1)
            ->where('loans.0.id', $mine->id));
    }

    public function test_a_loan_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('loans.store'), [
            'counterparty' => 'Ana',
            'direction' => LoanDirection::Lent->value,
            'principal' => '5000.50',
            'date' => '2026-08-01',
            'notes' => '   ',
        ]);

        $loan = $user->loans()->sole();

        $response->assertRedirect(route('loans.show', $loan));

        $this->assertSame('Ana', $loan->counterparty);
        $this->assertSame(5000.50, $loan->principal);
        $this->assertSame(LoanDirection::Lent, $loan->direction);
        $this->assertNull($loan->notes, 'A whitespace-only note should be stored as null.');
    }

    public function test_a_loan_requires_a_positive_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('loans.store'), $this->validAttributes(['principal' => '0']))
            ->assertSessionHasErrors('principal');

        $this->assertSame(0, $user->loans()->count());
    }

    public function test_a_loan_requires_a_direction(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('loans.store'), $this->validAttributes(['direction' => 'sideways']))
            ->assertSessionHasErrors('direction');
    }

    public function test_a_loan_can_be_updated(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create(['principal' => 1000]);

        $this->actingAs($user)
            ->put(route('loans.update', $loan), $this->validAttributes([
                'counterparty' => 'Ben',
                'principal' => '2500.00',
            ]))
            ->assertRedirect(route('loans.show', $loan));

        $loan->refresh();

        $this->assertSame('Ben', $loan->counterparty);
        $this->assertSame(2500.0, $loan->principal);
    }

    public function test_a_loan_cannot_be_touched_by_another_user(): void
    {
        $loan = Loan::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('loans.show', $loan))->assertForbidden();
        $this->actingAs($other)->get(route('loans.edit', $loan))->assertForbidden();
    }

    /**
     * The balance is the principal minus the payments, so recording one is the
     * whole of the update — there is no stored total to keep in step.
     */
    public function test_a_payment_reduces_what_is_outstanding(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create(['principal' => 1000]);

        $this->actingAs($user)
            ->post(route('loans.payments.store', $loan), [
                'amount' => '250.50',
                'date' => '2026-08-10',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('loans.show', $loan))
            ->assertInertia(fn ($page) => $page
                ->component('loans/show')
                ->where('loan.paid', 250.50)
                ->where('loan.outstanding', 749.50)
                ->where('loan.status', 'partial')
                ->has('payments', 1));
    }

    public function test_paying_the_balance_off_settles_the_loan(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create(['principal' => 800]);

        $this->actingAs($user)->post(route('loans.payments.store', $loan), [
            'amount' => '800',
            'date' => '2026-08-10',
        ]);

        $this->actingAs($user)
            ->get(route('loans.show', $loan))
            ->assertInertia(fn ($page) => $page
                ->where('loan.outstanding', 0)
                ->where('loan.status', 'settled'));
    }

    /**
     * Overpaying is a typo: the balance has nowhere to put the difference.
     */
    public function test_a_payment_cannot_exceed_what_is_left_to_pay(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create(['principal' => 500]);

        $this->actingAs($user)
            ->post(route('loans.payments.store', $loan), [
                'amount' => '500.01',
                'date' => '2026-08-10',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $loan->payments()->count());
    }

    public function test_a_payment_can_be_removed(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create(['principal' => 1000]);
        $payment = LoanPayment::factory()->for($loan)->create(['amount' => 400]);

        $this->actingAs($user)
            ->delete(route('loans.payments.destroy', [$loan, $payment]))
            ->assertRedirect();

        $this->assertSame(0, $loan->payments()->count());
        $this->assertSame(1000.0, $loan->fresh()->outstanding());
    }

    /**
     * The scoped binding is what stops a payment id from one loan being used
     * against another.
     */
    public function test_a_payment_cannot_be_removed_through_a_different_loan(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create();
        $otherLoan = Loan::factory()->for($user)->create();
        $payment = LoanPayment::factory()->for($loan)->create();

        $this->actingAs($user)
            ->delete(route('loans.payments.destroy', [$otherLoan, $payment]))
            ->assertNotFound();

        $this->assertSame(1, $loan->payments()->count());
    }

    public function test_payments_cannot_be_recorded_by_another_user(): void
    {
        $loan = Loan::factory()->create(['principal' => 1000]);

        $this->actingAs(User::factory()->create())
            ->post(route('loans.payments.store', $loan), [
                'amount' => '100',
                'date' => '2026-08-10',
            ])
            ->assertForbidden();

        $this->assertSame(0, $loan->payments()->count());
    }

    public function test_deleting_a_loan_takes_its_payments_with_it(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create();
        LoanPayment::factory()->for($loan)->create();

        $this->actingAs($user)
            ->delete(route('loans.destroy', $loan))
            ->assertRedirect(route('loans.index'));

        $this->assertSame(0, Loan::query()->count());
        $this->assertSame(0, LoanPayment::query()->count());
    }

    /**
     * Owing and being owed are different positions, so the two totals are
     * never netted off against each other.
     */
    public function test_the_summary_keeps_the_two_directions_apart(): void
    {
        $user = User::factory()->create();
        Loan::factory()->for($user)->borrowed()->create(['principal' => 3000.75]);
        Loan::factory()->for($user)->lent()->create(['principal' => 1200.50]);

        $this->actingAs($user)
            ->get(route('loans.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.owed_by_you', 3000.75)
                ->where('summary.owed_to_you', 1200.50)
                ->where('summary.open_count', 2)
                ->where('summary.settled_count', 0));
    }

    public function test_a_settled_loan_drops_out_of_the_outstanding_totals(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->borrowed()->create(['principal' => 500]);
        LoanPayment::factory()->for($loan)->create(['amount' => 500]);

        $this->actingAs($user)
            ->get(route('loans.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.owed_by_you', 0)
                ->where('summary.open_count', 0)
                ->where('summary.settled_count', 1));
    }

    /**
     * Lowering the principal below what was already paid should settle the
     * loan, not make it owe a negative amount.
     */
    public function test_an_overpaid_loan_reads_as_settled_rather_than_negative(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create(['principal' => 1000]);
        LoanPayment::factory()->for($loan)->create(['amount' => 1000]);

        $this->actingAs($user)->put(route('loans.update', $loan), $this->validAttributes([
            'principal' => '600',
        ]));

        $this->assertSame(0.0, $loan->fresh()->outstanding());
    }

    /**
     * Loan payments are deliberately not expenses — half of them are money
     * coming back, which the expense list cannot represent.
     */
    public function test_a_loan_payment_never_becomes_an_expense(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create(['principal' => 1000]);

        $this->actingAs($user)->post(route('loans.payments.store', $loan), [
            'amount' => '400',
            'date' => '2026-08-10',
        ]);

        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_a_loan_can_be_given_a_repayment_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('loans.store'), $this->validAttributes([
            'principal' => '1000',
            'term_months' => '3',
            'first_due_date' => '2026-09-15',
        ]));

        $loan = $user->loans()->sole();

        $this->assertTrue($loan->hasSchedule());
        $this->assertSame(3, $loan->term_months);
        $this->assertSame('2026-09-15', $loan->first_due_date->toDateString());
    }

    public function test_a_term_without_a_start_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('loans.store'), $this->validAttributes(['term_months' => '6']))
            ->assertSessionHasErrors('first_due_date');

        $this->assertSame(0, $user->loans()->count());
    }

    /**
     * The months land on the same day each month, and the last one absorbs
     * whatever the division left over so the plan adds up to the principal.
     */
    public function test_the_schedule_divides_the_principal_across_the_term(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create([
            'principal' => 1000,
            'term_months' => 3,
            'first_due_date' => '2026-09-15',
        ]);

        $installments = $loan->schedule()->toArray();

        $this->assertCount(3, $installments);
        $this->assertSame(333.33, $installments[0]['amount']);
        $this->assertSame(333.33, $installments[1]['amount']);
        $this->assertSame(333.34, $installments[2]['amount'], 'The last month should absorb the rounding.');
        $this->assertSame(
            1000.0,
            round(array_sum(array_column($installments, 'amount')), 2),
        );
        $this->assertSame('2026-09-15', $installments[0]['due_date']);
        $this->assertSame('2026-10-15', $installments[1]['due_date']);
        $this->assertSame('2026-11-15', $installments[2]['due_date']);
    }

    /**
     * A first payment due on the 31st has to clamp in the months that have no
     * 31st, rather than rolling into the next one.
     */
    public function test_a_month_too_short_for_the_due_day_clamps(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create([
            'principal' => 300,
            'term_months' => 3,
            'first_due_date' => '2026-12-31',
        ]);

        $dates = array_column($loan->schedule()->toArray(), 'due_date');

        $this->assertSame(['2026-12-31', '2027-01-31', '2027-02-28'], $dates);
    }

    /**
     * Payments fill the earliest unpaid month first, which is what makes a
     * short month stay owed instead of being carried forward.
     */
    public function test_a_short_payment_leaves_that_month_still_owing(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create([
            'principal' => 6000,
            'term_months' => 3,
            'first_due_date' => '2026-09-10',
        ]);

        $this->actingAs($user)->post(route('loans.payments.store', $loan), [
            'amount' => '500',
            'date' => '2026-09-10',
        ]);

        $installments = $loan->fresh()->schedule()->toArray();

        $this->assertSame(500.0, $installments[0]['paid']);
        $this->assertSame(1500.0, $installments[0]['outstanding']);
        $this->assertSame('partial', $installments[0]['status']);
        $this->assertSame(0.0, $installments[1]['paid'], 'Nothing should spill into the next month.');
        $this->assertSame('upcoming', $installments[1]['status']);
    }

    public function test_covering_a_month_in_full_marks_only_that_month_paid(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create([
            'principal' => 6000,
            'term_months' => 3,
            'first_due_date' => '2026-09-10',
        ]);

        $this->actingAs($user)->post(route('loans.payments.store', $loan), [
            'amount' => '2000',
            'date' => '2026-09-10',
        ]);

        $installments = $loan->fresh()->schedule()->toArray();

        $this->assertSame('paid', $installments[0]['status']);
        $this->assertSame('upcoming', $installments[1]['status']);
    }

    public function test_a_month_past_its_date_with_nothing_paid_is_overdue(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create([
            'principal' => 4000,
            'term_months' => 2,
            'first_due_date' => '2026-07-01',
        ]);

        $installments = $loan->schedule()->toArray();

        $this->assertSame('overdue', $installments[0]['status']);
        $this->assertSame('overdue', $installments[1]['status'], 'August 1 is also behind us.');
    }

    /**
     * Overdue outranks part paid — being behind is the thing worth saying.
     */
    public function test_a_part_paid_month_that_is_late_reads_as_overdue(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create([
            'principal' => 4000,
            'term_months' => 2,
            'first_due_date' => '2026-07-01',
        ]);
        LoanPayment::factory()->for($loan)->create(['amount' => 500]);

        $installments = $loan->fresh()->schedule()->toArray();

        $this->assertSame('overdue', $installments[0]['status']);
        $this->assertSame(500.0, $installments[0]['paid']);
    }

    public function test_the_next_installment_is_the_first_with_anything_left(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create([
            'principal' => 6000,
            'term_months' => 3,
            'first_due_date' => '2026-09-10',
        ]);
        LoanPayment::factory()->for($loan)->create(['amount' => 2000]);

        $this->actingAs($user)
            ->get(route('loans.show', $loan))
            ->assertInertia(fn ($page) => $page
                ->has('installments', 3)
                ->where('nextInstallment.number', 2)
                ->where('nextInstallment.due_date', '2026-10-10')
                ->where('nextInstallment.outstanding', 2000));
    }

    public function test_a_fully_paid_plan_has_no_next_installment(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create([
            'principal' => 2000,
            'term_months' => 2,
            'first_due_date' => '2026-09-10',
        ]);
        LoanPayment::factory()->for($loan)->create(['amount' => 2000]);

        $this->actingAs($user)
            ->get(route('loans.show', $loan))
            ->assertInertia(fn ($page) => $page->where('nextInstallment', null));
    }

    /**
     * A loan without a plan is the informal kind, and must keep working.
     */
    public function test_a_loan_without_a_plan_has_no_schedule(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->create(['term_months' => null, 'first_due_date' => null]);

        $this->assertFalse($loan->hasSchedule());

        $this->actingAs($user)
            ->get(route('loans.show', $loan))
            ->assertInertia(fn ($page) => $page
                ->has('installments', 0)
                ->where('nextInstallment', null)
                ->where('loan.next_due_date', null));
    }

    /**
     * The point of the two passes: ₱5,000 across a big loan on a plan and a
     * small informal one should cover both, not vanish into the first.
     */
    public function test_a_lump_sum_covers_what_each_loan_owes_now_first(): void
    {
        $user = User::factory()->create();

        $planned = Loan::factory()->for($user)->borrowed()->create([
            'counterparty' => 'Bank',
            'principal' => 20000,
            'term_months' => 10,
            'first_due_date' => '2026-08-01',
        ]);

        $informal = Loan::factory()->for($user)->borrowed()->create([
            'counterparty' => 'Ana',
            'principal' => 3000,
            'date' => '2026-08-02',
            'term_months' => null,
            'first_due_date' => null,
        ]);

        $this->actingAs($user)
            ->post(route('loans.allocate'), [
                'amount' => '5000',
                'date' => '2026-08-18',
                'loans' => [$planned->id, $informal->id],
            ])
            ->assertRedirect();

        // The plan wants ₱2,000 this month; the informal loan wants all ₱3,000.
        $this->assertSame(2000.0, $planned->fresh()->paidTotal());
        $this->assertSame(3000.0, $informal->fresh()->paidTotal());
    }

    /**
     * Anything left after every loan's month is covered goes against the
     * balances, in the same order.
     */
    public function test_money_left_over_goes_against_the_balances(): void
    {
        $user = User::factory()->create();

        $planned = Loan::factory()->for($user)->borrowed()->create([
            'counterparty' => 'Bank',
            'principal' => 20000,
            'term_months' => 10,
            'first_due_date' => '2026-08-01',
        ]);

        $informal = Loan::factory()->for($user)->borrowed()->create([
            'counterparty' => 'Ana',
            'principal' => 3000,
            'date' => '2026-08-02',
            'term_months' => null,
            'first_due_date' => null,
        ]);

        $this->actingAs($user)->post(route('loans.allocate'), [
            'amount' => '8000',
            'date' => '2026-08-18',
            'loans' => [$planned->id, $informal->id],
        ]);

        // ₱2,000 + ₱3,000 covers both months; the spare ₱3,000 goes to the
        // loan due soonest, which is the plan.
        $this->assertSame(5000.0, $planned->fresh()->paidTotal());
        $this->assertSame(3000.0, $informal->fresh()->paidTotal());
    }

    public function test_a_lump_sum_settles_a_loan_it_fully_covers(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->borrowed()->create(['principal' => 1500]);

        $this->actingAs($user)->post(route('loans.allocate'), [
            'amount' => '1500',
            'date' => '2026-08-18',
            'loans' => [$loan->id],
        ]);

        $this->assertSame(0.0, $loan->fresh()->outstanding());
        $this->assertSame('settled', $loan->fresh()->status()->value);
    }

    public function test_a_lump_sum_cannot_exceed_what_the_chosen_loans_owe(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->for($user)->borrowed()->create(['principal' => 1000]);

        $this->actingAs($user)
            ->post(route('loans.allocate'), [
                'amount' => '1500',
                'date' => '2026-08-18',
                'loans' => [$loan->id],
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $loan->payments()->count());
    }

    /**
     * Paying a lump sum is money going out, so a loan you made cannot be on
     * the receiving end of one.
     */
    public function test_a_lump_sum_cannot_be_aimed_at_a_loan_you_lent(): void
    {
        $user = User::factory()->create();
        $lent = Loan::factory()->for($user)->lent()->create(['principal' => 1000]);

        $this->actingAs($user)
            ->post(route('loans.allocate'), [
                'amount' => '500',
                'date' => '2026-08-18',
                'loans' => [$lent->id],
            ])
            ->assertSessionHasErrors('loans.0');

        $this->assertSame(0, $lent->payments()->count());
    }

    public function test_a_lump_sum_cannot_reach_another_users_loans(): void
    {
        $theirs = Loan::factory()->borrowed()->create(['principal' => 1000]);

        $this->actingAs(User::factory()->create())
            ->post(route('loans.allocate'), [
                'amount' => '500',
                'date' => '2026-08-18',
                'loans' => [$theirs->id],
            ])
            ->assertSessionHasErrors('loans.0');

        $this->assertSame(0, LoanPayment::query()->count());
    }

    public function test_a_lump_sum_only_touches_the_loans_it_was_given(): void
    {
        $user = User::factory()->create();
        $paid = Loan::factory()->for($user)->borrowed()->create(['principal' => 800]);
        $untouched = Loan::factory()->for($user)->borrowed()->create(['principal' => 900]);

        $this->actingAs($user)->post(route('loans.allocate'), [
            'amount' => '800',
            'date' => '2026-08-18',
            'loans' => [$paid->id],
        ]);

        $this->assertSame(800.0, $paid->fresh()->paidTotal());
        $this->assertSame(0.0, $untouched->fresh()->paidTotal());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAttributes(array $overrides = []): array
    {
        return [
            'counterparty' => 'Ana',
            'direction' => LoanDirection::Borrowed->value,
            'principal' => '1000.00',
            'date' => '2026-08-01',
            ...$overrides,
        ];
    }
}
