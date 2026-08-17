<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\DeleteExpense;
use App\Actions\Expenses\UpdateExpense;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Models\Expense;
use App\Support\ExpenseFilter;
use App\Support\MonthlySummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Personal Expense Tracker: monthly summary, search + filters, and CRUD.
 *
 * Where the mobile app drove the list from a reactive Drift stream, the web app
 * drives it from the query string — the filter is a link, so a filtered view is
 * shareable and survives a refresh.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = ExpenseFilter::fromRequest($request);
        $month = $this->resolveMonth($request);

        $expenses = $user->expenses()
            ->filter($filter)
            ->newestFirst()
            ->paginate(25)
            ->withQueryString()
            ->through($this->present(...));

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'filters' => $filter->toArray(),
            'summary' => MonthlySummary::forUser($user, $month)->toArray(),
            'categories' => ExpenseCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('expenses/create', [
            'categories' => ExpenseCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function store(StoreExpenseRequest $request, CreateExpense $createExpense): RedirectResponse
    {
        $createExpense->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense added.')]);

        return to_route('expenses.index');
    }

    public function edit(Expense $expense): Response
    {
        Gate::authorize('update', $expense);

        return Inertia::render('expenses/edit', [
            'expense' => $this->present($expense),
            'categories' => ExpenseCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense, UpdateExpense $updateExpense): RedirectResponse
    {
        Gate::authorize('update', $expense);

        $updateExpense->handle($expense, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

        return to_route('expenses.index');
    }

    public function destroy(Expense $expense, DeleteExpense $deleteExpense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $deleteExpense->handle($expense);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense deleted.')]);

        return back();
    }

    /**
     * The month shown in the summary card, from `?month=YYYY-MM`. Anything
     * unparseable falls back to the current month rather than erroring.
     */
    private function resolveMonth(Request $request): CarbonImmutable
    {
        $month = $request->string('month')->toString();

        try {
            return $month === ''
                ? CarbonImmutable::now()->startOfMonth()
                : CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }

    /**
     * The wire shape of an expense. Declared explicitly (rather than leaning on
     * model serialization) so the front-end type in `types/expenses.ts` has a
     * contract it can trust.
     *
     * @return array{id: int, title: string, category: string, amount: float, date: string, notes: string|null, payment_method: string}
     */
    private function present(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'title' => $expense->title,
            'category' => $expense->category->value,
            'amount' => $expense->amount,
            'date' => $expense->date->toDateString(),
            'notes' => $expense->notes,
            'payment_method' => $expense->payment_method->value,
        ];
    }
}
