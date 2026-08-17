<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('expenses.index'))->assertRedirect(route('login'));
    }

    public function test_the_list_only_shows_the_signed_in_users_expenses(): void
    {
        $user = User::factory()->create();
        $mine = Expense::factory()->for($user)->create(['title' => 'My coffee']);
        Expense::factory()->create(['title' => 'Someone else coffee']);

        $response = $this->actingAs($user)->get(route('expenses.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('expenses/index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.id', $mine->id));
    }

    public function test_an_expense_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.store'), [
            'title' => 'Groceries',
            'category' => ExpenseCategory::Food->value,
            'amount' => '42.50',
            'date' => '2026-08-01',
            'notes' => '   ',
            'payment_method' => PaymentMethod::Card->value,
        ]);

        $response->assertRedirect(route('expenses.index'));

        $expense = $user->expenses()->sole();

        $this->assertSame('Groceries', $expense->title);
        $this->assertSame(42.5, $expense->amount);
        $this->assertSame(ExpenseCategory::Food, $expense->category);
        $this->assertSame(PaymentMethod::Card, $expense->payment_method);
        $this->assertNull($expense->notes, 'A whitespace-only note should be stored as null.');
    }

    public function test_an_expense_requires_a_positive_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'title' => 'Groceries',
                'category' => ExpenseCategory::Food->value,
                'amount' => '0',
                'date' => '2026-08-01',
                'payment_method' => PaymentMethod::Cash->value,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_an_expense_can_be_updated(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->for($user)->create(['title' => 'Old title']);

        $this->actingAs($user)
            ->put(route('expenses.update', $expense), [
                'title' => 'New title',
                'category' => ExpenseCategory::Bills->value,
                'amount' => '99.99',
                'date' => '2026-08-02',
                'notes' => 'Paid early',
                'payment_method' => PaymentMethod::BankTransfer->value,
            ])
            ->assertRedirect(route('expenses.index'));

        $expense->refresh();

        $this->assertSame('New title', $expense->title);
        $this->assertSame(99.99, $expense->amount);
        $this->assertSame('Paid early', $expense->notes);
    }

    public function test_an_expense_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->for($user)->create();

        $this->actingAs($user)->delete(route('expenses.destroy', $expense));

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_a_user_cannot_touch_another_users_expense(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create();

        $this->actingAs($user)->get(route('expenses.edit', $expense))->assertForbidden();
        $this->actingAs($user)->delete(route('expenses.destroy', $expense))->assertForbidden();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_the_list_can_be_filtered_by_search_and_category(): void
    {
        $user = User::factory()->create();
        Expense::factory()->for($user)->create([
            'title' => 'Jeepney fare',
            'category' => ExpenseCategory::Transportation,
        ]);
        Expense::factory()->for($user)->create([
            'title' => 'Lunch',
            'category' => ExpenseCategory::Food,
        ]);

        $this->actingAs($user)
            ->get(route('expenses.index', ['search' => 'jeepney']))
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.title', 'Jeepney fare')
                ->where('filters.is_active', true));

        $this->actingAs($user)
            ->get(route('expenses.index', ['category' => ExpenseCategory::Food->value]))
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.title', 'Lunch'));
    }

    public function test_the_monthly_summary_totals_only_the_selected_month(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create([
            'amount' => 100,
            'date' => CarbonImmutable::parse('2026-08-10'),
            'category' => ExpenseCategory::Food,
        ]);
        Expense::factory()->for($user)->create([
            'amount' => 50,
            'date' => CarbonImmutable::parse('2026-08-20'),
            'category' => ExpenseCategory::Bills,
        ]);
        Expense::factory()->for($user)->create([
            'amount' => 999,
            'date' => CarbonImmutable::parse('2026-07-15'),
        ]);

        $this->actingAs($user)
            ->get(route('expenses.index', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page
                // Whole amounts come back from JSON as ints, so compare as ints.
                ->where('summary.total', 150)
                ->where('summary.transaction_count', 2)
                ->where('summary.month_label', 'August 2026')
                // Sorted by descending spend — food (100) leads bills (50).
                ->where('summary.by_category.0.category', ExpenseCategory::Food->value)
                ->where('summary.by_category.0.total', 100));
    }
}
