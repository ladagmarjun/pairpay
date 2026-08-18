<?php

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed monthly expenses — the recurring definition, and nothing else.
     *
     * There is deliberately no `paid` column. Paying a bill writes an ordinary
     * row in `expenses` pointing back here, so a month is settled when that row
     * exists. Keeping every peso in one table is what lets the monthly summary
     * stay correct without knowing bills exist at all.
     */
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->decimal('amount', 12, 2);
            // When it *first* falls due. From then on the day recurs every
            // month; the months before this date do not owe the bill at all,
            // which is what lets one be set up ahead of time. Months too short
            // for the day clamp to their last rather than spilling into the
            // next one, so the 31st is a usable way to say "the end of the
            // month".
            $table->date('due_date');
            $table->string('category', 32)->default(ExpenseCategory::Bills->value);
            $table->string('payment_method', 32)->default(PaymentMethod::Cash->value);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
