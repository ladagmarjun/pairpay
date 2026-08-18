<?php

namespace App\Enums;

/**
 * How far through a loan is. Derived from the payments rather than stored, so
 * it can never disagree with the money — the same reasoning as
 * {@see BillStatus}.
 */
enum LoanStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Settled = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Partial => 'Part paid',
            self::Settled => 'Settled',
        };
    }
}
