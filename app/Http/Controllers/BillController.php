<?php

namespace App\Http\Controllers;

use App\Actions\Bills\AllocateBillPayments;
use App\Actions\Bills\CreateBill;
use App\Actions\Bills\DeleteBill;
use App\Actions\Bills\PayBill;
use App\Actions\Bills\UnpayBill;
use App\Actions\Bills\UpdateBill;
use App\Enums\BillStatus;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Requests\Bills\AllocateBillPaymentsRequest;
use App\Http\Requests\Bills\StoreBillPaymentRequest;
use App\Http\Requests\Bills\StoreBillRequest;
use App\Http\Requests\Bills\UpdateBillRequest;
use App\Models\Bill;
use App\Support\MonthInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fixed Monthly Expenses: the bills that fall due every month, and whether
 * this month's have been paid.
 *
 * A bill stores no paid/unpaid state. Paying one writes an expense pointing
 * back at it, so "paid" is a question about the expenses table, asked one
 * month at a time — which is why every action here is month-scoped through
 * `?month=YYYY-MM`.
 */
class BillController extends Controller
{
    public function index(Request $request): Response
    {
        $month = MonthInput::fromRequest($request);

        // The payments are constrained to the month up front, so the list costs
        // two queries regardless of how many bills or how long they have run.
        // Sorted after presenting rather than in SQL: the order that matters is
        // the day this bill falls due *in the month being viewed*, which no
        // index can answer, and a person's bills are few enough to sort here.
        $bills = $request->user()
            ->bills()
            ->with(['payments' => fn ($query) => $query->forMonth($month)])
            ->get()
            ->map(fn (Bill $bill): array => $this->present($bill, $month))
            ->sortBy(fn (array $bill): array => [$bill['due_on'], $bill['name']])
            ->values()
            ->all();

        return Inertia::render('bills/index', [
            'bills' => $bills,
            'summary' => $this->summarize($bills, $month),
            'categories' => ExpenseCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('bills/create', [
            'categories' => ExpenseCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function store(StoreBillRequest $request, CreateBill $createBill): RedirectResponse
    {
        $createBill->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill added.')]);

        return to_route('bills.index');
    }

    public function edit(Request $request, Bill $bill): Response
    {
        Gate::authorize('update', $bill);

        return Inertia::render('bills/edit', [
            'bill' => $this->present($bill, MonthInput::fromRequest($request)),
            'categories' => ExpenseCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function update(UpdateBillRequest $request, Bill $bill, UpdateBill $updateBill): RedirectResponse
    {
        Gate::authorize('update', $bill);

        $updateBill->handle($bill, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill updated.')]);

        return to_route('bills.index');
    }

    public function destroy(Bill $bill, DeleteBill $deleteBill): RedirectResponse
    {
        Gate::authorize('delete', $bill);

        $deleteBill->handle($bill);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill deleted.')]);

        return back();
    }

    /**
     * Put money towards this month's bill, which is what writes the expense.
     * With no amount it clears whatever is left; with one it pays part.
     */
    public function pay(StoreBillPaymentRequest $request, Bill $bill, PayBill $payBill): RedirectResponse
    {
        Gate::authorize('update', $bill);

        $amount = $request->validated('amount');

        $payBill->handle(
            $bill,
            MonthInput::fromRequest($request),
            $amount === null ? null : (float) $amount,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    /**
     * Take the last payment back, deleting the expense it wrote. One step at a
     * time, so a month settled twice does not lose both.
     */
    public function unpay(Request $request, Bill $bill, UnpayBill $unpayBill): RedirectResponse
    {
        Gate::authorize('update', $bill);

        $unpayBill->handle($bill, MonthInput::fromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment undone.')]);

        return back();
    }

    /**
     * Spread one lump sum across several bills, oldest due first.
     *
     * Authorization is the `exists` rule in the request, which is scoped to
     * the signed-in user's bills — an id belonging to somebody else never
     * reaches here.
     */
    public function allocate(AllocateBillPaymentsRequest $request, AllocateBillPayments $allocate): RedirectResponse
    {
        $written = $allocate->handle(
            $request->selectedBills(),
            (float) $request->validated('amount'),
            MonthInput::fromRequest($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1}Paid one bill.|[2,*]Paid :count bills.',
                count($written),
                ['count' => count($written)],
            ),
        ]);

        return back();
    }

    /**
     * The wire shape of a bill. Declared explicitly (rather than leaning on
     * model serialization) so the front-end type in `types/bills.ts` has a
     * contract it can trust.
     *
     * There is deliberately only one date here. The stored `due_date` is an
     * anchor whose month and year mean nothing — only its day recurs — so
     * sending it as well would offer two dates for the same bill and let the
     * form show one while the list showed the other.
     *
     * `due_on`, `status`, `paid_amount` and `remaining` are answers about one
     * month, so a presented bill only means anything alongside the month it
     * came from.
     *
     * @return array{id: int, name: string, amount: float, due_on: string, category: string, payment_method: string, notes: string|null, status: string, paid_amount: float, remaining: float}
     */
    private function present(Bill $bill, CarbonImmutable $month): array
    {
        $dueOn = $bill->dueDateIn($month);
        $paid = $bill->paidIn($month);
        $remaining = $bill->remainingIn($month);

        return [
            'id' => $bill->id,
            'name' => $bill->name,
            'amount' => $bill->amount,
            'due_on' => $dueOn->toDateString(),
            'category' => $bill->category->value,
            'payment_method' => $bill->payment_method->value,
            'notes' => $bill->notes,
            'status' => $this->status($dueOn, $paid, $remaining)->value,
            // What was actually paid this month, across however many payments
            // it took. Not the bill's amount, which may have been edited since.
            'paid_amount' => $paid,
            'remaining' => $remaining,
        ];
    }

    /**
     * Overdue outranks part paid: being behind is the thing worth saying, and
     * the amount beside it shows by how much. A future month is never overdue,
     * however long ago the bill was set up.
     */
    private function status(CarbonImmutable $dueOn, float $paid, float $remaining): BillStatus
    {
        return match (true) {
            $remaining <= 0.0 => BillStatus::Paid,
            $dueOn->isBefore(CarbonImmutable::today()) => BillStatus::Overdue,
            $paid > 0.0 => BillStatus::Partial,
            default => BillStatus::Upcoming,
        };
    }

    /**
     * What the month costs in fixed expenses, and how much of it is settled.
     *
     * @param  array<int, array{amount: float, status: string, paid_amount: float, remaining: float}>  $bills
     * @return array{month: string, month_label: string, previous_month: string, next_month: string, count: int, paid_count: int, total: float, paid_total: float, remaining: float}
     */
    private function summarize(array $bills, CarbonImmutable $month): array
    {
        $paid = array_filter($bills, fn (array $bill): bool => $bill['status'] === BillStatus::Paid->value);

        $total = array_sum(array_column($bills, 'amount'));
        // Every peso put towards the month, part payments included — which is
        // why this sums all the bills rather than only the settled ones.
        $paidTotal = array_sum(array_column($bills, 'paid_amount'));

        return [
            'month' => $month->format('Y-m'),
            'month_label' => $month->format('F Y'),
            'previous_month' => $month->subMonth()->format('Y-m'),
            'next_month' => $month->addMonth()->format('Y-m'),

            'count' => count($bills),
            'paid_count' => count($paid),
            'total' => round((float) $total, 2),
            'paid_total' => round($paidTotal, 2),
            // What is still owed this month across every bill.
            'remaining' => round(array_sum(array_column($bills, 'remaining')), 2),
        ];
    }
}
