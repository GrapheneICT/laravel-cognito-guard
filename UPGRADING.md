# Upgrading from v1 to v2

v2 is a clean break. There is no compatibility shim. Plan a short rewrite of your wiring; the actual code touching `auth()->user()` should be unchanged in most cases.

## Minimum environment

- PHP `^8.3`
- Laravel `^11.0 | ^12.0 | ^13.0`

If you're on PHP 8.1 or Laravel 9 you cannot upgrade — stay on v1.x.

## Step 1 — config rename

The published config file is renamed. Republish it:

```bash
php artisan vendor:publish --tag=cognito-guard-config
```

Delete the old `config/cognito-auth.php` after copying any custom values across.

### Old shape (v1)

```php
return [
    'persist_user_data' => true,
    'models' => [
        'user' => ['model' => App\Models\User::class],
    ],
];
```

### New shape (v2)

```php
return [
    'default_pool' => env('COGNITO_DEFAULT_POOL', 'default'),
    'pools' => [
        'default' => [
            'user_pool_id' => env('COGNITO_USER_POOL_ID'),
            'region' => env('AWS_REGION', 'us-east-1'),
            'allowed_token_use' => ['access', 'id'],
            'allowed_client_ids' => [/* from env COGNITO_CLIENT_IDS */],
            'required_scopes' => [],
            'leeway' => 0,
        ],
    ],
    'jwks' => [/* cache_store, cache_ttl, … */],
    'user_provider' => [
        'auto_provision' => true,           // was persist_user_data
        'model' => App\Models\User::class,  // was models.user.model
        'sub_column' => 'provider_id',
        'attribute_map' => ['email' => 'email', 'cognito:username' => 'name'],
    ],
    'bridge_groups_to_gates' => true,
];
```

Mapping:

| v1 key | v2 key |
|---|---|
| `persist_user_data` | `user_provider.auto_provision` |
| `models.user.model` | `user_provider.model` |

## Step 2 — `config/auth.php`

You now wire the package via the standard Laravel `auth.providers` contract instead of relying on a hard-coded model lookup:

### Before

```php
'guards' => [
    'api' => ['driver' => 'cognito', 'provider' => 'users'],
],
```

### After

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

The guard name `cognito` is conventional; you can call it whatever you like, but `Route::middleware('auth:cognito')` reads more naturally than `auth:api` when several auth methods coexist.

## Step 3 — namespaces

If you imported anything from the package, the namespaces have moved:

| v1 | v2 |
|---|---|
| `GrapheneICT\CognitoGuard\Services\Auth\JwtGuard` | `GrapheneICT\CognitoGuard\Guards\CognitoGuard` |
| `GrapheneICT\CognitoGuard\Services\JwtService` | `GrapheneICT\CognitoGuard\JwtVerifier` |
| `GrapheneICT\CognitoGuard\Services\JwkConverter` | `GrapheneICT\CognitoGuard\JwksProvider` |
| `GrapheneICT\CognitoGuard\Services\CognitoService` | _Removed_ (claims come from the JWT) |
| `GrapheneICT\CognitoGuard\Exceptions\MethodNotSupportedException` | _Removed_ |

## Step 4 — environment

Add to `.env`:

```dotenv
COGNITO_USER_POOL_ID=us-east-1_XXXXXXXXX
AWS_REGION=us-east-1
```

Remove the now-unused `AWS_COGNITO_VERSION` env var if present — the package no longer instantiates the AWS SDK.

## Step 5 — verify

- `php artisan about` should show a **Cognito Guard** section listing your default pool and feature toggles.
- Hit a protected route with a real Cognito access token and confirm `auth()->user()` returns either an Eloquent model (DB-backed mode) or a `CognitoUser` value object (`db_less: true`).
- `Gate::allows('<group-name>')` should return `true` for groups present in `cognito:groups`.

## Notable behavior changes

- The verify path no longer calls Cognito's `GetUser` API — all user attributes come from the JWT itself. This means `email` is only available if the token's claims include it (id tokens always do; access tokens do if you configured the User Pool to surface attribute scopes).
- A new user record is created via the configured `attribute_map`; if your old setup relied on a `provider` column populated from the federated identities, re-add it via a model `creating` listener.
- `JwtGuard::validate()` previously threw `ErrorException`. The contract requires returning a `bool`, so v2's `CognitoGuard::validate()` returns `false`.
