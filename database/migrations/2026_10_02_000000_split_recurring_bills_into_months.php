<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bills used to be templates that recurred every month from their first due
 * date. They now belong to a single month, since amounts change from month to
 * month — so each old bill is spread into one row per month it covered.
 *
 * The original row keeps its first month. Every later month, up to this one
 * (or the last month a payment was made for, if that is later), gets a copy
 * due on the same day, and the payments dated in that month move onto it. The
 * bills list and dashboard read exactly as they did before; the difference is
 * that each month's amount can now be edited on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        $currentMonth = CarbonImmutable::today()->startOfMonth();

        DB::transaction(function () use ($currentMonth): void {
            foreach (DB::table('bills')->orderBy('id')->get() as $bill) {
                $firstDue = CarbonImmutable::parse($bill->due_date);
                $firstMonth = $firstDue->startOfMonth();

                $lastPayment = DB::table('expenses')->where('bill_id', $bill->id)->max('date');
                $lastMonth = $lastPayment !== null && CarbonImmutable::parse($lastPayment)->startOfMonth()->isAfter($currentMonth)
                    ? CarbonImmutable::parse($lastPayment)->startOfMonth()
                    : $currentMonth;

                for ($month = $firstMonth->addMonth(); ! $month->isAfter($lastMonth); $month = $month->addMonth()) {
                    // The same clamp the recurring bill used: the 31st lands
                    // on the last day of a shorter month.
                    $dueOn = $month->setDay(min($firstDue->day, $month->daysInMonth));

                    $copyId = DB::table('bills')->insertGetId([
                        'user_id' => $bill->user_id,
                        'name' => $bill->name,
                        'amount' => $bill->amount,
                        'due_date' => $dueOn->toDateString(),
                        'category' => $bill->category,
                        'payment_method' => $bill->payment_method,
                        'notes' => $bill->notes,
                        'created_at' => $bill->created_at,
                        'updated_at' => now(),
                    ]);

                    DB::table('expenses')
                        ->where('bill_id', $bill->id)
                        ->whereDate('date', '>=', $month->toDateString())
                        ->whereDate('date', '<=', $month->endOfMonth()->toDateString())
                        ->update(['bill_id' => $copyId]);
                }
            }
        });
    }

    /**
     * Not reversed: folding the months back into one template would have to
     * pick one amount for all of them, discarding the rest.
     */
    public function down(): void
    {
        //
    }
};
