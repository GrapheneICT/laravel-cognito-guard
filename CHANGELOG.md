# Changelog

All notable changes to `graphene-ict/laravel-cognito-guard` will be documented in this file.

## v2.2.0 — 2026-05-19

### Added

- `actingAs($userOrClaims, $claims = [])` on the guard — fake a Cognito user in tests without forging JWTs. Works for both DB-less (pass a claims array) and DB-backed (pass an Eloquent user + claims). Sets `lastPayload` too, so the groups → Gates bridge keeps working in tests.
- `user_provider.sub_claim` config knob — choose which JWT claim supplies the stable identifier. Defaults to `sub`; set to `cognito:username` or a custom attribute when a legacy users table is keyed by something other than the Cognito sub.
- `CognitoTokenValidated` and `CognitoTokenRejected` events dispatched from the guard for observability (audit logs, alerting, last-seen updates). Carries the user/claims/pool or exception/pool — raw JWTs are intentionally excluded.
- `cognito.scope:read,write` per-route middleware — fine-grained scope enforcement, complementing the pool-wide `required_scopes` config. 401 if unauthenticated, 403 if any required scope is missing.
- `php artisan cognito:warm-jwks` — pre-fetches and caches JWKS for every configured pool (or `--pool=<name>`) so the first authenticated request after a cold cache doesn't pay the round-trip, and so JWKS reachability is verified at deploy time.
- New recipe: end-to-end Hosted UI → SPA → API walkthrough using authorization code + PKCE, plus common gotchas (`docs/COGNITO-SETUP.md`).
- New recipe: running Cognito + Sanctum side by side (`docs/SANCTUM-HYBRID.md`).

### Changed

- `docs/COGNITO-SETUP.md` Hosted UI section rewritten around authorization code + PKCE; the implicit-flow sketch is gone.
- `CognitoGuard` constructor extended with a `string $pool = 'default'` argument so events can identify which pool authenticated the request. Backward-compatible default; the service provider sets it automatically.

**Full Changelog**: https://github.com/GrapheneICT/laravel-cognito-guard/compare/v2.1.0...v2.2.0

## v2.1.0 — 2026-05-15

### Added

- `php artisan cognito:test-token <jwt>` diagnostic command — validates a token and prints a step-by-step diagnosis (signature, issuer, `token_use`, `client_id`/`aud`, scopes, expiry). Supports `--pool=<name>` and `--verbose-claims`.
- Octane-safety regression test covering guard state isolation between requests.

### Changed

- Upgraded to PHPStan 8 / Larastan 3; tightened analysis to level max.
- Expanded README with FAQ section, configuration reference, and diagnostics docs.
- Added CONTRIBUTING and SECURITY docs.
- Pint runs in check-only mode on CI (removed auto-fix workflow).

**Full Changelog**: https://github.com/GrapheneICT/laravel-cognito-guard/compare/v2.0.0...v2.1.0

## v2.0.0 — 2026-05-15

Clean break from v1. See [UPGRADING.md](UPGRADING.md) for the migration guide.

### Added

- DB-less mode: set `db_less` on the guard to get a `CognitoUser` value object from claims instead of an Eloquent model.
- `cognito:groups` → Laravel Gates auto-bridge (toggle `bridge_groups_to_gates`).
- Multi-pool support: configure multiple Cognito User Pools under `cognito-guard.pools` and bind each to its own guard.
- `client_id` / `aud` allow-list per pool (`allowed_client_ids`).
- `token_use` allow-list per pool (`allowed_token_use`).
- Required-scopes enforcement per pool (`required_scopes`).
- `php artisan about` registers a **Cognito Guard** section.
- JWKS stale-cache fallback during Cognito outages.
- `CognitoUserProvider` — idiomatic `auth.providers` wiring via the standard Laravel UserProvider contract.
- Configurable JWKS cache TTL and cache store (`cognito-guard.jwks.cache_ttl`, `cognito-guard.jwks.cache_store`).
- `JwksFetchException` distinguishes transient JWKS-fetch failures from token-validation failures.

### Changed

- **Renamed** config file: `cognito-auth.php` → `cognito-guard.php` (publish tag: `cognito-guard-config`).
- **Restructured** config: `pools[]`, `jwks{}`, `user_provider{}`, `bridge_groups_to_gates`. The flat `models.user.model` shape is gone — see [UPGRADING.md](UPGRADING.md).
- **Moved** namespaces: `Services\Auth\JwtGuard` → `Guards\CognitoGuard`, `Services\JwtService` → `JwtVerifier`, `Services\JwkConverter` → `JwksProvider`. `Services\CognitoService` removed (claims now come from the JWT itself).
- Guard now reads from config (`config()`) instead of `env()` in services — fixes broken behavior under `config:cache`.
- Guard throws `InvalidTokenException` (extends `AuthenticationException` → returns 401) instead of `ErrorException`.
- `validate()` returns `false` rather than throwing, satisfying the `Guard` contract.

### Removed

- `aws/aws-sdk-php` dependency. The verify path no longer makes a Cognito `GetUser` API call — claims come from the JWT.
- `lcobucci/jwt` (declared but never used).
- `fruitcake/laravel-cors` (app-level CORS, not a guard concern).
- `phpseclib/phpseclib` moved to `require-dev` (only used by the test JWT forger).
- `MethodNotSupportedException` (unused).
- `models.user.model` config key — replaced by `user_provider.model` + the idiomatic `UserProvider` wiring.

### Requirements

- PHP `^8.3`
- Laravel `^11.0 | ^12.0`

**Full Changelog**: https://github.com/GrapheneICT/laravel-cognito-guard/compare/v1.0.0...v2.0.0

## 1.0.0 — 2022-09-25

- Initial release.
