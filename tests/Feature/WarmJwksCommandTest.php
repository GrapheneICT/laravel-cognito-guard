<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('warms the JWKS cache for all configured pools', function () {
    $bundle = $this->makeToken();
    Http::fake(['*/.well-known/jwks.json' => Http::response($bundle->jwks, 200)]);

    $this->artisan('cognito:warm-jwks')
        ->assertExitCode(0)
        ->expectsOutputToContain('warmed');

    expect(Cache::get('cognito-guard:jwks:'.$this->poolId.':v2'))->toBe($bundle->jwks);
});

it('honors --pool to warm a single pool', function () {
    config()->set('cognito-guard.pools.partners', [
        'user_pool_id' => 'us-east-1_PartnersPool',
        'region' => 'us-east-1',
    ]);

    $bundle = $this->makeToken();
    Http::fake(['*/.well-known/jwks.json' => Http::response($bundle->jwks, 200)]);

    $this->artisan('cognito:warm-jwks', ['--pool' => 'partners'])->assertExitCode(0);

    expect(Cache::has('cognito-guard:jwks:us-east-1_PartnersPool:v2'))->toBeTrue()
        ->and(Cache::has('cognito-guard:jwks:'.$this->poolId.':v2'))->toBeFalse();
});

it('fails when --pool names a pool that is not configured', function () {
    $this->artisan('cognito:warm-jwks', ['--pool' => 'ghost'])
        ->assertExitCode(1)
        ->expectsOutputToContain('not configured');
});

it('returns failure when the JWKS fetch fails and no stale cache exists', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    $this->artisan('cognito:warm-jwks')
        ->assertExitCode(1)
        ->expectsOutputToContain('fetch failed');
});

it('reports missing user_pool_id as a failure', function () {
    config()->set('cognito-guard.pools.broken', ['region' => 'us-east-1']);

    $this->artisan('cognito:warm-jwks', ['--pool' => 'broken'])
        ->assertExitCode(1)
        ->expectsOutputToContain('missing user_pool_id');
});
