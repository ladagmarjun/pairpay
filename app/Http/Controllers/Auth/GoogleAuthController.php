<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\FindOrCreateSocialUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * "Continue with Google" — the web counterpart of the mobile app's
 * `signInWithGoogle()`.
 *
 * The mobile app went Google → Firebase Auth → Firestore profile. Here the
 * chain is shorter: Google → Laravel session → `users` row. Someone who signed
 * in with Google on mobile lands on the same account here, because both are
 * keyed off the same Google address.
 */
class GoogleAuthController extends Controller
{
    /**
     * Send the user off to Google's account picker.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Google sends them back here with a code we exchange for their profile.
     */
    public function callback(Request $request, FindOrCreateSocialUser $findOrCreateSocialUser): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            // Stale or replayed callback — usually a bookmarked URL or the user
            // sitting on Google's consent screen past the session lifetime.
            return to_route('login')->withErrors([
                'email' => __('That Google sign-in expired. Please try again.'),
            ]);
        } catch (\Throwable) {
            return to_route('login')->withErrors([
                'email' => __('We could not sign you in with Google. Please try again.'),
            ]);
        }

        $user = $findOrCreateSocialUser->handle($googleUser);

        if ($user === null) {
            // An account with this address already exists, but Google has not
            // verified that the person signing in owns it. Linking anyway would
            // let anyone with an unverified Google address take over the
            // account, so send them through the password flow instead.
            return to_route('login')->withErrors([
                'email' => __('An account already exists for this email. Sign in with your password instead.'),
            ]);
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
