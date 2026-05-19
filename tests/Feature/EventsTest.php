<?php

use GrapheneICT\CognitoGuard\Events\CognitoTokenRejected;
use GrapheneICT\CognitoGuard\Events\CognitoTokenValidated;
use GrapheneICT\CognitoGuard\Exceptions\InvalidTokenException;
use GrapheneICT\CognitoGuard\Tests\Fixtures\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Event::fake([CognitoTokenValidated::class, CognitoTokenRejected::class]);
    $this->bundle = $this->makeToken();
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $this->bundle->jwks, 60);
});

it('dispatches CognitoTokenValidated after a successful auth', function () {
    Route::middleware('auth:cognito')->get('/api/me', fn () => ['ok' => true]);

    $this->getJson('/api/me', ['Authorization' => 'Bearer '.$this->bundle->jwt])->assertOk();

    Event::assertDispatched(CognitoTokenValidated::class, function (CognitoTokenValidated $e): bool {
        return $e->user instanceof User
            && $e->pool === 'default'
            && ($e->claims->sub ?? null) === $this->bundle->sub;
    });
    Event::assertNotDispatched(CognitoTokenRejected::class);
});

it('dispatches CognitoTokenRejected when a token fails verification', function () {
    Route::middleware('auth:cognito')->get('/api/me', fn () => ['ok' => true]);

    $this->getJson('/api/me', ['Authorization' => 'Bearer not.a.real.jwt'])->assertStatus(401);

    Event::assertDispatched(CognitoTokenRejected::class, function (CognitoTokenRejected $e): bool {
        return $e->exception instanceof InvalidTokenException && $e->pool === 'default';
    });
    Event::assertNotDispatched(CognitoTokenValidated::class);
});

it('does not dispatch any event when no bearer token is present', function () {
    Route::middleware('auth:cognito')->get('/api/me', fn () => ['ok' => true]);

    $this->getJson('/api/me')->assertStatus(401);

    Event::assertNotDispatched(CognitoTokenValidated::class);
    Event::assertNotDispatched(CognitoTokenRejected::class);
});
