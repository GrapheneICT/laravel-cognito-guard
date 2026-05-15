<?php

use GrapheneICT\CognitoGuard\CognitoUser;
use GrapheneICT\CognitoGuard\Tests\Fixtures\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->bundle = $this->makeToken();
    Cache::put('cognito-guard:jwks:'.$this->poolId.':v2', $this->bundle->jwks, 60);
});

it('authenticates a request and auto-provisions an Eloquent user', function () {
    Route::middleware('auth:cognito')->get('/api/me', fn () => [
        'id' => auth()->id(),
        'class' => auth()->user()::class,
    ]);

    $this->getJson('/api/me', ['Authorization' => 'Bearer '.$this->bundle->jwt])
        ->assertOk()
        ->assertJsonPath('class', User::class);

    expect(User::where('provider_id', $this->bundle->sub)->exists())->toBeTrue();
});

it('reuses an existing user when one already maps to the cognito sub', function () {
    User::create([
        'provider_id' => $this->bundle->sub,
        'name' => 'existing',
        'email' => 'existing@example.com',
    ]);

    Route::middleware('auth:cognito')->get('/api/me', fn () => auth()->user()->only(['name', 'email']));

    $this->getJson('/api/me', ['Authorization' => 'Bearer '.$this->bundle->jwt])
        ->assertOk()
        ->assertJson(['name' => 'existing', 'email' => 'existing@example.com']);

    expect(User::count())->toBe(1);
});

it('returns 401 with no bearer token', function () {
    Route::middleware('auth:cognito')->get('/api/me', fn () => auth()->user());

    $this->getJson('/api/me')->assertStatus(401);
});

it('returns 401 when the token signature is invalid', function () {
    Route::middleware('auth:cognito')->get('/api/me', fn () => auth()->user());

    $this->getJson('/api/me', ['Authorization' => 'Bearer not.a.real.jwt'])
        ->assertStatus(401);
});

it('returns a CognitoUser value object when db_less is true', function () {
    config()->set('auth.guards.cognito.db_less', true);
    config()->set('cognito-guard.user_provider.auto_provision', false);

    Route::middleware('auth:cognito')->get('/api/me', fn () => [
        'class' => auth()->user()::class,
        'sub' => auth()->user()->getAuthIdentifier(),
    ]);

    $this->getJson('/api/me', ['Authorization' => 'Bearer '.$this->bundle->jwt])
        ->assertOk()
        ->assertJson([
            'class' => CognitoUser::class,
            'sub' => $this->bundle->sub,
        ]);

    expect(User::count())->toBe(0);
});
