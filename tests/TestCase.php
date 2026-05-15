<?php

namespace GrapheneICT\CognitoGuard\Tests;

use Firebase\JWT\JWT;
use GrapheneICT\CognitoGuard\CognitoAuthServiceProvider;
use GrapheneICT\CognitoGuard\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use phpseclib3\Crypt\RSA;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    public string $poolId = 'us-east-1_TestPool';

    public string $region = 'us-east-1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [CognitoAuthServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.defaults.guard', 'cognito');
        $app['config']->set('auth.guards.cognito', [
            'driver' => 'cognito',
            'provider' => 'cognito',
            'pool' => 'default',
        ]);
        $app['config']->set('auth.providers.cognito', [
            'driver' => 'cognito',
        ]);

        $app['config']->set('cognito-guard.default_pool', 'default');
        $app['config']->set('cognito-guard.pools.default', [
            'user_pool_id' => $this->poolId,
            'region' => $this->region,
            'allowed_token_use' => ['access', 'id'],
            'allowed_client_ids' => [],
            'required_scopes' => [],
            'leeway' => 0,
        ]);
        $app['config']->set('cognito-guard.jwks', [
            'cache_store' => 'array',
            'cache_ttl' => 21600,
            'cache_key_prefix' => 'cognito-guard:jwks',
            'stale_on_error' => true,
            'http_timeout' => 5,
        ]);
        $app['config']->set('cognito-guard.user_provider', [
            'auto_provision' => true,
            'model' => User::class,
            'sub_column' => 'provider_id',
            'attribute_map' => [
                'email' => 'email',
                'cognito:username' => 'name',
            ],
        ]);
        $app['config']->set('cognito-guard.bridge_groups_to_gates', true);
    }

    protected function getIssuer(): string
    {
        return sprintf('https://cognito-idp.%s.amazonaws.com/%s', $this->region, $this->poolId);
    }

    /**
     * Build a signed JWT plus its matching JWKS using openssl.
     *
     * @param  array<string, mixed>  $payloadOverrides
     * @return object{payload: array, jwt: string, jwks: array, kid: string, sub: string}
     */
    protected function makeToken(array $payloadOverrides = []): object
    {
        ['privatePem' => $privatePem, 'jwk' => $jwk, 'kid' => $kid] = $this->generateKey();

        $sub = (string) ($payloadOverrides['sub'] ?? $this->uuid());
        $now = time();

        $payload = array_merge([
            'sub' => $sub,
            'token_use' => 'access',
            'iss' => $this->getIssuer(),
            'exp' => $now + 3600,
            'iat' => $now,
            'auth_time' => $now,
            'jti' => $this->uuid(),
            'client_id' => 'test-client',
            'username' => $sub,
            'scope' => 'aws.cognito.signin.user.admin',
        ], $payloadOverrides);

        // Allow `null` overrides to remove a default claim entirely.
        $payload = array_filter($payload, fn ($v) => $v !== null);

        $jwt = JWT::encode($payload, $privatePem, 'RS256', $kid);

        return (object) [
            'payload' => $payload,
            'jwt' => $jwt,
            'jwks' => ['keys' => [$jwk]],
            'kid' => $kid,
            'sub' => $sub,
        ];
    }

    /**
     * @return array{privatePem: string, jwk: array, kid: string}
     */
    private function generateKey(): array
    {
        $private = RSA::createKey(2048);

        $privatePem = (string) $private->toString('PKCS8');
        $publicKey = $private->getPublicKey();
        $publicPem = (string) $publicKey->toString('PKCS8');

        $kid = $this->base64url(hash('sha256', $publicPem, true));

        $jwkJson = (string) $publicKey->toString('JWK');
        $jwk = json_decode($jwkJson, true)['keys'][0];
        $jwk['kid'] = $kid;
        $jwk['alg'] = 'RS256';
        $jwk['use'] = 'sig';

        return [
            'privatePem' => $privatePem,
            'jwk' => $jwk,
            'kid' => $kid,
        ];
    }

    private function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
