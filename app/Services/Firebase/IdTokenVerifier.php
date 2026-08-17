<?php

namespace App\Services\Firebase;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies a Firebase ID token minted in the browser.
 *
 * The token is the only thing standing between a POST body and a signed-in
 * session, so every check Google documents is applied here:
 *
 *   - RS256, signed by one of Google's current public keys
 *   - `aud` is our project, `iss` is Google's issuer for our project
 *   - `exp` / `iat` / `auth_time` are sane
 *   - `sub` is a non-empty uid
 *
 * Doing it this way — rather than through the Admin SDK — keeps the deployment
 * free of service account keys. The trade-off is that revocation is not
 * checked: a token stays valid for its full hour even if the Firebase session
 * is revoked mid-flight. That is acceptable for a sign-in bridge, where the
 * token is exchanged for a Laravel session within seconds of being minted.
 */
class IdTokenVerifier
{
    /**
     * The public half of the keys Google signs ID tokens with, as x509
     * certificates keyed by the token header's `kid`.
     */
    private const CERTIFICATE_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    private const CACHE_KEY = 'firebase:id-token-certificates';

    /** Tolerance for clock drift between this server and Google, in seconds. */
    private const LEEWAY = 60;

    /**
     * @throws InvalidIdToken when the token cannot be trusted
     */
    public function verify(string $token): FirebaseIdentity
    {
        $projectId = (string) config('services.firebase.project_id');

        if ($projectId === '') {
            throw new InvalidIdToken('FIREBASE_PROJECT_ID is not set.');
        }

        // The decoder checks the signature, `exp`, `iat` and `nbf`; pinning
        // RS256 on every Key stops a token from talking us into another
        // algorithm.
        $original = JWT::$leeway;
        JWT::$leeway = self::LEEWAY;

        try {
            $claims = JWT::decode($token, $this->publicKeys());
        } catch (Throwable $e) {
            throw new InvalidIdToken('Could not decode the ID token: '.$e->getMessage(), previous: $e);
        } finally {
            JWT::$leeway = $original;
        }

        $this->assertClaims($claims, $projectId);

        return FirebaseIdentity::fromClaims($claims);
    }

    /**
     * The claims the decoder does not know to check for us.
     */
    private function assertClaims(object $claims, string $projectId): void
    {
        if (($claims->aud ?? null) !== $projectId) {
            throw new InvalidIdToken('The token was minted for a different Firebase project.');
        }

        if (($claims->iss ?? null) !== "https://securetoken.google.com/{$projectId}") {
            throw new InvalidIdToken('Unexpected token issuer.');
        }

        $uid = $claims->sub ?? null;

        if (! is_string($uid) || trim($uid) === '') {
            throw new InvalidIdToken('The token carries no subject.');
        }

        // A token whose sign-in happened in the future is not a token we minted.
        $authTime = $claims->auth_time ?? null;

        if (! is_numeric($authTime) || $authTime > time() + self::LEEWAY) {
            throw new InvalidIdToken('The token has no usable auth_time.');
        }
    }

    /**
     * Google's signing keys, ready for the decoder.
     *
     * @return array<string, Key> keyed by `kid`
     */
    private function publicKeys(): array
    {
        $keys = [];

        foreach ($this->certificates() as $kid => $certificate) {
            if (! is_string($certificate)) {
                continue;
            }

            $publicKey = openssl_pkey_get_public($certificate);

            if ($publicKey !== false) {
                $keys[(string) $kid] = new Key($publicKey, 'RS256');
            }
        }

        if ($keys === []) {
            throw new InvalidIdToken('Google returned no usable signing certificates.');
        }

        return $keys;
    }

    /**
     * The certificates, cached for exactly as long as Google says they are
     * good for. Honouring `Cache-Control` is what keeps this in step with key
     * rotation: Google keeps retiring keys usable until every token they signed
     * has expired, and always advertises a max-age inside that window.
     *
     * @return array<string, mixed>
     */
    private function certificates(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $response = $this->fetch();
        $certificates = $response->json();

        if (! is_array($certificates) || $certificates === []) {
            throw new InvalidIdToken('Google returned an unreadable certificate list.');
        }

        Cache::put(self::CACHE_KEY, $certificates, $this->lifetimeFrom($response));

        return $certificates;
    }

    private function fetch(): Response
    {
        try {
            $response = Http::timeout(5)->retry(2, 200)->get(self::CERTIFICATE_URL);
        } catch (Throwable $e) {
            throw new InvalidIdToken('Could not reach Google to fetch signing certificates.', previous: $e);
        }

        if ($response->failed()) {
            throw new InvalidIdToken('Google returned '.$response->status().' for the signing certificates.');
        }

        return $response;
    }

    /**
     * `Cache-Control: public, max-age=19860, must-revalidate, no-transform`
     */
    private function lifetimeFrom(Response $response): int
    {
        $header = $response->header('Cache-Control');

        if (preg_match('/max-age=(\d+)/', $header, $matches) === 1) {
            // Clamped: a nonsensically short max-age would hammer Google, and a
            // very long one would outlive the keys it describes.
            return max(60, min((int) $matches[1], 24 * 3600));
        }

        return 3600;
    }
}
