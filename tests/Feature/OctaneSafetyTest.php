<?php

use GrapheneICT\CognitoGuard\Guards\CognitoGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

it('does not leak user state when the same Guard instance handles consecutive requests with different tokens', function () {
    $bundleA = $this->makeToken();
    $bundleB = $this->makeToken();

    // Both pools' JWKs must be cached so the verifier can resolve each kid.
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', [
        'keys' => [$bundleA->jwks['keys'][0], $bundleB->jwks['keys'][0]],
    ], 60);

    config()->set('auth.guards.cognito.db_less', true);
    config()->set('cognito-guard.user_provider.auto_provision', false);

    /** @var CognitoGuard $guard */
    $guard = Auth::guard('cognito');
    expect($guard)->toBeInstanceOf(CognitoGuard::class);

    $requestA = Request::create('/me', 'GET', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$bundleA->jwt,
    ]);
    $guard->setRequest($requestA);
    $userA = $guard->user();
    expect($userA?->getAuthIdentifier())->toBe($bundleA->sub);

    $requestB = Request::create('/me', 'GET', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$bundleB->jwt,
    ]);
    $guard->setRequest($requestB);
    $userB = $guard->user();
    expect($userB?->getAuthIdentifier())->toBe($bundleB->sub)
        ->and($userA?->getAuthIdentifier())->not->toBe($userB?->getAuthIdentifier());
});

it('does not leak lastPayload across requests', function () {
    $bundleA = $this->makeToken(['cognito:groups' => ['admins']]);
    $bundleB = $this->makeToken(['cognito:groups' => ['guests']]);

    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', [
        'keys' => [$bundleA->jwks['keys'][0], $bundleB->jwks['keys'][0]],
    ], 60);

    config()->set('auth.guards.cognito.db_less', true);
    config()->set('cognito-guard.user_provider.auto_provision', false);

    /** @var CognitoGuard $guard */
    $guard = Auth::guard('cognito');

    $guard->setRequest(Request::create('/me', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$bundleA->jwt]));
    $guard->user();
    expect($guard->lastPayload()?->{'cognito:groups'})->toBe(['admins']);

    $guard->setRequest(Request::create('/me', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$bundleB->jwt]));
    $guard->user();
    expect($guard->lastPayload()?->{'cognito:groups'})->toBe(['guests']);
});
