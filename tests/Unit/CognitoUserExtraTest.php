<?php

declare(strict_types=1);

use GrapheneICT\CognitoGuard\CognitoUser;

it('returns null email when the claim is missing', function () {
    $user = CognitoUser::fromClaims((object) ['sub' => 'x']);

    expect($user->email())->toBeNull()
        ->and($user->tokenUse())->toBeNull();
});

it('treats a non-array cognito:groups claim as no groups', function () {
    $user = CognitoUser::fromClaims((object) [
        'sub' => 'x',
        'cognito:groups' => 'not-an-array',
    ]);

    expect($user->groups())->toBe([]);
});

it('setRememberToken is a no-op', function () {
    $user = CognitoUser::fromClaims((object) ['sub' => 'x']);
    $user->setRememberToken('anything');

    expect($user->getRememberToken())->toBeNull();
});
