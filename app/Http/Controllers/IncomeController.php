<?php

namespace App\Http\Controllers;

use App\Actions\Income\CreateIncome;
use App\Actions\Income\DeleteIncome;
use App\Actions\Income\UpdateIncome;
use App\Enums\IncomeCategory;
use App\Enums\PaymentMethod;
use App\Http\Requests\Income\StoreIncomeRequest;
use App\Http\Requests\Income\UpdateIncomeRequest;
use App\Models\Income;
use App\Support\IncomeFilter;
use App\Support\MonthInput;
use App\Support\MonthlyIncomeSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Money in: the other half of what the expense tracker records.
 *
 * Laid out the same way as {@see ExpenseController} — monthly summary, search
 * and filters in the query string, CRUD — because it is the same job in the
 * opposite direction, and two ledgers that behaved differently would be two
 * things to learn.
 */
class IncomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = IncomeFilter::fromRequest($request);
        $month = MonthInput::fromRequest($request);

        $income = $user->incomes()
            ->filter($filter)
            ->newestFirst()
            ->paginate(25)
            ->withQueryString()
            ->through($this->present(...));

        return Inertia::render('income/index', [
            'income' => $income,
            'filters' => $filter->toArray(),
            'summary' => MonthlyIncomeSummary::forUser($user, $month)->toArray(),
            'categories' => IncomeCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('income/create', [
            'categories' => IncomeCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function store(StoreIncomeRequest $request, CreateIncome $createIncome): RedirectResponse
    {
        $createIncome->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Income added.')]);

        return to_route('income.index');
    }

    public function edit(Income $income): Response
    {
        Gate::authorize('update', $income);

        return Inertia::render('income/edit', [
            'income' => $this->present($income),
            'categories' => IncomeCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function update(UpdateIncomeRequest $request, Income $income, UpdateIncome $updateIncome): RedirectResponse
    {
        Gate::authorize('update', $income);

        $updateIncome->handle($income, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Income updated.')]);

        return to_route('income.index');
    }

    public function destroy(Income $income, DeleteIncome $deleteIncome): RedirectResponse
    {
        Gate::authorize('delete', $income);

        $deleteIncome->handle($income);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Income deleted.')]);

        return back();
    }

    /**
     * The wire shape of an income entry. Declared explicitly (rather than
     * leaning on model serialization) so the front-end type in
     * `types/income.ts` has a contract it can trust.
     *
     * @return array{id: int, source: string, category: string, amount: float, date: string, notes: string|null, payment_method: string}
     */
    private function present(Income $income): array
    {
        return [
            'id' => $income->id,
            'source' => $income->source,
            'category' => $income->category->value,
            'amount' => $income->amount,
            'date' => $income->date->toDateString(),
            'notes' => $income->notes,
            'payment_method' => $income->payment_method->value,
        ];
    }
}
