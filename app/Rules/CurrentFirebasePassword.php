<?php

namespace App\Rules;

use App\Services\Firebase\FirebaseUserSession;
use App\Services\Firebase\IdentityToolkit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * The Firebase equivalent of Laravel's `current_password` rule.
 *
 * That rule compares against `users.password`, which this app no longer writes,
 * so it would reject every correct password. This asks Firebase instead — the
 * only thing that knows.
 *
 * Used wherever the app wants proof that the person at the keyboard is the
 * account holder: changing a password, deleting an account, and confirming
 * before managing two-factor or passkeys.
 *
 * Proving the password also yields a fresh Firebase session, which is kept.
 * Whatever comes next — updating the password, deleting the account — needs an
 * ID token to do it with, and this is the most recent one there will be.
 */
class CurrentFirebasePassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        if ($user === null || ! is_string($value) || $value === '') {
            $fail(__('The password is incorrect.'));

            return;
        }

        $session = app(IdentityToolkit::class)->signIn((string) $user->email, $value);

        if ($session === null) {
            $fail(__('The password is incorrect.'));

            return;
        }

        app(FirebaseUserSession::class)->remember($session);
    }
}
