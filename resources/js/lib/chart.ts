/**
 * Shared bits of the hand-built dashboard charts.
 */

/**
 * The year a chart covers, for its caption.
 *
 * Every chart runs January to December of one year, so the first point names
 * it. Read off the `YYYY-MM` key rather than the label, which is prose.
 */
export function chartYear(points: { month: string }[]): string {
    return points[0]?.month.slice(0, 4) ?? '';
}

/**
 * Gridline values at round numbers, the top one at or above the tallest month.
 *
 * Scaling straight to the peak would press the tallest bar against the top of
 * the plot and leave the axis printing figures like ₱4,317.
 */
export function axisTicks(peak: number): number[] {
    if (peak <= 0) {
        return [0, 1];
    }

    const rough = peak / 3;
    const magnitude = 10 ** Math.floor(Math.log10(rough));

    // Whole pesos at minimum, and only 1/2/5/10 of the magnitude, so every
    // tick survives rounding as a distinct round number. Allowing 2.5 here
    // would print an axis of 3 / 5 / 8 once the magnitude dropped to one.
    const step = Math.max(
        1,
        magnitude * ([1, 2, 5, 10].find((m) => m * magnitude >= rough) ?? 10),
    );

    const ticks: number[] = [];

    for (let value = 0; value < peak + step; value += step) {
        ticks.push(Math.round(value));
    }

    return ticks;
}
