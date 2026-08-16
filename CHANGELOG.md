# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/2.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Added a deterministic, reviewable architecture baseline workflow with
  `module:baseline`, conservative count matching, explicit replacement,
  safe stale-entry pruning, and audit metadata in text, JSON, and GitHub output.

## [0.3.0-beta.2] - 2026-08-16

This beta adds deterministic production caching for Module discovery and typed
architecture metadata through Laravel's native optimization lifecycle.

### Added

- Added deterministic `module:cache` and idempotent `module:clear` commands for
  versioned Module discovery and typed metadata manifests, including runtime
  loading and Laravel `optimize` / `optimize:clear` integration.

## [0.3.0-beta.1] - 2026-08-16

This beta starts the Developer Experience release line with deterministic
machine-readable checks and native GitHub Actions annotations.

### Added

- Added deterministic `module:check --format=json` output with a versioned
  report schema, stable status and exit-code mapping, complete violation
  context, unavailable-rule evidence, and machine-readable tool errors.
- Added `module:check --format=github` output with source-linked GitHub Actions
  error, warning, and notice annotations while preserving existing exit codes.

## [0.2.0-beta.3] - 2026-08-15

This beta makes the published Composer distribution smaller and adds an exact
public-package installation gate for release verification.

### Changed

- Distribution archives now exclude repository automation, tests, benchmarks,
  workbench files, and development-only analysis configuration.
- Clean Laravel installation acceptance can install one exact Packagist version
  and reject published archives that contain development-only files.

## [0.2.0-beta.2] - 2026-08-15

This beta adds complete Level 2 architecture observability through Capability
and combined graphs, focused Module inspection, and one connected acceptance
fixture that exercises the full contract.

### Added

- Added a typed, deterministic Capability graph domain and builder that retain
  `requires()` / `provides()` evidence and consumer Port / Adapter metadata
  while reusing runtime provider-resolution validation.
- Added `module:graph --view=capability` with deterministic text and Mermaid
  exporters plus complete provider/consumer neighborhoods for a selected
  Module. The existing direct Module view remains the default.
- Added `module:graph --view=combined` to preserve direct `depends` edges beside
  Capability `requires` and `provides` edges in text, Mermaid, and union
  neighborhood projections.
- Added `module:inspect {module}` with identity, effective level, direct
  dependency status, ServiceProviders, resolved Capability details, and the
  current convention-based Public API.
- Added an eight-Module Level 2 acceptance fixture with five shared
  Capabilities, twelve consumer-owned Port/Adapter bindings, three executable
  workflows, and command-level architecture observability coverage.

## [0.2.0-beta.1] - 2026-08-15

This beta completes Moduark's Level 2 Decoupled architecture contract with
typed Capabilities, consumer-owned Ports, runtime Adapter composition, and all
eight preset rules implemented.

### Added

- Added typed Capability identities and validated `requires()` / `provides()`
  Module metadata as the first Level 2 contract slice.
- Added deterministic, descriptor-only Capability provider resolution with
  cache-safe binding plans and stable missing or ambiguous provider diagnostics.
- Added Capability lifecycle preflight so missing or ambiguous providers fail
  before any Module ServiceProvider is registered.
- Added runtime Capability composition after Module ServiceProvider registration,
  including deterministic rejection when multiple consumer Modules declare the
  same container Port.
- Added the `capability_contracts` architecture rule with stable diagnostics for
  missing providers, ambiguous providers, and cross-Module Port collisions.
- Added the source-backed `adapter_boundaries` architecture rule for exact
  consumer-owned `Ports/` and `Adapters/{Provider}/` placement, core bypasses,
  unrelated provider access, concrete Adapter leakage, and provider reverse
  dependencies. The eight-rule Level 2 preset is complete in this beta.

## [0.1.0-beta.2] - 2026-08-15

### Changed

- Replaced the local path repository installation instructions with the
  published Packagist beta constraint.
- Added the Cluion author, project homepage, and Packagist discovery keywords
  to the Composer package metadata.

## [0.1.0-beta.1] - 2026-08-15

This first beta establishes Moduark's Laravel-native package foundation and
Level 1 architecture enforcement. Level 2 and Level 3 remain intentionally
incomplete and are not part of this release contract.

### Added

- Laravel 12 and 13 support with package auto-discovery and optional
  configuration publishing.
- Typed Module descriptors, deterministic discovery, lifecycle ordering, and
  Laravel-native routes, views, translations, migrations, commands, and
  providers.
- `make:module`, `module:list`, `module:check`, and `module:graph` Artisan
  commands with actionable diagnostics and stable exit codes.
- Level 0 and Level 1 presets with validated configuration, temporary level
  overrides, and individual rule overrides.
- Detection for missing and undeclared dependencies, dependency cycles, and
  cross-Module internal API access using AST-based source ownership.
- Deterministic text and Mermaid dependency graphs.
- Performance baselines, staged adoption guidance, clean Laravel installation
  tests, and Laravel 12/13 lowest/highest compatibility CI.

### Fixed

- Clean installation acceptance from release-tag checkouts by explicitly
  mapping the local path repository to `dev-main`.

[Unreleased]: https://github.com/cluion/moduark/compare/v0.3.0-beta.2...HEAD
[0.3.0-beta.2]: https://github.com/cluion/moduark/compare/v0.3.0-beta.1...v0.3.0-beta.2
[0.3.0-beta.1]: https://github.com/cluion/moduark/compare/v0.2.0-beta.3...v0.3.0-beta.1
[0.2.0-beta.3]: https://github.com/cluion/moduark/compare/v0.2.0-beta.2...v0.2.0-beta.3
[0.2.0-beta.2]: https://github.com/cluion/moduark/compare/v0.2.0-beta.1...v0.2.0-beta.2
[0.2.0-beta.1]: https://github.com/cluion/moduark/compare/v0.1.0-beta.2...v0.2.0-beta.1
[0.1.0-beta.2]: https://github.com/cluion/moduark/compare/v0.1.0-beta.1...v0.1.0-beta.2
[0.1.0-beta.1]: https://github.com/cluion/moduark/releases/tag/v0.1.0-beta.1
