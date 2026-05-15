# Changelog

All notable changes to `graphene-ict/laravel-cognito-guard` will be documented in this file.

## v2.0.0 - 2026-05-15

### What's Changed

* Bump dependabot/fetch-metadata from 1.3.3 to 1.3.4 by @dependabot[bot] in https://github.com/GrapheneICT/laravel-cognito-guard/pull/13
* Bump dependabot/fetch-metadata from 1.3.4 to 1.3.5 by @dependabot[bot] in https://github.com/GrapheneICT/laravel-cognito-guard/pull/14
* Bump dependabot/fetch-metadata from 1.3.5 to 1.3.6 by @dependabot[bot] in https://github.com/GrapheneICT/laravel-cognito-guard/pull/17
* Bump dependabot/fetch-metadata from 1.3.6 to 1.4.0 by @dependabot[bot] in https://github.com/GrapheneICT/laravel-cognito-guard/pull/19
* Bump dependabot/fetch-metadata from 1.4.0 to 1.5.1 by @dependabot[bot] in https://github.com/GrapheneICT/laravel-cognito-guard/pull/21
* Bump dependabot/fetch-metadata from 1.5.1 to 1.6.0 by @dependabot[bot] in https://github.com/GrapheneICT/laravel-cognito-guard/pull/22
* v2.0.0 revival: lean Cognito JWT guard, multi-pool, groups into Gates… by @jstojiljkovic in https://github.com/GrapheneICT/laravel-cognito-guard/pull/25

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/GrapheneICT/laravel-cognito-guard/pull/13

**Full Changelog**: https://github.com/GrapheneICT/laravel-cognito-guard/compare/v1.0.0...v2.0.0

## 2.0.0 — Unreleased

This is a clean break from v1. See [UPGRADING.md](UPGRADING.md) for the migration guide.

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
- Laravel `^11.0 | ^12.0 | ^13.0`

## 1.0.0 — 2022-09-25

- Initial release.
