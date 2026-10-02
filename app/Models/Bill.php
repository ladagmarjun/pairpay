<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One month's bill: this month's rent, electricity, a subscription.
 *
 * A bill belongs to a single month — the one its `due_date` falls in — and
 * does not recur. Amounts change from month to month (the electricity is never
 * the same twice), so each month gets its own bill with its own amount.
 *
 * Paying it creates an {@see Expense} carrying `bill_id`, which is the only
 * record of the payment — see `paid()`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property float $amount
 * @property CarbonImmutable $due_date
 * @property ExpenseCategory $category
 * @property PaymentMethod $payment_method
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Expense> $payments
 */
#[Fillable(['name', 'amount', 'due_date', 'category', 'payment_method', 'notes'])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'float',
            'due_date' => 'immutable_date',
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
     * Every expense this bill has produced — one per payment made towards it.
     *
     * @return HasMany<Expense, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * The month this bill belongs to — the one its `due_date` falls in.
     */
    public function month(): CarbonImmutable
    {
        return $this->due_date->startOfMonth();
    }

    /**
     * Only the bills due in the given month.
     *
     * @param  Builder<Bill>  $query
     */
    public function scopeDueIn(Builder $query, CarbonImmutable $month): void
    {
        $query
            ->whereDate('due_date', '>=', $month->startOfMonth())
            ->whereDate('due_date', '<=', $month->endOfMonth());
    }

    /**
     * Every payment made towards this bill. A bill can be settled a bit at a
     * time, so this is a collection rather than a single row.
     *
     * Prefers the eager-loaded `payments` relation when rendering a list.
     *
     * @return Collection<int, Expense>
     */
    public function paymentsMade(): Collection
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments;
        }

        return $this->payments()->get();
    }

    /**
     * What has been paid towards this bill so far.
     */
    public function paid(): float
    {
        return round((float) $this->paymentsMade()->sum('amount'), 2);
    }

    /**
     * What is still owed. Floored at zero so an overpaid bill — or one edited
     * downwards after the fact — reads as settled rather than as owing a
     * negative amount.
     */
    public function remaining(): float
    {
        return max(0.0, round($this->amount - $this->paid(), 2));
    }
}
