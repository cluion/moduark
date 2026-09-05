# Contributing to Moduark

Moduark accepts focused bug fixes, false-positive reductions, documentation,
compatibility work, and changes that strengthen its Laravel-native modular
architecture contract. Keep each contribution narrow enough to review and
verify independently.

Security vulnerabilities must follow [the private reporting policy](SECURITY.md),
not the normal public issue or pull-request workflow.

## Set Up the Repository

Install the development dependencies and validate the package metadata:

```bash
composer install
composer validate --strict
```

The supported PHP and Laravel matrix is defined by `composer.json` and
`.github/workflows/tests.yml`. A passing checkout on one local runtime does not
prove the whole compatibility matrix.

## Run Focused and Full Checks

Start with the smallest relevant test target:

```bash
composer test:unit
composer test:feature
php vendor/bin/phpunit tests/Architecture
php vendor/bin/phpunit tests/Architecture/RepositoryPolicyContractTest.php
```

Before requesting review, run the local verification and distribution gates:

```bash
composer verify
composer test:distribution
```

Changes to framework compatibility, installation, package discovery, or the
Laravel Boost Skill should also run the relevant slower, networked gates:

```bash
composer test:dependencies
composer test:lowest
composer test:installation
composer test:installation -- --boost
```

`composer test:dependencies` verifies dependency resolution; it does not
execute the test suite on every simulated PHP runtime. `composer test:lowest`
installs the Laravel 12 lowest graph in an isolated copy and runs the
Architecture, Unit, and Feature suites on the current PHP interpreter. GitHub
Actions runs the blocking Laravel 12 / 13 lowest- and highest-dependency matrix
on their declared PHP runtimes; its result is the authoritative compatibility result.

## Change and Test Expectations

- Preserve strict types, existing namespaces, deterministic ordering, stable
  diagnostic identities, and actionable error messages.
- Add or update a focused executable test with each behavior change. Prefer a
  small synthetic fixture over a large snapshot.
- Do not guess unresolved dynamic PHP or Laravel behavior. Preserve an explicit
  warning or incomplete result when the analyzer cannot make a safe conclusion.
- Treat exit code `2` or machine output with `complete: false` as an analyzer
  failure, never as a clean architecture result.
- Do not silently regenerate or weaken application-owned baselines and
  suppressions. Any identity migration needs explicit upgrade guidance and a
  reviewable before/after test.
- Keep unrelated formatting, refactors, generated files, and local planning
  artifacts out of the contribution. `.internal/` is repository-local planning
  state and must not be committed.

Use Testbench lifecycle helpers for framework lifecycle behavior. A package
test must not assume that a Testbench application restarts exactly like a
normal deployed Laravel application.

## ADR Threshold

Add or update an Architecture Decision Record in `docs/adr/` when a change
introduces or reclassifies a public or Stable surface, changes a Level preset or
rule meaning, changes a diagnostic or persistent schema identity, changes the
package lifecycle, or adopts a new cache, distribution, or dependency strategy.
A narrow bug fix that preserves an accepted contract normally needs a focused
regression test and changelog entry, not a new ADR.

## Fixtures and Real-Project Corpus Data

Prefer synthetic fixtures containing only the minimum source needed to prove a
rule. A public corpus manifest must record public provenance, use a pinned
revision, and avoid vendoring the target application's source into this
repository.

For a private local adoption run, start from
`tools/corpus/manifests/local-laravel.json`. Keep repository identity and source
data out of the manifest. Write reports outside the repository when possible,
and never commit private source code, secrets, customer data, absolute private
paths, or an unreviewed private corpus report. Reduce any report used in a test
or issue to an anonymous synthetic fixture.

Changes to a precision or recall oracle, expected diagnostic count, baseline,
or suppression must explain why the old expectation was wrong. Do not update an
expectation only to make a failing check pass.

## Pull Request Checklist

- The change is focused and its user-visible behavior is documented.
- Relevant focused tests pass, followed by `composer verify` and
  `composer test:distribution` when applicable.
- Compatibility or installation gates were run when their surface changed.
- New public contracts have an ADR and stability classification.
- Diagnostic, baseline, suppression, cache, and upgrade effects were reviewed.
- Fixtures and reports contain no private source, credentials, or customer data.
