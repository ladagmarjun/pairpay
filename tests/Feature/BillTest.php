<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Overdue and upcoming are decided against today, so the clock has to
        // stand still for those assertions to mean anything.
        Carbon::setTestNow('2026-08-18');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('bills.index'))->assertRedirect(route('login'));
    }

    public function test_the_list_only_shows_the_signed_in_users_bills(): void
    {
        $user = User::factory()->create();
        $mine = Bill::factory()->for($user)->create(['name' => 'My rent']);
        Bill::factory()->create(['name' => 'Someone else rent']);

        $response = $this->actingAs($user)->get(route('bills.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('bills/index')
            ->has('bills', 1)
            ->where('bills.0.id', $mine->id));
    }

    public function test_a_bill_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('bills.store'), [
            'name' => 'Electricity',
            'amount' => '1250.75',
            'due_date' => '2026-08-15',
            'category' => ExpenseCategory::Bills->value,
            'payment_method' => PaymentMethod::EWallet->value,
            'notes' => '   ',
        ]);

        $response->assertRedirect(route('bills.index'));

        $bill = $user->bills()->sole();

        $this->assertSame('Electricity', $bill->name);
        $this->assertSame(1250.75, $bill->amount);
        $this->assertSame('2026-08-15', $bill->due_date->toDateString());
        $this->assertSame(PaymentMethod::EWallet, $bill->payment_method);
        $this->assertNull($bill->notes, 'A whitespace-only note should be stored as null.');
    }

    public function test_a_bill_requires_a_positive_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('bills.store'), $this->validAttributes(['amount' => '0']))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $user->bills()->count());
    }

    public function test_a_bill_requires_a_real_due_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('bills.store'), $this->validAttributes(['due_date' => 'the 32nd']))
            ->assertSessionHasErrors('due_date');

        $this->assertSame(0, $user->bills()->count());
    }

    public function test_a_bill_can_be_updated(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 500]);

        $this->actingAs($user)
            ->put(route('bills.update', $bill), $this->validAttributes([
                'name' => 'Internet',
                'amount' => '999.00',
            ]))
            ->assertRedirect(route('bills.index'));

        $bill->refresh();

        $this->assertSame('Internet', $bill->name);
        $this->assertSame(999.0, $bill->amount);
    }

    public function test_a_bill_cannot_be_touched_by_another_user(): void
    {
        $bill = Bill::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('bills.edit', $bill))
            ->assertForbidden();
    }

    /**
     * The months already paid are a record of what happened, so they outlive
     * the bill that produced them.
     */
    public function test_deleting_a_bill_keeps_the_expenses_it_produced(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create();

        $this->actingAs($user)->post(route('bills.pay', $bill));

        $this->actingAs($user)
            ->delete(route('bills.destroy', $bill))
            ->assertRedirect();

        $expense = $user->expenses()->sole();

        $this->assertNull($expense->bill_id);
        $this->assertSame(0, Bill::query()->count());
    }

    public function test_marking_a_bill_paid_writes_the_expense_it_becomes(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create([
            'name' => 'Rent',
            'amount' => 8000,
            'due_date' => '2026-08-05',
            'category' => ExpenseCategory::Bills,
            'payment_method' => PaymentMethod::BankTransfer,
        ]);

        $this->actingAs($user)
            ->post(route('bills.pay', $bill))
            ->assertRedirect();

        $expense = $user->expenses()->sole();

        $this->assertSame('Rent', $expense->title);
        $this->assertSame(8000.0, $expense->amount);
        $this->assertSame($bill->id, $expense->bill_id);
        $this->assertSame(PaymentMethod::BankTransfer, $expense->payment_method);
        // Dated on the due day, not today, so an early payment still lands in
        // the month it settles.
        $this->assertSame('2026-08-05', $expense->date->toDateString());
    }

    public function test_paying_the_same_month_twice_does_not_double_count_it(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create();

        $this->actingAs($user)->post(route('bills.pay', $bill));
        $this->actingAs($user)->post(route('bills.pay', $bill));

        $this->assertSame(1, $user->expenses()->count());
    }

    public function test_a_payment_can_be_undone(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create();

        $this->actingAs($user)->post(route('bills.pay', $bill));
        $this->assertSame(1, $user->expenses()->count());

        $this->actingAs($user)
            ->delete(route('bills.unpay', $bill))
            ->assertRedirect();

        $this->assertSame(0, $user->expenses()->count());
    }

    /**
     * Paying August must not mark September paid — every question a bill
     * answers is about one month.
     */
    public function test_paying_one_month_leaves_the_others_unpaid(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['due_date' => '2026-08-10']);

        $this->actingAs($user)->post(route('bills.pay', ['bill' => $bill, 'month' => '2026-08']));

        $this->actingAs($user)
            ->get(route('bills.index', ['month' => '2026-09']))
            ->assertInertia(fn ($page) => $page
                ->where('bills.0.status', 'upcoming')
                ->where('bills.0.paid_amount', 0));

        $this->actingAs($user)
            ->get(route('bills.index', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page->where('bills.0.status', 'paid'));
    }

    public function test_an_unpaid_bill_past_its_due_date_is_overdue(): void
    {
        $user = User::factory()->create();
        Bill::factory()->for($user)->create(['due_date' => '2026-08-01']);

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertInertia(fn ($page) => $page
                ->where('bills.0.status', 'overdue')
                ->where('bills.0.due_on', '2026-08-01'));
    }

    /**
     * A due day of 31 has to mean "the end of the month" in a month that has
     * no 31st, rather than spilling into the next one.
     */
    public function test_a_due_day_longer_than_the_month_clamps_to_its_last_day(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->dueOn('2026-01-31')->create();

        $this->actingAs($user)->post(route('bills.pay', ['bill' => $bill, 'month' => '2026-02']));

        $this->assertSame('2026-02-28', $user->expenses()->sole()->date->toDateString());
    }

    public function test_the_summary_counts_what_is_still_owed_this_month(): void
    {
        $user = User::factory()->create();
        $paid = Bill::factory()->for($user)->create(['amount' => 1000.50, 'due_date' => '2026-08-01']);
        Bill::factory()->for($user)->create(['amount' => 250.25, 'due_date' => '2026-08-02']);

        $this->actingAs($user)->post(route('bills.pay', $paid));

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.count', 2)
                ->where('summary.paid_count', 1)
                ->where('summary.total', 1250.75)
                ->where('summary.paid_total', 1000.50)
                ->where('summary.remaining', 250.25));
    }

    /**
     * A hand-edited month should not produce a 500.
     */
    public function test_an_unparseable_month_falls_back_to_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('bills.index', ['month' => 'not-a-month']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.month', '2026-08'));
    }

    /**
     * Bills produce ordinary expenses, so the month's total has to include
     * them without the summary knowing bills exist.
     */
    public function test_a_paid_bill_shows_up_in_the_monthly_expense_summary(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 1500.50, 'due_date' => '2026-08-08']);

        $this->actingAs($user)->post(route('bills.pay', $bill));

        $this->actingAs($user)
            ->get(route('expenses.index'))
            ->assertInertia(fn ($page) => $page->where('summary.total', 1500.50));
    }

    public function test_a_bill_cannot_be_paid_by_another_user(): void
    {
        $bill = Bill::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('bills.pay', $bill))
            ->assertForbidden();

        $this->assertSame(0, Expense::query()->count());
    }

    /**
     * The case this exists for: ₱900 of electricity and ₱600 of water are due
     * and there is ₱1,100 to pay. Before part payments the ₱500 that went to
     * electricity had nowhere to go.
     */
    public function test_a_bill_can_be_paid_in_part(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create([
            'name' => 'Electricity',
            'amount' => 900,
            'due_date' => '2026-08-25',
        ]);

        $this->actingAs($user)
            ->post(route('bills.pay', $bill), ['amount' => '500'])
            ->assertRedirect();

        $expense = $user->expenses()->sole();

        $this->assertSame(500.0, $expense->amount, 'The expense should record what was actually paid.');

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertInertia(fn ($page) => $page
                ->where('bills.0.status', 'partial')
                ->where('bills.0.paid_amount', 500)
                ->where('bills.0.remaining', 400));
    }

    public function test_paying_the_rest_settles_the_month(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 900, 'due_date' => '2026-08-10']);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['amount' => '500']);
        $this->actingAs($user)->post(route('bills.pay', $bill), ['amount' => '400']);

        $this->assertSame(2, $user->expenses()->count(), 'Each payment is its own expense.');

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertInertia(fn ($page) => $page
                ->where('bills.0.status', 'paid')
                ->where('bills.0.remaining', 0));
    }

    public function test_a_payment_cannot_exceed_what_is_left_on_the_month(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 900, 'due_date' => '2026-08-10']);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['amount' => '500']);

        $this->actingAs($user)
            ->post(route('bills.pay', $bill), ['amount' => '500'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, $user->expenses()->count());
    }

    /**
     * Undo steps back one payment: a month settled twice should not lose both
     * because the second was a typo.
     */
    public function test_undo_removes_only_the_last_payment(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 900, 'due_date' => '2026-08-10']);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['amount' => '500']);
        $this->actingAs($user)->post(route('bills.pay', $bill), ['amount' => '400']);

        $this->actingAs($user)->delete(route('bills.unpay', $bill));

        $this->assertSame(1, $user->expenses()->count());
        $this->assertSame(500.0, $user->expenses()->sole()->amount);
    }

    /**
     * Overdue outranks part paid — being behind is the thing worth saying.
     */
    public function test_a_part_paid_month_past_its_date_reads_as_overdue(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 900, 'due_date' => '2026-08-01']);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['amount' => '100']);

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertInertia(fn ($page) => $page->where('bills.0.status', 'overdue'));
    }

    /**
     * ₱1,100 against ₱900 of electricity and ₱600 of water: water is due
     * first, so it clears, and electricity takes the remaining ₱500.
     */
    public function test_a_lump_sum_is_split_across_bills_oldest_due_first(): void
    {
        $user = User::factory()->create();
        $water = Bill::factory()->for($user)->create([
            'name' => 'Water',
            'amount' => 600,
            'due_date' => '2026-08-05',
        ]);
        $electricity = Bill::factory()->for($user)->create([
            'name' => 'Electricity',
            'amount' => 900,
            'due_date' => '2026-08-20',
        ]);

        $this->actingAs($user)
            ->post(route('bills.allocate'), [
                'amount' => '1100',
                'bills' => [$water->id, $electricity->id],
            ])
            ->assertRedirect();

        $this->assertSame(600.0, $water->paidIn(CarbonImmutable::parse('2026-08-01')));
        $this->assertSame(500.0, $electricity->paidIn(CarbonImmutable::parse('2026-08-01')));

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.paid_total', 1100)
                ->where('summary.remaining', 400));
    }

    public function test_a_lump_sum_cannot_exceed_what_the_chosen_bills_owe(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['amount' => 600, 'due_date' => '2026-08-05']);

        $this->actingAs($user)
            ->post(route('bills.allocate'), [
                'amount' => '1000',
                'bills' => [$bill->id],
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $user->expenses()->count());
    }

    /**
     * Only the bills that were ticked get paid, however much is left over.
     */
    public function test_a_lump_sum_only_touches_the_bills_it_was_given(): void
    {
        $user = User::factory()->create();
        $water = Bill::factory()->for($user)->create(['amount' => 600, 'due_date' => '2026-08-05']);
        $electricity = Bill::factory()->for($user)->create(['amount' => 900, 'due_date' => '2026-08-20']);

        $this->actingAs($user)->post(route('bills.allocate'), [
            'amount' => '600',
            'bills' => [$water->id],
        ]);

        $month = CarbonImmutable::parse('2026-08-01');

        $this->assertSame(600.0, $water->paidIn($month));
        $this->assertSame(0.0, $electricity->paidIn($month));
    }

    public function test_a_lump_sum_cannot_reach_another_users_bills(): void
    {
        $user = User::factory()->create();
        $theirs = Bill::factory()->create(['amount' => 600]);

        $this->actingAs($user)
            ->post(route('bills.allocate'), [
                'amount' => '100',
                'bills' => [$theirs->id],
            ])
            ->assertSessionHasErrors('bills.0');

        $this->assertSame(0, Expense::query()->count());
    }

    /**
     * The edit form and the list must show the same date. They did not: the
     * form was handed the stored anchor (the month the bill was set up in)
     * while the list showed the day resolved against the month in view, so a
     * bill created in March read "Aug 25" in the list and "25 March" in the
     * form.
     */
    public function test_the_edit_form_shows_the_same_date_as_the_list(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['due_date' => '2026-03-25']);

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertInertia(fn ($page) => $page->where('bills.0.due_on', '2026-08-25'));

        $this->actingAs($user)
            ->get(route('bills.edit', $bill))
            ->assertInertia(fn ($page) => $page->where('bill.due_on', '2026-08-25'));
    }

    /**
     * Editing from another month opens on that month, so the two never
     * disagree wherever you came from.
     */
    public function test_the_edit_form_follows_the_month_it_was_opened_from(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['due_date' => '2026-03-25']);

        $this->actingAs($user)
            ->get(route('bills.edit', ['bill' => $bill, 'month' => '2026-11']))
            ->assertInertia(fn ($page) => $page->where('bill.due_on', '2026-11-25'));
    }

    /**
     * Changing the day moves the date the list shows; changing the month does
     * not, because only the day recurs.
     */
    public function test_editing_the_day_moves_the_due_date(): void
    {
        $user = User::factory()->create();
        $bill = Bill::factory()->for($user)->create(['due_date' => '2026-08-25']);

        $this->actingAs($user)->put(route('bills.update', $bill), $this->validAttributes([
            'due_date' => '2026-08-09',
        ]));

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertInertia(fn ($page) => $page->where('bills.0.due_on', '2026-08-09'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAttributes(array $overrides = []): array
    {
        return [
            'name' => 'Electricity',
            'amount' => '1200.00',
            'due_date' => '2026-08-15',
            'category' => ExpenseCategory::Bills->value,
            'payment_method' => PaymentMethod::Cash->value,
            ...$overrides,
        ];
    }
}
