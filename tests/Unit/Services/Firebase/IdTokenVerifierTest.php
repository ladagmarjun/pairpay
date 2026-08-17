<?php

namespace Tests\Unit\Services\Firebase;

use App\Services\Firebase\IdTokenVerifier;
use App\Services\Firebase\InvalidIdToken;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MintsFirebaseIdTokens;
use Tests\TestCase;

/**
 * The ID token is the only evidence of who somebody is once Firebase has
 * accepted their password, so most of what is worth testing here is the ways a
 * token should be refused.
 */
class IdTokenVerifierTest extends TestCase
{
    use MintsFirebaseIdTokens;

    private IdTokenVerifier $verifier;

    /** Set to make Google's certificate endpoint fail instead of answering. */
    private ?int $certificateFailureStatus = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureFirebase();

        // A closure rather than a URL map: `Http::fake()` merges stubs and the
        // first match wins, so a second `fake()` in a test could never override
        // this one. Reading a property keeps that switch in the test's hands.
        Http::fake(fn () => $this->certificateFailureStatus !== null
            ? Http::response('', $this->certificateFailureStatus)
            : Http::response(
                [self::FIREBASE_KEY_ID => $this->firebaseFixture('signing-certificate.pem')],
                headers: ['Cache-Control' => 'public, max-age=19860'],
            ));

        $this->verifier = new IdTokenVerifier;
    }

    public function test_it_reads_the_identity_out_of_a_good_token(): void
    {
        $identity = $this->verifier->verify($this->firebaseIdToken());

        $this->assertSame('firebase-uid-1', $identity->uid);
        $this->assertSame('ada@example.com', $identity->email);
        $this->assertTrue($identity->emailVerified);
        $this->assertSame('Ada Lovelace', $identity->name);
        $this->assertSame('https://example.com/ada.jpg', $identity->avatar);
        $this->assertSame('password', $identity->signInProvider);
        $this->assertNull($identity->googleId);
    }

    public function test_it_finds_a_linked_google_account(): void
    {
        $identity = $this->verifier->verify($this->firebaseIdToken([
            'firebase' => [
                'sign_in_provider' => 'google.com',
                'identities' => ['google.com' => ['google-123']],
            ],
        ]));

        $this->assertSame('google-123', $identity->googleId);
        $this->assertSame('google.com', $identity->signInProvider);
    }

    public function test_an_unverified_address_is_reported_as_unverified(): void
    {
        $identity = $this->verifier->verify($this->firebaseIdToken(['email_verified' => false]));

        $this->assertFalse($identity->emailVerified);
    }

    /**
     * Anything not an explicit `true` must not read as verified, or a provider
     * that never tells us could be used to claim someone else's account.
     */
    public function test_a_truthy_but_non_boolean_verification_flag_is_not_trusted(): void
    {
        $identity = $this->verifier->verify($this->firebaseIdToken(['email_verified' => 'true']));

        $this->assertFalse($identity->emailVerified);
    }

    public function test_it_caches_the_certificates_for_the_advertised_lifetime(): void
    {
        $this->verifier->verify($this->firebaseIdToken());
        $this->verifier->verify($this->firebaseIdToken());

        Http::assertSentCount(1);
    }

    public function test_a_token_for_another_firebase_project_is_refused(): void
    {
        $this->assertRefused($this->firebaseIdToken([
            'aud' => 'someone-elses-project',
            'iss' => 'https://securetoken.google.com/someone-elses-project',
        ]));
    }

    public function test_a_token_from_another_issuer_is_refused(): void
    {
        $this->assertRefused($this->firebaseIdToken([
            'iss' => 'https://evil.example.com/'.self::FIREBASE_PROJECT_ID,
        ]));
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->assertRefused($this->firebaseIdToken([
            'iat' => time() - 7200,
            'auth_time' => time() - 7200,
            'exp' => time() - 3600,
        ]));
    }

    public function test_a_token_whose_sign_in_is_in_the_future_is_refused(): void
    {
        $this->assertRefused($this->firebaseIdToken(['auth_time' => time() + 7200]));
    }

    public function test_a_token_signed_by_the_wrong_key_is_refused(): void
    {
        $this->assertRefused($this->firebaseIdToken(
            privateKey: $this->firebaseFixture('other-signing-key.pem'),
        ));
    }

    public function test_a_token_with_an_unknown_key_id_is_refused(): void
    {
        $this->assertRefused($this->firebaseIdToken(keyId: 'some-other-key'));
    }

    /**
     * The classic "alg: none" downgrade. The decoder is handed RS256-pinned
     * keys, so it must not entertain an unsigned token.
     */
    public function test_an_unsigned_token_is_refused(): void
    {
        $segment = fn (array $data) => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

        $this->assertRefused(
            $segment(['alg' => 'none', 'typ' => 'JWT', 'kid' => self::FIREBASE_KEY_ID])
            .'.'.$segment([
                'iss' => 'https://securetoken.google.com/'.self::FIREBASE_PROJECT_ID,
                'aud' => self::FIREBASE_PROJECT_ID,
                'sub' => 'firebase-uid-1',
                'auth_time' => time() - 10,
                'iat' => time() - 10,
                'exp' => time() + 3600,
                'email' => 'ada@example.com',
                'email_verified' => true,
            ]).'.'
        );
    }

    public function test_a_token_with_no_subject_is_refused(): void
    {
        $this->assertRefused($this->firebaseIdToken(['sub' => '']));
    }

    public function test_garbage_is_refused(): void
    {
        $this->assertRefused('not-a-jwt');
    }

    public function test_it_refuses_everything_when_no_project_is_configured(): void
    {
        config()->set('services.firebase.project_id', null);

        $this->assertRefused($this->firebaseIdToken());
    }

    public function test_it_refuses_when_google_cannot_be_reached(): void
    {
        $this->certificateFailureStatus = 503;

        $this->assertRefused($this->firebaseIdToken());
    }

    private function assertRefused(string $token): void
    {
        $this->expectException(InvalidIdToken::class);

        $this->verifier->verify($token);
    }
}
