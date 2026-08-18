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
 * A fixed monthly expense: rent, electricity, a subscription.
 *
 * The bill is the template. Paying it for a month creates an {@see Expense}
 * carrying `bill_id`, which is the only record of the payment — see
 * `paymentFor()`.
 *
 * `due_date` is when it *first* falls due. It recurs on that day every month
 * from then on, and does not exist in the months before it — a bill set up to
 * start next September is not something you owe this August.
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
     * Every expense this bill has produced — one per month it was paid.
     *
     * @return HasMany<Expense, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * The date this bill falls due within the given month.
     *
     * Only the day of `due_date` recurs, so a bill starting on 31 January
     * lands on the 28th of a February rather than rolling into March.
     *
     * Says nothing about whether the bill has started yet — that is
     * {@see scopeStartedBy()}.
     */
    public function dueDateIn(CarbonImmutable $month): CarbonImmutable
    {
        $start = $month->startOfMonth();

        return $start->setDay(min($this->due_date->day, $start->daysInMonth));
    }

    /**
     * Only the bills that have begun by the given month.
     *
     * Compared against the end of the month rather than its start, so a bill
     * first due on the 20th still belongs to the month it starts in.
     *
     * @param  Builder<Bill>  $query
     */
    public function scopeStartedBy(Builder $query, CarbonImmutable $month): void
    {
        $query->whereDate('due_date', '<=', $month->endOfMonth());
    }

    /**
     * Every payment made towards this bill in a month. A month can be settled
     * a bit at a time, so this is a collection rather than a single row.
     *
     * Prefers the eager-loaded `payments` relation when rendering a list; the
     * query is for the single-bill case, where one more round trip is cheaper
     * than loading every payment the bill has ever had.
     *
     * @return Collection<int, Expense>
     */
    public function paymentsIn(CarbonImmutable $month): Collection
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments;
        }

        return $this->payments()->forMonth($month)->get();
    }

    /**
     * What has been paid towards this month so far.
     */
    public function paidIn(CarbonImmutable $month): float
    {
        return round((float) $this->paymentsIn($month)->sum('amount'), 2);
    }

    /**
     * What is still owed this month. Floored at zero so an overpaid month — or
     * a bill edited downwards after the fact — reads as settled rather than as
     * owing a negative amount.
     */
    public function remainingIn(CarbonImmutable $month): float
    {
        return max(0.0, round($this->amount - $this->paidIn($month), 2));
    }
}
