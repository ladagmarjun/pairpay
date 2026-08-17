<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    public function test_the_redirect_sends_the_user_to_google(): void
    {
        $response = $this->get(route('google.redirect'));

        $response->assertRedirectContains('accounts.google.com');
    }

    public function test_a_new_google_account_is_created_and_signed_in(): void
    {
        $this->mockGoogleUser();

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'ada@example.com')->sole();

        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('https://example.com/ada.jpg', $user->avatar);
        $this->assertSame('USD', $user->currency_code);
        $this->assertNull($user->password, 'A Google-only account has no password.');
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_returning_google_account_does_not_create_a_duplicate(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $user->forceFill(['google_id' => 'google-123'])->save();

        $this->mockGoogleUser();

        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

        $this->assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_verified_google_address_links_to_an_existing_password_account(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->mockGoogleUser();

        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertSame('google-123', $user->google_id);
        $this->assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_unverified_google_address_cannot_take_over_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->mockGoogleUser(emailVerified: false);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');

        $this->assertNull($user->fresh()->google_id);
        $this->assertGuest();
    }

    public function test_a_failed_handshake_returns_to_the_login_page(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('boom'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function mockGoogleUser(bool $emailVerified = true): void
    {
        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-123';
        $googleUser->name = 'Ada Lovelace';
        $googleUser->email = 'ada@example.com';
        $googleUser->avatar = 'https://example.com/ada.jpg';
        $googleUser->user = ['email_verified' => $emailVerified];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
