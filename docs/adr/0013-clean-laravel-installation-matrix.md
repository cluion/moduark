# ADR-0013: Clean Laravel Installation Matrix

- Status: Accepted for Slice 8C
- Date: 2026-08-15

## Context

Testbench proves package behavior inside a maintained Laravel-compatible
workbench, but it does not prove that a fresh application can resolve and install
the package through Composer. The beta acceptance criteria require Laravel 12
and 13 applications to use package auto-discovery, generate their first Module,
and run the Level 1 tools without publishing configuration.

## Decision

- `composer test:installation` creates disposable applications with Composer's
  official `laravel/laravel` project for majors 12 and 13.
- The runner installs the current checkout as `cluion/moduark:dev-main` through
  a Composer path repository. The repository explicitly maps the current
  checkout to `dev-main` so the same acceptance path works from branch and tag
  refs. It does not require a remote or published tag.
- Passing `--package=VERSION` selects one exact stable or pre-release version
  from Packagist, does not configure the path repository, prefers the dist
  archive, and validates its required and excluded files before Artisan runs.
  Ranges, branch names, and tag-prefixed versions are rejected so the release
  gate cannot silently select another package build.
- Composer home and cache directories live below the disposable matrix root so
  the test does not depend on writable user-level caches.
- Each application must expose all five commands through package auto-discovery,
  while `config/modules.php` remains absent.
- Acceptance generates exactly one `UserModule.php`, then verifies deterministic
  listing, the complete default six-rule Level 1 check, text graph output, and a
  second Level 1 check after `config:cache`.
- The generated applications are removed by default. Cleanup unlinks Composer
  path-repository symlinks instead of traversing them. `--keep` is available for
  failure investigation.
- The networked installation matrix remains separate from `composer verify`.
  Fast PHPUnit and PHPStan runs must remain usable offline.
- Local runs test the highest stable dependency resolution available to the
  current PHP runtime. The compatibility workflow runs each Laravel major's
  clean installation in its highest-dependency job; lowest dependency jobs
  remain focused on package verification. See [ADR-0014](0014-ci-compatibility-matrix.md).

## Acceptance Evidence

Command:

```bash
composer test:installation

# Post-publication verification against an exact Packagist dist:
composer test:installation -- --package=0.2.0-beta.3
```

Environment: PHP 8.5.9 on 2026-08-15. Both applications resolved from the
network with isolated Composer caches and were removed after acceptance.

| Laravel project | Resolved framework | Auto-discovery | Unpublished config | Core commands | Config cache |
|---|---:|:---:|:---:|:---:|:---:|
| `laravel/laravel` 12.x | 12.66.0 | Pass | Pass | Pass | Pass |
| `laravel/laravel` 13.x | 13.25.0 | Pass | Pass | Pass | Pass |

For each row, core commands means `make:module User` generated exactly one entry
file, `module:list` reported Level 1, `module:check` evaluated all six Level 1
rules, and `module:graph` rendered the generated Module.

## Consequences

- Package metadata, dependency constraints, Composer installation, Laravel
  auto-discovery, default configuration, generation, analysis, graphing, and
  configuration caching are tested as one consumer-visible path.
- The matrix is intentionally slower and needs network access on an empty
  Composer cache.
- A passing local matrix complements, but does not replace, the PHP and
  lowest/highest CI combinations.
- The Packagist mode is a post-publication gate: an untagged commit cannot prove
  the contents of a dist archive that does not exist yet. See
  [ADR-0027](0027-distribution-archive-contract.md).
