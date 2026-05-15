<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use GrapheneICT\CognitoGuard\Exceptions\InvalidTokenException;
use GrapheneICT\CognitoGuard\JwksProvider;
use GrapheneICT\CognitoGuard\JwtVerifier;
use Illuminate\Support\Facades\Cache;

function verifier(array $poolOverrides = []): JwtVerifier
{
    return new JwtVerifier(
        new JwksProvider('us-east-1_TestPool', 'us-east-1', [
            'cache_store' => null,
            'cache_ttl' => 60,
            'cache_key_prefix' => 'cognito-guard:jwks',
            'stale_on_error' => true,
            'http_timeout' => 5,
        ]),
        array_merge([
            'allowed_token_use' => ['access', 'id'],
            'allowed_client_ids' => [],
            'required_scopes' => [],
            'leeway' => 0,
        ], $poolOverrides),
    );
}

it('rejects a token whose kid does not appear in the JWKS', function () {
    $bundle = $this->makeToken();
    $other = $this->makeToken();
    // Cache *other* bundle's keys; the verifier will fail to find the original kid.
    Cache::put('cognito-guard:jwks:us-east-1_TestPool:v2', $other->jwks, 60);

    verifier()->verify($bundle->jwt);
})->throws(InvalidTokenException::class, 'Unknown kid');

it('honors leeway when a token has just expired', function () {
    // Encode a token whose exp is 5 seconds in the past.
    $now = time();
    $bundle = $this->makeToken([
        'exp' => $now - 5,
        'iat' => $now - 3600,
    ]);
    Cache::put('cognito-guard:jwks:us-east-1_TestPool:v2', $bundle->jwks, 60);

    // 30s leeway should accept the just-expired token.
    $payload = verifier(['leeway' => 30])->verify($bundle->jwt);
    expect($payload->sub)->toBe($bundle->sub);
});

it('rejects a token whose header is not valid base64 / JSON', function () {
    // Build a structurally-correct three-segment JWT whose header is garbage.
    $broken = base64_encode('not-json').'.eyJzdWIiOiJ4In0.sig';

    verifier()->verify($broken);
})->throws(InvalidTokenException::class);

it('rejects a token missing both username and cognito:username', function () {
    $bundle = $this->makeToken(['username' => null]);
    Cache::put('cognito-guard:jwks:us-east-1_TestPool:v2', $bundle->jwks, 60);

    verifier()->verify($bundle->jwt);
})->throws(InvalidTokenException::class, 'must include one of');
