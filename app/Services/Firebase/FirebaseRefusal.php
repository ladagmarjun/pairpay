<?php

namespace App\Services\Firebase;

use RuntimeException;

/**
 * Firebase declined something, and the caller needs to know why in order to say
 * something sensible to the person — "that address is already registered" reads
 * very differently from "we could not reach Firebase".
 *
 * The `errorCode` is Firebase's own, e.g. `EMAIL_EXISTS`, `WEAK_PASSWORD`,
 * `INVALID_LOGIN_CREDENTIALS`, or `UNAVAILABLE` when the request never landed.
 * It is deliberately not called `code`: `Exception` already has one, and it is
 * an untyped int.
 */
class FirebaseRefusal extends RuntimeException
{
    public const UNAVAILABLE = 'UNAVAILABLE';

    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    /**
     * Whether Firebase answered at all. A refusal it never sent is a fault on
     * our side of the wire, not a verdict on the credentials.
     */
    public function isOutage(): bool
    {
        return $this->errorCode === self::UNAVAILABLE;
    }
}
