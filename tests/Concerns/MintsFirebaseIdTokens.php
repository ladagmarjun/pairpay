<?php

namespace Tests\Concerns;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Stands in for Firebase: mints ID tokens signed with a throwaway key, and
 * serves the matching certificate from a faked Google endpoint.
 *
 * That lets the real verification path run end to end — signature, claims and
 * all — without reaching the network. See `tests/Fixtures/firebase/README.md`
 * for why the keys are checked in rather than generated per run.
 */
trait MintsFirebaseIdTokens
{
    protected const FIREBASE_PROJECT_ID = 'pairpay-test';

    protected const FIREBASE_API_KEY = 'test-api-key';

    protected const FIREBASE_KEY_ID = 'test-key';

    protected const IDENTITY_TOOLKIT_URL = 'identitytoolkit.googleapis.com/*';

    protected const CERTIFICATE_URL = 'www.googleapis.com/*';

    protected const TOKEN_REFRESH_URL = 'securetoken.googleapis.com/*';

    protected function configureFirebase(): void
    {
        config()->set('services.firebase', [
            'project_id' => self::FIREBASE_PROJECT_ID,
            'api_key' => self::FIREBASE_API_KEY,
        ]);
    }

    /**
     * Google's signing certificates, advertised with the same `Cache-Control`
     * the real endpoint sends.
     *
     * @return array<string, mixed>
     */
    protected function fakeCertificateEndpoint(): array
    {
        return [self::CERTIFICATE_URL => Http::response(
            [self::FIREBASE_KEY_ID => $this->firebaseFixture('signing-certificate.pem')],
            headers: ['Cache-Control' => 'public, max-age=19860'],
        )];
    }

    /**
     * Firebase accepting a password.
     *
     * @param  array<string, mixed>  $claims  overrides for the returned token
     * @return array<string, mixed>
     */
    protected function fakeFirebaseAccepts(array $claims = []): array
    {
        return [self::IDENTITY_TOOLKIT_URL => Http::response([
            'kind' => 'identitytoolkit#VerifyPasswordResponse',
            'localId' => $claims['sub'] ?? 'firebase-uid-1',
            'email' => $claims['email'] ?? 'ada@example.com',
            'idToken' => $this->firebaseIdToken($claims),
            'refreshToken' => 'test-refresh-token',
            'registered' => true,
            'expiresIn' => '3600',
        ])];
    }

    /**
     * Firebase vouching for an existing user, so a test can sign them in — or
     * satisfy a "confirm your current password" check — without caring how.
     *
     * Anything hitting Firebase in that test is answered, so this is the one
     * call most tests need.
     *
     * @param  object{email: string, firebase_uid: string|null}  $user
     */
    protected function fakeFirebaseSignInFor(object $user): void
    {
        $uid = $user->firebase_uid ?? 'firebase-uid-'.md5($user->email);

        if ($user->firebase_uid === null && method_exists($user, 'forceFill')) {
            // Every account is a Firebase account now, so give the row the uid
            // it would have been created with.
            $user->forceFill(['firebase_uid' => $uid])->save();
        }

        Http::fake([
            ...$this->fakeFirebaseAccepts(['sub' => $uid, 'email' => $user->email]),
            ...$this->fakeTokenRefresh(['sub' => $uid, 'email' => $user->email]),
            ...$this->fakeCertificateEndpoint(),
        ]);
    }

    /**
     * Trading a refresh token for a fresh ID token. Note the snake_case — the
     * token endpoint answers differently from the accounts endpoints.
     *
     * @param  array<string, mixed>  $claims  overrides for the returned token
     * @return array<string, mixed>
     */
    protected function fakeTokenRefresh(array $claims = []): array
    {
        return [self::TOKEN_REFRESH_URL => Http::response([
            'access_token' => 'test-access-token',
            'expires_in' => '3600',
            'token_type' => 'Bearer',
            'refresh_token' => 'test-refresh-token',
            'id_token' => $this->firebaseIdToken($claims),
            'user_id' => $claims['sub'] ?? 'firebase-uid-1',
        ])];
    }

    /**
     * Firebase refusing a password, in its usual shape.
     *
     * @return array<string, mixed>
     */
    protected function fakeFirebaseRefuses(string $code = 'INVALID_LOGIN_CREDENTIALS'): array
    {
        return [self::IDENTITY_TOOLKIT_URL => Http::response([
            'error' => ['code' => 400, 'message' => $code, 'errors' => []],
        ], status: 400)];
    }

    /**
     * A well-formed ID token, before any overrides.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function firebaseIdToken(array $claims = [], ?string $privateKey = null, ?string $keyId = null): string
    {
        $payload = array_merge([
            'iss' => 'https://securetoken.google.com/'.self::FIREBASE_PROJECT_ID,
            'aud' => self::FIREBASE_PROJECT_ID,
            'sub' => 'firebase-uid-1',
            'auth_time' => time() - 10,
            'iat' => time() - 10,
            'exp' => time() + 3600,
            'email' => 'ada@example.com',
            'email_verified' => true,
            'name' => 'Ada Lovelace',
            'picture' => 'https://example.com/ada.jpg',
            'firebase' => [
                'sign_in_provider' => 'password',
                'identities' => ['email' => ['ada@example.com']],
            ],
        ], $claims);

        return JWT::encode(
            array_filter($payload, fn ($value) => $value !== null),
            $privateKey ?? $this->firebaseFixture('signing-key.pem'),
            'RS256',
            $keyId ?? self::FIREBASE_KEY_ID,
        );
    }

    protected function firebaseFixture(string $name): string
    {
        static $cache = [];

        return $cache[$name] ??= (string) file_get_contents(
            dirname(__DIR__).'/Fixtures/firebase/'.$name,
        );
    }
}
