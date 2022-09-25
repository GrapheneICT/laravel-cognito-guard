<?php

namespace GrapheneICT\CognitoGuard\Services;

use DomainException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use GrapheneICT\CognitoGuard\Exceptions\InvalidTokenException;
use InvalidArgumentException;
use UnexpectedValueException;

class JwtService
{
    /**
     * Checks if the token is valid and decode it
     *
     * @param  string  $token
     * @return \stdClass
     *
     * @throws InvalidTokenException
     */
    public function decode(string $token): \stdClass
    {
        $kid = $this->getKid($token);
        $jwtConverter = app()->make(JwkConverter::class);
        $keys = $jwtConverter->getJwks();

        try {
            $decodedToken = JWT::decode($token, new Key($keys[$kid]->getKeyMaterial(), 'RS256'));
            $this->validatePayload($decodedToken, $jwtConverter->getIssuer());

            return JWT::decode($token, new Key($keys[$kid]->getKeyMaterial(), 'RS256'));
        } catch (
            InvalidArgumentException
            | UnexpectedValueException
            | SignatureInvalidException
            | BeforeValidException
            | ExpiredException
            | DomainException
            $e
        ) {
            throw new InvalidTokenException($e->getMessage());
        }
    }

    /**
     * Get Kid from the JWT token
     *
     * @param  string  $token
     * @return null
     *
     * @throws InvalidTokenException
     */
    private function getKid(string $token)
    {
        $tks = explode('.', $token);

        if (count($tks) != 3) {
            throw new InvalidTokenException('Token has a wrong number of segments');
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($tks[0]));
        } catch (DomainException $e) {
            throw new InvalidTokenException($e->getMessage());
        }

        if (empty($header->kid)) {
            throw new InvalidTokenException('No kid present in token header');
        }

        if (empty($header->alg)) {
            throw new InvalidTokenException('No alg present in token header');
        }

        if ($header->alg !== 'RS256') {
            throw new InvalidTokenException('The token alg is not RS256');
        }

        return $header->kid;
    }

    /**
     * Although we already know the token has a valid signature and is
     * unexpired, this method is used to validate the payload.
     * e
     *
     * @param  object  $payload
     * @param $issuer
     *
     * @throws InvalidTokenException
     */
    private function validatePayload(object $payload, $issuer)
    {
        if ($payload->iss !== $issuer) {
            throw new InvalidTokenException('Invalid issuer. Expected:'.$issuer);
        }

        if (! in_array($payload->token_use, ['id', 'access'])) {
            throw new InvalidTokenException('Invalid token_use. Must be one of ["id","access"].');
        }

        if (! isset($payload->username) && ! isset($payload->{'cognito:username'})) {
            throw new InvalidTokenException('Invalid token attributes. Token must include one of "username","cognito:username".');
        }
    }
}
