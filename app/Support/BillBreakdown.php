<?php

namespace App\Support;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * One month of bills, bill by bill: what each owed, and how much of it was
 * settled — the dashboard's bills chart.
 *
 * {@see MonthlyTrend} plots the year of spending. This stays on the single
 * month being viewed, since every bill belongs to exactly one month. `paid` is
 * read from the expenses the payments wrote.
 */
final readonly class BillBreakdown
{
    /**
     * @param  array<int, array{id: int, name: string, due_on: string, due: float, paid: float}>  $bills  in due-day order
     */
    public function __construct(
        public CarbonImmutable $month,
        public array $bills,
        public float $due,
        public float $paid,
    ) {}

    /**
     * Two queries: the month's bills, and one grouped sum of what was paid
     * against each of them.
     */
    public static function forUser(User $user, CarbonImmutable $month): self
    {
        $month = $month->startOfMonth();

        $bills = $user->bills()->dueIn($month)->get(['id', 'name', 'amount', 'due_date']);

        $payments = Expense::query()
            ->where('user_id', $user->id)
            // Paying a bill writes an expense carrying `bill_id`; that is the
            // only record of the payment, so it is also the only thing to sum.
            ->whereIn('bill_id', $bills->modelKeys())
            ->groupBy('bill_id')
            ->select(['bill_id', DB::raw('SUM(amount) as paid_total')])
            ->pluck('paid_total', 'bill_id');

        $rows = $bills
            ->map(fn (Bill $bill): array => [
                'id' => $bill->id,
                'name' => $bill->name,
                'due_on' => $bill->due_date->toDateString(),
                'due' => round($bill->amount, 2),
                'paid' => round((float) ($payments[$bill->id] ?? 0), 2),
            ])
            ->sortBy([['due_on', 'asc'], ['name', 'asc']])
            ->values()
            ->all();

        return new self(
            month: $month,
            bills: $rows,
            due: round(array_sum(array_column($rows, 'due')), 2),
            paid: round(array_sum(array_column($rows, 'paid')), 2),
        );
    }

    /**
     * @return array{month: string, label: string, bills: array<int, array{id: int, name: string, due_on: string, due: float, paid: float}>, due: float, paid: float, peak: float}
     */
    public function toArray(): array
    {
        return [
            'month' => $this->month->format('Y-m'),
            'label' => $this->month->format('F Y'),
            'bills' => $this->bills,
            'due' => $this->due,
            'paid' => $this->paid,
            // Both figures share one scale, so the larger of the two sets it —
            // a bill paid beyond what it owed still has to fit.
            'peak' => round((float) max([
                0.0,
                ...array_column($this->bills, 'due'),
                ...array_column($this->bills, 'paid'),
            ]), 2),
        ];
    }
}
