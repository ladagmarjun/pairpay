<?php

namespace App\Http\Controllers;

use App\Actions\Payments\AllocateLumpSum;
use App\Enums\LoanDirection;
use App\Http\Requests\Payments\AllocateLumpSumRequest;
use App\Models\Bill;
use App\Models\Loan;
use App\Support\MonthInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pay day: one sum against everything that wants paying.
 *
 * Bills and loans are listed side by side and ordered by date, because that is
 * the only thing they have in common when the money has to be split between
 * them. Money you lent out is not here — this screen is money going out.
 */
class LumpSumPaymentController extends Controller
{
    public function create(Request $request): Response
    {
        $month = MonthInput::fromRequest($request);

        return Inertia::render('payments/lump-sum', [
            'payables' => $this->payables($request, $month),
            // The stepper matters here: which month is being paid decides
            // every bill's due date, and without it this screen could sit on a
            // different month from the one the bills page was showing.
            'month' => [
                'value' => $month->format('Y-m'),
                'label' => $month->format('F Y'),
                'previous' => $month->subMonth()->format('Y-m'),
                'next' => $month->addMonth()->format('Y-m'),
            ],
        ]);
    }

    public function store(AllocateLumpSumRequest $request, AllocateLumpSum $allocate): RedirectResponse
    {
        $month = MonthInput::fromRequest($request);

        $paid = $allocate->handle(
            $request->selectedBills(),
            $request->selectedLoans(),
            (float) $request->validated('amount'),
            CarbonImmutable::parse((string) $request->validated('date')),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Paid :bills and :loans.', [
                'bills' => trans_choice('{0}no bills|{1}one bill|[2,*]:count bills', $paid['bills'], ['count' => $paid['bills']]),
                'loans' => trans_choice('{0}no loans|{1}one loan|[2,*]:count loans', $paid['loans'], ['count' => $paid['loans']]),
            ]),
        ]);

        return to_route('payments.create', ['month' => $month->format('Y-m')]);
    }

    /**
     * Everything with something still owed on it, as one list the front end
     * can render and plan against without knowing the difference.
     *
     * @return array<int, array{type: string, id: int, name: string, detail: string, due_date: string, has_due_date: bool, due_now: float, outstanding: float}>
     */
    private function payables(Request $request, CarbonImmutable $month): array
    {
        $user = $request->user();

        // This month's bills only — the same ones the bills list shows.
        $bills = $user->bills()
            ->dueIn($month)
            ->with('payments')
            ->get()
            ->filter(fn (Bill $bill): bool => $bill->remaining() > 0.0)
            ->map(fn (Bill $bill): array => [
                'type' => 'bill',
                'id' => $bill->id,
                'name' => $bill->name,
                'detail' => $bill->category->label(),
                'due_date' => $bill->due_date->toDateString(),
                'has_due_date' => true,
                'due_now' => $bill->remaining(),
                // A bill cannot be more than settled, so what it owes now and
                // in total are the same number.
                'outstanding' => $bill->remaining(),
            ]);

        $loans = $user->loans()
            ->ofDirection(LoanDirection::Borrowed)
            ->withSum('payments', 'amount')
            ->get()
            ->filter(fn (Loan $loan): bool => $loan->outstanding() > 0.0)
            ->map(fn (Loan $loan): array => [
                'type' => 'loan',
                'id' => $loan->id,
                'name' => $loan->counterparty,
                'detail' => $loan->hasSchedule()
                    ? sprintf(
                        'Installment %d of %d',
                        (int) ($loan->schedule()->next()['number'] ?? $loan->term_months),
                        (int) $loan->term_months,
                    )
                    : 'Borrowed',
                // A loan without a plan is ordered by when it was made, but
                // that is not a due date and must not be shown as one.
                'due_date' => explode('|', $loan->urgencyKey())[0],
                'has_due_date' => $loan->hasSchedule(),
                'due_now' => $loan->amountDueNow(),
                'outstanding' => $loan->outstanding(),
            ]);

        return $bills
            ->concat($loans)
            ->sortBy(fn (array $payable): string => $payable['due_date'].'|'.$payable['name'])
            ->values()
            ->all();
    }
}
