<?php

namespace GrapheneICT\JwtGuard\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;

class InvalidTokenException extends AuthenticationException
{

}
