<?php

namespace App\Http\Controllers;

use App\Actions\Loans\DeleteLoanPayment;
use App\Actions\Loans\RecordLoanPayment;
use App\Http\Requests\Loans\StoreLoanPaymentRequest;
use App\Models\Loan;
use App\Models\LoanPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Payments against a loan.
 *
 * They have no policy of their own: a payment is reached through its loan and
 * authorized against it, and the route's scoped binding is what guarantees the
 * payment in the URL actually belongs to that loan.
 */
class LoanPaymentController extends Controller
{
    public function store(StoreLoanPaymentRequest $request, Loan $loan, RecordLoanPayment $recordLoanPayment): RedirectResponse
    {
        Gate::authorize('update', $loan);

        $recordLoanPayment->handle($loan, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    public function destroy(Loan $loan, LoanPayment $payment, DeleteLoanPayment $deleteLoanPayment): RedirectResponse
    {
        Gate::authorize('update', $loan);

        $deleteLoanPayment->handle($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment removed.')]);

        return back();
    }
}
