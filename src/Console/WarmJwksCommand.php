<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard\Console;

use GrapheneICT\CognitoGuard\Exceptions\JwksFetchException;
use GrapheneICT\CognitoGuard\JwksProvider;
use Illuminate\Console\Command;

final class WarmJwksCommand extends Command
{
    protected $signature = 'cognito:warm-jwks
                            {--pool= : Warm a single pool only (defaults to all configured pools)}';

    protected $description = 'Pre-fetch and cache JWKS for the configured Cognito pool(s). Run at deploy time so the first authenticated request does not pay the JWKS round-trip and so reachability is verified before traffic arrives.';

    public function handle(): int
    {
        $only = $this->option('pool');
        $pools = (array) config('cognito-guard.pools', []);

        if (is_string($only) && $only !== '') {
            if (! isset($pools[$only])) {
                $this->components->error(sprintf('Pool "%s" is not configured under cognito-guard.pools.', $only));

                return self::FAILURE;
            }
            $pools = [$only => $pools[$only]];
        }

        if ($pools === []) {
            $this->components->warn('No pools configured under cognito-guard.pools — nothing to warm.');

            return self::SUCCESS;
        }

        $failed = 0;
        $jwksConfig = (array) config('cognito-guard.jwks', []);

        foreach ($pools as $name => $config) {
            if (! is_array($config) || empty($config['user_pool_id'])) {
                $this->components->twoColumnDetail((string) $name, '<fg=red>missing user_pool_id</>');
                $failed++;

                continue;
            }

            $provider = new JwksProvider(
                (string) $config['user_pool_id'],
                (string) ($config['region'] ?? 'us-east-1'),
                $jwksConfig,
            );

            try {
                $keys = $provider->getJwks();
                $this->components->twoColumnDetail(
                    (string) $name,
                    sprintf('<fg=green>warmed (%d key%s)</>', count($keys), count($keys) === 1 ? '' : 's'),
                );
            } catch (JwksFetchException $e) {
                $this->components->twoColumnDetail((string) $name, '<fg=red>fetch failed: '.$e->getMessage().'</>');
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
