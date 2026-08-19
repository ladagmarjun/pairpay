<?php

namespace App\Http\Controllers;

use App\Support\BillTrend;
use App\Support\LoanTrend;
use App\Support\MonthInput;
use App\Support\MonthlySummary;
use App\Support\MonthlyTrend;
use App\Support\TrendWindow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing screen once you are signed in.
 *
 * Until now this was a static Inertia view listing what the app can do. It
 * still does that, but it leads with the thing a dashboard is for: what the
 * months have actually cost.
 */
class DashboardController extends Controller
{
    /**
     * How many months the charts cover — a calendar year, January through
     * December, rather than a rolling twelve ending on the month being viewed.
     * The months read the way a year does, and comparing one January with the
     * next does not depend on when you happened to open the page.
     *
     * The months the year has not reached yet are drawn empty; where that line
     * falls is {@see TrendWindow}.
     */
    private const TREND_MONTHS = 12;

    public function __invoke(Request $request): Response
    {
        $month = MonthInput::fromRequest($request);
        $user = $request->user();

        // The charts run to December of whichever year is being viewed, so
        // counting twelve months back from there lands on January.
        $december = $month->endOfYear()->startOfMonth();

        return Inertia::render('dashboard', [
            'trend' => MonthlyTrend::forUser($user, $december, self::TREND_MONTHS)->toArray(),
            'bills' => BillTrend::forUser($user, $december, self::TREND_MONTHS)->toArray(),
            'loans' => LoanTrend::forUser($user, $december, self::TREND_MONTHS)->toArray(),
            'summary' => MonthlySummary::forUser($user, $month)->toArray(),
        ]);
    }
}
