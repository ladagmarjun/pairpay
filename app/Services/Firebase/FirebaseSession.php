<?php

namespace App\Services\Firebase;

/**
 * What Firebase hands back when it accepts a sign-in or a sign-up.
 *
 * The refresh token is the useful part beyond the moment: ID tokens last an
 * hour, and keeping the refresh token in the session is what lets the app ask
 * Firebase something on the person's behalf later — has this address been
 * verified yet? — without making them type their password again.
 */
final readonly class FirebaseSession
{
    public function __construct(
        public string $idToken,
        public string $refreshToken,
        public string $uid,
    ) {}

    /**
     * The accounts endpoints answer in camelCase and the token endpoint in
     * snake_case, for the same three values. Both spellings are read here so
     * callers never have to care which one they are talking to.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): ?self
    {
        $idToken = $payload['idToken'] ?? $payload['id_token'] ?? null;
        $refreshToken = $payload['refreshToken'] ?? $payload['refresh_token'] ?? null;
        $uid = $payload['localId'] ?? $payload['user_id'] ?? null;

        if (! is_string($idToken) || $idToken === '') {
            return null;
        }

        return new self(
            idToken: $idToken,
            refreshToken: is_string($refreshToken) ? $refreshToken : '',
            uid: is_string($uid) ? $uid : '',
        );
    }
}
