<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money owed, in both directions: what you borrowed and what you lent.
     *
     * There is no `outstanding` or `settled` column. Both are the principal
     * minus the payments recorded against it, computed on read — a stored
     * balance is a second copy of the truth, and the first edit makes the two
     * disagree.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Whoever is on the other side of it — a person, not an account.
            $table->string('counterparty', 200);
            $table->string('direction', 16);
            $table->decimal('principal', 12, 2);
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
