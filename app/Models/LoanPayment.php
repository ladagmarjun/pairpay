<?php

namespace App\Models;

use Database\Factories\LoanPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment against a loan — either direction, since a repayment you receive
 * and a repayment you make reduce the balance in exactly the same way.
 *
 * @property int $id
 * @property int $loan_id
 * @property float $amount
 * @property Carbon $date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Loan $loan
 */
#[Fillable(['amount', 'date', 'notes'])]
class LoanPayment extends Model
{
    /** @use HasFactory<LoanPaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('date')->orderByDesc('created_at');
    }
}
