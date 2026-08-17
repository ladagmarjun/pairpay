<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Currencies;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Resolves a Socialite profile to a local account, in three cases:
 *
 * 1. We have seen this Google account before → that user.
 * 2. An account exists with the same email → link Google to it, but only if
 *    Google says the address is verified.
 * 3. Nobody matches → create the account.
 *
 * `google_id`, `avatar`, and `firebase_uid` are deliberately not mass
 * assignable, so every write to them here is an explicit `forceFill`.
 */
class FindOrCreateSocialUser
{
    /**
     * @return User|null null when an existing account could not be safely linked
     */
    public function handle(SocialiteUser $googleUser): ?User
    {
        $existing = User::query()->where('google_id', $googleUser->getId())->first();

        if ($existing !== null) {
            $existing->forceFill(['avatar' => $googleUser->getAvatar()])->save();

            return $existing;
        }

        $email = $googleUser->getEmail();
        $byEmail = User::query()->where('email', $email)->first();

        if ($byEmail !== null) {
            if (! $this->emailIsVerifiedByGoogle($googleUser)) {
                return null;
            }

            $byEmail->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $byEmail->email_verified_at ?? now(),
            ])->save();

            return $byEmail;
        }

        $user = new User([
            'name' => $googleUser->getName() ?: (string) str($email)->before('@'),
            'email' => $email,
            'currency_code' => Currencies::DEFAULT,
        ]);

        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            // Google has already proven they own the address.
            'email_verified_at' => now(),
            // No password: this account signs in with Google. They can set one
            // later through the forgot-password flow if they want to.
            'password' => null,
        ])->save();

        return $user;
    }

    /**
     * Google reports address ownership in the raw profile, which the Socialite
     * contract does not expose — only the concrete OAuth user does. Anything we
     * cannot read as an explicit true is treated as unverified, so a provider
     * that never tells us can't be used to claim someone else's account.
     */
    private function emailIsVerifiedByGoogle(SocialiteUser $googleUser): bool
    {
        if (! $googleUser instanceof AbstractUser) {
            return false;
        }

        return ($googleUser->getRaw()['email_verified'] ?? false) === true;
    }
}
