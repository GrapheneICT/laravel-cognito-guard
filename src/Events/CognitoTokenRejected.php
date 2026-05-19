<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard\Events;

use GrapheneICT\CognitoGuard\Exceptions\InvalidTokenException;

/**
 * Dispatched when a Cognito JWT fails verification, just before the
 * InvalidTokenException propagates out of the guard.
 *
 * Listeners can use this for alerting / rate-limiting / failed-auth metrics.
 * The raw token is intentionally not included.
 */
final class CognitoTokenRejected
{
    public function __construct(
        public readonly InvalidTokenException $exception,
        public readonly string $pool,
    ) {}
}
