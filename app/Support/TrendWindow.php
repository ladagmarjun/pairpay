<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The run of months a dashboard chart covers.
 *
 * The window is a calendar year, so for most of the year it runs past today.
 * Those remaining months are part of the picture — the year has room left in
 * it — but nothing is known about them yet, so they are drawn empty rather
 * than filled in. On the loan chart in particular, carrying today's balance
 * forward would be presenting a guess as history.
 *
 * `lastDrawn` is where that line falls, and `elapsed` is how many months sit
 * behind it — which is what a per-month average has to divide by, or every
 * average would be dragged down by months that have not happened.
 */
final readonly class TrendWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $lastDrawn,
        public int $months,
        public int $elapsed,
    ) {}

    /**
     * The `$months` months ending with (and including) `$endMonth`.
     */
    public static function ending(CarbonImmutable $endMonth, int $months): self
    {
        $end = $endMonth->startOfMonth();
        $start = $end->subMonths($months - 1);
        $current = CarbonImmutable::today()->startOfMonth();

        $lastDrawn = $end->isBefore($current) ? $end : $current;

        return new self(
            start: $start,
            end: $end,
            lastDrawn: $lastDrawn,
            months: $months,
            elapsed: $start->isAfter($lastDrawn)
                ? 0
                : min($months, (int) $start->diffInMonths($lastDrawn) + 1),
        );
    }

    /**
     * Every month in the window, oldest first. A month with nothing in it is
     * still yielded: a gap in the bars is itself the information, and dropping
     * the month would silently close it up.
     *
     * @return iterable<int, CarbonImmutable>
     */
    public function months(): iterable
    {
        for ($offset = 0; $offset < $this->months; $offset++) {
            yield $this->start->addMonths($offset);
        }
    }

    /**
     * Whether this month has happened, and so has anything to show.
     */
    public function draws(CarbonImmutable $month): bool
    {
        return ! $month->startOfMonth()->isAfter($this->lastDrawn);
    }

    /**
     * The latest date any query needs to reach. Rows beyond it belong to
     * months that are not drawn, so fetching them would only be work.
     */
    public function horizon(): CarbonImmutable
    {
        return $this->lastDrawn->endOfMonth();
    }

    /**
     * Divisor for a per-month average, safe to use when the whole window is
     * still ahead.
     */
    public function average(float $total): float
    {
        return $this->elapsed > 0 ? round($total / $this->elapsed, 2) : 0.0;
    }
}
