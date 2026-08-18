<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The link that makes a bill "paid": the expense a payment produced.
     *
     * `nullOnDelete` rather than a cascade — deleting a bill you no longer pay
     * must not erase the months you did pay it. Those expenses simply stop
     * being attributed to a bill.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('bill_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['bill_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['bill_id']);
            $table->dropIndex(['bill_id', 'date']);
            $table->dropColumn('bill_id');
        });
    }
};
