# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/2.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0-rc.1] - 2026-09-05

This release candidate adds Preview Module activation, extractability and
standalone package export, Composer package Module discovery, package-set
materialization, and an opt-in native Laravel Maker bridge. Levels 0 through 2
remain Stable, Level 3 remains Preview, and every new `1.3` surface remains
Preview while the candidate is evaluated on Laravel 12 and 13 applications.

### Added

- Added dependency- and Capability-aware Module activation planning with stable
  blocker identities, deterministic scalar plans, and explicit standalone or
  nwidart state-driver ownership.
- Added `moduark:enable` and `moduark:disable` commands with schema-versioned
  JSON output, optional dry-run planning, atomic standalone / nwidart file-state
  mutation, optimistic concurrency checks, and graph-cache invalidation.
- Added deploy-safe standalone activation state in `moduark-modules.json`;
  failed cache invalidation or state commit preserves the previous state.
- Added a permanent cold/cached active-set parity fixture covering list,
  inspect, doctor, resources, every graph, source analysis, Module cache,
  providers, Capabilities, and runtime resources.
- Added Preview `moduark:doctor <module> --extractable` diagnostics with a
  deterministic schema and blockers for layout, autoload, provider/resource
  ownership, and declared application-global metadata coupling.
- Added Module-scoped raw Level 3 architecture extraction gates for dependency,
  Capability, table, foreign-key, transaction, and explicit-export evidence;
  baselines, suppressions, warnings, or unevaluated rules cannot hide blockers.
- Added portable runtime extraction gates for resource plugin contracts,
  Module-scoped namespaces, manifest collisions, publish targets / asset inputs,
  and statically detected provider container bindings.
- Added Preview `moduark:export <module> --dry-run` package planning with explicit
  target, Composer identity and namespace, deterministic file transforms,
  dependency evidence, and fail-closed collision checks without filesystem writes.
- Added atomic `moduark:export` materialization, deterministic Composer/provider
  rendering, namespace rewriting, rollback diagnostics, and a portable one-Module
  package runtime for Laravel and Testbench applications.
- Added schema-versioned `extra.moduark` package Module descriptors and a
  deterministic Composer installed-package catalog with source validation,
  stable fingerprints, and fail-closed identity collisions.
- Added one canonical active Module registry across application or nwidart and
  Composer package Modules, including analysis, graphs, cache, lifecycle,
  providers, Capabilities, and resources.
- Added explicit Module-to-package export dependency mappings with validated
  Composer constraints, dependency namespace rewrites, and a transitive
  two-package Laravel 12 / 13 clean-install regression gate.
- Added read-only `moduark:export-set` planning with explicit package and target
  mappings, dependency-closed validation, deterministic dependency-first order,
  embedded single-package plans, and cross-target collision blockers.
- Added explicit `moduark:export-set --materialize` package-set preparation,
  dependency-ordered publication, optimistic collision rechecks, rollback, and
  machine-readable residual-target and cleanup-failure evidence.
- Added the plan-only `moduark:native-bridge` capability report with explicit
  opt-in, reviewed Laravel 12 / 13 Maker ownership, stable command / signature /
  option collision diagnostics, and a default zero-mutation boundary.
- Added opt-in, all-or-none native Laravel Maker decoration so reviewed
  `make:* --module` calls reuse Moduark's Generator Registry and Generation Plan,
  preserve unqualified Laravel behavior, and fail closed on ownership drift,
  unsupported options, or registration failure.

### Changed

- Advanced the Preview export plan to schema version 2 with a nullable
  dependency namespace field.

- Extended the rebuildable Module cache to schema version 6 and invalidate it
  when the installed package Module catalog fingerprint changes.
- Made Composer-installed package Modules immutable-active until Composer
  removes them, while retaining package Modules in local activation dependency
  and Capability planning.
- Made descriptor-aware portable package providers delegate to the canonical
  runtime so provider and resource registration remain single-owner regardless
  of Laravel provider order.

### Fixed

- Made native Laravel Maker registration and rollback use Laravel's console
  compatibility layer, preserving Laravel 12 support with Symfony Console 7.2;
  added an isolated `composer test:lowest` runtime regression gate for the exact
  Laravel 12 lowest-dependency graph.

