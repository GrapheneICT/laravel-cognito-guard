<?php

use GrapheneICT\CognitoGuard\Exceptions\InvalidTokenException;
use GrapheneICT\CognitoGuard\JwksProvider;
use GrapheneICT\CognitoGuard\JwtVerifier;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->bundle = $this->makeToken();
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $this->bundle->jwks, 60);
});

function makeVerifier($test, array $overrides = []): JwtVerifier
{
    $poolConfig = array_merge([
        'user_pool_id' => $test->poolId,
        'region' => $test->region,
        'allowed_token_use' => ['access', 'id'],
        'allowed_client_ids' => [],
        'required_scopes' => [],
        'leeway' => 0,
    ], $overrides);

    $jwks = new JwksProvider($test->poolId, $test->region, [
        'cache_store' => null,
        'cache_ttl' => 60,
        'cache_key_prefix' => 'cognito-guard:jwks',
        'stale_on_error' => true,
        'http_timeout' => 5,
    ]);

    return new JwtVerifier($jwks, $poolConfig);
}

it('verifies a valid Cognito access token', function () {
    $payload = makeVerifier($this)->verify($this->bundle->jwt);

    expect($payload->sub)->toBe($this->bundle->sub)
        ->and($payload->token_use)->toBe('access');
});

it('rejects a token with the wrong segment count', function () {
    makeVerifier($this)->verify('not.a.valid.jwt');
})->throws(InvalidTokenException::class, 'wrong number of segments');

it('rejects a token missing kid', function () {
    $header = ['alg' => 'RS256'];
    $token = base64_encode(json_encode($header)).'.seg2.seg3';

    makeVerifier($this)->verify($token);
})->throws(InvalidTokenException::class, 'No kid');

it('rejects a token with alg other than RS256', function () {
    $header = ['kid' => 'k', 'alg' => 'HS256'];
    $token = base64_encode(json_encode($header)).'.seg2.seg3';

    makeVerifier($this)->verify($token);
})->throws(InvalidTokenException::class, 'is not RS256');

it('rejects a token with the wrong issuer', function () {
    $bundle = $this->makeToken(['iss' => 'https://attacker.example.com/pool']);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    makeVerifier($this)->verify($bundle->jwt);
})->throws(InvalidTokenException::class, 'Invalid issuer');

it('rejects a token with disallowed token_use', function () {
    $bundle = $this->makeToken(['token_use' => 'refresh']);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    makeVerifier($this, ['allowed_token_use' => ['access', 'id']])->verify($bundle->jwt);
})->throws(InvalidTokenException::class, 'Invalid token_use');

it('rejects a token whose client_id is not in the allow-list', function () {
    $bundle = $this->makeToken(['client_id' => 'other-client']);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    makeVerifier($this, ['allowed_client_ids' => ['expected-client']])->verify($bundle->jwt);
})->throws(InvalidTokenException::class, 'client_id/aud is not in the allow-list');

it('accepts an id token via the aud claim when client_id is absent', function () {
    $bundle = $this->makeToken([
        'token_use' => 'id',
        'aud' => 'audience-A',
        'client_id' => null,
    ]);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    $payload = makeVerifier($this, ['allowed_client_ids' => ['audience-A']])->verify($bundle->jwt);

    expect($payload->aud)->toBe('audience-A');
});

it('rejects a token missing required scopes', function () {
    $bundle = $this->makeToken(['scope' => 'aws.cognito.signin.user.admin']);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    makeVerifier($this, ['required_scopes' => ['payments:read']])->verify($bundle->jwt);
})->throws(InvalidTokenException::class, 'missing required scopes');

it('accepts a token whose scopes include all required ones', function () {
    $bundle = $this->makeToken(['scope' => 'payments:read payments:write']);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    $payload = makeVerifier($this, ['required_scopes' => ['payments:read']])->verify($bundle->jwt);

    expect($payload->scope)->toContain('payments:read');
});
