<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PairPay is a peso app. The column default was USD only because the
     * starter kit's was.
     *
     * The backfill re-denominates accounts still sitting on the old default.
     * It is safe here because the app has a single account and has not shipped
     * to anyone; on an app with real users this would be overwriting a choice
     * they made, and would want to be a one-off command instead.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('PHP')->change();
        });

        DB::table('users')->where('currency_code', 'USD')->update(['currency_code' => 'PHP']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('USD')->change();
        });

        DB::table('users')->where('currency_code', 'PHP')->update(['currency_code' => 'USD']);
    }
};
