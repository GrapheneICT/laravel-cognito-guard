<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard\Guards;

use GrapheneICT\CognitoGuard\Auth\CognitoUserProvider;
use GrapheneICT\CognitoGuard\CognitoUser;
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
