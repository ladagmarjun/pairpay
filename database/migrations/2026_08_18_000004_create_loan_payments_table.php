<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payment against a loan.
     *
     * Deliberately not an expense, unlike a bill payment. Half of these are
     * money coming *back* to you on a loan you made, and this app has no way
     * to represent income — putting them in `expenses` would either overstate
     * spending or need a negative amount. So loans keep their own ledger, and
     * the expense totals stay a statement about what was spent.
     *
     * Cascades with the loan: a payment against a loan that no longer exists
     * is not a record of anything.
     */
    public function up(): void
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
