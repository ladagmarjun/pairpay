<?php

use App\Enums\IncomeCategory;
use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money coming in, kept in its own table rather than as a negative
     * `expenses` row.
     *
     * A signed amount on `expenses` would make every total in the app a
     * question of which rows you meant, and the expense figures are supposed
     * to be a plain statement about what was spent. Two tables, two honest
     * sums, and the netting off happens where it is asked for.
     *
     * Deliberately holds only money *earned*. Borrowing is not income and a
     * repayment coming back on a loan you made is your own money returning —
     * both of those live in `loans` and `loan_payments`, and counting them
     * here would inflate every month you were paid back.
     */
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Who it came from, which is the thing you scan the list for.
            $table->string('source', 200);
            $table->string('category', 32)->default(IncomeCategory::Other->value);
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->text('notes')->nullable();
            // How it arrived. The same set an expense uses: cash is cash
            // whichever way it is moving.
            $table->string('payment_method', 32)->default(PaymentMethod::BankTransfer->value);
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
