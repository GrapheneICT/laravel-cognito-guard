<?php

declare(strict_types=1);

use GrapheneICT\CognitoGuard\Auth\CognitoUserProvider;
use GrapheneICT\CognitoGuard\Tests\Fixtures\User;
use Illuminate\Contracts\Auth\Authenticatable;

function makeProvider(array $overrides = []): CognitoUserProvider
{
    return new CognitoUserProvider(array_merge([
        'model' => User::class,
        'sub_column' => 'provider_id',
        'auto_provision' => true,
        'attribute_map' => [
            'email' => 'email',
            'cognito:username' => 'name',
        ],
    ], $overrides));
}

it('returns null from retrieveById when the identifier is empty', function () {
    expect(makeProvider()->retrieveById(null))->toBeNull()
        ->and(makeProvider()->retrieveById(''))->toBeNull();
});

it('returns null when the identifier does not match any record', function () {
    expect(makeProvider()->retrieveById('unknown-sub'))->toBeNull();
});

it('returns null from resolveFromClaims when sub is empty', function () {
    expect(makeProvider()->resolveFromClaims((object) []))->toBeNull();
});

it('returns null from resolveFromClaims when auto_provision is false and the user is absent', function () {
    $provider = makeProvider(['auto_provision' => false]);

    expect($provider->resolveFromClaims((object) ['sub' => 'never-seen']))->toBeNull();
});

it('throws when the configured model class does not exist', function () {
    makeProvider(['model' => 'NotAClass'])->retrieveById('any-sub');
})->throws(RuntimeException::class, 'not configured to a valid class');

it('throws when the configured model does not extend Eloquent\\Model', function () {
    makeProvider(['model' => stdClass::class])->retrieveById('any-sub');
})->throws(RuntimeException::class, 'must extend');

it('treats no-op auth methods as inert', function () {
    $provider = makeProvider();
    $user = User::create(['name' => 'x', 'email' => 'x@x.test', 'provider_id' => 'sub-1']);

    expect($provider->retrieveByToken('id', 'token'))->toBeNull()
        ->and($provider->retrieveByCredentials(['email' => 'x@x.test']))->toBeNull()
        ->and($provider->validateCredentials($user, ['password' => 'x']))->toBeFalse();

    // updateRememberToken + rehashPasswordIfRequired are void no-ops; just exercise them.
    $provider->updateRememberToken($user, 'token');
    $provider->rehashPasswordIfRequired($user, [], true);

    expect(true)->toBeTrue();
});

it('uses a custom sub_claim when configured', function () {
    $provider = makeProvider(['sub_claim' => 'cognito:username']);

    $user = $provider->resolveFromClaims((object) [
        'sub' => 'real-sub-ignored',
        'cognito:username' => 'legacy-id-42',
        'email' => 'legacy@example.com',
    ]);

    expect($user)->toBeInstanceOf(Authenticatable::class)
        ->and(User::where('provider_id', 'legacy-id-42')->exists())->toBeTrue()
        ->and(User::where('provider_id', 'real-sub-ignored')->exists())->toBeFalse();
});

it('falls back to sub when sub_claim is empty string', function () {
    $provider = makeProvider(['sub_claim' => '']);

    $user = $provider->resolveFromClaims((object) ['sub' => 'fallback-sub']);

    expect($user)->toBeInstanceOf(Authenticatable::class)
        ->and(User::where('provider_id', 'fallback-sub')->exists())->toBeTrue();
});

it('maps configured claims onto provisioned user attributes', function () {
    $provider = makeProvider();

    $user = $provider->resolveFromClaims((object) [
        'sub' => 'new-sub',
        'email' => 'mapped@example.com',
        'cognito:username' => 'mappedname',
    ]);

    expect($user)->toBeInstanceOf(Authenticatable::class)
        ->and(User::where('provider_id', 'new-sub')->first())
        ->not->toBeNull()
        ->and(User::where('provider_id', 'new-sub')->first()->email)->toBe('mapped@example.com')
        ->and(User::where('provider_id', 'new-sub')->first()->name)->toBe('mappedname');
});
