<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use stdClass;

final class CognitoUserProvider implements UserProvider
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $user = $this->newModelQuery()->where($this->subColumn(), (string) $identifier)->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    public function resolveFromClaims(stdClass $claims): ?Authenticatable
    {
        $sub = (string) ($claims->sub ?? '');
        if ($sub === '') {
            return null;
        }

        $user = $this->retrieveById($sub);
        if ($user !== null) {
            return $user;
        }

        if (! $this->autoProvision()) {
            return null;
        }

        $attributes = $this->mapAttributes($claims);
        $attributes[$this->subColumn()] = $sub;

        $modelClass = $this->modelClass();

        return $modelClass::create($attributes);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Token-based auth: no remember token.
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Token-based auth: no password to rehash.
    }

    /**
     * @return Builder<Model>
     */
    private function newModelQuery(): Builder
    {
        $modelClass = $this->modelClass();

        return (new $modelClass)->newQuery();
    }

    /**
     * @return class-string<Model&Authenticatable>
     */
    private function modelClass(): string
    {
        $class = $this->config['model'] ?? null;
        if (! is_string($class) || ! class_exists($class)) {
            throw new \RuntimeException('cognito-guard.user_provider.model is not configured to a valid class.');
        }
        if (! is_subclass_of($class, Model::class) || ! is_subclass_of($class, Authenticatable::class)) {
            throw new \RuntimeException(
                'cognito-guard.user_provider.model must extend '.Model::class.' and implement '.Authenticatable::class.'.',
            );
        }

        return $class;
    }

    private function subColumn(): string
    {
        return (string) ($this->config['sub_column'] ?? 'provider_id');
    }

    private function autoProvision(): bool
    {
        return (bool) ($this->config['auto_provision'] ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAttributes(stdClass $claims): array
    {
        $attributes = [];
        foreach (($this->config['attribute_map'] ?? []) as $claim => $column) {
            if (isset($claims->{$claim})) {
                $attributes[(string) $column] = $claims->{$claim};
            }
        }

        return $attributes;
    }
}
