<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Support\ExpenseFilter;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single personal expense.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $bill_id
 * @property string $title
 * @property ExpenseCategory $category
 * @property float $amount
 * @property Carbon $date
 * @property string|null $notes
 * @property PaymentMethod $payment_method
 * @property string|null $receipt_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['title', 'category', 'amount', 'date', 'notes', 'payment_method', 'receipt_path'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
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
     * The recurring bill this expense settled, if any. Null for an expense
     * entered by hand, and null again once the bill it came from is deleted.
     *
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * Apply a search / category / date-range filter to the query.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeFilter(Builder $query, ExpenseFilter $filter): void
    {
        $query
            ->when(
                $filter->category,
                fn (Builder $q, ExpenseCategory $category) => $q->where('category', $category),
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
                    ->where('title', 'like', $like)
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
     * Newest first, matching the order the expense list is rendered in.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('date')->orderByDesc('created_at');
    }
}
