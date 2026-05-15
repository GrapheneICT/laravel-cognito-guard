<?php

namespace GrapheneICT\CognitoGuard;

use DomainException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use GrapheneICT\CognitoGuard\Exceptions\InvalidTokenException;
use InvalidArgumentException;
use stdClass;
use Throwable;
use UnexpectedValueException;

class JwtVerifier
{
    private const ALGORITHM = 'RS256';

    /**
     * @param  array<string, mixed>  $poolConfig
     */
    public function __construct(
        private readonly JwksProvider $jwks,
        private readonly array $poolConfig,
    ) {}

    public function verify(string $token): stdClass
    {
        $kid = $this->extractKid($token);
        $keys = $this->jwks->getJwks();

        if (! isset($keys[$kid])) {
            throw new InvalidTokenException(sprintf('Unknown kid "%s"; signing key not present in JWKS.', $kid));
        }

        $key = new Key($keys[$kid]->getKeyMaterial(), self::ALGORITHM);

        JWT::$leeway = (int) ($this->poolConfig['leeway'] ?? 0);

        try {
            $payload = JWT::decode($token, $key);
        } catch (
            InvalidArgumentException
            |UnexpectedValueException
            |SignatureInvalidException
            |BeforeValidException
            |ExpiredException
            |DomainException $e
        ) {
            throw new InvalidTokenException($e->getMessage());
        }

        $this->validatePayload($payload);

        return $payload;
    }

    private function extractKid(string $token): string
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new InvalidTokenException('Token has a wrong number of segments.');
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));
        } catch (DomainException $e) {
            throw new InvalidTokenException($e->getMessage());
        } catch (Throwable $e) {
            throw new InvalidTokenException('Malformed token header: '.$e->getMessage());
        }

        if (! is_object($header)) {
            throw new InvalidTokenException('Malformed token header.');
        }

        if (empty($header->kid)) {
            throw new InvalidTokenException('No kid present in token header.');
        }

        if (empty($header->alg)) {
            throw new InvalidTokenException('No alg present in token header.');
        }

        if ($header->alg !== self::ALGORITHM) {
            throw new InvalidTokenException(sprintf('Token alg %s is not %s.', $header->alg, self::ALGORITHM));
        }

        return (string) $header->kid;
    }

    public function validatePayload(stdClass $payload): void
    {
        $expectedIssuer = $this->jwks->getIssuer();
        if (($payload->iss ?? null) !== $expectedIssuer) {
            throw new InvalidTokenException(sprintf('Invalid issuer. Expected: %s', $expectedIssuer));
        }

        $allowedTokenUse = $this->poolConfig['allowed_token_use'] ?? ['access', 'id'];
        $tokenUse = $payload->token_use ?? null;
        if (! in_array($tokenUse, $allowedTokenUse, true)) {
            throw new InvalidTokenException(sprintf(
                'Invalid token_use "%s". Allowed: %s',
                (string) $tokenUse,
                implode(',', $allowedTokenUse),
            ));
        }

        if (! isset($payload->username) && ! isset($payload->{'cognito:username'})) {
            throw new InvalidTokenException('Token must include one of "username", "cognito:username".');
        }

        $allowedClients = $this->poolConfig['allowed_client_ids'] ?? [];
        if (! empty($allowedClients)) {
            $client = $payload->client_id ?? $payload->aud ?? null;
            if (is_array($client)) {
                $client = $client[0] ?? null;
            }
            if ($client === null || ! in_array($client, $allowedClients, true)) {
                throw new InvalidTokenException('Token client_id/aud is not in the allow-list.');
            }
        }

        $required = $this->poolConfig['required_scopes'] ?? [];
        if (! empty($required)) {
            $tokenScopes = preg_split('/\s+/', (string) ($payload->scope ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $missing = array_diff($required, $tokenScopes ?: []);
            if (! empty($missing)) {
                throw new InvalidTokenException('Token missing required scopes: '.implode(',', $missing));
            }
        }
    }
}
