<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Finishes a deploy on hosting where CI cannot run artisan. 404s unless
// DEPLOY_HOOK_TOKEN is set — see docs/DEPLOYMENT.md.
Route::post('deploy-hook', DeployController::class)
    ->middleware('throttle:5,1')
    ->name('deploy.hook');

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
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('expenses', ExpenseController::class)->except('show');

    // Destinations that exist in the navigation but ship in a later phase. Same
    // approach as the mobile app's `ComingSoon` tabs — the shape of the product
    // stays visible while the modules are built one at a time.
    Route::inertia('bills', 'coming-soon', [
        'title' => 'Bills',
        'icon' => 'receipt-text',
        'phase' => 'Arrives in Phase 3 — Fixed Monthly Expenses',
    ])->name('bills');

    Route::inertia('loans', 'coming-soon', [
        'title' => 'Loans',
        'icon' => 'landmark',
        'phase' => 'Arrives in Phase 4 — Loan Management',
    ])->name('loans');

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
