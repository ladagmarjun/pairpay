<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;
use Tests\Concerns\MintsFirebaseIdTokens;
use Tests\TestCase;

/**
 * Registration creates the account in Firebase and mirrors it here, so the row
 * that lands in `users` carries a `firebase_uid` and no password at all.
 */
class RegistrationTest extends TestCase
{
    use MintsFirebaseIdTokens, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
        $this->configureFirebase();
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        Http::fake([
            ...$this->fakeFirebaseAccepts(['sub' => 'new-firebase-uid', 'email' => 'test@example.com']),
            ...$this->fakeCertificateEndpoint(),
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->sole();

        $this->assertSame('new-firebase-uid', $user->firebase_uid);
        $this->assertNull($user->password, 'The password belongs to Firebase, not the users table.');
        $this->assertSame('USD', $user->currency_code);
    }

    public function test_an_address_firebase_already_knows_is_a_validation_error()
    {
        Http::fake([
            self::IDENTITY_TOOLKIT_URL => Http::response([
                'error' => ['code' => 400, 'message' => 'EMAIL_EXISTS'],
            ], status: 400),
        ]);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::query()->where('email', 'taken@example.com')->count());
    }

    public function test_no_local_row_is_left_behind_when_firebase_is_unreachable()
    {
        Http::fake([self::IDENTITY_TOOLKIT_URL => Http::response(null, 503)]);

        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }
}
