<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The `?month=YYYY-MM` a month-scoped page is looking at.
 *
 * Both the expenses summary and the bills list are steered by the query string
 * rather than local state, so a month is shareable and survives a refresh. That
 * makes this the one place that decides what an unparseable month means.
 */
final class MonthInput
{
    /**
     * Anything missing or unparseable falls back to the current month rather
     * than erroring — a hand-edited URL should not produce a 500.
     */
    public static function fromRequest(Request $request): CarbonImmutable
    {
        $month = $request->string('month')->toString();

        try {
            return $month === ''
                ? CarbonImmutable::now()->startOfMonth()
                : CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }
}
