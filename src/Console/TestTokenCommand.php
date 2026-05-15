<?php

declare(strict_types=1);

namespace GrapheneICT\CognitoGuard\Console;

use GrapheneICT\CognitoGuard\Exceptions\InvalidTokenException;
use GrapheneICT\CognitoGuard\Exceptions\JwksFetchException;
use GrapheneICT\CognitoGuard\JwksProvider;
use GrapheneICT\CognitoGuard\JwtVerifier;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class TestTokenCommand extends Command
{
    protected $signature = 'cognito:test-token
                            {token : The JWT to validate (raw or "Bearer <jwt>")}
                            {--pool=default : Which configured pool to validate against}
                            {--verbose-claims : Print the full decoded claims payload}';

    protected $description = 'Validate a Cognito JWT against the configured pool and print a step-by-step diagnosis.';

    public function handle(): int
    {
        $tokenArg = $this->argument('token');
        $poolArg = $this->option('pool');

        if (! is_string($tokenArg)) {
            $this->error('The token argument must be a string.');

            return self::FAILURE;
        }

        $token = $this->normalizeToken($tokenArg);
        $poolName = is_string($poolArg) ? $poolArg : 'default';

        $poolConfig = config('cognito-guard.pools.'.$poolName);
        if (! is_array($poolConfig)) {
            $this->error(sprintf('Pool "%s" is not configured under cognito-guard.pools.', $poolName));

            return self::FAILURE;
        }

        $this->components->info(sprintf('Validating token against pool "%s"', $poolName));

        $issuer = sprintf(
            'https://cognito-idp.%s.amazonaws.com/%s',
            (string) ($poolConfig['region'] ?? ''),
            (string) ($poolConfig['user_pool_id'] ?? ''),
        );
        $this->line('  <fg=gray>Issuer:</> '.$issuer);

        $jwks = new JwksProvider(
            (string) $poolConfig['user_pool_id'],
            (string) $poolConfig['region'],
            (array) config('cognito-guard.jwks', []),
        );

        try {
            $verifier = new JwtVerifier($jwks, $poolConfig);
            $payload = $verifier->verify($token);
        } catch (InvalidTokenException $e) {
            $this->components->error('Token rejected: '.$e->getMessage());

            return self::FAILURE;
        } catch (JwksFetchException $e) {
            $this->components->error('JWKS fetch failed: '.$e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error('Unexpected failure: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Signature', '<fg=green>verified</>');
        $this->components->twoColumnDetail('Issuer', '<fg=green>matches</>');
        $this->components->twoColumnDetail('token_use', (string) ($payload->token_use ?? '<none>'));
        $this->components->twoColumnDetail('sub', (string) ($payload->sub ?? '<none>'));
        $this->components->twoColumnDetail('username', (string) ($payload->username ?? $payload->{'cognito:username'} ?? '<none>'));
        $this->components->twoColumnDetail('client_id / aud', (string) ($payload->client_id ?? $payload->aud ?? '<none>'));
        $this->components->twoColumnDetail('scope', (string) ($payload->scope ?? '<none>'));

        $groups = $payload->{'cognito:groups'} ?? [];
        $this->components->twoColumnDetail('cognito:groups', is_array($groups) ? implode(', ', $groups) ?: '<none>' : '<invalid>');

        $exp = (int) ($payload->exp ?? 0);
        $ttl = $exp - time();
        $this->components->twoColumnDetail(
            'expires',
            sprintf('%s (%+ds)', date('c', $exp), $ttl),
        );

        if ($this->option('verbose-claims')) {
            $this->newLine();
            $this->line('<fg=gray>Full claims payload:</>');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }

        $this->components->info('Token is valid.');

        return self::SUCCESS;
    }

    private function normalizeToken(string $input): string
    {
        $input = trim($input);
        if (stripos($input, 'bearer ') === 0) {
            $input = trim(substr($input, 7));
        }

        if ($input === '') {
            throw new RuntimeException('Empty token argument.');
        }

        return $input;
    }
}
