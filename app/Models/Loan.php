<?php

namespace App\Models;

use App\Enums\LoanDirection;
use App\Enums\LoanStatus;
use App\Support\LoanSchedule;
use Carbon\CarbonImmutable;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Money owed — borrowed from someone, or lent to them.
 *
 * What is left to pay is never stored. It is the principal minus the payments,
 * and so are the status and the settled state; see `outstanding()`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $counterparty
 * @property LoanDirection $direction
 * @property float $principal
 * @property int|null $term_months
 * @property CarbonImmutable|null $first_due_date
 * @property Carbon $date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, LoanPayment> $payments
 */
#[Fillable(['counterparty', 'direction', 'principal', 'term_months', 'first_due_date', 'date', 'notes'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => LoanDirection::class,
            'principal' => 'float',
            'term_months' => 'integer',
            'first_due_date' => 'immutable_date',
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LoanPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    /**
     * Everything paid against this loan so far.
     *
     * Prefers the total a list query already fetched — `withSum('payments')`
     * leaves it on the model, which keeps rendering a page of loans to one
     * query instead of one per row.
     */
    public function paidTotal(): float
    {
        $loaded = $this->getAttribute('payments_sum_amount');

        if ($loaded !== null) {
            return round((float) $loaded, 2);
        }

        if ($this->relationLoaded('payments')) {
            return round((float) $this->payments->sum('amount'), 2);
        }

        return round((float) $this->payments()->sum('amount'), 2);
    }

    /**
     * What is still owed. Floored at zero: overpaying is guarded against when
     * a payment is recorded, but a loan edited downwards afterwards should
     * read as settled rather than as owing a negative amount.
     */
    public function outstanding(): float
    {
        return max(0.0, round($this->principal - $this->paidTotal(), 2));
    }

    public function status(): LoanStatus
    {
        return match (true) {
            $this->outstanding() <= 0.0 => LoanStatus::Settled,
            $this->paidTotal() > 0.0 => LoanStatus::Partial,
            default => LoanStatus::Open,
        };
    }

    /**
     * Whether this loan is being repaid to a plan. Both columns are set
     * together or not at all — a loan without them is the informal kind, paid
     * back whenever.
     */
    public function hasSchedule(): bool
    {
        return $this->term_months !== null
            && $this->term_months > 0
            && $this->first_due_date !== null;
    }

    /**
     * What one month costs. The final installment differs by whatever the
     * division left over; `LoanSchedule` is where that is settled.
     */
    public function installmentAmount(): float
    {
        if (! $this->hasSchedule()) {
            return 0.0;
        }

        return round($this->principal / (int) $this->term_months, 2);
    }

    /**
     * The month-by-month plan, derived rather than stored.
     */
    public function schedule(): LoanSchedule
    {
        return LoanSchedule::forLoan($this);
    }

    /**
     * What this loan wants paying right now: the next month of the plan, or
     * the whole balance if there is no plan.
     *
     * The distinction matters when one sum is spread across several loans —
     * without it, everything would go into whichever loan came first instead
     * of covering each one's month.
     */
    public function amountDueNow(): float
    {
        if (! $this->hasSchedule()) {
            return $this->outstanding();
        }

        $next = $this->schedule()->next();

        return $next === null ? 0.0 : (float) $next['outstanding'];
    }

    /**
     * How pressing this loan is, as a sortable key. A loan on a plan is ranked
     * by the month it next owes; one without falls back to when it was made,
     * so the oldest informal debt still comes before a newer one.
     */
    public function urgencyKey(): string
    {
        $next = $this->hasSchedule() ? $this->schedule()->next() : null;

        $date = $next['due_date'] ?? $this->date->toDateString();

        return $date.'|'.$this->counterparty;
    }

    /**
     * Newest first, matching the order the list is rendered in.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('date')->orderByDesc('created_at');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOfDirection(Builder $query, LoanDirection $direction): void
    {
        $query->where('direction', $direction);
    }
}
