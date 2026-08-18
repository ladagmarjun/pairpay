<?php

namespace App\Actions\Bills;

use App\Models\Bill;
use App\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Spreads one lump sum across several bills.
 *
 * The case this exists for: ₱900 of electricity and ₱600 of water are due, and
 * there is ₱1,100 to pay. Left to do by hand that is three sums and two
 * screens; here it is one number.
 *
 * Bills are settled in the order they fall due, each taking what it still
 * needs until the money runs out — so the oldest debt clears first and at most
 * one bill ends up part paid.
 */
class AllocateBillPayments
{
    public function __construct(private readonly PayBill $payBill) {}

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array<int, Expense> the payments actually written
     */
    public function handle(Collection $bills, float $amount, CarbonImmutable $month): array
    {
        $left = round($amount, 2);
        $written = [];

        // One callable, so the collection sorts by the value it returns. The
        // name is folded into the key rather than passed as a second sort:
        // `sortBy` treats an array of closures as comparators, not extractors.
        $ordered = $bills->sortBy(
            fn (Bill $bill): string => $bill->dueDateIn($month)->toDateString().'|'.$bill->name,
        );

        foreach ($ordered as $bill) {
            if ($left <= 0.0) {
                break;
            }

            $paying = min($bill->remainingIn($month), $left);

            if ($paying <= 0.0) {
                continue;
            }

            $payment = $this->payBill->handle($bill, $month, $paying);

            if ($payment !== null) {
                $written[] = $payment;
                $left = round($left - $paying, 2);
            }
        }

        return $written;
    }
}
