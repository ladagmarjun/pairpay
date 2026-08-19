<?php

namespace App\Support;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What fell due in fixed bills each month, and how much of it was settled —
 * the dashboard's second chart.
 *
 * {@see MonthlyTrend} plots everything that was spent. This narrows to the
 * bills and puts the two halves of the question side by side: what the month
 * owed, and what was actually paid against it.
 *
 * A bill stores no history. It is a template that recurs from its first due
 * date onwards, so what a past month owed is reconstructed from the bills that
 * had started by then — at whatever they cost *today*. Editing rent changes
 * every month's `due` on this chart, while `paid` stays as it happened, since
 * that side is read from the expenses the payments wrote.
 */
final readonly class BillTrend
{
    /**
     * @param  array<int, array{month: string, label: string, short_label: string, due: float, paid: float}>  $points  oldest first
     */
    public function __construct(
        public array $points,
        public float $due,
        public float $paid,
    ) {}

    /**
     * The `$months` months ending with (and including) `$endMonth`.
     *
     * Two queries regardless of the window: the bills, which are few and get
     * spread across the months in PHP, and one grouped sum of the payments.
     */
    public static function forUser(User $user, CarbonImmutable $endMonth, int $months = 12): self
    {
        $end = $endMonth->startOfMonth();
        $start = $end->subMonths($months - 1);

        // Bills that had not started by the end of the window cannot have been
        // owed in any month of it.
        $bills = $user->bills()->startedBy($end)->get(['amount', 'due_date']);

        $payments = Expense::query()
            ->where('user_id', $user->id)
            // Paying a bill writes an expense carrying `bill_id`; that is the
            // only record of the payment, so it is also the only thing to sum.
            ->whereNotNull('bill_id')
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end->endOfMonth())
            ->groupBy('period')
            ->select([MonthPeriod::expression(), DB::raw('SUM(amount) as period_total')])
            ->pluck('period_total', 'period');

        $points = [];
        $due = 0.0;
        $paid = 0.0;

        for ($offset = 0; $offset < $months; $offset++) {
            $month = $start->addMonths($offset);
            $key = $month->format('Y-m');
            $endOfMonth = $month->endOfMonth();

            $monthDue = round((float) $bills
                ->filter(fn (Bill $bill): bool => $bill->due_date->lessThanOrEqualTo($endOfMonth))
                ->sum('amount'), 2);

            $monthPaid = round((float) ($payments[$key] ?? 0), 2);

            $points[] = [
                'month' => $key,
                'label' => $month->format('F Y'),
                'short_label' => $month->format('M'),
                'due' => $monthDue,
                'paid' => $monthPaid,
            ];

            $due += $monthDue;
            $paid += $monthPaid;
        }

        return new self(
            points: $points,
            due: round($due, 2),
            paid: round($paid, 2),
        );
    }

    /**
     * @return array{points: array<int, array{month: string, label: string, short_label: string, due: float, paid: float}>, due: float, paid: float, peak: float}
     */
    public function toArray(): array
    {
        return [
            'points' => $this->points,
            'due' => $this->due,
            'paid' => $this->paid,
            // Both series share one axis, so the taller of the two sets the
            // scale — a month paid beyond what it owed still has to fit.
            'peak' => round((float) max([
                0.0,
                ...array_column($this->points, 'due'),
                ...array_column($this->points, 'paid'),
            ]), 2),
        ];
    }
}
