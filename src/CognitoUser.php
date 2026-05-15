<?php

namespace GrapheneICT\CognitoGuard;

use Illuminate\Contracts\Auth\Authenticatable;
use stdClass;

class CognitoUser implements Authenticatable
{
    public function __construct(public readonly stdClass $claims) {}

    public static function fromClaims(stdClass $claims): self
    {
        return new self($claims);
    }

    public function getAuthIdentifierName(): string
    {
        return 'sub';
    }

    public function getAuthIdentifier(): string
    {
        return (string) ($this->claims->sub ?? '');
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Token-based auth: no remember token.
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function username(): ?string
    {
        return $this->claims->username
            ?? $this->claims->{'cognito:username'}
            ?? null;
    }

    public function email(): ?string
    {
        return isset($this->claims->email) ? (string) $this->claims->email : null;
    }

    /**
     * @return array<int, string>
     */
    public function groups(): array
    {
        $groups = $this->claims->{'cognito:groups'} ?? [];

        return is_array($groups) ? array_values(array_map('strval', $groups)) : [];
    }

    /**
     * @return array<int, string>
     */
    public function scopes(): array
    {
        $scope = $this->claims->scope ?? null;
        if (! is_string($scope) || $scope === '') {
            return [];
        }

        return preg_split('/\s+/', $scope, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public function tokenUse(): ?string
    {
        return isset($this->claims->token_use) ? (string) $this->claims->token_use : null;
    }

    public function claims(): stdClass
    {
        return $this->claims;
    }

    public function claim(string $name, mixed $default = null): mixed
    {
        return $this->claims->{$name} ?? $default;
    }
}
