<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MintsFirebaseIdTokens;
use Tests\TestCase;

/**
 * Verification happens on Firebase's pages. This app only has to notice, which
 * it does by re-asking Firebase every time the notice screen is opened — so
 * clicking the link in the mail and coming back is the whole flow.
 */
class EmailVerificationTest extends TestCase
{
    use MintsFirebaseIdTokens, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureFirebase();
    }

    public function test_the_notice_screen_is_shown_to_an_unverified_user(): void
    {
        Http::fake([
            self::IDENTITY_TOOLKIT_URL => Http::response(['users' => [['emailVerified' => false]]]),
            ...$this->fakeTokenRefresh(),
        ]);

        $this->actingAs($this->unverifiedUser())
            ->withSession(['firebase.refresh_token' => 'test-refresh-token'])
            ->get(route('verification.notice'))
            ->assertOk();
    }

    public function test_a_verified_user_is_sent_on_to_the_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertRedirect(route('dashboard'));
    }

    /**
     * The point of re-asking: they verified on Firebase's page, came back, and
     * should simply be through.
     */
    public function test_verifying_in_firebase_is_noticed_on_the_next_visit(): void
    {
        $user = $this->unverifiedUser();

        Http::fake([
            self::IDENTITY_TOOLKIT_URL => Http::response(['users' => [['emailVerified' => true]]]),
            ...$this->fakeTokenRefresh(),
        ]);

        $this->actingAs($user)
            ->withSession(['firebase.refresh_token' => 'test-refresh-token'])
            ->get(route('verification.notice'))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_the_verification_email_can_be_resent(): void
    {
        Http::fake([
            self::IDENTITY_TOOLKIT_URL => Http::response(['email' => 'ada@example.com']),
            ...$this->fakeTokenRefresh(),
        ]);

        $this->actingAs($this->unverifiedUser())
            ->withSession(['firebase.refresh_token' => 'test-refresh-token'])
            ->post(route('verification.send'))
            ->assertSessionHas('status', 'verification-link-sent');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendOobCode')
            && $request['requestType'] === 'VERIFY_EMAIL');
    }

    /**
     * Without a usable refresh token the app cannot act for them, and should
     * say so rather than silently doing nothing.
     */
    public function test_resending_asks_them_to_sign_in_again_when_the_session_has_no_token(): void
    {
        $this->actingAs($this->unverifiedUser())
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertSessionHasErrors('email');
    }

    public function test_the_notice_screen_requires_authentication(): void
    {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
    }

    private function unverifiedUser(): User
    {
        $user = User::factory()->unverified()->create();
        $user->forceFill(['firebase_uid' => 'firebase-uid-1'])->save();

        return $user;
    }
}
