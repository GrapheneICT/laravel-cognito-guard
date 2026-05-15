# Laravel Cognito Guard

[![CI](https://github.com/GrapheneICT/laravel-cognito-guard/actions/workflows/run-tests.yml/badge.svg)](https://github.com/GrapheneICT/laravel-cognito-guard/actions/workflows/run-tests.yml)
[![codecov](https://codecov.io/gh/GrapheneICT/laravel-cognito-guard/graph/badge.svg)](https://codecov.io/gh/GrapheneICT/laravel-cognito-guard)
[![Packagist Version](https://img.shields.io/packagist/v/graphene-ict/laravel-cognito-guard.svg)](https://packagist.org/packages/graphene-ict/laravel-cognito-guard)
[![Packagist Downloads](https://img.shields.io/packagist/dt/graphene-ict/laravel-cognito-guard.svg)](https://packagist.org/packages/graphene-ict/laravel-cognito-guard)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D11-FF2D20.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

A lean Laravel auth guard that validates JSON Web Tokens issued by an **AWS Cognito User Pool**. Verifies the JWT signature against Cognito's JWKS, enforces standard Cognito claims, and resolves the authenticated user via a `UserProvider` — or returns a value object in DB-less mode.

## Requirements

- PHP 8.3+
- Laravel 11 / 12
- A configured AWS Cognito User Pool

## Installation

You can install the package via Composer:

```bash
composer require graphene-ict/laravel-cognito-guard
```

Publish the config file:

```bash
php artisan vendor:publish --tag=cognito-guard-config
```

Set the env vars:

```dotenv
COGNITO_USER_POOL_ID=us-east-1_XXXXXXXXX
AWS_REGION=us-east-1
# Optional comma-separated allow-list:
COGNITO_CLIENT_IDS=app-client-1,app-client-2
```

## Usage

### DB-backed users (default)

1. Add `provider_id` to your `users` table:

   ```php
   $table->string('provider_id')->unique()->nullable();
   ```

2. Add `provider_id` to the model's `$fillable`.

3. Register the guard in `config/auth.php`:

   ```php
   'guards' => [
       'cognito' => [
           'driver' => 'cognito',
           'provider' => 'cognito',
           'pool' => 'default',
       ],
   ],

   'providers' => [
       'cognito' => ['driver' => 'cognito'],
   ],
   ```

4. Protect routes:

   ```php
   Route::middleware('auth:cognito')->get('/me', fn () => auth()->user());
   ```

A new `User` record is auto-provisioned on the first authenticated request whose `sub` is not yet known. Disable by setting `cognito-guard.user_provider.auto_provision` to `false`.

### DB-less mode

For SPA / service-to-service callers that don't need a local users table:

```php
'guards' => [
    'cognito' => [
        'driver' => 'cognito',
        'provider' => 'cognito',
        'pool' => 'default',
        'db_less' => true,
    ],
],
```

`auth()->user()` returns a `GrapheneICT\CognitoGuard\CognitoUser` value object:

```php
$user = auth()->user();
$user->username();        // string|null
$user->email();           // string|null
$user->groups();          // string[]
$user->scopes();          // string[]
$user->claim('sub');      // any single claim
$user->claims();          // raw payload (stdClass)
```

### Groups → Gates bridge

With `cognito-guard.bridge_groups_to_gates` enabled (default), entries in the `cognito:groups` claim become Gate abilities for free:

```php
Gate::allows('admins');                       // true if 'admins' is in cognito:groups
Route::middleware('can:moderators')->...;     // works the same
```

### Multi-pool

```php
// config/cognito-guard.php
'pools' => [
    'default'  => ['user_pool_id' => env('COGNITO_USER_POOL_ID'),  'region' => 'us-east-1'],
    'partners' => ['user_pool_id' => env('PARTNER_POOL_ID'),       'region' => 'us-east-1'],
],

// config/auth.php
'guards' => [
    'cognito'  => ['driver' => 'cognito', 'provider' => 'cognito', 'pool' => 'default'],
    'partners' => ['driver' => 'cognito', 'provider' => 'cognito', 'pool' => 'partners'],
],
```

## Configuration reference

See [`config/cognito-guard.php`](config/cognito-guard.php). Key knobs:

- `pools.<name>.allowed_token_use` — `['access']`, `['id']`, or both.
- `pools.<name>.allowed_client_ids` — empty = accept any; populated = strict allow-list against `client_id` (access) / `aud` (id).
- `pools.<name>.required_scopes` — every scope must be present in the token's `scope` claim.
- `pools.<name>.leeway` — clock-skew tolerance for `exp`/`nbf`/`iat`, in seconds.
- `jwks.cache_ttl` — JWKS cache TTL (default 6h). Stale entries kept 30d and used on Cognito outages.
- `bridge_groups_to_gates` — toggle the `cognito:groups` → Gate bridge.

## Diagnostics

```bash
php artisan about                          # shows the Cognito Guard section
php artisan cognito:test-token <jwt>       # validates a token + prints a step-by-step diagnosis
```

The `cognito:test-token` command accepts the raw JWT or a `Bearer <jwt>` string and prints which validation step passed or failed (signature, issuer, `token_use`, `client_id`/`aud`, scopes, expiry). Add `--pool=<name>` to test against a non-default pool, or `--verbose-claims` to dump the full payload.

## FAQ

<details>
<summary><strong><code>InvalidTokenException: Invalid token_use "id". Allowed: access</code></strong></summary>

Your guard is configured to accept access tokens only, but the client is sending an id token. Either send an access token, or widen `cognito-guard.pools.<name>.allowed_token_use` to `['access', 'id']` (the default).
</details>

<details>
<summary><strong><code>InvalidTokenException: Token client_id/aud is not in the allow-list</code></strong></summary>

The token's `client_id` (access tokens) or `aud` (id tokens) doesn't match `COGNITO_CLIENT_IDS`. Either add the App Client ID to that env var (comma-separated), or leave `allowed_client_ids` empty to accept any client.
</details>

<details>
<summary><strong><code>InvalidTokenException: Invalid issuer</code></strong></summary>

The token was issued by a different User Pool. Check `COGNITO_USER_POOL_ID` and `AWS_REGION`.
</details>

<details>
<summary><strong><code>InvalidTokenException: Token has expired</code></strong></summary>

Either the token genuinely expired, or your server clock has drifted. Bump `cognito-guard.pools.<name>.leeway` (seconds) to tolerate minor skew — but **fix the underlying NTP issue**, don't paper over it long-term.
</details>

<details>
<summary><strong><code>JwksFetchException: Failed to fetch JWKS from https://cognito-idp.&lt;region&gt;.amazonaws.com/...</code></strong></summary>

Your app can't reach Cognito's JWKS endpoint. Check egress to `cognito-idp.<region>.amazonaws.com`. The guard will serve from stale cache for up to 30 days if the JWKS was ever fetched successfully (toggle via `cognito-guard.jwks.stale_on_error`).
</details>

<details>
<summary><strong>The user returned from <code>auth()->user()</code> isn't my Eloquent model</strong></summary>

If your guard config has `'db_less' => true`, the package returns a `CognitoUser` value object built from JWT claims instead of looking up a database row. Set `db_less => false` (the default) and configure `cognito-guard.user_provider.model` to point at your Eloquent user model.
</details>

<details>
<summary><strong><code>Auth provider "..." is not configured</code></strong></summary>

You set `'provider' => 'cognito'` on the guard but didn't add a `cognito` entry under `auth.providers`. Add:
```php
'providers' => ['cognito' => ['driver' => 'cognito']],
```
</details>

<details>
<summary><strong>Multiple Cognito pools — how do I authenticate against the second one?</strong></summary>

Register a second guard with a different `pool` key and pick which one to apply per route:
```php
'guards' => [
    'cognito'  => ['driver' => 'cognito', 'provider' => 'cognito', 'pool' => 'default'],
    'partners' => ['driver' => 'cognito', 'provider' => 'cognito', 'pool' => 'partners'],
],
```
Then `Route::middleware('auth:partners')->...`.
</details>

## Testing

```bash
composer install
composer test
composer analyse
```

## Upgrading from v1

Breaking changes — see [UPGRADING.md](UPGRADING.md).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jovan Stojiljkovic](https://github.com/GrapheneICT)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
