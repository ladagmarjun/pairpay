<?php

namespace App\Actions\Loans;

use App\Models\Loan;
use App\Models\LoanPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Spreads one lump sum across several loans you owe.
 *
 * The counterpart of `AllocateBillPayments`, with one difference that matters:
 * a loan on a repayment plan wants its *month*, not its whole balance. So the
 * money goes round twice —
 *
 *   1. cover what each loan needs right now, most pressing first;
 *   2. anything still in hand goes against the balances, same order.
 *
 * Without the first pass, ₱5,000 across a ₱20,000 loan and a ₱3,000 one would
 * disappear entirely into the first, paying it four months ahead while the
 * other stayed untouched.
 */
class AllocateLoanPayments
{
    public function __construct(private readonly RecordLoanPayment $recordLoanPayment) {}

    /**
     * @param  Collection<int, Loan>  $loans
     * @return array<int, LoanPayment> the payments actually written
     */
    public function handle(Collection $loans, float $amount, CarbonImmutable $date): array
    {
        $ordered = $loans
            ->filter(fn (Loan $loan): bool => $loan->outstanding() > 0.0)
            ->sortBy(fn (Loan $loan): string => $loan->urgencyKey())
            ->values();

        $planned = $this->plan($ordered, round($amount, 2));

        $written = [];

        foreach ($planned as $loanId => $paying) {
            $loan = $ordered->firstWhere('id', $loanId);

            if ($loan === null || $paying <= 0.0) {
                continue;
            }

            $written[] = $this->recordLoanPayment->handle($loan, [
                'amount' => $paying,
                'date' => $date->toDateString(),
                'notes' => null,
            ]);
        }

        return $written;
    }

    /**
     * Work out what each loan gets, before anything is written.
     *
     * @param  Collection<int, Loan>  $loans
     * @return array<int, float> keyed by loan id
     */
    public function plan(Collection $loans, float $amount): array
    {
        $left = round($amount, 2);
        $paying = [];

        // First pass: what each loan owes right now.
        foreach ($loans as $loan) {
            if ($left <= 0.0) {
                break;
            }

            $take = round(min($loan->amountDueNow(), $left), 2);

            if ($take <= 0.0) {
                continue;
            }

            $paying[$loan->id] = $take;
            $left = round($left - $take, 2);
        }

        // Second pass: whatever is still in hand, against the balances.
        foreach ($loans as $loan) {
            if ($left <= 0.0) {
                break;
            }

            $already = $paying[$loan->id] ?? 0.0;
            $take = round(min($loan->outstanding() - $already, $left), 2);

            if ($take <= 0.0) {
                continue;
            }

            $paying[$loan->id] = round($already + $take, 2);
            $left = round($left - $take, 2);
        }

        return $paying;
    }
}
