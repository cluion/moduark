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
  a Composer path repository. It does not require a remote or published tag.
- Composer home and cache directories live below the disposable matrix root so
  the test does not depend on writable user-level caches.
- Each application must expose all four commands through package auto-discovery,
  while `config/modules.php` remains absent.
- Acceptance generates exactly one `UserModule.php`, then verifies deterministic
  listing, the complete default six-rule Level 1 check, text graph output, and a
  second Level 1 check after `config:cache`.
- The generated applications are removed by default. Cleanup unlinks Composer
  path-repository symlinks instead of traversing them. `--keep` is available for
  failure investigation.
- The networked installation matrix remains separate from `composer verify`.
  Fast PHPUnit and PHPStan runs must remain usable offline.
- This slice tests the highest stable dependency resolution available to the
  current PHP runtime. Lowest dependency combinations and CI workflow wiring
  remain separate release work.

## Acceptance Evidence

Command:

```bash
composer test:installation
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
- A passing local matrix is not a replacement for the planned PHP and
  lowest/highest CI combinations.
