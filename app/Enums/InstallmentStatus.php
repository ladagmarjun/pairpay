<?php

namespace App\Enums;

/**
 * Where one month of a repayment plan stands. Derived from the payments
 * recorded against the loan, never stored — the same reasoning as
 * {@see BillStatus} and {@see LoanStatus}.
 */
enum InstallmentStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Overdue = 'overdue';
    case Upcoming = 'upcoming';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Partial => 'Part paid',
            self::Overdue => 'Overdue',
            self::Upcoming => 'Upcoming',
        };
    }
}
