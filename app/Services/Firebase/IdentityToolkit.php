<?php

namespace App\Services\Firebase;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Auth, over Google's Identity Toolkit REST API.
 *
 * Every credential in this app lives in Firebase — `users.password` is never
 * written — so this class is the whole of the account lifecycle: creating them,
 * checking them, changing passwords, and sending the reset and verification
 * emails. It is all server-to-server, which is why no Firebase SDK ships to the
 * browser and no service account key is deployed. The Web API key is enough,
 * and it is a public identifier rather than a secret.
 *
 * Sign-in is the one method that treats refusal as routine and returns null.
 * Everywhere else a refusal is something the person needs told about, so it
 * arrives as a {@see FirebaseRefusal} carrying Firebase's own error code.
 */
class IdentityToolkit
{
    private const ACCOUNTS_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:';

    private const TOKEN_URL = 'https://securetoken.googleapis.com/v1/token';

    /**
     * The ways Firebase says "no" to a sign-in. Routine, so not logged.
     */
    private const EXPECTED_REFUSALS = [
        'EMAIL_NOT_FOUND',
        'INVALID_PASSWORD',
        'INVALID_LOGIN_CREDENTIALS',
        'INVALID_EMAIL',
        'MISSING_PASSWORD',
        'USER_DISABLED',
        'TOO_MANY_ATTEMPTS_TRY_LATER',
        'PASSWORD_LOGIN_DISABLED',
    ];

    /**
     * Sign-ins already answered this request, keyed by a digest of the
     * credentials. Fortify runs its authentication callback twice on the way
     * through the login pipeline — once to decide about two-factor, once to
     * actually sign in — and this keeps that from costing two round trips.
     *
     * @var array<string, FirebaseSession|null>
     */
    private array $answered = [];

    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * Check an email and password. Null means Firebase said no — wrong
     * password, no such account, or the account is disabled.
     */
    public function signIn(string $email, string $password): ?FirebaseSession
    {
        if (! $this->configured() || $email === '' || $password === '') {
            return null;
        }

        // The digest keeps the password out of the array key, so it cannot
        // surface in a stack trace or a dumped container.
        $key = hash('sha256', $email."\0".$password);

        if (array_key_exists($key, $this->answered)) {
            return $this->answered[$key];
        }

        try {
            $payload = $this->post('signInWithPassword', [
                'email' => $email,
                'password' => $password,
                'returnSecureToken' => true,
            ]);
        } catch (FirebaseRefusal $refusal) {
            $this->reportIfUnexpected($refusal);

            return $this->answered[$key] = null;
        }

        return $this->answered[$key] = FirebaseSession::fromResponse($payload);
    }

    /**
     * Create the account. `EMAIL_EXISTS` and `WEAK_PASSWORD` are the refusals
     * worth surfacing to whoever is filling in the form.
     *
     * @throws FirebaseRefusal
     */
    public function signUp(string $email, string $password): FirebaseSession
    {
        $session = FirebaseSession::fromResponse($this->post('signUp', [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => true,
        ]));

        if ($session === null) {
            throw new FirebaseRefusal('MISSING_ID_TOKEN', 'Firebase created the account but returned no token.');
        }

        return $session;
    }

    /**
     * Ask Firebase to email a reset link. Firebase hosts the page the link
     * lands on, so the new password is set entirely on their side and this app
     * never sees it.
     *
     * @throws FirebaseRefusal
     */
    public function sendPasswordResetEmail(string $email): void
    {
        $this->post('sendOobCode', [
            'requestType' => 'PASSWORD_RESET',
            'email' => $email,
        ]);
    }

    /**
     * @throws FirebaseRefusal
     */
    public function sendVerificationEmail(string $idToken): void
    {
        $this->post('sendOobCode', [
            'requestType' => 'VERIFY_EMAIL',
            'idToken' => $idToken,
        ]);
    }

    /**
     * @throws FirebaseRefusal
     */
    public function updatePassword(string $idToken, string $password): void
    {
        $this->post('update', [
            'idToken' => $idToken,
            'password' => $password,
            'returnSecureToken' => true,
        ]);
    }

    /**
     * @throws FirebaseRefusal
     */
    public function deleteAccount(string $idToken): void
    {
        $this->post('delete', ['idToken' => $idToken]);
    }

    /**
     * Everything Firebase knows about the holder of this token — used to find
     * out whether an address has been verified since they last signed in.
     *
     * @return array<string, mixed>|null
     *
     * @throws FirebaseRefusal
     */
    public function lookup(string $idToken): ?array
    {
        $users = $this->post('lookup', ['idToken' => $idToken])['users'] ?? null;

        return is_array($users) && isset($users[0]) && is_array($users[0]) ? $users[0] : null;
    }

    /**
     * Trade a stored refresh token for a fresh ID token. Null when the refresh
     * token has been revoked or the account is gone, which callers should treat
     * as "ask them to sign in again".
     */
    public function refresh(string $refreshToken): ?FirebaseSession
    {
        if (! $this->configured() || $refreshToken === '') {
            return null;
        }

        try {
            $payload = $this->send(
                Http::asForm(),
                self::TOKEN_URL,
                ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken],
            );
        } catch (FirebaseRefusal) {
            return null;
        }

        return FirebaseSession::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws FirebaseRefusal
     */
    private function post(string $method, array $body): array
    {
        if (! $this->configured()) {
            throw new FirebaseRefusal(FirebaseRefusal::UNAVAILABLE, 'FIREBASE_API_KEY is not set.');
        }

        return $this->send(Http::asJson(), self::ACCOUNTS_URL.$method, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws FirebaseRefusal
     */
    private function send(PendingRequest $request, string $url, array $body): array
    {
        try {
            $response = $request->timeout(8)->post($url.'?key='.urlencode($this->apiKey()), $body);
        } catch (Throwable $e) {
            // Firebase being unreachable must never be mistaken for a verdict
            // on the credentials, so it gets its own code.
            Log::warning('Could not reach Firebase.', ['url' => $url, 'reason' => $e->getMessage()]);

            throw new FirebaseRefusal(FirebaseRefusal::UNAVAILABLE, $e->getMessage());
        }

        if ($response->failed()) {
            throw new FirebaseRefusal($this->codeFrom($response), (string) $response->json('error.message', ''));
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * Firebase returns things like `TOO_MANY_ATTEMPTS_TRY_LATER : Access to
     * this account has been temporarily disabled`, so only the leading code is
     * kept. The message is never logged alongside the credentials that caused
     * it.
     */
    private function codeFrom(Response $response): string
    {
        $message = $response->json('error.message');

        if (! is_string($message) || trim($message) === '') {
            return 'HTTP_'.$response->status();
        }

        return trim(explode(':', $message, 2)[0]);
    }

    private function reportIfUnexpected(FirebaseRefusal $refusal): void
    {
        if (in_array($refusal->errorCode, self::EXPECTED_REFUSALS, true)) {
            return;
        }

        Log::warning('Unexpected response from Firebase sign-in.', ['code' => $refusal->errorCode]);
    }

    private function apiKey(): string
    {
        return (string) config('services.firebase.api_key');
    }
}
