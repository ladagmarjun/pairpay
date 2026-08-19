<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_screen()
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    public function test_signed_in_users_are_sent_to_the_dashboard()
    {
        $response = $this->actingAs(User::factory()->create())->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }
}
