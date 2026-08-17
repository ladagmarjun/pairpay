<?php

namespace App\Support;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Aggregated spend for a single month: the total plus a per-category
 * breakdown. Consumed by the monthly summary card and (later) the dashboard
 * charts.
 */
final readonly class MonthlySummary
{
    /**
     * @param  array<string, float>  $byCategory  keyed by ExpenseCategory value
     */
    public function __construct(
        public CarbonImmutable $month,
        public float $total,
        public array $byCategory,
        public int $transactionCount,
    ) {}

    public static function empty(CarbonImmutable $month): self
    {
        return new self(
            month: $month->startOfMonth(),
            total: 0.0,
            byCategory: [],
            transactionCount: 0,
        );
    }

    /**
     * Build the summary with a single grouped aggregate query — no need to pull
     * every row of the month into PHP just to add it up.
     */
    public static function forUser(User $user, CarbonImmutable $month): self
    {
        $rows = Expense::query()
            ->where('user_id', $user->id)
            ->forMonth($month)
            ->groupBy('category')
            ->selectRaw('category, SUM(amount) as category_total, COUNT(*) as category_count')
            ->get();

        $byCategory = [];
        $total = 0.0;
        $count = 0;

        foreach ($rows as $row) {
            $categoryTotal = (float) $row->getAttributeValue('category_total');

            $byCategory[$row->category->value] = $categoryTotal;
            $total += $categoryTotal;
            $count += (int) $row->getAttributeValue('category_count');
        }

        return new self(
            month: $month->startOfMonth(),
            total: round($total, 2),
            byCategory: $byCategory,
            transactionCount: $count,
        );
    }

    /**
     * Categories sorted by descending spend — the "top categories" list.
     *
     * @return array<int, array{category: string, label: string, color: string, icon: string, total: float, fraction: float}>
     */
    public function sortedCategories(): array
    {
        $byCategory = $this->byCategory;
        arsort($byCategory);

        return array_map(
            function (string $value) use ($byCategory): array {
                $category = ExpenseCategory::fromValue($value);

                return [
                    ...$category->toArray(),
                    'category' => $category->value,
                    'total' => $byCategory[$value],
                    'fraction' => $this->total > 0 ? $byCategory[$value] / $this->total : 0.0,
                ];
            },
            array_keys($byCategory),
        );
    }

    /**
     * @return array{month: string, month_label: string, previous_month: string, next_month: string, total: float, transaction_count: int, by_category: array<int, array{category: string, label: string, color: string, icon: string, total: float, fraction: float}>}
     */
    public function toArray(): array
    {
        return [
            'month' => $this->month->format('Y-m'),
            'month_label' => $this->month->format('F Y'),
            'previous_month' => $this->month->subMonth()->format('Y-m'),
            'next_month' => $this->month->addMonth()->format('Y-m'),
            'total' => $this->total,
            'transaction_count' => $this->transactionCount,
            'by_category' => $this->sortedCategories(),
        ];
    }
}
