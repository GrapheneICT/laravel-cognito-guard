<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard\Http\Middleware;

use Closure;
use GrapheneICT\CognitoGuard\CognitoUser;
use GrapheneICT\CognitoGuard\Guards\CognitoGuard;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Per-route OAuth scope enforcement, complementing the pool-wide
 * `required_scopes` config knob.
 *
 *     Route::middleware(['auth:cognito', 'cognito.scope:read,write'])->...;
 *
 * 401 if unauthenticated, 403 if any required scope is missing.
 */
final class EnsureCognitoScopes
{
    public function handle(Request $request, Closure $next, string ...$scopes): mixed
    {
        $user = Auth::user();
        if (! $user instanceof Authenticatable) {
            throw new AuthenticationException;
        }

        $granted = $this->resolveScopes($user);

        foreach ($scopes as $required) {
            if (! in_array($required, $granted, true)) {
                throw new HttpException(403, sprintf('Missing required Cognito scope: %s', $required));
            }
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function resolveScopes(Authenticatable $user): array
    {
        if ($user instanceof CognitoUser) {
            return $user->scopes();
        }

        foreach ((array) config('auth.guards', []) as $guardName => $guardConfig) {
            if (($guardConfig['driver'] ?? null) !== 'cognito') {
                continue;
            }

            $guard = Auth::guard($guardName);
            if (! $guard instanceof CognitoGuard || ! $guard->check()) {
                continue;
            }

            if ($guard->user() !== $user) {
                continue;
            }

            $payload = $guard->lastPayload();
            if ($payload === null) {
                continue;
            }

            $scope = $payload->scope ?? null;
            if (! is_string($scope) || $scope === '') {
                return [];
            }

            return preg_split('/\s+/', $scope, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return [];
    }
}
