<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Firebase\FirebaseIdentity;
use App\Support\Currencies;

/**
 * Resolves a verified Firebase identity to a local account, in three cases:
 *
 * 1. We already know this Firebase uid → that user. This is the path every
 *    account imported by `pairpay:import-firebase-users` takes, since the
 *    import wrote `localId` into `firebase_uid`.
 * 2. An account exists with the same email → link Firebase to it, but only if
 *    Firebase says the address is verified.
 * 3. Nobody matches → create the account.
 *
 * Whatever the case, the identity is recorded on the row (`firebase_uid`, and
 * `google_id` when Google is linked inside Firebase) so the person can drop
 * back to the native sign-in paths afterwards and still land here. That is what
 * makes this a bridge rather than a second identity system.
 *
 * `google_id`, `avatar` and `firebase_uid` are deliberately not mass
 * assignable, so every write to them here is an explicit `forceFill`.
 */
class FindOrCreateFirebaseUser
{
    /**
     * @return User|null null when the identity could not be safely resolved
     */
    public function handle(FirebaseIdentity $identity): ?User
    {
        $existing = User::query()->where('firebase_uid', $identity->uid)->first();

        if ($existing !== null) {
            $existing->forceFill($this->linkable($identity, $existing))->save();

            return $existing;
        }

        $email = $identity->email;

        if ($email === null) {
            // An anonymous or phone-only Firebase account. There is nothing to
            // match on and nothing to create an account from.
            return null;
        }

        $byEmail = User::query()->where('email', $email)->first();

        if ($byEmail !== null) {
            if (! $identity->emailVerified) {
                // Firebase will happily mint a token for an email/password
                // account that never confirmed its address. Linking on that
                // alone would let anyone who registered the address in Firebase
                // take over the matching PairPay account.
                return null;
            }

            $byEmail->forceFill([
                ...$this->linkable($identity, $byEmail),
                'firebase_uid' => $identity->uid,
                'email_verified_at' => $byEmail->email_verified_at ?? now(),
            ])->save();

            return $byEmail;
        }

        $user = new User([
            'name' => $identity->name ?: (string) str($email)->before('@'),
            'email' => $email,
            'currency_code' => Currencies::DEFAULT,
        ]);

        $user->forceFill([
            ...$this->linkable($identity, $user),
            'firebase_uid' => $identity->uid,
            // Carried across as-is. An unverified Firebase address stays
            // unverified here, so `verified` middleware still asks for proof.
            'email_verified_at' => $identity->emailVerified ? now() : null,
            // No password: this account signs in through Firebase for now, and
            // is nudged to set one so it eventually will not need to.
            'password' => null,
        ])->save();

        return $user;
    }

    /**
     * The identity details worth copying onto the row.
     *
     * `google_id` is unique, so it is only written when no *other* account
     * already holds it — a collision would otherwise turn a sign-in into a
     * database error.
     *
     * @return array<string, string|null>
     */
    private function linkable(FirebaseIdentity $identity, User $user): array
    {
        $attributes = [];

        if ($identity->avatar !== null) {
            $attributes['avatar'] = $identity->avatar;
        }

        if ($identity->googleId !== null && $user->google_id === null && ! $this->googleIdIsTaken($identity->googleId, $user)) {
            $attributes['google_id'] = $identity->googleId;
        }

        return $attributes;
    }

    private function googleIdIsTaken(string $googleId, User $user): bool
    {
        return User::query()
            ->where('google_id', $googleId)
            ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->exists();
    }
}
