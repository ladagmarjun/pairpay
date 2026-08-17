<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MintsFirebaseIdTokens;
use Tests\TestCase;

/**
 * Firebase sends the reset mail and hosts the page it lands on, so all this app
 * does is ask — and take care not to answer the question "does this address
 * have an account?" while doing it.
 */
class PasswordResetTest extends TestCase
{
    use MintsFirebaseIdTokens, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureFirebase();
    }

    public function test_the_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_it_asks_firebase_to_send_the_reset_email(): void
    {
        Http::fake([self::IDENTITY_TOOLKIT_URL => Http::response(['email' => 'ada@example.com'])]);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'ada@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendOobCode')
            && $request['requestType'] === 'PASSWORD_RESET'
            && $request['email'] === 'ada@example.com');
    }

    /**
     * Saying "no such account" here would turn this form into a way to discover
     * who has one, so an unknown address gets the same answer as a known one.
     */
    public function test_an_unknown_address_is_answered_the_same_way(): void
    {
        Http::fake([
            self::IDENTITY_TOOLKIT_URL => Http::response([
                'error' => ['code' => 400, 'message' => 'EMAIL_NOT_FOUND'],
            ], status: 400),
        ]);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }

    public function test_an_outage_is_admitted_to(): void
    {
        Http::fake([self::IDENTITY_TOOLKIT_URL => fn () => throw new \RuntimeException('down')]);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'ada@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_the_email_is_required(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.email'), [])
            ->assertSessionHasErrors('email');
    }

    /**
     * Firebase owns the reset page, so this app deliberately has no route for
     * it. A leftover link should 404 rather than land somewhere half-working.
     */
    public function test_there_is_no_local_reset_password_route(): void
    {
        $this->get('/reset-password/some-token')->assertNotFound();
    }
}
