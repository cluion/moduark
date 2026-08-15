# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/2.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/cluion/moduark/compare/v0.1.0-beta.2...HEAD
[0.1.0-beta.2]: https://github.com/cluion/moduark/compare/v0.1.0-beta.1...v0.1.0-beta.2
[0.1.0-beta.1]: https://github.com/cluion/moduark/releases/tag/v0.1.0-beta.1
