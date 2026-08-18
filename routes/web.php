<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanPaymentController;
use App\Http\Controllers\LumpSumPaymentController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('google.redirect');

    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('google.callback');

    // Firebase sends the reset mail and hosts the page it lands on, so there is
    // no `/reset-password/{token}` counterpart to these.
    Route::get('forgot-password', [PasswordResetController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
});

Route::middleware('auth')->group(function () {
    // Opening this re-asks Firebase, so clicking the link in the mail and
    // coming back is all it takes.
    Route::get('email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('expenses', ExpenseController::class)->except('show');

    Route::resource('bills', BillController::class)->except('show');

    // Paying is not editing the bill, it is writing the expense the bill
    // becomes — so it gets its own verbs rather than a column on `update`.
    Route::post('bills/{bill}/payment', [BillController::class, 'pay'])
        ->name('bills.pay');

    Route::delete('bills/{bill}/payment', [BillController::class, 'unpay'])
        ->name('bills.unpay');

    // One lump sum spread across several bills, oldest due first — the answer
    // to having ₱1,100 against ₱1,500 of bills.
    Route::post('bills-payment/allocate', [BillController::class, 'allocate'])
        ->name('bills.allocate');

    // `show` is kept here, unlike expenses and bills: a loan is a running
    // balance with a history behind it, so it needs somewhere to live.
    Route::resource('loans', LoanController::class);

    // Payments belong to their loan and are authorized against it. The scoped
    // binding is what stops a payment id from one loan being used on another.
    Route::post('loans/{loan}/payments', [LoanPaymentController::class, 'store'])
        ->name('loans.payments.store');

    Route::delete('loans/{loan}/payments/{payment}', [LoanPaymentController::class, 'destroy'])
        ->scopeBindings()
        ->name('loans.payments.destroy');

    // One lump sum spread across the loans you owe, most pressing first.
    Route::post('loans-payment/allocate', [LoanController::class, 'allocate'])
        ->name('loans.allocate');

    // Pay day: one sum against bills and loans together, which neither
    // module can answer on its own.
    Route::get('pay', [LumpSumPaymentController::class, 'create'])
        ->name('payments.create');

    Route::post('pay', [LumpSumPaymentController::class, 'store'])
        ->name('payments.store');

    // Destinations that exist in the navigation but ship in a later phase. Same
    // approach as the mobile app's `ComingSoon` tabs — the shape of the product
    // stays visible while the modules are built one at a time.
    Route::inertia('shared', 'coming-soon', [
        'title' => 'Shared',
        'icon' => 'users',
        'phase' => 'Arrives in Phase 5 — Shared Expenses',
    ])->name('shared');

    Route::inertia('reports', 'coming-soon', [
        'title' => 'Reports',
        'icon' => 'chart-column',
        'phase' => 'Arrives in Phase 7 — Reports & Insights',
    ])->name('reports');
});

require __DIR__.'/settings.php';
