<?php

namespace App\Actions\Payments;

use App\Actions\Bills\PayBill;
use App\Actions\Loans\RecordLoanPayment;
use App\Models\Bill;
use App\Models\Loan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One lump sum spread across bills *and* loans together.
 *
 * The per-module allocators each answer "I have ₱1,100 and these bills"; this
 * answers the question people actually have on payday — "I have ₱5,000, what
 * does it cover?" — where the electricity bill and the loan installment
 * compete for the same money and the only sensible order is by date.
 *
 * Everything here is measured in *what is due now* — a bill's remainder for the
 * month, a loan's current installment — and the money is handed out in date
 * order until it runs out.
 *
 * Nothing runs ahead. A loan takes its current installment and no more, and a
 * bill's month cannot be more than settled. Paying a debt down faster is a
 * different decision, made on the loan itself, and folding it in here made the
 * totals on this screen impossible to read: a ₱20,000 loan would report ₱20,000
 * owed when only ₱2,000 was actually due.
 */
class AllocateLumpSum
{
    public function __construct(
        private readonly PayBill $payBill,
        private readonly RecordLoanPayment $recordLoanPayment,
    ) {}

    /**
     * @param  Collection<int, Bill>  $bills
     * @param  Collection<int, Loan>  $loans
     * @return array{bills: int, loans: int} how many of each were paid
     */
    public function handle(
        Collection $bills,
        Collection $loans,
        float $amount,
        CarbonImmutable $month,
        CarbonImmutable $date,
    ): array {
        $plan = $this->plan($bills, $loans, $amount, $month);

        $paidBills = 0;
        $paidLoans = 0;

        foreach ($plan as $key => $paying) {
            if ($paying <= 0.0) {
                continue;
            }

            [$type, $id] = explode(':', $key);

            if ($type === 'bill') {
                $bill = $bills->firstWhere('id', (int) $id);

                if ($bill !== null && $this->payBill->handle($bill, $month, $paying) !== null) {
                    $paidBills++;
                }

                continue;
            }

            $loan = $loans->firstWhere('id', (int) $id);

            if ($loan !== null) {
                $this->recordLoanPayment->handle($loan, [
                    'amount' => $paying,
                    'date' => $date->toDateString(),
                    'notes' => null,
                ]);

                $paidLoans++;
            }
        }

        return ['bills' => $paidBills, 'loans' => $paidLoans];
    }

    /**
     * Work out what each thing gets, before anything is written.
     *
     * @param  Collection<int, Bill>  $bills
     * @param  Collection<int, Loan>  $loans
     * @return array<string, float> keyed `bill:{id}` / `loan:{id}`
     */
    public function plan(Collection $bills, Collection $loans, float $amount, CarbonImmutable $month): array
    {
        $entries = $this->entries($bills, $loans, $month);

        $left = round($amount, 2);
        $paying = [];

        // Soonest due first, each taking what it owes now and no more.
        foreach ($entries as $entry) {
            if ($left <= 0.0) {
                break;
            }

            $take = round(min($entry['due_now'], $left), 2);

            if ($take <= 0.0) {
                continue;
            }

            $paying[$entry['key']] = $take;
            $left = round($left - $take, 2);
        }

        return $paying;
    }

    /**
     * Bills and loans flattened into one list, ordered by when they fall due.
     *
     * @param  Collection<int, Bill>  $bills
     * @param  Collection<int, Loan>  $loans
     * @return array<int, array{key: string, type: string, sort: string, due_now: float}>
     */
    private function entries(Collection $bills, Collection $loans, CarbonImmutable $month): array
    {
        $entries = [];

        foreach ($bills as $bill) {
            $remaining = $bill->remainingIn($month);

            if ($remaining <= 0.0) {
                continue;
            }

            $entries[] = [
                'key' => 'bill:'.$bill->id,
                'type' => 'bill',
                'sort' => $bill->dueDateIn($month)->toDateString().'|'.$bill->name,
                'due_now' => $remaining,
            ];
        }

        foreach ($loans as $loan) {
            if ($loan->outstanding() <= 0.0) {
                continue;
            }

            $entries[] = [
                'key' => 'loan:'.$loan->id,
                'type' => 'loan',
                'sort' => $loan->urgencyKey(),
                'due_now' => $loan->amountDueNow(),
            ];
        }

        usort($entries, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $entries;
    }
}
