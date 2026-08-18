<?php

namespace App\Enums;

use App\Models\Bill;

/**
 * Where a bill stands in the month being viewed. Derived rather than stored —
 * a bill is paid when the expenses pointing back at it (see {@see Bill})
 * cover its amount, so there is no column that can fall out of step with the
 * money. Part paid is a real state: a month can be settled a bit at a time.
 */
enum BillStatus: string
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
