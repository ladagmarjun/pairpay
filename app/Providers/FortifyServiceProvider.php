<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUser;
use App\Actions\Fortify\CreateNewUser;
use App\Services\Firebase\FirebaseUserSession;
use App\Services\Firebase\IdentityToolkit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, not bound fresh: Fortify runs its authentication callback
        // twice per login — once to decide about two-factor, once to sign in —
        // and `IdentityToolkit` remembers its answer within the request so that
        // costs one round trip rather than two.
        $this->app->scoped(IdentityToolkit::class);

        // Scoped for the same reason, and built by hand because the session
        // contract is not one the container resolves on its own.
        $this->app->scoped(FirebaseUserSession::class, fn ($app) => new FirebaseUserSession(
            $app['session.store'],
            $app->make(IdentityToolkit::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        // Replaces Fortify's own credential check: passwords live in Firebase,
        // not in `users.password`. Rate limiting, the two-factor step and the
        // rest of the login pipeline are untouched.
        Fortify::authenticateUsing(fn (Request $request) => $this->app->make(AuthenticateUser::class)->handle(
            (string) $request->input(Fortify::username(), ''),
            (string) $request->input('password', ''),
        ));

        // Password confirmation guards two-factor and passkey management, and
        // compares against `users.password` by default — which is never set.
        Fortify::confirmPasswordsUsing(
            fn ($user, string $password) => $this->app->make(IdentityToolkit::class)
                ->signIn((string) $user->email, $password) !== null,
        );
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        // Password resets and email verification are Firebase's now — it sends
        // the mail and hosts the pages — so Fortify has no views for them. See
        // `PasswordResetController` and `EmailVerificationController`.

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
