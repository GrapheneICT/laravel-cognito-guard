<?php

declare(strict_types=1);

use GrapheneICT\CognitoGuard\Guards\CognitoGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

it('returns false from validate() regardless of arguments', function () {
    $guard = Auth::guard('cognito');

    expect($guard)->toBeInstanceOf(CognitoGuard::class)
        ->and($guard->validate())->toBeFalse()
        ->and($guard->validate(['email' => 'x']))->toBeFalse();
});

it('returns null when the request has no bearer token', function () {
    /** @var CognitoGuard $guard */
    $guard = Auth::guard('cognito');
    $guard->setRequest(Request::create('/', 'GET'));

    expect($guard->user())->toBeNull();
});

it('caches the resolved user across consecutive user() calls', function () {
    $bundle = $this->makeToken();
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    config()->set('auth.guards.cognito.db_less', true);
    config()->set('cognito-guard.user_provider.auto_provision', false);

    /** @var CognitoGuard $guard */
    $guard = Auth::guard('cognito');
    $guard->setRequest(Request::create('/', 'GET', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$bundle->jwt,
    ]));

    $first = $guard->user();
    $second = $guard->user();

    expect($first)->not->toBeNull()
        ->and($second)->toBe($first); // identity, not just equality
});

it('exposes lastPayload() after a successful authentication', function () {
    $bundle = $this->makeToken(['cognito:groups' => ['ops', 'support']]);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    config()->set('auth.guards.cognito.db_less', true);
    config()->set('cognito-guard.user_provider.auto_provision', false);

    /** @var CognitoGuard $guard */
    $guard = Auth::guard('cognito');
    $guard->setRequest(Request::create('/', 'GET', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$bundle->jwt,
    ]));
    $guard->user();

    expect($guard->lastPayload()?->{'cognito:groups'})->toBe(['ops', 'support']);
});
