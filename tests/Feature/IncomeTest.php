<?php

namespace Tests\Feature;

use App\Enums\IncomeCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('income.index'))->assertRedirect(route('login'));
    }

    public function test_the_list_only_shows_the_signed_in_users_income(): void
    {
        $user = User::factory()->create();
        $mine = Income::factory()->for($user)->create(['source' => 'My employer']);
        Income::factory()->create(['source' => 'Someone else employer']);

        $response = $this->actingAs($user)->get(route('income.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('income/index')
            ->has('income.data', 1)
            ->where('income.data.0.id', $mine->id));
    }

    public function test_income_can_be_recorded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('income.store'), [
            'source' => '  Acme Corp  ',
            'category' => IncomeCategory::Salary->value,
            'amount' => '30000.00',
            'date' => '2026-08-15',
            'notes' => '   ',
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]);

        $response->assertRedirect(route('income.index'));

        $income = $user->incomes()->sole();

        $this->assertSame('Acme Corp', $income->source, 'The source should be trimmed.');
        $this->assertSame(30000.0, $income->amount);
        $this->assertSame(IncomeCategory::Salary, $income->category);
        $this->assertSame(PaymentMethod::BankTransfer, $income->payment_method);
        $this->assertNull($income->notes, 'A whitespace-only note should be stored as null.');
    }

    public function test_income_requires_a_positive_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('income.store'), [
                'source' => 'Acme Corp',
                'category' => IncomeCategory::Salary->value,
                'amount' => '0',
                'date' => '2026-08-15',
                'payment_method' => PaymentMethod::BankTransfer->value,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Income::query()->count());
    }

    public function test_income_can_be_updated(): void
    {
        $user = User::factory()->create();
        $income = Income::factory()->for($user)->create(['amount' => 100]);

        $this->actingAs($user)
            ->put(route('income.update', $income), [
                'source' => 'Acme Corp',
                'category' => IncomeCategory::Freelance->value,
                'amount' => '2500',
                'date' => '2026-08-15',
                'payment_method' => PaymentMethod::EWallet->value,
            ])
            ->assertRedirect(route('income.index'));

        $income->refresh();

        $this->assertSame(2500.0, $income->amount);
        $this->assertSame(IncomeCategory::Freelance, $income->category);
    }

    public function test_income_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $income = Income::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('income.destroy', $income))
            ->assertRedirect();

        $this->assertSame(0, Income::query()->count());
    }

    /**
     * The policy is the whole of the ownership rule, so it is checked on every
     * verb that can reach someone else's row.
     */
    public function test_a_user_cannot_touch_someone_elses_income(): void
    {
        $user = User::factory()->create();
        $theirs = Income::factory()->create();

        $this->actingAs($user)->get(route('income.edit', $theirs))->assertForbidden();

        $this->actingAs($user)->put(route('income.update', $theirs), [
            'source' => 'Acme Corp',
            'category' => IncomeCategory::Salary->value,
            'amount' => '1',
            'date' => '2026-08-15',
            'payment_method' => PaymentMethod::Cash->value,
        ])->assertForbidden();

        $this->actingAs($user)->delete(route('income.destroy', $theirs))->assertForbidden();
    }

    public function test_the_month_summary_totals_only_that_month(): void
    {
        $user = User::factory()->create();

        Income::factory()->for($user)->create(['amount' => 30000, 'date' => '2026-08-15']);
        Income::factory()->for($user)->create(['amount' => 5000, 'date' => '2026-08-28']);
        Income::factory()->for($user)->create(['amount' => 9999, 'date' => '2026-07-15']);

        $this->actingAs($user)
            ->get(route('income.index', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page
                ->where('summary.total', 35000)
                ->where('summary.entry_count', 2));
    }

    public function test_the_list_can_be_filtered_by_category_and_search(): void
    {
        $user = User::factory()->create();

        Income::factory()->for($user)->create([
            'source' => 'Acme Corp',
            'category' => IncomeCategory::Salary,
        ]);

        Income::factory()->for($user)->create([
            'source' => 'Logo commission',
            'category' => IncomeCategory::Freelance,
        ]);

        $this->actingAs($user)
            ->get(route('income.index', ['category' => IncomeCategory::Freelance->value]))
            ->assertInertia(fn ($page) => $page
                ->has('income.data', 1)
                ->where('income.data.0.source', 'Logo commission'));

        $this->actingAs($user)
            ->get(route('income.index', ['search' => 'acme']))
            ->assertInertia(fn ($page) => $page
                ->has('income.data', 1)
                ->where('income.data.0.source', 'Acme Corp'));
    }

    /**
     * The two ledgers are separate tables for a reason: recording money in must
     * not move any figure that is a statement about money out.
     */
    public function test_income_never_lands_in_the_expense_totals(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 400, 'date' => '2026-08-10']);
        Income::factory()->for($user)->create(['amount' => 30000, 'date' => '2026-08-10']);

        $this->actingAs($user)
            ->get(route('expenses.index', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('summary.total', 400));
    }
}
