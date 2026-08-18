<?php

namespace App\Http\Controllers;

use App\Actions\Loans\AllocateLoanPayments;
use App\Actions\Loans\CreateLoan;
use App\Actions\Loans\DeleteLoan;
use App\Actions\Loans\UpdateLoan;
use App\Enums\LoanDirection;
use App\Http\Requests\Loans\AllocateLoanPaymentsRequest;
use App\Http\Requests\Loans\StoreLoanRequest;
use App\Http\Requests\Loans\UpdateLoanRequest;
use App\Models\Loan;
use App\Models\LoanPayment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loan Management: what you owe, what you are owed, and the payments against
 * each.
 *
 * A loan stores no balance. What is left to pay is the principal minus the
 * payments recorded against it, so the number on screen cannot drift from the
 * rows underneath it — the same reasoning that keeps bills free of a `paid`
 * column.
 *
 * Unlike bills, a payment here is *not* an expense. Half of them are money
 * coming back to you, and this app has no way to represent income; see
 * `create_loan_payments_table`.
 */
class LoanController extends Controller
{
    public function index(Request $request): Response
    {
        // One query for the loans and their payment totals, rather than one
        // per row to work out each balance.
        $loans = $request->user()
            ->loans()
            ->withSum('payments', 'amount')
            ->newestFirst()
            ->get();

        return Inertia::render('loans/index', [
            'loans' => $loans->map($this->present(...))->all(),
            'summary' => $this->summarize($loans),
            'directions' => LoanDirection::options(),
        ]);
    }

    public function show(Loan $loan): Response
    {
        Gate::authorize('view', $loan);

        $loan->load(['payments' => fn ($query) => $query->newestFirst()]);

        $schedule = $loan->schedule();

        return Inertia::render('loans/show', [
            'loan' => $this->present($loan),
            'payments' => $loan->payments->map($this->presentPayment(...))->all(),
            'installments' => $schedule->toArray(),
            // What a "pay this month" button should reach for, so the amount
            // does not have to be worked out by hand.
            'nextInstallment' => $schedule->next(),
            'directions' => LoanDirection::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('loans/create', [
            'directions' => LoanDirection::options(),
        ]);
    }

    public function store(StoreLoanRequest $request, CreateLoan $createLoan): RedirectResponse
    {
        $loan = $createLoan->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan added.')]);

        // Straight to the loan, because the next thing anybody wants is to
        // record a payment against it.
        return to_route('loans.show', $loan);
    }

    public function edit(Loan $loan): Response
    {
        Gate::authorize('update', $loan);

        return Inertia::render('loans/edit', [
            'loan' => $this->present($loan),
            'directions' => LoanDirection::options(),
        ]);
    }

    public function update(UpdateLoanRequest $request, Loan $loan, UpdateLoan $updateLoan): RedirectResponse
    {
        Gate::authorize('update', $loan);

        $updateLoan->handle($loan, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan updated.')]);

        return to_route('loans.show', $loan);
    }

    public function destroy(Loan $loan, DeleteLoan $deleteLoan): RedirectResponse
    {
        Gate::authorize('delete', $loan);

        $deleteLoan->handle($loan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan deleted.')]);

        return to_route('loans.index');
    }

    /**
     * Spread one lump sum across several loans you owe, most pressing first.
     *
     * Authorization is the `exists` rule in the request, scoped to the
     * signed-in user's borrowed loans — anything else never reaches here.
     */
    public function allocate(AllocateLoanPaymentsRequest $request, AllocateLoanPayments $allocate): RedirectResponse
    {
        $written = $allocate->handle(
            $request->selectedLoans(),
            (float) $request->validated('amount'),
            CarbonImmutable::parse((string) $request->validated('date')),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1}Paid one loan.|[2,*]Paid :count loans.',
                count($written),
                ['count' => count($written)],
            ),
        ]);

        return back();
    }

    /**
     * The wire shape of a loan. Declared explicitly (rather than leaning on
     * model serialization) so the front-end type in `types/loans.ts` has a
     * contract it can trust.
     *
     * @return array{id: int, counterparty: string, direction: string, principal: float, paid: float, outstanding: float, due_now: float, status: string, date: string, term_months: int|null, first_due_date: string|null, installment_amount: float|null, next_due_date: string|null, notes: string|null}
     */
    private function present(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'counterparty' => $loan->counterparty,
            'direction' => $loan->direction->value,
            'principal' => $loan->principal,
            'paid' => $loan->paidTotal(),
            'outstanding' => $loan->outstanding(),
            // What it wants right now: this month on a plan, the lot
            // otherwise. The lump-sum split works from this.
            'due_now' => $loan->amountDueNow(),
            'status' => $loan->status()->value,
            'date' => $loan->date->toDateString(),
            'term_months' => $loan->term_months,
            'first_due_date' => $loan->first_due_date?->toDateString(),
            'installment_amount' => $loan->hasSchedule() ? $loan->installmentAmount() : null,
            'next_due_date' => $loan->hasSchedule() ? $loan->schedule()->next()['due_date'] ?? null : null,
            'notes' => $loan->notes,
        ];
    }

    /**
     * @return array{id: int, amount: float, date: string, notes: string|null}
     */
    private function presentPayment(LoanPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'date' => $payment->date->toDateString(),
            'notes' => $payment->notes,
        ];
    }

    /**
     * The two figures that matter, kept apart on purpose: owing ₱5,000 and
     * being owed ₱5,000 is not the same position as owing nothing, so these
     * are never netted off against each other.
     *
     * @param  Collection<int, Loan>  $loans
     * @return array{owed_by_you: float, owed_to_you: float, open_count: int, settled_count: int}
     */
    private function summarize($loans): array
    {
        $open = $loans->filter(fn (Loan $loan): bool => $loan->outstanding() > 0.0);

        return [
            'owed_by_you' => round(
                $open->where('direction', LoanDirection::Borrowed)
                    ->sum(fn (Loan $loan): float => $loan->outstanding()),
                2,
            ),
            'owed_to_you' => round(
                $open->where('direction', LoanDirection::Lent)
                    ->sum(fn (Loan $loan): float => $loan->outstanding()),
                2,
            ),
            'open_count' => $open->count(),
            'settled_count' => $loans->count() - $open->count(),
        ];
    }
}
