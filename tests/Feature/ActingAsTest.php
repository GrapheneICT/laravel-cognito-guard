<?php

use GrapheneICT\CognitoGuard\CognitoUser;
use GrapheneICT\CognitoGuard\Guards\CognitoGuard;
use GrapheneICT\CognitoGuard\Tests\Fixtures\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

it('fakes a DB-less user from a claims array', function () {
    config()->set('auth.guards.cognito.db_less', true);

    $guard = Auth::guard('cognito');
    expect($guard)->toBeInstanceOf(CognitoGuard::class);

    $user = $guard->actingAs([
        'sub' => 'u-1',
        'email' => 'a@example.com',
        'cognito:groups' => ['admins'],
    ]);

    expect($user)->toBeInstanceOf(CognitoUser::class)
        ->and($guard->user())->toBe($user)
        ->and($guard->check())->toBeTrue()
        ->and($guard->id())->toBe('u-1');
});

it('fakes an Eloquent user with attached claims', function () {
    $eloquent = User::create([
        'provider_id' => 'u-2',
        'name' => 'fixture',
        'email' => 'fixture@example.com',
    ]);

    $guard = Auth::guard('cognito');
    $returned = $guard->actingAs($eloquent, ['cognito:groups' => ['editors']]);

    expect($returned)->toBe($eloquent)
        ->and($guard->user())->toBe($eloquent)
        ->and($guard->id())->toBe($eloquent->getAuthIdentifier())
        ->and($guard->lastPayload()?->{'cognito:groups'})->toBe(['editors']);
});

it('lets the groups→Gates bridge see groups for a faked DB-less user', function () {
    config()->set('auth.guards.cognito.db_less', true);

    Route::middleware('auth:cognito')->get('/needs-admin', function () {
        abort_unless(Gate::allows('admins'), 403);

        return ['ok' => true];
    });

    Auth::guard('cognito')->actingAs(['sub' => 'u-3', 'cognito:groups' => ['admins']]);

    $this->getJson('/needs-admin')->assertOk();
});

it('lets the groups→Gates bridge see groups for a faked Eloquent user', function () {
    $eloquent = User::create([
        'provider_id' => 'u-4',
        'name' => 'fixture',
        'email' => 'fixture@example.com',
    ]);

    Route::middleware('auth:cognito')->get('/needs-editor', function () {
        abort_unless(Gate::allows('editors'), 403);

        return ['ok' => true];
    });

    Auth::guard('cognito')->actingAs($eloquent, ['cognito:groups' => ['editors']]);

    $this->getJson('/needs-editor')->assertOk();
});

it('denies abilities not present in the faked claims', function () {
    config()->set('auth.guards.cognito.db_less', true);

    Auth::guard('cognito')->actingAs(['sub' => 'u-5', 'cognito:groups' => ['readers']]);

    expect(Gate::allows('admins'))->toBeFalse();
});
