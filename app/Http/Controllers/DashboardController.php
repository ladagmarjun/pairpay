<?php

namespace App\Http\Controllers;

use App\Support\MonthInput;
use App\Support\MonthlySummary;
use App\Support\MonthlyTrend;
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
     * How many months the trend chart covers. A year is the shortest window
     * that shows a seasonal shape, and it still fits without the axis ticks
     * colliding on a phone.
     */
    private const TREND_MONTHS = 12;

    public function __invoke(Request $request): Response
    {
        $month = MonthInput::fromRequest($request);
        $user = $request->user();

        return Inertia::render('dashboard', [
            'trend' => MonthlyTrend::forUser($user, $month, self::TREND_MONTHS)->toArray(),
            'summary' => MonthlySummary::forUser($user, $month)->toArray(),
        ]);
    }
}
