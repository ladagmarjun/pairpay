<?php

namespace App\Enums;

/**
 * How an expense was paid. Stored in the database by value.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case EWallet = 'e_wallet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::BankTransfer => 'Bank Transfer',
            self::EWallet => 'E-Wallet',
            self::Other => 'Other',
        };
    }

    /**
     * Name of the Lucide icon that represents this payment method.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'banknote',
            self::Card => 'credit-card',
            self::BankTransfer => 'landmark',
            self::EWallet => 'wallet',
            self::Other => 'ellipsis',
        };
    }

    /**
     * Safe parse from a stored value; unknown values fall back to [Cash].
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Cash;
    }

    /**
     * Every payment method shaped for the front end.
     *
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $method): array => $method->toArray(),
            self::cases(),
        );
    }

    /**
     * @return array{value: string, label: string, icon: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'icon' => $this->icon(),
        ];
    }
}
