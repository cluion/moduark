# ADR-0014: CI Compatibility Matrix

- Status: Accepted for Slice 8D
- Date: 2026-08-15

## Context

The package declares Laravel 12 and 13 support, with PHP 8.2 as the package
source floor, but a single developer runtime and an unconstrained
`composer update` cannot prove that contract. A branch label such as "lowest"
is also not evidence: Composer's security policy or transitive package
constraints may move the earliest installable framework version forward.

The release gate therefore needs real PHP runtimes, explicit framework and
Testbench pairs, lowest and highest dependency solving, and static analysis at
the package's PHP syntax floor.

## Decision

- GitHub Actions runs four explicit jobs:

  | Job | PHP | Laravel | Testbench | Dependency mode |
  |---|---:|---:|---:|---|
  | Laravel 12 lowest | 8.2 | `^12.0` | `^10.0` | `--prefer-lowest` |
  | Laravel 12 highest | 8.5 | `^12.0` | `^10.0` | latest stable |
  | Laravel 13 lowest | 8.3 | `^13.0` | `^11.0` | `--prefer-lowest` |
  | Laravel 13 highest | 8.5 | `^13.0` | `^11.0` | latest stable |

- Every compatibility job resolves dependencies without disabling Composer's
  insecure-package blocking, validates the resulting metadata, and runs the
  complete PHPUnit suite.
- Each highest job additionally runs `composer test:installation` for its own
  Laravel major. This covers consumer installation without duplicating both
  clean applications inside every matrix job.
- A separate PHP 8.2 job resolves highest stable dependencies and runs PHPStan
  at level max. Static analysis is not part of `--prefer-lowest` dependency
  compatibility because PHPStan is build tooling rather than a package runtime
  dependency.
- The workflow grants only `contents: read`; checkout does not persist Git
  credentials. Jobs have a 30-minute timeout and do not fail fast, so all matrix
  outcomes remain visible.
- `composer test:dependencies` is a reproducible local resolution probe. It
  copies package metadata into disposable projects, sets Composer's PHP platform
  to the matrix runtime, resolves without installing or running scripts, and
  reports selected versions. Platform simulation proves dependency solving, not
  runtime behavior; the CI jobs provide the runtime evidence.
- `composer test:lowest` copies the current checkout into a disposable project,
  resolves and installs the Laravel 12 lowest graph with platform PHP 8.2, and
  executes the Architecture, Unit, and Feature suites except the process-based
  generation benchmark covered by its dedicated performance gate. It reports
  the actual interpreter separately and does not replace the blocking PHP 8.2 CI job.
- The package intentionally does not track `composer.lock`. Every matrix job and
  local probe creates a fresh lock from the current package constraints.

## Local Resolution Evidence

Command:

```bash
composer test:dependencies
```

Environment: PHP 8.5.9 and Composer 2.10.2 with isolated temporary home/cache
directories on 2026-08-15. Composer reported no security vulnerability
advisories for the four selected graphs.

| Case | Simulated PHP | Laravel | Testbench | PHPUnit |
|---|---:|---:|---:|---:|
| Laravel 12 lowest | 8.2.0 | 12.61.1 | 10.0.0 | 11.5.50 |
| Laravel 12 highest | 8.5.0 | 12.66.0 | 10.11.0 | 13.1.14 |
| Laravel 13 lowest | 8.3.0 | 13.12.0 | 11.0.0 | 11.5.50 |
| Laravel 13 highest | 8.5.0 | 13.25.0 | 11.2.0 | 13.3.1 |

These versions are dated evidence, not pins. In particular, the lowest jobs
selected later framework releases than the first releases allowed by `^12.0`
and `^13.0`; future secure resolution may move them again.

## Consequences

- Laravel 12/13 support requires four green compatibility jobs and a green
  static-analysis job before release.
- Local dependency resolution can diagnose constraint or advisory failures
  before a push, while CI remains the authority for PHP-runtime compatibility.
- The two highest jobs are slower because they also create clean Laravel
  applications and exercise package auto-discovery and core commands.
- This local slice establishes the workflow contract but is not evidence that a
  remote GitHub Actions run has passed.
