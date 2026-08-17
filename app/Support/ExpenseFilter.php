<?php

namespace App\Support;

use App\Enums\ExpenseCategory;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Describes how the expense list should be filtered/searched. An empty filter
 * (all fields null) means "show everything for this user".
 *
 * The filter lives in the query string, so a filtered list is linkable,
 * bookmarkable, and survives a refresh.
 */
final readonly class ExpenseFilter
{
    public function __construct(
        public ?string $search = null,
        public ?ExpenseCategory $category = null,
        public ?CarbonImmutable $startDate = null,
        public ?CarbonImmutable $endDate = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $search = trim((string) $request->string('search'));

        return new self(
            search: $search === '' ? null : $search,
            category: ExpenseCategory::tryFrom((string) $request->string('category')),
            startDate: self::parseDate($request->string('start_date')->toString()),
            endDate: self::parseDate($request->string('end_date')->toString()),
        );
    }

    /**
     * A filter constrained to a single calendar month.
     */
    public static function forMonth(CarbonImmutable $month): self
    {
        return new self(
            startDate: $month->startOfMonth(),
            endDate: $month->endOfMonth(),
        );
    }

    public function isActive(): bool
    {
        return $this->search !== null
            || $this->category !== null
            || $this->startDate !== null
            || $this->endDate !== null;
    }

    /**
     * Shape sent to the front end so the controls can render their current
     * state. Dates are ISO so they drop straight into `<input type="date">`.
     *
     * @return array{search: string|null, category: string|null, start_date: string|null, end_date: string|null, is_active: bool}
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'category' => $this->category?->value,
            'start_date' => $this->startDate?->toDateString(),
            'end_date' => $this->endDate?->toDateString(),
            'is_active' => $this->isActive(),
        ];
    }

    private static function parseDate(string $value): ?CarbonImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
