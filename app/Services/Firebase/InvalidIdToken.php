<?php

namespace App\Services\Firebase;

use RuntimeException;

/**
 * Thrown whenever an ID token cannot be trusted — a bad signature, a token
 * minted for another Firebase project, an expired one, or a set of claims that
 * does not look like something Firebase issued.
 *
 * The message is for the log, never for the browser: telling a caller *why*
 * their token was rejected helps nobody but someone probing the endpoint.
 */
class InvalidIdToken extends RuntimeException {}
