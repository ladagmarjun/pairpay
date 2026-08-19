<?php

namespace App\Enums;

/**
 * Where money came from. Shaped exactly like {@see ExpenseCategory} — label,
 * color and icon travel with the case so the UI needs no lookup map — but kept
 * as its own enum: a salary is not a kind of spending, and letting the two
 * share a list would put "Food" in the income form.
 */
enum IncomeCategory: string
{
    case Salary = 'salary';
    case Freelance = 'freelance';
    case Business = 'business';
    case Investment = 'investment';
    case Gift = 'gift';
    case Refund = 'refund';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Salary',
            self::Freelance => 'Freelance',
            self::Business => 'Business',
            self::Investment => 'Investment',
            self::Gift => 'Gift',
            self::Refund => 'Refund',
            self::Other => 'Other',
        };
    }

    /**
     * Brand color for the category, as a hex string the front end can use
     * directly for chips and avatars. Greens and blues throughout, so an
     * income row never reads as one of the warmer expense categories at a
     * glance.
     */
    public function color(): string
    {
        return match ($this) {
            self::Salary => '#2E7D32',
            self::Freelance => '#00695C',
            self::Business => '#1565C0',
            self::Investment => '#4527A0',
            self::Gift => '#AD1457',
            self::Refund => '#00838F',
            self::Other => '#546E7A',
        };
    }

    /**
     * Name of the Lucide icon that represents this category.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Salary => 'briefcase',
            self::Freelance => 'laptop',
            self::Business => 'store',
            self::Investment => 'trending-up',
            self::Gift => 'gift',
            self::Refund => 'undo-2',
            self::Other => 'shapes',
        };
    }

    /**
     * Safe parse from a stored value; unknown values fall back to [Other].
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Other;
    }

    /**
     * Every category shaped for the front end.
     *
     * @return array<int, array{value: string, label: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => $category->toArray(),
            self::cases(),
        );
    }

    /**
     * @return array{value: string, label: string, color: string, icon: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'color' => $this->color(),
            'icon' => $this->icon(),
        ];
    }
}
