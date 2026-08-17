<?php

namespace App\Actions\Fortify;

use App\Actions\Auth\FindOrCreateFirebaseUser;
use App\Models\User;
use App\Services\Firebase\FirebaseUserSession;
use App\Services\Firebase\IdentityToolkit;
use App\Services\Firebase\IdTokenVerifier;
use App\Services\Firebase\InvalidIdToken;
use Illuminate\Support\Facades\Log;

/**
 * The credential check behind the login form.
 *
 * Firebase holds every password in this app — `users.password` is never
 * written — so this asks Firebase, and only then decides who that makes them
 * here. The `users` row is the profile and the thing expenses hang off; it is
 * not a credential store.
 *
 * Fortify still owns the pipeline around this: throttling, the two-factor step
 * and session handling all run exactly as they did.
 */
class AuthenticateUser
{
    public function __construct(
        private readonly IdentityToolkit $firebase,
        private readonly IdTokenVerifier $verifier,
        private readonly FindOrCreateFirebaseUser $findOrCreateFirebaseUser,
        private readonly FirebaseUserSession $firebaseSession,
    ) {}

    public function handle(string $email, string $password): ?User
    {
        $session = $this->firebase->signIn($email, $password);

        if ($session === null) {
            return null;
        }

        try {
            // The token arrived over TLS straight from Google, but it is still
            // verified in full: it is the only evidence of who this is, and the
            // check is cheap once the certificates are cached.
            $identity = $this->verifier->verify($session->idToken);
        } catch (InvalidIdToken $e) {
            Log::warning('Firebase accepted the password but returned a token we could not verify.', [
                'reason' => $e->getMessage(),
            ]);

            return null;
        }

        $user = $this->findOrCreateFirebaseUser->handle($identity);

        if ($user === null) {
            return null;
        }

        // Verification happens on Firebase's pages, so this is where the app
        // finds out it happened.
        $this->syncVerification($user, $identity->emailVerified);

        // Held for the rest of the session so the app can resend a verification
        // email or re-check their address without another password.
        $this->firebaseSession->remember($session);

        return $user;
    }

    private function syncVerification(User $user, bool $verifiedInFirebase): void
    {
        if ($verifiedInFirebase && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();

            return;
        }

        // Unverifying is deliberate too: an address changed in Firebase comes
        // back unverified, and this app should not keep trusting the old state.
        if (! $verifiedInFirebase && $user->email_verified_at !== null) {
            $user->forceFill(['email_verified_at' => null])->save();
        }
    }
}
