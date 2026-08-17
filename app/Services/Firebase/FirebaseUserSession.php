<?php

namespace App\Services\Firebase;

use Illuminate\Contracts\Session\Session;

/**
 * Keeps the signed-in person's Firebase refresh token alongside their Laravel
 * session.
 *
 * ID tokens last an hour; the refresh token is what lets the app ask Firebase
 * something later on their behalf — resend a verification email, check whether
 * they have confirmed their address yet — without making them type their
 * password again.
 *
 * It lives in the session rather than the database on purpose: it is a bearer
 * credential, the session is already encrypted and already expires, and logging
 * out should take it with it. Nothing here survives a logout.
 */
class FirebaseUserSession
{
    private const REFRESH_TOKEN = 'firebase.refresh_token';

    /** The ID token minted this request, if we have already spent one. */
    private ?string $idToken = null;

    public function __construct(
        private readonly Session $session,
        private readonly IdentityToolkit $firebase,
    ) {}

    public function remember(FirebaseSession $firebaseSession): void
    {
        $this->idToken = $firebaseSession->idToken;

        if ($firebaseSession->refreshToken !== '') {
            $this->session->put(self::REFRESH_TOKEN, $firebaseSession->refreshToken);
        }
    }

    /**
     * A usable ID token, minting a fresh one if this request has not already.
     *
     * Null means we cannot act for them any more — the refresh token was
     * revoked, the account was deleted, or they signed in before this app
     * started keeping one. Callers should fall back to asking for the password.
     */
    public function idToken(): ?string
    {
        if ($this->idToken !== null) {
            return $this->idToken;
        }

        $refreshToken = $this->session->get(self::REFRESH_TOKEN);

        if (! is_string($refreshToken) || $refreshToken === '') {
            return null;
        }

        $refreshed = $this->firebase->refresh($refreshToken);

        if ($refreshed === null) {
            $this->forget();

            return null;
        }

        $this->remember($refreshed);

        return $refreshed->idToken;
    }

    public function forget(): void
    {
        $this->idToken = null;
        $this->session->forget(self::REFRESH_TOKEN);
    }
}
