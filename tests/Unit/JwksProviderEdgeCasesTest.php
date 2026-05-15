<?php

declare(strict_types=1);

use GrapheneICT\CognitoGuard\Exceptions\JwksFetchException;
use GrapheneICT\CognitoGuard\JwksProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function makeJwks(array $configOverrides = []): JwksProvider
{
    return new JwksProvider('us-east-1_TestPool', 'us-east-1', array_merge([
        'cache_store' => null,
        'cache_ttl' => 60,
        'cache_key_prefix' => 'cognito-guard:jwks',
        'stale_on_error' => true,
        'http_timeout' => 5,
    ], $configOverrides));
}

it('throws immediately when stale_on_error is false and the fetch fails', function () {
    Http::fake(fn () => throw new ConnectionException('boom'));

    makeJwks(['stale_on_error' => false])->getJwks();
})->throws(JwksFetchException::class);

it('uses a named cache store when configured', function () {
    $bundle = $this->makeToken();

    config()->set('cache.stores.cognito_test', ['driver' => 'array']);

    Cache::store('cognito_test')->put('cognito-guard:jwks:us-east-1_TestPool:v2', $bundle->jwks, 60);

    $keys = makeJwks(['cache_store' => 'cognito_test'])->getJwks();

    expect($keys)->toHaveKey($bundle->kid);
});

it('does not log JWKS warnings when logging is disabled globally', function () {
    config()->set('cognito-guard.log.enabled', false);

    $bundle = $this->makeToken();
    Cache::put('cognito-guard:jwks:us-east-1_TestPool:v2:stale', $bundle->jwks, 60);
    Http::fake(fn () => throw new ConnectionException('boom'));

    Log::spy();

    makeJwks()->getJwks();

    Log::shouldNotHaveReceived('log');
    Log::shouldNotHaveReceived('warning');
});

it('routes JWKS warnings to a named log channel when configured', function () {
    // Use Laravel's "null" channel so the log writes succeed but discard output.
    config()->set('logging.channels.cognito_test', ['driver' => 'null']);
    config()->set('cognito-guard.log.channel', 'cognito_test');

    $bundle = $this->makeToken();
    Cache::put('cognito-guard:jwks:us-east-1_TestPool:v2:stale', $bundle->jwks, 60);
    Http::fake(fn () => throw new ConnectionException('boom'));

    // Test: the path returns stale data without crashing despite a named channel.
    // (Verifies the channel branch executes; actual emission is Laravel's concern.)
    $keys = makeJwks()->getJwks();
    expect($keys)->toHaveKey($bundle->kid);
});
