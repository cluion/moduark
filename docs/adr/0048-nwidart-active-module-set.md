# ADR-0048: nwidart Active Module Set

- Status: Accepted for the next `1.0.x` patch
- Date: 2026-08-23

## Context

[ADR-0047](0047-nwidart-interoperability.md) made Moduark follow nwidart's
configured Module root when `moduark.path` is absent or `null`. Discovery still
treated every matching entry file below that root as enabled, however.
Disabling a Module through nwidart therefore removed its nwidart providers and
routes while Moduark continued to expose it through the registry, analysis,
graphs, metadata cache, lifecycle providers, Capability bindings, and native
Module resources.

Filtering separately in each consumer would allow those views to diverge again.
The enabled state must instead become an input to the single registry boundary.
Laravel package discovery can register Moduark before nwidart, so the solution
also cannot assume nwidart's repository binding already exists during
`ModuarkServiceProvider::register()`.

## Decision

- When `moduark.path` is absent or `null` and Moduark follows nwidart's Module
  root, resolve one deterministic active Module set before discovery.
- For nwidart's standard file activator, use the configured statuses file and
  its existing semantics: only a Module whose stored status is exactly `true`
  is active; a missing file or missing name is inactive. This works before the
  nwidart service provider has registered its repository.
- When a custom nwidart activator is configured, instantiate the configured
  activator using nwidart's constructor convention and query `hasStatus()` for
  each Module directory.
- Filter inactive Module directories before parsing or autoloading their
  Moduark entry files. The resulting `ModuleRegistry` remains the only Module
  source consumed by listing, inspection, analysis, graphs, lifecycle
  providers, Capability resolution, and native Module resources.
- Keep an explicit non-empty `moduark.path` independent. It remains a
  standalone Moduark root and does not inherit nwidart activation state merely
  because both paths happen to point to the same directory.
- Add an active-set fingerprint to the Module metadata manifest and advance its
  schema to version `4`. A manifest is used only when its schema, Module root,
  and active-set fingerprint all match. Enabling or disabling a Module therefore
  bypasses stale metadata without requiring a preceding `moduark:clear`.

## Acceptance

- Unit coverage proves deterministic active-set fingerprints, file-activator
  semantics, custom-activator filtering, pre-inspection filtering, and cache
  invalidation when the active set changes.
- The permanent interoperability matrix runs identical assertions on Laravel
  12 with nwidart 12 and Laravel 13 with nwidart 13. Each fixture caches enabled
  `User` and `Order` Modules, disables `Order`, and proves that
  registry/list/inspection, analysis, graphs, providers, Capability bindings,
  routes, and cache all use only `User`.
- The fixture then re-enables `Order` without first clearing the one-Module
  metadata cache and proves that the complete runtime surface and two-Module
  cache are restored.
- Initial Composer package discovery succeeds even when nwidart's default
  configuration has not yet been merged.

## Consequences

- nwidart enable/disable becomes authoritative only for Moduark's automatic
  nwidart-root integration.
- A malformed statuses file, invalid custom activator, or invalid activator
  result fails explicitly instead of silently enabling every Module.
- Schema `1` through `3` Module metadata caches are bypassed and rebuilt on the
  next cache operation.
- No nwidart package is added to Moduark's required Composer dependencies, and
  no new public Moduark configuration or command is introduced.
