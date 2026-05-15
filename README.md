# Laravel Cognito Guard

[![Latest Version on Packagist](https://img.shields.io/packagist/v/graphene-ict/laravel-cognito-guard.svg?style=flat-square)](https://packagist.org/packages/graphene-ict/laravel-cognito-guard)
[![Total Downloads](https://img.shields.io/packagist/dt/graphene-ict/laravel-cognito-guard.svg?style=flat-square)](https://packagist.org/packages/graphene-ict/laravel-cognito-guard)
[![License](https://img.shields.io/packagist/l/graphene-ict/laravel-cognito-guard.svg?style=flat-square)](LICENSE.md)

A lean Laravel auth guard that validates JSON Web Tokens issued by an **AWS Cognito User Pool**. Verifies the JWT signature against Cognito's JWKS, enforces standard Cognito claims, and resolves the authenticated user via a `UserProvider` — or returns a value object in DB-less mode.

## Why this package

| | This package | `ellaisys/aws-cognito` | `devadamlar/laravel-oidc` |
|---|---|---|---|
| **Focus** | Just verify the JWT | Full Cognito SDK wrapper (signup, MFA, hosted UI…) | Generic OIDC, any issuer |
| **Footprint** | `firebase/php-jwt` + Illuminate | AWS SDK, dynamodb, fido2, etc. | Generic OIDC stack |
| **Cognito-specific claim checks** | yes (`token_use`, `client_id`, scopes) | yes | partial |
| **`cognito:groups` → Gates bridge** | yes | no | n/a |
| **Multi-pool** | yes | yes | yes |
| **DB-less mode (no users table)** | yes | no | yes |

## Requirements

- PHP 8.3+
- Laravel 11 / 12 / 13
- A configured AWS Cognito User Pool

## Installation

```bash
composer require graphene-ict/laravel-cognito-guard
php artisan vendor:publish --tag=cognito-guard-config
```

Set the env vars:

```dotenv
COGNITO_USER_POOL_ID=us-east-1_XXXXXXXXX
AWS_REGION=us-east-1
# Optional comma-separated allow-list:
COGNITO_CLIENT_IDS=app-client-1,app-client-2
```

## Quick start

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
php artisan about           # shows Cognito Guard section
```

## Upgrading from v1

Breaking changes — see [UPGRADING.md](UPGRADING.md).

## Testing the package

```bash
composer install
composer test
composer analyse
```

## Changelog

[CHANGELOG.md](CHANGELOG.md).

## Security

Report vulnerabilities via [our security policy](../../security/policy).

## License

MIT — see [LICENSE.md](LICENSE.md).