## [1.2.0] - 2026-08-29

This minor release completes Runtime Completeness while preserving the Stable
Level 0 through 2 architecture contract and Level 3 Preview boundary. It adds
one serializable resource manifest for discovery, cache, diagnostics, runtime
registration, assets, and forward-only Module operations, with Laravel 12 / 13
and nwidart interoperability coverage.

### Added

- Added an extensible Resource Plugin contract with separate pure discovery and
  runtime handling, third-party registration, scalar-only descriptors, and a
  schema-versioned resource manifest embedded in the Module cache.
- Added opt-in Module config, custom route files, recursive commands, factories,
  seeders, policies, events/listeners, Blade components, generic assets, tests,
  and extension metadata while retaining all existing resource conventions.
- Added deterministic generic Vite inputs and `moduark-assets` publication for
  framework-neutral public assets.
- Added schema-versioned text/JSON `moduark:resources` and `moduark:doctor`
  diagnostics, including disabled Module state, framework support, missing
  resources or handlers, duplicates, and cross-Module collisions.
- Added forward-only `moduark:migrate`, `moduark:seed`, and PHPUnit/Pest-backed
  `moduark:test` operations that resolve the selected Module exclusively through
  the canonical registry and runtime manifest.

### Changed

- Extended the rebuildable Module cache to schema version 5 so cold discovery
  and cached boot use the same dependency-ordered active Module and resource
  manifest.
- Made resource registration idempotent across repeated provider boot while
  retaining Laravel config-cache and route-cache behavior.

### Fixed

- Resolved PHPUnit and Pest binaries from the Composer project root so
  `moduark:test` remains usable when Laravel or Testbench uses an isolated
  application base path.
- Kept resource diagnostics, operations, providers, assets, registry, analysis,
  graphs, cache, and Capabilities aligned with nwidart's authoritative active
  Module set, including after config caching and with an observable
  known-disabled state.
- Prevented duplicate conventional resource registration when Moduark and
  nwidart share a Module root; explicit `Module::resources()` entries remain
  Moduark-owned.

## [1.1.0] - 2026-08-23

This minor release completes the Generation Foundation while preserving the
Stable Level 0 through 2 architecture contract and Level 3 Preview boundary.
It adds complete plan-first Module generation without changing existing
application configuration, architecture debt files, or runtime activation.

### Added

- Added a Generation Foundation completion contract that binds the exact
  31-ID registry to the reviewed Laravel 12 / 13 Maker inventories and requires
  every built-in Maker to retain versioned fixture coverage.
- Added the final Module-owned `command`, `config`, and `provider` Makers,
  completing all 31 name-based Laravel 12 / 13 inventory candidates. Commands
  retain native stubs and runtime discovery, while config/provider templates
  avoid application-global config and bootstrap-provider mutations.
- Added a large Generation performance regression gate covering 100 full
  scaffold Modules and 1,400 production-template targets. A fixed PHP 8.5 CI
  job now enforces a generous median-total budget while the standalone
  benchmark retains text and JSON evidence modes.
- Added the Stable third-party generator registration API. Composer packages
  can contribute template-backed Module generators through Laravel discovery
  while shared option validation, JSON/text plans, collision preflight,
  overwrite rules, and rollback remain enforced centrally.
- Added shared human-readable and schema-versioned JSON Generation Plan output
  for `moduark:make` and `moduark:make-module`. JSON dry runs expose portable
  target operations, generator ownership, overwrite intent, collisions, status,
  and exit codes without filesystem mutation or mixed Laravel command output.
- Added the reviewed `1.1` Generator Registry contract and deterministic
  Laravel 12 / 13 native Maker inventory fixtures. The fixtures cover all 37
  framework `make:*` commands and detect command, alias, argument, or option
  drift without changing the current `moduark:make` runtime contract.
- Added the executable Generator Registry and immutable Generation Plan for the
  existing model and controller Makers. `moduark:make --dry-run` now validates
  and renders the same preflighted Module-relative targets without filesystem
  mutation, while normal generation retains the existing Laravel delegation.
