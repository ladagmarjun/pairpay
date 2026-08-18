<?php

namespace App\Enums;

/**
 * Which way the money went. This is the whole of what makes a loan a debt or
 * an asset, so it is stored by value and never inferred from the amount.
 */
enum LoanDirection: string
{
    case Borrowed = 'borrowed';
    case Lent = 'lent';

    public function label(): string
    {
        return match ($this) {
            self::Borrowed => 'Borrowed',
            self::Lent => 'Lent',
        };
    }

    /**
     * How the direction reads in a sentence, for headings and totals.
     */
    public function description(): string
    {
        return match ($this) {
            self::Borrowed => 'Money you owe',
            self::Lent => 'Money owed to you',
        };
    }

    /**
     * What to call the other party, which depends entirely on the direction.
     */
    public function counterpartyLabel(): string
    {
        return match ($this) {
            self::Borrowed => 'Lender',
            self::Lent => 'Borrower',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrowed => '#C62828',
            self::Lent => '#2E7D32',
        };
    }

    /**
     * Name of the Lucide icon that represents this direction. Resolved to a
     * component by `resources/js/lib/expense-meta.ts`.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Borrowed => 'hand-coins',
            self::Lent => 'piggy-bank',
        };
    }

    /**
     * Safe parse from a stored value; unknown values fall back to [Borrowed].
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Borrowed;
    }

    /**
     * Every direction shaped for the front end.
     *
     * @return array<int, array{value: string, label: string, description: string, counterparty_label: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $direction): array => $direction->toArray(),
            self::cases(),
        );
    }

    /**
     * @return array{value: string, label: string, description: string, counterparty_label: string, color: string, icon: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'counterparty_label' => $this->counterpartyLabel(),
            'color' => $this->color(),
            'icon' => $this->icon(),
        ];
    }
}
