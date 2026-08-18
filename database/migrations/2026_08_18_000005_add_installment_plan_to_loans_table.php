<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional repayment plan: pay this off over N months, starting here.
     *
     * Both columns are nullable and go together — a loan without them is the
     * informal kind, paid back whenever, which is all loans could be before
     * this. There is no schedule table: the installments are derived from the
     * principal, the term, and the payments already recorded, so nothing can
     * fall out of step. See `App\Support\LoanSchedule`.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedSmallInteger('term_months')->nullable()->after('principal');
            // When the first installment falls due. Later ones land on the same
            // day of each following month, clamped for months too short for it.
            $table->date('first_due_date')->nullable()->after('term_months');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['term_months', 'first_due_date']);
        });
    }
};