- Added model `--factory` and `--migration` composite plans with Module-owned
  targets, complete collision preflight, runtime factory wiring, and executor
  rollback for newly created or overwritten files. Rollback failures are
  surfaced explicitly instead of being reported as atomic success.
- Added Module-owned `class`, `enum`, `interface`, and `trait` Maker descriptors
  with Laravel-native stubs, fixed Laravel 12 / 13 plan fixtures, nested
  namespaces, descriptor-specific option allowlists, and shared dry-run,
  collision, and force behavior.
- Added Module-owned `cast`, `exception`, and `scope` Maker descriptors with
  Laravel-native stubs and reviewed `--inbound`, `--render`, and `--report`
  option ownership across the Laravel 12 / 13 regression matrix.
- Added Module-owned `request` and `resource` Maker descriptors with native
  Form Request, JSON Resource, Resource Collection, and JSON:API stubs. Resource
  modes are explicitly allowlisted and conflicting modes fail before writing.
- Added the Module-owned `middleware` Maker below `Http/Middleware/` with its
  Laravel-native stub and explicit refusal of unsupported force or related-test
  generation rather than writing outside the single-target Module plan.
- Added the Module-owned `policy` Maker below `Policies/` with native plain and
  model-aware stubs. Relative `--model` values resolve inside the selected
  Module, while `--guard` retains Laravel's application auth-provider semantics.
- Added the Module-owned validation `rule` Maker below `Rules/` with native
  plain and `--implicit` stubs, versioned Laravel 12 / 13 fixtures, and shared
  single-target dry-run, collision, and force behavior.
- Added standalone Module-owned `factory` and `seeder` Makers below
  `Database/Factories/` and `Database/Seeders/`, including Module-relative
  factory model inference without writes to Laravel's root `database/` tree.
- Added the Module-owned `observer` Maker below `Observers/` with Laravel-native
  plain and model-aware stubs, Module-relative model qualification, native
  collision/force behavior, and no implicit registration side effects.
- Added Module-owned Blade component generation with atomic class-and-view,
  inline-class, anonymous-view, and safe custom-view-path modes. Laravel 12 / 13
  fixtures lock every planned target without writing application-global views.
- Added deterministic standalone Blade view generation below each Module's
  `resources/views/`, including nested dot/slash names, safe custom extensions,
  collision preflight, force, and dry-run behavior for Laravel 12 and 13.
- Added Module-owned PHPUnit and Pest test generation below fixed
  `Tests/Feature/` and `Tests/Unit/` roots. Supported Laravel Makers can include
  matching feature tests in the same atomic generation plan without writing to
  the application-global `tests/` tree.
- Added the package-owned planning contract for additive `minimal`, `web`,
  `api`, `domain`, and `full` Module scaffold presets, with deterministic target
  manifests and shared collision ownership. `moduark:make-module --preset=` now
  executes those plans with complete collision preflight and rollback-safe
  writes, while `--dry-run` renders the same targets without mutation and the
  omitted preset retains the existing minimal scaffold.
- Added the standalone Module-owned `migration` Maker below
  `Database/Migrations/` with Laravel 12 / 13 compatible plain, create, update,
  name inference, timestamp, collision, and no-force contracts.
- Added the Module-owned `event` Maker below `Events/` with Laravel-native
  stubs, versioned Laravel 12 / 13 fixtures, shared collision/force behavior,
  and no implicit listener or provider side effects.
- Added the Module-owned `listener` Maker below `Listeners/` with Laravel-native
  plain, typed, queued, and typed-queued stubs. Relative `--event` values resolve
  inside the selected Module without creating events or provider registrations.
- Added the Module-owned `job` Maker below `Jobs/` with Laravel-native queued,
  synchronous, and batched queued stubs. `--sync` and `--batched` are explicitly
  mutually exclusive and never create related tests or queue infrastructure.
- Added the Module-owned plain `notification` Maker below `Notifications/`.
  Laravel's `--markdown` mode is explicitly rejected because it also writes an
  application-global view below `resources/views/`.
- Added the Module-owned plain `mail` Maker below `Mail/`. Laravel's
  `--markdown` and `--view` modes are explicitly rejected because both create
  application-global views.
