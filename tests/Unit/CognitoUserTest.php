<?php

use GrapheneICT\CognitoGuard\CognitoUser;

it('exposes claims via typed accessors', function () {
    $claims = (object) [
        'sub' => 'abc-123',
        'username' => 'jdoe',
        'email' => 'jdoe@example.com',
        'cognito:groups' => ['admins', 'editors'],
        'scope' => 'payments:read payments:write',
        'token_use' => 'access',
    ];

    $user = CognitoUser::fromClaims($claims);

    expect($user->getAuthIdentifier())->toBe('abc-123')
        ->and($user->getAuthIdentifierName())->toBe('sub')
        ->and($user->username())->toBe('jdoe')
        ->and($user->email())->toBe('jdoe@example.com')
        ->and($user->groups())->toBe(['admins', 'editors'])
        ->and($user->scopes())->toBe(['payments:read', 'payments:write'])
        ->and($user->tokenUse())->toBe('access');
});

it('prefers cognito:username when username is absent', function () {
    $user = CognitoUser::fromClaims((object) [
        'sub' => 'abc',
        'cognito:username' => 'alt-name',
    ]);

    expect($user->username())->toBe('alt-name');
});

it('returns empty arrays for missing groups/scopes', function () {
    $user = CognitoUser::fromClaims((object) ['sub' => 'x']);

    expect($user->groups())->toBe([])
        ->and($user->scopes())->toBe([]);
});

it('returns a raw claim via claim()', function () {
    $user = CognitoUser::fromClaims((object) ['sub' => 'x', 'custom' => 'value']);

    expect($user->claim('custom'))->toBe('value')
        ->and($user->claim('missing', 'fallback'))->toBe('fallback');
});

it('returns empty strings for password and remember-token getters', function () {
    $user = CognitoUser::fromClaims((object) ['sub' => 'x']);

    expect($user->getAuthPassword())->toBe('')
        ->and($user->getAuthPasswordName())->toBe('')
        ->and($user->getRememberToken())->toBeNull()
        ->and($user->getRememberTokenName())->toBe('');
});
