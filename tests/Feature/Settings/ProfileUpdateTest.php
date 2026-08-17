<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MintsFirebaseIdTokens;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use MintsFirebaseIdTokens, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureFirebase();
    }

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $this->fakeFirebaseSignInFor($user);

        $response = $this
            ->actingAs($user)
            ->withSession(['firebase.refresh_token' => 'test-refresh-token'])
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());

        // The credential has to go too, or signing in again would silently
        // recreate the profile they just deleted.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'accounts:delete'));
    }

    public function test_the_local_row_survives_when_firebase_will_not_delete_the_account()
    {
        $user = User::factory()->create();
        $user->forceFill(['firebase_uid' => 'firebase-uid-1'])->save();

        Http::fake([
            'identitytoolkit.googleapis.com/v1/accounts:delete*' => Http::response(null, 503),
            ...$this->fakeFirebaseAccepts(['sub' => 'firebase-uid-1', 'email' => $user->email]),
            ...$this->fakeCertificateEndpoint(),
        ]);

        $this->actingAs($user)
            ->withSession(['firebase.refresh_token' => 'test-refresh-token'])
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($user->fresh(), 'Half-deleting the account would be worse than not deleting it.');
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        Http::fake([...$this->fakeFirebaseRefuses(), ...$this->fakeCertificateEndpoint()]);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