- Added the Module-owned `channel` Maker below `Broadcasting/` with Laravel's
  native auth-provider model reference and no implicit model, test, route,
  provider, or registration side effects.
- Added the Module-owned `job-middleware` Maker below `Jobs/Middleware/` with
  Laravel's native single-target stub and no implicit job, test, queue, or
  registration side effects.

## [1.0.1] - 2026-08-23

This patch keeps the Stable Level 0 through 2 contract and Level 3 Preview
boundary unchanged while correcting nwidart enabled-state interoperability.

### Changed

- Updated the optional PHPStan companion documentation from the pre-release
  constraint to the published stable `cluion/moduark-phpstan:^0.2` line after
  Laravel 12 / 13 published-distribution acceptance.

### Fixed

- Made nwidart's enabled Module set authoritative when Moduark automatically
  follows its Module root. Registry consumers, analysis, graphs, lifecycle
  providers, Capability bindings, native resources, and the version `4`
  metadata cache now exclude disabled Modules and restore them after enable;
  the permanent regression matrix covers Laravel 12 with nwidart 12 and
  Laravel 13 with nwidart 13.

## [1.0.0] - 2026-08-23

This first stable release promotes the publicly reviewed RC.2 contract without
another runtime or machine-schema change. Levels 0 through 2 are Stable, Level
3 remains Preview, and the zero-configuration default remains Level 1.

### Changed

- Promoted the RC.2 PHP extension points, `moduark.*` configuration,
  `moduark:*` commands, architecture presets, diagnostics, and versioned machine
  formats to the documented Stable `1.x` contract.
- Clarified nwidart adoption prerequisites: load each generated Module's
  Composer PSR-4 mapping, use nwidart-owned Makers for its external `Modules/`
  root, and keep the Moduark entry below `Modules/<Name>/app`.
- Updated installation, upgrade, security-support, PHPStan companion, and
  stability guidance for the stable `^1.0` line while retaining Level 3's
  Preview limits.

## [1.0.0-rc.2] - 2026-08-22

### Added

- Added a Laravel 13 + `nwidart/laravel-modules` interoperability fixture that
  verifies package installation, command ownership, configuration publishing,
  `Modules/*/app` discovery, Public API boundaries, architecture checks,
  Module caching, and Laravel optimization lifecycle behavior.
- Added discovery support for both `<Module>/<Module>Module.php` and
  `<Module>/app/<Module>Module.php`; convention-based Public API folders remain
  relative to the selected entry class source root.

### Changed

- Namespaced every Moduark Artisan command below `moduark:*` and moved the
  package configuration from `config/modules.php` / `modules.*` to
  `config/moduark.php` / `moduark.*` so Moduark can coexist with packages that
  own the generic Module command and configuration namespaces.

### Fixed

- Fixed false-green interoperability checks that could unknowingly execute a
  third-party `module:*` command or overwrite another package's
  `config/modules.php`.
- Updated the 1.0 RC integration guidance for the separately published
  `moduark-phpstan` `v0.2.0-beta.1`, whose Composer constraint, independent
  configuration input, nwidart source-root handling, and Laravel 12 / 13
  compatibility matrix now match this candidate.

## [1.0.0-rc.1] - 2026-08-22

This first 1.0 release candidate freezes the candidate Stable contract for
Levels 0 through 2, keeps Level 3 in Preview, and packages the reviewed upgrade,
security, contribution, Laravel Boost Skill, and release policies for public
validation before `1.0.0`.

### Added

- Added the candidate `1.0.0` Stable, Preview, and Internal contract boundary,
  with executable coverage for supported PHP extension points, configuration,
  Level presets, CLI definitions, exit codes, and versioned machine schemas.
- Added the candidate beta-to-1.0 upgrade guide and testable deprecation policy,
  including reviewed `MOD-DEPENDENCY-002` pair-identity migration, cache rebuild,
  and baseline / suppression safety gates.
- Added executable security support and contribution policies with a verified
  private vulnerability reporting channel, current pre-release support matrix,
  real repository commands, ADR thresholds, and corpus privacy safeguards.
- Added a complete Level 3 adoption fixture covering Capability wiring, owned
  migrations, resolved and unresolved foreign keys, inline transactions, and
  all fourteen rules; the 1.0 go/no-go review keeps Level 3 in Preview pending
  independent brownfield evidence.
