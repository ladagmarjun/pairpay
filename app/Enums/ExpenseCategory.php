<?php

namespace App\Enums;

/**
 * The fixed set of expense categories. Each carries its own label, icon, and
 * color so the UI can render a category chip/avatar without a lookup map
 * scattered across components. Stored in the database by value.
 */
enum ExpenseCategory: string
{
    case Food = 'food';
    case Transportation = 'transportation';
    case Bills = 'bills';
    case Shopping = 'shopping';
    case Health = 'health';
    case Entertainment = 'entertainment';
    case Education = 'education';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Food => 'Food',
            self::Transportation => 'Transportation',
            self::Bills => 'Bills',
            self::Shopping => 'Shopping',
            self::Health => 'Health',
            self::Entertainment => 'Entertainment',
            self::Education => 'Education',
            self::Other => 'Other',
        };
    }

    /**
     * Brand color for the category, as a hex string the front end can use
     * directly for chips, avatars, and chart bars.
     */
    public function color(): string
    {
        return match ($this) {
            self::Food => '#EF6C00',
            self::Transportation => '#1565C0',
            self::Bills => '#6A1B9A',
            self::Shopping => '#AD1457',
            self::Health => '#C62828',
            self::Entertainment => '#00838F',
            self::Education => '#2E7D32',
            self::Other => '#546E7A',
        };
    }

    /**
     * Name of the Lucide icon that represents this category. Resolved to a
     * component by `resources/js/lib/expense-meta.ts`.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Food => 'utensils',
            self::Transportation => 'car',
            self::Bills => 'receipt-text',
            self::Shopping => 'shopping-bag',
            self::Health => 'heart',
            self::Entertainment => 'film',
            self::Education => 'graduation-cap',
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
