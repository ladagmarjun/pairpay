<?php

namespace App\Support;

use App\Enums\LoanDirection;
use App\Http\Controllers\LoanController;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What was still owed at the end of each month, in both directions — the
 * dashboard's loan chart.
 *
 * The other two charts plot flows: money that moved during the month. This
 * one plots a balance, which is a different kind of number. It is the position
 * as each month closed, so a month with no activity repeats the one before it
 * rather than dropping to zero.
 *
 * The two directions are carried separately the whole way and never netted
 * off, for the reason given on {@see LoanController}:
 * owing ₱5,000 while being owed ₱5,000 is not the position of owing nothing.
 *
 * Nothing about a past balance is stored, so it is rebuilt: a loan counts from
 * the month the money changed hands, less every payment dated up to the month
 * being drawn. Months the year has not reached yet are left empty rather than
 * carrying today's balance forward — see {@see TrendWindow}.
 */
final readonly class LoanTrend
{
    /**
     * @param  array<int, array{month: string, label: string, short_label: string, borrowed: float, lent: float}>  $points  oldest first
     */
    public function __construct(
        public array $points,
        public float $owedByYou,
        public float $owedToYou,
    ) {}

    /**
     * The `$months` months ending with (and including) `$endMonth`.
     */
    public static function forUser(User $user, CarbonImmutable $endMonth, int $months = 12): self
    {
        $window = TrendWindow::ending($endMonth, $months);
        $horizon = $window->horizon();

        $loans = $user->loans()
            ->whereDate('date', '<=', $horizon)
            ->get(['id', 'direction', 'principal', 'date']);

        // Payments from before the window are folded into an opening balance:
        // they are not drawn, but the months that are drawn would be wrong
        // without them.
        [$opening, $byMonth] = self::payments($loans->pluck('id')->all(), $horizon, $window->start->format('Y-m'));

        $paid = $opening;
        $points = [];
        $closing = ['borrowed' => 0.0, 'lent' => 0.0];

        foreach ($window->months() as $month) {
            $key = $month->format('Y-m');
            $closes = $month->endOfMonth();

            $outstanding = [LoanDirection::Borrowed->value => 0.0, LoanDirection::Lent->value => 0.0];

            if ($window->draws($month)) {
                foreach ($loans as $loan) {
                    $paid[$loan->id] = ($paid[$loan->id] ?? 0.0) + ($byMonth[$loan->id][$key] ?? 0.0);

                    // A loan the money had not changed hands on yet is not a
                    // balance, however soon it is coming.
                    if ($loan->date->greaterThan($closes)) {
                        continue;
                    }

                    $outstanding[$loan->direction->value] += max(0.0, $loan->principal - $paid[$loan->id]);
                }

                // Where the drawn months stop is the position as it stands,
                // which is the figure worth putting in the caption.
                $closing = [
                    'borrowed' => round($outstanding[LoanDirection::Borrowed->value], 2),
                    'lent' => round($outstanding[LoanDirection::Lent->value], 2),
                ];
            }

            $points[] = [
                'month' => $key,
                'label' => $month->format('F Y'),
                'short_label' => $month->format('M'),
                'borrowed' => round($outstanding[LoanDirection::Borrowed->value], 2),
                'lent' => round($outstanding[LoanDirection::Lent->value], 2),
            ];
        }

        return new self(
            points: $points,
            // A balance is not a sum over the window — where it stands at the
            // end *is* the figure, which is what the loans page shows too.
            owedByYou: $closing['borrowed'],
            owedToYou: $closing['lent'],
        );
    }

    /**
     * Payments per loan per month, split into what landed before the window
     * and what landed inside it.
     *
     * @param  array<int, int>  $loanIds
     * @return array{0: array<int, float>, 1: array<int, array<string, float>>}
     */
    private static function payments(array $loanIds, CarbonImmutable $horizon, string $firstMonth): array
    {
        if ($loanIds === []) {
            return [[], []];
        }

        $rows = LoanPayment::query()
            ->whereIn('loan_id', $loanIds)
            ->whereDate('date', '<=', $horizon)
            ->groupBy('loan_id', 'period')
            ->select(['loan_id', MonthPeriod::expression(), DB::raw('SUM(amount) as period_total')])
            ->get();

        $opening = [];
        $byMonth = [];

        foreach ($rows as $row) {
            $loanId = (int) $row->getAttributeValue('loan_id');
            $period = (string) $row->getAttributeValue('period');
            $total = (float) $row->getAttributeValue('period_total');

            if ($period < $firstMonth) {
                $opening[$loanId] = ($opening[$loanId] ?? 0.0) + $total;

                continue;
            }

            $byMonth[$loanId][$period] = $total;
        }

        return [$opening, $byMonth];
    }

    /**
     * @return array{points: array<int, array{month: string, label: string, short_label: string, borrowed: float, lent: float}>, owed_by_you: float, owed_to_you: float, peak: float}
     */
    public function toArray(): array
    {
        return [
            'points' => $this->points,
            'owed_by_you' => $this->owedByYou,
            'owed_to_you' => $this->owedToYou,
            // Both directions share one axis so that their sizes can be
            // compared, which is the whole point of drawing them together.
            'peak' => round((float) max([
                0.0,
                ...array_column($this->points, 'borrowed'),
                ...array_column($this->points, 'lent'),
            ]), 2),
        ];
    }
}
