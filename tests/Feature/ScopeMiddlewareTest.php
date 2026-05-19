<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Cache::flush();
});

it('allows a DB-less request whose token carries every required scope', function () {
    config()->set('auth.guards.cognito.db_less', true);

    $bundle = $this->makeToken(['scope' => 'aws.cognito.signin.user.admin read:reports write:reports']);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    Route::middleware(['auth:cognito', 'cognito.scope:read:reports,write:reports'])
        ->get('/reports', fn () => ['ok' => true]);

    $this->getJson('/reports', ['Authorization' => 'Bearer '.$bundle->jwt])->assertOk();
});

it('denies a DB-less request whose token is missing a required scope', function () {
    config()->set('auth.guards.cognito.db_less', true);

    $bundle = $this->makeToken(['scope' => 'read:reports']);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    Route::middleware(['auth:cognito', 'cognito.scope:write:reports'])
        ->get('/reports', fn () => ['ok' => true]);

    $this->getJson('/reports', ['Authorization' => 'Bearer '.$bundle->jwt])->assertStatus(403);
});

it('reads scopes from the guard lastPayload for DB-backed users', function () {
    $bundle = $this->makeToken(['scope' => 'admin:reports']);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $bundle->jwks, 60);

    Route::middleware(['auth:cognito', 'cognito.scope:admin:reports'])
        ->get('/reports', fn () => ['ok' => true]);

    $this->getJson('/reports', ['Authorization' => 'Bearer '.$bundle->jwt])->assertOk();
});

it('returns 401 when no user is authenticated', function () {
    Route::middleware('cognito.scope:read:reports')->get('/reports', fn () => ['ok' => true]);

    $this->getJson('/reports')->assertStatus(401);
});

it('works against a faked user via actingAs', function () {
    config()->set('auth.guards.cognito.db_less', true);

    Route::middleware(['auth:cognito', 'cognito.scope:read:reports'])
        ->get('/reports', fn () => ['ok' => true]);

    Auth::guard('cognito')->actingAs(['sub' => 'u-1', 'scope' => 'read:reports write:reports']);

    $this->getJson('/reports')->assertOk();
});
