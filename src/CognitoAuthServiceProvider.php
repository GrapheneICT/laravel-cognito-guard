<?php

namespace GrapheneICT\CognitoGuard;

use GrapheneICT\CognitoGuard\Auth\CognitoUserProvider;
use GrapheneICT\CognitoGuard\Guards\CognitoGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class CognitoAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cognito-guard.php' => config_path('cognito-guard.php'),
            ], 'cognito-guard-config');
        }

        $this->registerAuth();
        $this->registerGroupsToGatesBridge();
        $this->registerAboutCommand();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cognito-guard.php', 'cognito-guard');
    }

    private function registerAuth(): void
    {
        Auth::provider('cognito', function (Application $app, array $config): CognitoUserProvider {
            return new CognitoUserProvider(
                array_merge(config('cognito-guard.user_provider', []), $config),
            );
        });

        Auth::extend('cognito', function (Application $app, string $name, array $config): CognitoGuard {
            $poolName = $config['pool'] ?? config('cognito-guard.default_pool', 'default');
            $poolConfig = config('cognito-guard.pools.'.$poolName);

            if (! is_array($poolConfig)) {
                throw new RuntimeException(sprintf(
                    'Cognito pool "%s" is not configured in cognito-guard.pools.',
                    $poolName,
                ));
            }

            if (empty($poolConfig['user_pool_id'])) {
                throw new RuntimeException(sprintf(
                    'Cognito pool "%s" is missing user_pool_id.',
                    $poolName,
                ));
            }

            $jwks = new JwksProvider(
                $poolConfig['user_pool_id'],
                (string) ($poolConfig['region'] ?? config('cognito-guard.pools.default.region', 'us-east-1')),
                config('cognito-guard.jwks', []),
            );

            $verifier = new JwtVerifier($jwks, $poolConfig);

            $provider = $app['auth']->createUserProvider($config['provider'] ?? null);

            $dbLess = (bool) ($config['db_less'] ?? false);

            return new CognitoGuard($provider, $app['request'], $verifier, $dbLess);
        });
    }

    private function registerGroupsToGatesBridge(): void
    {
        if (! (bool) config('cognito-guard.bridge_groups_to_gates', true)) {
            return;
        }

        Gate::before(function (?Authenticatable $user, string $ability): ?bool {
            if ($user === null) {
                return null;
            }

            $groups = $this->resolveGroups($user);
            if ($groups === []) {
                return null;
            }

            return in_array($ability, $groups, true) ? true : null;
        });
    }

    /**
     * @return array<int, string>
     */
    private function resolveGroups(Authenticatable $user): array
    {
        if ($user instanceof CognitoUser) {
            return $user->groups();
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

            $claim = $payload->{'cognito:groups'} ?? [];

            return is_array($claim) ? array_values(array_map('strval', $claim)) : [];
        }

        return [];
    }

    private function registerAboutCommand(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Cognito Guard', fn (): array => [
            'Default pool' => (string) config('cognito-guard.default_pool', 'default'),
            'Configured pools' => implode(', ', array_keys((array) config('cognito-guard.pools', []))),
            'Auto provision' => config('cognito-guard.user_provider.auto_provision') ? 'enabled' : 'disabled',
            'Groups → Gates bridge' => config('cognito-guard.bridge_groups_to_gates') ? 'enabled' : 'disabled',
            'JWKS cache TTL (s)' => (string) (int) config('cognito-guard.jwks.cache_ttl', 21600),
        ]);
    }
}