- Added a maintainer release policy with a mandatory 1.0 RC, separately
  authorized publication stages, exact-commit CI, annotated-tag, Packagist, and
  published Laravel 12 / 13 dist acceptance gates.
- Added a Laravel Boost-compatible `moduark-development` Agent Skill with
  focused adoption, diagnostics, debt, inspection, and upgrade guidance; the
  Skill ships with the Composer package and preserves Moduark's machine-readable
  exit, warning, incomplete-analysis, baseline, and suppression contracts.
- Added an opt-in clean Laravel 12 / 13 installation gate that verifies Laravel
  Boost discovery, complete Codex Skill synchronization, and idempotent repeated
  installation from the current package checkout.

## [0.5.0-beta.1] - 2026-08-22

This beta completes the Ecosystem and Documentation release line with optional
PHPStan integration guidance, staged migration recipes, interactive graph
examples, and real-project analyzer hardening.

### Added

- Added a public PHPStan and Larastan integration guide for the optional
  `cluion/moduark-phpstan` companion package, including beta installation,
  automatic and manual extension loading, effective configuration alignment,
  baseline and suppression reuse, CI recipes, diagnostics, and current scope.
- Added a brownfield Level 0 to Level 1 migration recipe with concrete Module
  metadata and provider-owned Public API changes, staged acceptance checkpoints,
  baseline and suppression decisions, rollback boundaries, and a CI gate.
- Added a brownfield Level 1 to Level 2 migration recipe covering provider-neutral
  Capability identities, consumer-owned Ports, provider-scoped Adapters,
  automatic runtime wiring, graph inspection, rollback boundaries, and CI gates.
- Added a brownfield Level 2 to Level 3 migration recipe covering explicit table
  ownership, migration placement, Model and query isolation, reviewed foreign
  keys and transactions, explicit exports, analyzer limits, and CI gates.
- Added a dependency-free interactive graph explorer and static Mermaid examples
  for Module, Capability, and Combined views, backed by the executable Large
  Level 2 fixture and a drift-prevention test.
- Added a repository-only real-project corpus harness with pinned public and
  privacy-safe local manifests, independent token-based precision, anchoring,
  and recall oracles, cold/warm source-cache measurements, and an ADR recording
  beta adoption across 1,511 PHP files.

### Changed

- Changed `MOD-DEPENDENCY-002` to report one deterministic representative per
  ordered consumer / provider Module pair instead of repeating the same missing
  dependency for every class reference. New baselines use stable pair identity,
  and suppressions must select both Modules. Existing per-symbol beta baselines
  and suppressions must be reviewed and migrated after upgrading.

### Fixed

- Fixed console Module discovery rejecting co-located interfaces, traits,
  enums, and abstract classes as invalid commands while preserving autoloaded
  source verification and concrete non-command rejection.
- Fixed fluent Query Builder table evidence pointing to the first line of the
  whole chain instead of the line containing the table argument, with source
  analysis cache schema `7` invalidating older line evidence safely.

## [0.4.0-beta.1] - 2026-08-16

This beta completes the Level 3 Isolated preset with explicit persistence and
Public API boundaries across all fourteen architecture rules.

### Added

- Added the first Level 3 rule, `cross_module_model_access`, with AST-resolved
  direct and indexed indirect Eloquent Model inheritance, source-level evidence
  for cross-Module type and expression references, and a versioned source-cache
  schema for retained parent metadata.
- Added explicit `Module::tables()` metadata and a deterministic table ownership
  index with canonical-name validation, case-insensitive single-owner conflict
  detection, cache-safe descriptors, and `module:inspect` visibility.
- Added the Level 3 `database_ownership` rule with Laravel-aware AST evidence
  for Facade table access and rooted fluent queries, blocking cross-Module and
  unowned literals, reviewable unresolved-expression warnings, and source-cache
  schema `3` support.
- Added the Level 3 `migration_ownership` rule for Laravel Schema mutations,
  enforcing canonical Module migration locations and explicit ownership for
  created, altered, renamed, and dropped tables with source-cache schema `4`.
