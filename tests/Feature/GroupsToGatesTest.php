<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('auth.guards.cognito.db_less', true);
    config()->set('cognito-guard.bridge_groups_to_gates', true);

    $this->bundle = $this->makeToken(['cognito:groups' => ['admins', 'editors']]);
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $this->bundle->jwks, 60);
});

it('allows abilities whose name matches a cognito:groups entry', function () {
    Route::middleware('auth:cognito')->get('/admin-only', function () {
        abort_unless(Gate::allows('admins'), 403);

        return ['ok' => true];
    });

    $this->getJson('/admin-only', ['Authorization' => 'Bearer '.$this->bundle->jwt])
        ->assertOk();
});

it('denies abilities that do not match any cognito group', function () {
    Route::middleware('auth:cognito')->get('/super-admin', function () {
        abort_unless(Gate::allows('super-admins'), 403);

        return ['ok' => true];
    });

    $this->getJson('/super-admin', ['Authorization' => 'Bearer '.$this->bundle->jwt])
        ->assertStatus(403);
});

it('treats abilities that do not match a group as denied by default', function () {
    Route::middleware('auth:cognito')->get('/super-admin-direct', function () {
        abort_unless(Gate::allows('not-a-cognito-group'), 403);

        return ['ok' => true];
    });

    $this->getJson('/super-admin-direct', ['Authorization' => 'Bearer '.$this->bundle->jwt])
        ->assertStatus(403);
});
