<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard\Guards;

use GrapheneICT\CognitoGuard\Auth\CognitoUserProvider;
use GrapheneICT\CognitoGuard\CognitoUser;
use GrapheneICT\CognitoGuard\Events\CognitoTokenRejected;
use GrapheneICT\CognitoGuard\Events\CognitoTokenValidated;
use GrapheneICT\CognitoGuard\Exceptions\InvalidTokenException;
use GrapheneICT\CognitoGuard\JwtVerifier;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use stdClass;

class CognitoGuard implements Guard
{
    use GuardHelpers;

    private ?stdClass $lastPayload = null;

    public function __construct(
        UserProvider $provider,
        private Request $request,
        private readonly JwtVerifier $verifier,
        private readonly bool $dbLessByDefault = false,
        private readonly string $pool = 'default',
    ) {
        $this->provider = $provider;
    }

    public function user(): ?Authenticatable
    {
        if ($this->user instanceof Authenticatable) {
            return $this->user;
        }

        $token = $this->request->bearerToken();
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $payload = $this->verifier->verify($token);
        } catch (InvalidTokenException $e) {
            $this->log('warning', 'Token rejected: '.$e->getMessage());
            event(new CognitoTokenRejected($e, $this->pool));

            throw $e;
        }
        $this->lastPayload = $payload;

        $sub = $payload->sub ?? null;
        if ($sub === null) {
            return null;
        }

        $resolved = $this->provider instanceof CognitoUserProvider
            ? $this->provider->resolveFromClaims($payload)
            : $this->provider->retrieveById($sub);

        if ($resolved === null && $this->dbLessByDefault) {
            $resolved = CognitoUser::fromClaims($payload);
        }

        if ($resolved !== null) {
            event(new CognitoTokenValidated($resolved, $payload, $this->pool));
        }

        return $this->user = $resolved;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;
        $this->user = null;
        $this->lastPayload = null;

        return $this;
    }

    public function lastPayload(): ?stdClass
    {
        return $this->lastPayload;
    }

    /**
     * Authenticate the guard as a fake user, bypassing JWT verification.
     *
     * Intended for use in test suites of consuming applications, so callers
     * do not have to forge JWTs to exercise routes protected by `auth:cognito`.
     *
     * @param  Authenticatable|array<string, mixed>  $userOrClaims  An Eloquent user, a CognitoUser, or an array of JWT claims.
     * @param  array<string, mixed>  $claims  When the first argument is an Authenticatable, the JWT claims to attach (so the groups→Gates bridge works).
     */
    public function actingAs(Authenticatable|array $userOrClaims, array $claims = []): Authenticatable
    {
        if (is_array($userOrClaims)) {
            $claims = $userOrClaims;
            $user = CognitoUser::fromClaims((object) $claims);
        } else {
            $user = $userOrClaims;
        }

        $this->lastPayload = (object) $claims;
        $this->setUser($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (! (bool) config('cognito-guard.log.enabled', true)) {
            return;
        }

        $channel = config('cognito-guard.log.channel');
        $logger = is_string($channel) && $channel !== '' ? Log::channel($channel) : Log::driver();

        $logger->log($level, '[cognito-guard] '.$message, $context);
    }
}
