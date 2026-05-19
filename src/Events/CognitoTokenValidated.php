<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use stdClass;

/**
 * Dispatched after a Cognito JWT has been verified AND a user has been resolved.
 *
 * Listeners can use this for audit logs, telemetry, "last seen" updates, etc.
 * The raw JWT is intentionally not included — log claims only.
 */
final class CognitoTokenValidated
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly stdClass $claims,
        public readonly string $pool,
    ) {}
}
