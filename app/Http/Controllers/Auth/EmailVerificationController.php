<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Firebase\FirebaseRefusal;
use App\Services\Firebase\FirebaseUserSession;
use App\Services\Firebase\IdentityToolkit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email verification, which happens on Firebase's pages.
 *
 * Firebase sends the mail and confirms the address; this app only needs to
 * notice. Rather than a callback route Firebase would have to be configured to
 * hit, the notice screen simply asks Firebase every time it is opened — so
 * somebody who clicks the link in their mail and comes back finds themselves
 * through, without anything to click.
 */
class EmailVerificationController extends Controller
{
    public function notice(
        Request $request,
        IdentityToolkit $firebase,
        FirebaseUserSession $firebaseSession,
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        if ($this->isVerifiedInFirebase($firebase, $firebaseSession)) {
            $user->forceFill(['email_verified_at' => now()])->save();

            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function send(
        Request $request,
        IdentityToolkit $firebase,
        FirebaseUserSession $firebaseSession,
    ): RedirectResponse {
        $idToken = $firebaseSession->idToken();

        if ($idToken === null) {
            // We cannot act for them any more — the refresh token expired or
            // was revoked. Signing in again is what fixes it.
            return back()->withErrors([
                'email' => __('Please sign in again to resend the verification email.'),
            ]);
        }

        try {
            $firebase->sendVerificationEmail($idToken);
        } catch (FirebaseRefusal) {
            return back()->withErrors([
                'email' => __('We could not send that just now. Please try again in a moment.'),
            ]);
        }

        return back()->with('status', 'verification-link-sent');
    }

    private function isVerifiedInFirebase(
        IdentityToolkit $firebase,
        FirebaseUserSession $firebaseSession,
    ): bool {
        $idToken = $firebaseSession->idToken();

        if ($idToken === null) {
            return false;
        }

        try {
            return ($firebase->lookup($idToken)['emailVerified'] ?? false) === true;
        } catch (FirebaseRefusal) {
            return false;
        }
    }
}
