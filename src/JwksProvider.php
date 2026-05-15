<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use GrapheneICT\CognitoGuard\Exceptions\JwksFetchException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class JwksProvider
{
    private const STALE_TTL_SECONDS = 2592000;

    /**
     * @param  array<string, mixed>  $jwksConfig
     */
    public function __construct(
        private readonly string $poolId,
        private readonly string $region,
        private readonly array $jwksConfig,
    ) {}

    public function getIssuer(): string
    {
        return sprintf('https://cognito-idp.%s.amazonaws.com/%s', $this->region, $this->poolId);
    }

    /**
     * @return array<string, Key>
     */
    public function getJwks(): array
    {
        $store = $this->cache();
        $key = $this->cacheKey();

        $cached = $store->get($key);
        if (is_array($cached)) {
            return JWK::parseKeySet($cached);
        }

        try {
            $jwks = $this->fetch();
        } catch (Throwable $e) {
            if ($this->staleFallbackEnabled()) {
                $stale = $store->get($this->staleKey());
                if (is_array($stale)) {
                    $this->log('warning', 'JWKS fetch failed; serving from stale cache.', [
                        'issuer' => $this->getIssuer(),
                        'error' => $e->getMessage(),
                    ]);

                    return JWK::parseKeySet($stale);
                }
            }

            $this->log('error', 'JWKS fetch failed and no stale cache available.', [
                'issuer' => $this->getIssuer(),
                'error' => $e->getMessage(),
            ]);

            throw new JwksFetchException(
                sprintf('Failed to fetch JWKS from %s: %s', $this->getIssuer(), $e->getMessage()),
                previous: $e,
            );
        }

        $ttl = (int) ($this->jwksConfig['cache_ttl'] ?? 21600);
        $store->put($key, $jwks, $ttl);
        $store->put($this->staleKey(), $jwks, self::STALE_TTL_SECONDS);

        return JWK::parseKeySet($jwks);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(): array
    {
        return Http::timeout((int) ($this->jwksConfig['http_timeout'] ?? 5))
            ->get($this->getIssuer().'/.well-known/jwks.json')
            ->throw()
            ->json();
    }

    private function cache(): Repository
    {
        $store = $this->jwksConfig['cache_store'] ?? null;

        return $store ? Cache::store($store) : Cache::store();
    }

    private function cacheKey(): string
    {
        return $this->prefix().':'.$this->poolId.':v2';
    }

    private function staleKey(): string
    {
        return $this->prefix().':'.$this->poolId.':v2:stale';
    }

    private function prefix(): string
    {
        return (string) ($this->jwksConfig['cache_key_prefix'] ?? 'cognito-guard:jwks');
    }

    private function staleFallbackEnabled(): bool
    {
        return (bool) ($this->jwksConfig['stale_on_error'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (! (bool) config('cognito-guard.log.enabled', true)) {
            return;
        }

        $channel = config('cognito-guard.log.channel');
        $logger = is_string($channel) && $channel !== '' ? Log::channel($channel) : Log::driver();

        $logger->log($level, '[cognito-guard] '.$message, $context);
    }
}
