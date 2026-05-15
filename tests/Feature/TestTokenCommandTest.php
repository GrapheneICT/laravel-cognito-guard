<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->bundle = $this->makeToken();
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $this->bundle->jwks, 60);
});

it('reports a valid token as valid', function () {
    $this->artisan('cognito:test-token', ['token' => $this->bundle->jwt])
        ->expectsOutputToContain('Validating token against pool "default"')
        ->expectsOutputToContain('Token is valid.')
        ->assertExitCode(0);
});

it('strips a Bearer prefix from the token argument', function () {
    $this->artisan('cognito:test-token', ['token' => 'Bearer '.$this->bundle->jwt])
        ->expectsOutputToContain('Token is valid.')
        ->assertExitCode(0);
});

it('prints the full claims payload with --verbose-claims', function () {
    $this->artisan('cognito:test-token', [
        'token' => $this->bundle->jwt,
        '--verbose-claims' => true,
    ])
        ->expectsOutputToContain('Full claims payload')
        ->expectsOutputToContain('"token_use"')
        ->assertExitCode(0);
});

it('reports a non-existent pool as misconfigured', function () {
    $this->artisan('cognito:test-token', [
        'token' => $this->bundle->jwt,
        '--pool' => 'does-not-exist',
    ])
        ->expectsOutputToContain('Pool "does-not-exist" is not configured')
        ->assertExitCode(1);
});

it('rejects a malformed token with an InvalidTokenException', function () {
    $this->artisan('cognito:test-token', ['token' => 'not.a.valid.jwt'])
        ->expectsOutputToContain('Token rejected')
        ->assertExitCode(1);
});

it('rejects a token whose signature does not verify against the JWKS', function () {
    // Generate a *different* keypair token whose kid is not in the cached JWKS.
    $foreign = $this->makeToken();
    // Keep the original JWKS cached (so the foreign kid is unknown).

    $this->artisan('cognito:test-token', ['token' => $foreign->jwt])
        ->expectsOutputToContain('Token rejected')
        ->assertExitCode(1);
});
