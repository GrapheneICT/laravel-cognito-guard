# Running Cognito + Sanctum side by side

A common setup: your first-party web app uses Sanctum (SPA cookie auth + CSRF), while a separate API or set of routes is consumed by mobile / third-party clients that authenticate with Cognito JWTs. The two coexist cleanly — they're just two guards.

## When to reach for this

- You ship a browser SPA on the same domain as Laravel (Sanctum is the right answer there).
- AND you expose an API that mobile apps, partners, or a separate frontend hit with Bearer tokens (Cognito).

If everything is Bearer-token-driven, you don't need Sanctum — use this package alone.

## Wiring

### `composer.json`

```bash
composer require laravel/sanctum graphene-ict/laravel-cognito-guard
```

### `config/auth.php`

```php
'guards' => [
    // Default web sessions for your first-party SPA.
    'web' => ['driver' => 'session', 'provider' => 'users'],

    // Sanctum: cookie auth for the SPA, fallback to PAT for tooling.
    'sanctum' => ['driver' => 'sanctum', 'provider' => 'users'],

    // Cognito: JWT auth for the API.
    'cognito' => ['driver' => 'cognito', 'provider' => 'cognito', 'pool' => 'default'],
],

'providers' => [
    'users'    => ['driver' => 'eloquent', 'model' => App\Models\User::class],
    'cognito'  => ['driver' => 'cognito'],
],
```

### Routes

```php
// routes/web.php — Sanctum cookie SPA
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', DashboardController::class);
});

// routes/api.php — Cognito-protected API
Route::middleware('auth:cognito')->group(function () {
    Route::get('/v1/me', fn () => auth()->user());
});

// Optional: accept either, useful during a migration window
Route::middleware('auth:sanctum,cognito')->group(function () {
    Route::get('/v1/profile', ProfileController::class);
});
```

The `auth:sanctum,cognito` form tells Laravel: try each guard in order, succeed on the first that resolves a user.

## Gotchas

- **Two different user shapes.** `auth('sanctum')->user()` returns your Eloquent `User`. `auth('cognito')->user()` returns either your Eloquent `User` (DB-backed mode) or a `CognitoUser` value object (DB-less). Code that reads from `auth()->user()` without specifying a guard will get whichever ran last — pin it explicitly when it matters.

- **CSRF only applies to Sanctum.** Cognito-protected routes don't need (and shouldn't expect) the `X-XSRF-TOKEN` header. Exclude your API prefix from `VerifyCsrfToken::$except` if it's not there already.

- **Don't share a route group.** `auth:sanctum,cognito` is fine, but `auth:sanctum` followed by `auth:cognito` middleware on the same route runs both — the second 401's any request that authenticated via Sanctum.

- **Provider columns.** When using DB-backed Cognito mode, the same `users` table is the source of truth for both guards. Make sure `provider_id` (the Cognito sub column) exists and is unique, **and** that the row also has whatever Sanctum needs (e.g. a hashed password if your SPA logs in with credentials).

- **Gates bridge applies to whichever user is currently authenticated.** The `cognito:groups` → Gate bridge in this package looks at the active Cognito guard's `lastPayload` — so a Sanctum-authenticated request will never get group abilities from Cognito, even for the same user. Wire group abilities through your own `Gate::define()` for the Sanctum path.

## When NOT to do this

If you're building a single SPA against a single backend and considering "should I use Sanctum or Cognito?", pick one. Running both adds two auth flows, two user-resolution paths, and two CSRF stories to reason about. Justify the second guard with a real second client.
