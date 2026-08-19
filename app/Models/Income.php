<?php

namespace App\Models;

use App\Enums\IncomeCategory;
use App\Enums\PaymentMethod;
use App\Support\IncomeFilter;
use Carbon\CarbonImmutable;
use Database\Factories\IncomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money received. The mirror of {@see Expense}, and deliberately a separate
 * table rather than a signed amount on that one — see the migration.
 *
 * @property int $id
 * @property int $user_id
 * @property string $source
 * @property IncomeCategory $category
 * @property float $amount
 * @property Carbon $date
 * @property string|null $notes
 * @property PaymentMethod $payment_method
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['source', 'category', 'amount', 'date', 'notes', 'payment_method'])]
class Income extends Model
{
    /** @use HasFactory<IncomeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => IncomeCategory::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'float',
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
     * Apply a search / category / date-range filter to the query.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeFilter(Builder $query, IncomeFilter $filter): void
    {
        $query
            ->when(
                $filter->category,
                fn (Builder $q, IncomeCategory $category) => $q->where('category', $category),
            )
            ->when(
                $filter->startDate,
                fn (Builder $q, CarbonImmutable $start) => $q->whereDate('date', '>=', $start),
            )
            ->when(
                $filter->endDate,
                fn (Builder $q, CarbonImmutable $end) => $q->whereDate('date', '<=', $end),
            )
            ->when($filter->search, function (Builder $q, string $search): void {
                $like = '%'.$search.'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('source', 'like', $like)
                    ->orWhere('notes', 'like', $like));
            });
    }

    /**
     * Constrain the query to a single calendar month.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForMonth(Builder $query, CarbonImmutable $month): void
    {
        $query
            ->whereDate('date', '>=', $month->startOfMonth())
            ->whereDate('date', '<=', $month->endOfMonth());
    }

    /**
     * Newest first, matching the order the income list is rendered in.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('date')->orderByDesc('created_at');
    }
}
