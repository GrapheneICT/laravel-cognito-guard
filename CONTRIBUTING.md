# Contributing

Thank you for considering contributing to Laravel Cognito Guard. Contributions are welcome via pull requests, issues, and discussions.

## How to contribute

1. Fork the repository.
2. Create a feature branch off `main`: `git checkout -b feat/my-change`.
3. Make your change, including tests for any new behavior.
4. Run the test suite and static analysis locally (see below).
5. Open a pull request describing the change and the reasoning behind it.

## Running tests

```bash
composer install
composer test
```

Tests must pass on all supported PHP / Laravel combinations in `.github/workflows/run-tests.yml`. If you need to test against a different combination locally:

```bash
composer require "laravel/framework:12.*" "orchestra/testbench:10.*" --no-update
composer update --prefer-stable
composer test
```

## Static analysis

```bash
composer analyse
```

PHPStan must run clean at level 6. Do not suppress errors via `@phpstan-ignore` comments or baseline entries — fix the underlying type.

## Coding standards

- PSR-12 enforced by [Laravel Pint](https://laravel.com/docs/pint). Run `composer format` before pushing.
- Type-hint everything. Use `readonly` where the property is set once in the constructor.
- Prefer constructor property promotion.
- No new dependencies without a clear justification in the PR description.

## Reporting bugs

Open an issue using the **Report a bug** link in [`.github/ISSUE_TEMPLATE/config.yml`](.github/ISSUE_TEMPLATE/config.yml). Include:

- PHP and Laravel versions
- The package version
- A minimal reproduction (snippet or a small repo)
- The actual vs. expected behavior

For security issues, **do not open a public issue** — see [`SECURITY.md`](SECURITY.md).

## Releases

This package follows [Semantic Versioning](https://semver.org/). Maintainers cut releases by tagging `vX.Y.Z` on `main` after updating [`CHANGELOG.md`](CHANGELOG.md).
