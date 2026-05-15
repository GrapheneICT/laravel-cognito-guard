<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard\Exceptions;

use Illuminate\Auth\AuthenticationException;

final class InvalidTokenException extends AuthenticationException {}
