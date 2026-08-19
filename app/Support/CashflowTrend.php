<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What came in against what went out, month by month — the dashboard's
 * headline chart.
 *
 * The two sides are the two ledgers, and nothing else. Loan movements are left
 * out on purpose: borrowing is not earning, a repayment coming back on a loan
 * you made is your own money returning, and either one counted here would show
 * a month as comfortable that was not. They have their own chart.
 *
 * Bill payments *are* counted, once — paying a bill writes an expense, so it
 * is already in the spending side and must not be added again.
 *
 * The surplus is the number the chart exists for: what a month kept. It is
 * left negative when a month spent more than it took in, because that is the
 * thing worth seeing.
 */
final readonly class CashflowTrend
{
    /**
     * @param  array<int, array{month: string, label: string, short_label: string, income: float, spent: float}>  $points  oldest first
     */
    public function __construct(
        public array $points,
        public float $income,
        public float $spent,
    ) {}

    /**
     * The `$months` months ending with (and including) `$endMonth`.
     *
     * Two grouped queries regardless of the window, then filled out in PHP so
     * that a month with nothing on either side still gets its place on the
     * axis.
     */
    public static function forUser(User $user, CarbonImmutable $endMonth, int $months = 12): self
    {
        $window = TrendWindow::ending($endMonth, $months);

        $received = self::monthlyTotals(Income::query()->where('user_id', $user->id), $window);
        $spent = self::monthlyTotals(Expense::query()->where('user_id', $user->id), $window);

        $points = [];
        $incomeTotal = 0.0;
        $spentTotal = 0.0;

        foreach ($window->months() as $month) {
            $key = $month->format('Y-m');
            $monthIncome = 0.0;
            $monthSpent = 0.0;

            if ($window->draws($month)) {
                $monthIncome = round((float) ($received[$key] ?? 0), 2);
                $monthSpent = round((float) ($spent[$key] ?? 0), 2);
            }

            $points[] = [
                'month' => $key,
                'label' => $month->format('F Y'),
                'short_label' => $month->format('M'),
                'income' => $monthIncome,
                'spent' => $monthSpent,
            ];

            $incomeTotal += $monthIncome;
            $spentTotal += $monthSpent;
        }

        return new self(
            points: $points,
            income: round($incomeTotal, 2),
            spent: round($spentTotal, 2),
        );
    }

    /**
     * Sum a ledger by month across the window. Both tables carry `amount` and
     * `date`, so the one query shape serves either.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Collection<array-key, mixed>
     */
    private static function monthlyTotals($query, TrendWindow $window): Collection
    {
        return $query
            ->whereDate('date', '>=', $window->start)
            ->whereDate('date', '<=', $window->horizon())
            ->groupBy('period')
            ->select([MonthPeriod::expression(), DB::raw('SUM(amount) as period_total')])
            ->pluck('period_total', 'period');
    }

    /**
     * What the window kept: everything in, less everything out. Negative when
     * the months spent more than they earned.
     */
    public function surplus(): float
    {
        return round($this->income - $this->spent, 2);
    }

    /**
     * @return array{points: array<int, array{month: string, label: string, short_label: string, income: float, spent: float}>, income: float, spent: float, surplus: float, peak: float}
     */
    public function toArray(): array
    {
        return [
            'points' => $this->points,
            'income' => $this->income,
            'spent' => $this->spent,
            'surplus' => $this->surplus(),
            // Both sides share one axis — comparing their heights is the whole
            // point — so the taller of the two sets the scale.
            'peak' => round((float) max([
                0.0,
                ...array_column($this->points, 'income'),
                ...array_column($this->points, 'spent'),
            ]), 2),
        ];
    }
}