- Added the advisory Level 3 `cross_module_foreign_keys` rule for Laravel
  Blueprint constraints, reporting cross-owner, unowned, and unresolved table
  evidence with source-cache schema `5`.
- Added the advisory Level 3 `cross_module_transactions` rule for direct Query
  Builder writes inside inline Laravel transaction callbacks, reporting
  cross-owner, unowned, and unresolved write evidence with source-cache schema
  `6`.
- Added explicit `Module::exports()` metadata and the final Level 3
  `explicit_public_exports` rule, enforcing owner-validated cross-Module API
  narrowing with Module-cache schema `3` and inspection visibility. All fourteen
  Level 3 rules are now implemented and can produce a complete pass.

## [0.3.0-beta.5] - 2026-08-16

This beta adds auditable, narrowly scoped architecture suppressions without
weakening active rules or duplicating baseline debt.

### Added

- Added auditable architecture suppressions through a strict external manifest
  with mandatory reasons, narrow portable selectors, overlap rejection,
  stale/inactive debt reporting, `--show-suppressions`, JSON/GitHub metadata,
  and suppression-aware baseline generation.

## [0.3.0-beta.4] - 2026-08-16

This beta adds Laravel-native Module-aware generation and content-verified
incremental source analysis for faster repeated architecture checks.

### Added

- Added `module:make {module} {type} {name}` for Laravel-native model and
  controller generation inside existing application Modules, with explicit safe
  options and deterministic rejection of unsupported paths or related artifacts.
- Added automatic incremental source analysis with SHA-256 file invalidation,
  Module-owner and schema guards, atomic best-effort manifests, safe cold
  fallback, and cleanup through `module:clear` or `optimize:clear`.

## [0.3.0-beta.3] - 2026-08-16

This beta adds a reviewable architecture baseline workflow for adopting existing
debt without weakening active rules or silently accepting regressions.

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

[Unreleased]: https://github.com/cluion/moduark/compare/v1.3.0-rc.1...HEAD
[1.3.0-rc.1]: https://github.com/cluion/moduark/compare/v1.2.0...v1.3.0-rc.1
[1.2.0]: https://github.com/cluion/moduark/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/cluion/moduark/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/cluion/moduark/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/cluion/moduark/compare/v1.0.0-rc.2...v1.0.0
[1.0.0-rc.2]: https://github.com/cluion/moduark/compare/v1.0.0-rc.1...v1.0.0-rc.2
[1.0.0-rc.1]: https://github.com/cluion/moduark/compare/v0.5.0-beta.1...v1.0.0-rc.1
[0.5.0-beta.1]: https://github.com/cluion/moduark/compare/v0.4.0-beta.1...v0.5.0-beta.1
[0.4.0-beta.1]: https://github.com/cluion/moduark/compare/v0.3.0-beta.5...v0.4.0-beta.1
[0.3.0-beta.5]: https://github.com/cluion/moduark/compare/v0.3.0-beta.4...v0.3.0-beta.5
[0.3.0-beta.4]: https://github.com/cluion/moduark/compare/v0.3.0-beta.3...v0.3.0-beta.4
[0.3.0-beta.3]: https://github.com/cluion/moduark/compare/v0.3.0-beta.2...v0.3.0-beta.3
[0.3.0-beta.2]: https://github.com/cluion/moduark/compare/v0.3.0-beta.1...v0.3.0-beta.2
[0.3.0-beta.1]: https://github.com/cluion/moduark/compare/v0.2.0-beta.3...v0.3.0-beta.1
[0.2.0-beta.3]: https://github.com/cluion/moduark/compare/v0.2.0-beta.2...v0.2.0-beta.3
[0.2.0-beta.2]: https://github.com/cluion/moduark/compare/v0.2.0-beta.1...v0.2.0-beta.2
[0.2.0-beta.1]: https://github.com/cluion/moduark/compare/v0.1.0-beta.2...v0.2.0-beta.1
[0.1.0-beta.2]: https://github.com/cluion/moduark/compare/v0.1.0-beta.1...v0.1.0-beta.2
[0.1.0-beta.1]: https://github.com/cluion/moduark/releases/tag/v0.1.0-beta.1
