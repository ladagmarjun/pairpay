<?php

namespace App\Services\Firebase;

use stdClass;

/**
 * The parts of a verified Firebase ID token we actually use.
 *
 * Everything here has already been checked by {@see IdTokenVerifier}, so the
 * rest of the app can treat these values as facts Google vouched for rather
 * than as user input.
 */
final readonly class FirebaseIdentity
{
    public function __construct(
        /** The Firebase uid — `localId` in an auth export, `firebase_uid` here. */
        public string $uid,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
        public ?string $avatar,
        /** e.g. `password`, `google.com`. */
        public ?string $signInProvider,
        /** Present when the person has Google linked inside Firebase. */
        public ?string $googleId,
    ) {}

    /**
     * Build an identity from the token's decoded payload.
     *
     * The claims arrive as nested `stdClass` from the JWT decoder, hence the
     * defensive reads: a missing or oddly-typed claim becomes null rather than
     * a type error.
     */
    public static function fromClaims(stdClass $claims): self
    {
        $email = self::string($claims, 'email');

        return new self(
            uid: (string) $claims->sub,
            email: $email,
            // Firebase reports this as a real boolean. Anything we cannot read
            // as an explicit true is treated as unverified, so an unverified
            // address can never be used to claim someone else's account.
            emailVerified: ($claims->email_verified ?? false) === true,
            name: self::string($claims, 'name'),
            avatar: self::string($claims, 'picture'),
            signInProvider: self::string($claims->firebase ?? null, 'sign_in_provider'),
            googleId: self::googleIdFrom($claims),
        );
    }

    /**
     * Firebase lists every provider linked to the account under
     * `firebase.identities`, each one an array of ids for that provider.
     */
    private static function googleIdFrom(stdClass $claims): ?string
    {
        $identities = $claims->firebase->identities ?? null;

        if (! $identities instanceof stdClass) {
            return null;
        }

        $google = $identities->{'google.com'} ?? null;

        if (! is_array($google) || $google === []) {
            return null;
        }

        return is_scalar($google[0]) ? (string) $google[0] : null;
    }

    /**
     * A non-empty string claim, or null.
     */
    private static function string(?stdClass $source, string $claim): ?string
    {
        $value = $source->{$claim} ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
