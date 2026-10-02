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
 * Monthly bills: what falls due in a month, and whether it has been paid.
 *
 * Each bill belongs to the one month its due date falls in — amounts change
 * from month to month, so next month's electricity is its own bill. The list
 * is browsed a month at a time through `?month=YYYY-MM`.
 *
 * A bill stores no paid/unpaid state. Paying one writes an expense pointing
 * back at it, so "paid" is a question about the expenses table.
 */
class BillController extends Controller
{
    public function index(Request $request): Response
    {
        $month = MonthInput::fromRequest($request);

        // Only the bills due in this month. Payments are eager loaded, so the
        // list costs two queries however many bills there are.
        $bills = $request->user()
            ->bills()
            ->dueIn($month)
            ->with('payments')
            ->get()
            ->map(fn (Bill $bill): array => $this->present($bill))
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

    public function create(Request $request): Response
    {
        return Inertia::render('bills/create', [
            // New bills default to the month being viewed, so adding one from
            // September's list lands it in September.
            'month' => MonthInput::fromRequest($request)->format('Y-m'),
            'categories' => ExpenseCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function store(StoreBillRequest $request, CreateBill $createBill): RedirectResponse
    {
        $bill = $createBill->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill added.')]);

        // Back to the month the bill landed in, so it is there on the list.
        return to_route('bills.index', ['month' => $bill->month()->format('Y-m')]);
    }

    public function edit(Bill $bill): Response
    {
        Gate::authorize('update', $bill);

        return Inertia::render('bills/edit', [
            'bill' => $this->present($bill),
            'categories' => ExpenseCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function update(UpdateBillRequest $request, Bill $bill, UpdateBill $updateBill): RedirectResponse
    {
        Gate::authorize('update', $bill);

        $updateBill->handle($bill, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill updated.')]);

        return to_route('bills.index', ['month' => $bill->month()->format('Y-m')]);
    }

    public function destroy(Bill $bill, DeleteBill $deleteBill): RedirectResponse
    {
        Gate::authorize('delete', $bill);

        $deleteBill->handle($bill);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill deleted.')]);

        return back();
    }

    /**
     * Put money towards a bill, which is what writes the expense. With no
     * amount it clears whatever is left; with one it pays part.
     */
    public function pay(StoreBillPaymentRequest $request, Bill $bill, PayBill $payBill): RedirectResponse
    {
        Gate::authorize('update', $bill);

        $amount = $request->validated('amount');

        $payBill->handle($bill, $amount === null ? null : (float) $amount);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    /**
     * Take the last payment back, deleting the expense it wrote. One step at a
     * time, so a bill settled twice does not lose both.
     */
    public function unpay(Bill $bill, UnpayBill $unpayBill): RedirectResponse
    {
        Gate::authorize('update', $bill);

        $unpayBill->handle($bill);

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
     * `due_date` and `due_on` are the same date: a bill falls due once. Both
     * are kept so the form (which edits `due_date`) and the list (which shows
     * `due_on`) keep reading the field they always have.
     *
     * @return array{id: int, name: string, amount: float, due_date: string, due_on: string, category: string, payment_method: string, notes: string|null, status: string, paid_amount: float, remaining: float}
     */
    private function present(Bill $bill): array
    {
        $dueOn = $bill->due_date;
        $paid = $bill->paid();
        $remaining = $bill->remaining();

        return [
            'id' => $bill->id,
            'name' => $bill->name,
            'amount' => $bill->amount,
            'due_date' => $bill->due_date->toDateString(),
            'due_on' => $dueOn->toDateString(),
            'category' => $bill->category->value,
            'payment_method' => $bill->payment_method->value,
            'notes' => $bill->notes,
            'status' => $this->status($dueOn, $paid, $remaining)->value,
            // What was actually paid, across however many payments it took.
            // Not the bill's amount, which may have been edited since.
            'paid_amount' => $paid,
            'remaining' => $remaining,
        ];
    }

    /**
     * Overdue outranks part paid: being behind is the thing worth saying, and
     * the amount beside it shows by how much.
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
     * What the month's bills come to, and how much of it is settled.
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
