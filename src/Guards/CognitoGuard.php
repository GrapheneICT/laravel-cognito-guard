<?php

namespace GrapheneICT\CognitoGuard\Guards;

use GrapheneICT\CognitoGuard\Auth\CognitoUserProvider;
use GrapheneICT\CognitoGuard\CognitoUser;
use GrapheneICT\CognitoGuard\JwtVerifier;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
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

        $payload = $this->verifier->verify($token);
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
}
