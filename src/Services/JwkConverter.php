<?php

namespace GrapheneICT\JwtGuard\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class JwkConverter
{
    /**
     * Caches and returns jwk
     *
     * @return Key[]
     */
    public function getJwks(): array
    {
        $jwks = Cache::rememberForever('cognito-auth.jwk', function () {
            return Http::get($this->getIssuer().'/.well-known/jwks.json')
                ->throw()
                ->json();
        });

        return JWK::parseKeySet($jwks);
    }

    /**
     * Returns the issuer of a cognito
     *
     * @return string
     */
    public function getIssuer(): string
    {
        return sprintf(
            'https://cognito-idp.%s.amazonaws.com/%s',
            env('AWS_COGNITO_REGION'),
            env('AWS_COGNITO_USER_POOL_ID'));
    }
}
