<?php

use GrapheneICT\CognitoGuard\Exceptions\JwksFetchException;
use GrapheneICT\CognitoGuard\JwksProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function makeJwksProvider($test): JwksProvider
{
    return new JwksProvider($test->poolId, $test->region, [
        'cache_store' => null,
        'cache_ttl' => 60,
        'cache_key_prefix' => 'cognito-guard:jwks',
        'stale_on_error' => true,
        'http_timeout' => 5,
    ]);
}

it('serves JWKS from cache when present', function () {
    $bundle = $this->makeToken();
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    $keys = makeJwksProvider($this)->getJwks();

    expect($keys)->toHaveKey($bundle->kid);
    Http::assertNothingSent();
});

it('fetches JWKS over HTTP on cache miss and writes both fresh and stale entries', function () {
    $bundle = $this->makeToken();
    Http::fake([
        '*/.well-known/jwks.json' => Http::response($bundle->jwks, 200),
    ]);

    $keys = makeJwksProvider($this)->getJwks();

    expect($keys)->toHaveKey($bundle->kid)
        ->and(Cache::get('cognito-guard:jwks:'.$this->poolId.':v2'))->toBe($bundle->jwks)
        ->and(Cache::get('cognito-guard:jwks:'.$this->poolId.':v2:stale'))->toBe($bundle->jwks);
});

it('falls back to stale cache when Cognito is unreachable', function () {
    $bundle = $this->makeToken();
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2:stale', $bundle->jwks, 60);
    Http::fake(fn () => throw new ConnectionException('Network down'));

    $keys = makeJwksProvider($this)->getJwks();

    expect($keys)->toHaveKey($bundle->kid);
});

it('throws JwksFetchException when both fresh and stale caches are empty and the fetch fails', function () {
    Http::fake(fn () => throw new ConnectionException('Network down'));

    makeJwksProvider($this)->getJwks();
})->throws(JwksFetchException::class, 'Failed to fetch JWKS');

it('builds the correct Cognito issuer URL', function () {
    expect(makeJwksProvider($this)->getIssuer())
        ->toBe('https://cognito-idp.us-east-1.amazonaws.com/us-east-1_TestPool');
});
