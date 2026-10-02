<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Grouping expenses by the month they fall in.
 *
 * Lives on its own because the SQL differs per driver and more than one
 * aggregate needs it — see {@see MonthlyTrend} and {@see CashflowTrend}.
 */
final class MonthPeriod
{
    /**
     * `YYYY-MM` for an expense's `date`, aliased `period`, in the dialect of
     * whichever database is answering.
     *
     * Tests run on SQLite and production on MySQL, so a bare `DATE_FORMAT`
     * would pass CI and fail nowhere until deploy. Anything unrecognised gets
     * the standard-SQL form rather than a guess.
     *
     * The column is fixed rather than a parameter: every arm has to stay a
     * literal string, since a raw expression is spliced into the query
     * unescaped.
     */
    public static function expression(): Expression
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => DB::raw("strftime('%Y-%m', date) as period"),
            'pgsql' => DB::raw("to_char(date, 'YYYY-MM') as period"),
            'sqlsrv' => DB::raw("format(date, 'yyyy-MM') as period"),
            default => DB::raw("date_format(date, '%Y-%m') as period"),
        };
    }
}
