<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The deploy hook runs migrations from a URL, so the thing worth testing is
 * that it is closed by default and stays shut to anyone without the token.
 */
class DeployHookTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_exist_when_no_token_is_configured(): void
    {
        config()->set('deploy.token', null);

        $this->postJson(route('deploy.hook'))->assertNotFound();
    }

    public function test_it_does_not_exist_without_the_token(): void
    {
        config()->set('deploy.token', 'a-long-random-token');

        $this->postJson(route('deploy.hook'))->assertNotFound();
    }

    /**
     * A wrong token gets the same 404 as a missing one, so probing it reveals
     * nothing about whether the hook is even enabled.
     */
    public function test_a_wrong_token_is_indistinguishable_from_the_hook_being_off(): void
    {
        config()->set('deploy.token', 'a-long-random-token');

        $this->postJson(route('deploy.hook'), [], ['X-Deploy-Token' => 'wrong'])
            ->assertNotFound();
    }

    /**
     * The hook has no 403 in it, at any token, and that is load-bearing when a
     * deploy fails: a 403 in CI means the request was stopped in front of
     * Laravel — a host WAF, usually — and chasing the token is wasted effort.
     * The deploy workflow prints that guidance on a 403, so pin the premise.
     */
    public function test_it_never_answers_forbidden(): void
    {
        // More attempts than the throttle allows, and the throttle has its own
        // test — a 429 here would say nothing about the status under test.
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach ([null, 'a-long-random-token'] as $configured) {
            config()->set('deploy.token', $configured);

            foreach ([[], ['X-Deploy-Token' => ''], ['X-Deploy-Token' => 'wrong']] as $headers) {
                $this->postJson(route('deploy.hook'), [], $headers)
                    ->assertStatus(404);
            }
        }
    }

    public function test_it_runs_the_deploy_commands_with_the_right_token(): void
    {
        config()->set('deploy.token', 'a-long-random-token');

        $this->stubTheCacheBuilders();

        $this->postJson(route('deploy.hook'), [], ['X-Deploy-Token' => 'a-long-random-token'])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['ok', 'failed', 'results' => ['migrate', 'config:cache']]);
    }

    public function test_a_failing_command_is_reported_rather_than_swallowed(): void
    {
        config()->set('deploy.token', 'a-long-random-token');

        $this->stubTheCacheBuilders();

        Artisan::command('migrate', fn () => 1);

        $this->postJson(route('deploy.hook'), [], ['X-Deploy-Token' => 'a-long-random-token'])
            ->assertStatus(500)
            ->assertJson(['ok' => false]);
    }

    public function test_it_is_throttled(): void
    {
        config()->set('deploy.token', 'a-long-random-token');

        foreach (range(1, 5) as $ignored) {
            $this->postJson(route('deploy.hook'), [], ['X-Deploy-Token' => 'wrong']);
        }

        $this->postJson(route('deploy.hook'), [], ['X-Deploy-Token' => 'wrong'])
            ->assertTooManyRequests();
    }

    /**
     * The cache builders write real files into `bootstrap/cache`, and a config
     * cache built here would bake the testing environment -- sqlite on
     * `:memory:` -- into every later request and artisan call, until someone
     * ran `config:clear`. What this test is about is that the hook works
     * through its list and reports what happened, so stub them.
     */
    private function stubTheCacheBuilders(): void
    {
        foreach (['config:cache', 'route:cache', 'view:cache', 'storage:link'] as $command) {
            Artisan::command($command, fn () => 0);
        }
    }
}
