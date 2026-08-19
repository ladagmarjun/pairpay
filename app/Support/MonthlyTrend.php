<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Spend per month across the year — the dashboard's trend chart.
 *
 * Where {@see MonthlySummary} answers "what did this month cost, and on what",
 * this answers "how does this month compare with the ones before it". Months
 * with no expenses are still returned, at zero: a gap in the bars is itself the
 * information, and dropping the row would silently close it up.
 *
 * Which months those are, and where the year stops being history, is
 * {@see TrendWindow}.
 */
final readonly class MonthlyTrend
{
    /**
     * @param  array<int, array{month: string, label: string, short_label: string, total: float}>  $points  oldest first
     */
    public function __construct(
        public array $points,
        public float $total,
        public float $average,
    ) {}

    /**
     * The `$months` months ending with (and including) `$endMonth`.
     *
     * One grouped query regardless of the window, then filled out in PHP so
     * that an empty month still gets a bar.
     */
    public static function forUser(User $user, CarbonImmutable $endMonth, int $months = 12): self
    {
        $window = TrendWindow::ending($endMonth, $months);

        $totals = Expense::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $window->start)
            ->whereDate('date', '<=', $window->horizon())
            ->groupBy('period')
            ->select([MonthPeriod::expression(), DB::raw('SUM(amount) as period_total')])
            ->pluck('period_total', 'period');

        $points = [];
        $total = 0.0;

        foreach ($window->months() as $month) {
            $key = $month->format('Y-m');
            $amount = $window->draws($month)
                ? round((float) ($totals[$key] ?? 0), 2)
                : 0.0;

            $points[] = [
                'month' => $key,
                'label' => $month->format('F Y'),
                // Three letters is enough to read a 12-month axis without the
                // ticks colliding; the tooltip carries the full month.
                'short_label' => $month->format('M'),
                'total' => $amount,
            ];

            $total += $amount;
        }

        return new self(
            points: $points,
            total: round($total, 2),
            average: $window->average($total),
        );
    }

    /**
     * @return array{points: array<int, array{month: string, label: string, short_label: string, total: float}>, total: float, average: float, peak: float}
     */
    public function toArray(): array
    {
        return [
            'points' => $this->points,
            'total' => $this->total,
            'average' => $this->average,
            // The chart scales to this, and it is cheaper to say it once here
            // than to have the client re-derive it on every render.
            'peak' => round((float) max([0.0, ...array_column($this->points, 'total')]), 2),
        ];
    }
}
