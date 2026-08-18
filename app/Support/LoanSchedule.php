<?php

namespace App\Support;

use App\Enums\InstallmentStatus;
use App\Models\Loan;
use Carbon\CarbonImmutable;

/**
 * The month-by-month repayment plan for a loan.
 *
 * Nothing here is stored. The installments are worked out from the principal,
 * the term, and the payments already recorded — so a schedule can never
 * disagree with the money, and editing the loan re-plans it for free.
 *
 * Payments are applied to the earliest unpaid month first. That is what makes
 * a short month stay owed: paying ₱500 against a ₱2,000 installment leaves
 * ₱1,500 against *that* month rather than quietly counting towards the next
 * one, and the term stays where it was while the loan runs behind.
 */
final readonly class LoanSchedule
{
    /**
     * Anything within half a centavo of the installment counts as covering it,
     * so a plan whose months do not divide evenly cannot leave a month one
     * hundredth short forever.
     */
    private const TOLERANCE = 0.005;

    /**
     * @param  array<int, array{number: int, due_date: string, amount: float, paid: float, outstanding: float, status: string}>  $installments
     */
    private function __construct(
        public array $installments,
    ) {}

    public static function forLoan(Loan $loan): self
    {
        if (! $loan->hasSchedule()) {
            return new self([]);
        }

        $term = (int) $loan->term_months;
        $firstDue = $loan->first_due_date;
        $regular = $loan->installmentAmount();
        $paid = $loan->paidTotal();
        $today = CarbonImmutable::today();

        $installments = [];
        $covered = 0.0;

        foreach (range(1, $term) as $number) {
            // The last month absorbs the rounding, so the plan adds up to the
            // principal exactly rather than to a few centavos either side.
            $amount = $number === $term
                ? round($loan->principal - $regular * ($term - 1), 2)
                : $regular;

            $dueDate = $firstDue->addMonthsNoOverflow($number - 1);
            $paidToward = max(0.0, min($amount, round($paid - $covered, 2)));
            $covered = round($covered + $amount, 2);

            $installments[] = [
                'number' => $number,
                'due_date' => $dueDate->toDateString(),
                'amount' => $amount,
                'paid' => $paidToward,
                'outstanding' => round($amount - $paidToward, 2),
                'status' => self::status($amount, $paidToward, $dueDate, $today)->value,
            ];
        }

        return new self($installments);
    }

    /**
     * The next month with anything left on it — what a "pay this month" button
     * should reach for. Null once the plan is fully paid.
     *
     * @return array{number: int, due_date: string, amount: float, paid: float, outstanding: float, status: string}|null
     */
    public function next(): ?array
    {
        foreach ($this->installments as $installment) {
            if ($installment['outstanding'] > 0.0) {
                return $installment;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{number: int, due_date: string, amount: float, paid: float, outstanding: float, status: string}>
     */
    public function toArray(): array
    {
        return $this->installments;
    }

    /**
     * Overdue outranks part paid: a month behind on its date is the thing
     * worth saying, and the amount alongside it shows how far behind.
     */
    private static function status(
        float $amount,
        float $paidToward,
        CarbonImmutable $dueDate,
        CarbonImmutable $today,
    ): InstallmentStatus {
        return match (true) {
            $paidToward >= $amount - self::TOLERANCE => InstallmentStatus::Paid,
            $dueDate->isBefore($today) => InstallmentStatus::Overdue,
            $paidToward > 0.0 => InstallmentStatus::Partial,
            default => InstallmentStatus::Upcoming,
        };
    }
}
