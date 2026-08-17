<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns for social sign-in.
     *
     * `password` becomes nullable: a user who only ever signs in with Google
     * never sets one. They can still create a password later through the
     * forgot-password flow.
     *
     * `firebase_uid` lets accounts imported from the mobile app's Firebase
     * project be matched back to their Firestore profile document.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('currency_code');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('firebase_uid')->nullable()->unique()->after('avatar');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar', 'firebase_uid']);
        });
    }
};
