<?php

namespace GrapheneICT\CognitoGuard\Services\Auth;

use ErrorException;
use GrapheneICT\CognitoGuard\Services\CognitoService;
use GrapheneICT\CognitoGuard\Services\JwtService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class JwtGuard implements Guard
{
    use GuardHelpers;

    /**
     * The request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * @param  Request  $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return JwtGuard|Authenticatable|\stdClass
     *
     * @throws ErrorException
     * @throws InvalidTokenException
     */
    public function user()
    {
        if ($this->user instanceof Authenticatable) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if (! $token) {
            abort(403, 'Token is missing');
        }

        $jwtService = new JwtService();
        $decodedToken = $jwtService->decode($token);

        $cognitoService = new CognitoService();

        if (! config('cognito-auth.persist_user_data')) {
            return $decodedToken;
        }

        return $this->setUser($cognitoService->getOrCreateUser($decodedToken->sub, $token));
    }

    /**
     * @param  array  $credentials
     * @return void
     *
     * @throws ErrorException
     */
    public function validate(array $credentials = [])
    {
        throw new ErrorException('JwtGuard::validate() not implemented by design.');
    }
}
