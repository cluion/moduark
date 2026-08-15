# ADR-0027: Distribution Archive Contract

- Status: Accepted for the first post-`0.2.0-beta.2` distribution slice
- Date: 2026-08-15

## Context

Composer correctly ignores a dependency package's `autoload-dev` definitions,
so Moduark's tests, benchmarks, and workbench do not participate in an
application's runtime autoloader. The published GitHub dist for
`v0.2.0-beta.2` nevertheless contains those tracked files below
`vendor/cluion/moduark/` because the repository had no archive exclusion
contract.

The existing clean installation matrix proved fresh Laravel application setup
against the current checkout through a Composer path repository. It did not
claim that the published Packagist dist was minimal.

## Decision

- Add root `.gitattributes` `export-ignore` rules for `.github/`, `tests/`,
  `benchmarks/`, `workbench/`, the Git metadata files, PHPStan and PHPUnit
  configuration, and `testbench.yaml`.
- Keep `src/`, `config/`, `stubs/`, `docs/`, `composer.json`, `LICENSE`,
  `README.md`, and `CHANGELOG.md` in the distribution.
- Add a distribution test that creates an actual Git tar archive using the
  worktree attributes, checks required files, and rejects every development
  tree or file in the contract.
- Keep the installation runner's default current-checkout path mode. Add an
  explicit `--package=VERSION` mode that installs one exact Packagist dist into
  fresh Laravel 12 and 13 applications, validates archive contents, and then
  exercises the same auto-discovery, commands, and configuration-cache path.
- Treat public-package installation as a post-publication gate. Local archive
  validation runs before the tag; exact Packagist installation runs after the
  tag and webhook update.

## Acceptance evidence

- Before adding the attributes, the archive test failed because `.github/` was
  present. With the accepted rules it passes with all runtime files retained and
  all development paths absent.
- Running the new Packagist mode against `0.2.0-beta.2` installs its real dist
  and then fails on `.github/`, confirming that the gate detects the published
  archive problem rather than using the local checkout.
- The first release containing these attributes must additionally download the
  GitHub archive, install the exact Packagist version into both supported Laravel
  majors, and verify that the public dist matches the local contract.

## Consequences

- Future Packagist installs no longer need repository tests, benchmarks,
  workbench code, CI workflow files, or analysis configuration in `vendor/`.
- Contributors retain every development file in clones and path repositories;
  `export-ignore` changes archives, not the tracked repository.
- Public documentation remains available inside the package instead of reducing
  the archive to runtime PHP alone.
- A release is not distribution-verified merely because current-checkout clean
  installation passes; both pre-tag archive and post-tag Packagist gates are
  required.
