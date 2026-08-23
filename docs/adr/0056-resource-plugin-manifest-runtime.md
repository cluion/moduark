# ADR-0056: Resource Plugin and Serializable Runtime Manifest

- Status: Accepted
- Date: 2026-08-23

## Context

Moduark 1.1 completed Module-owned generation, but runtime resource loading was
still a fixed set of conventions. That model could not safely add config,
custom routes, recursive commands, listeners, components, assets, tests, or
third-party resource types without teaching one provider every behavior. It
also allowed cold discovery and cached boot to become separate runtime truths.

The 1.2 runtime must remain additive for existing 1.x applications. Existing
route, view, translation, migration, command, and provider conventions must
continue to work, while every new resource class requires explicit Module
metadata. nwidart enabled state must remain authoritative when its layout is
adopted.

## Decision

- Add overridable, parameterless `Module::resources(): array`. Its result is
  pure scalar, null, or nested-array metadata; objects and closures fail
  manifest construction.
- Split each `ResourcePlugin` into a pure discovery side and a phase-specific
  runtime handler. Third-party providers register plugins through
  `ResourcePluginRegistration::register()` before application booting.
- Compile all enabled, dependency-ordered Modules into `ResourceManifest`
  schema version `1`. Each `ResourceDescriptor` records Module class, plugin,
  stable identity, optional source path and runtime namespace, normalized
  attributes, and an optional collision key.
- Embed that manifest in the rebuildable Module cache. Cold discovery and
  cached boot bind the same `ResourceManifest`; handlers never rescan the
  filesystem during cached boot.
- Keep the existing six conventions active. Config, custom routes, recursive
  commands, factories, seeders, policies, events/listeners, Blade components,
  generic assets, tests, and extension metadata are opt-in through
  `Module::resources()`.
- When Moduark resolves the same Module root as nwidart, nwidart owns its
  conventional route, view, translation, migration, and direct-command runtime
  registration. Moduark still exposes those descriptors for diagnostics and
  cache identity, but only handles resources explicitly declared through
  `Module::resources()`. Path-equivalence detection survives Laravel config
  caching and keeps the nwidart active set authoritative.
- Register config handlers during the provider register phase and other
  handlers during boot. A registration-state guard makes repeated provider boot
  idempotent.
- Expose `ModuleAssetManifest` schema version `1` for deterministic generic
  Vite inputs. Public assets use the `moduark-assets` publish group; the core
  does not select a JavaScript framework.
- Add `moduark:resources` and `moduark:doctor`, plus forward-only
  `moduark:migrate`, `moduark:seed`, and `moduark:test`. Every operation resolves
  the selected active Module and paths from the canonical registry and resource
  manifest. Rollback, reset, refresh, and fresh remain outside 1.2.
- Use exit `0` for success, `1` for diagnosed collisions or test failure/no
  tests, and `2` for invalid input or tool failure. All five commands expose
  schema-versioned JSON.

## Built-in Metadata

The accepted built-in keys are `routes`, `config`, `commands`, `factories`,
`seeders`, `policies`, `listeners`, `components`, `assets`, `tests`, and
`extensions`. Events are derived as their own descriptors from listener-map
keys. Existing views, translations, migrations, providers, and top-level
commands remain convention-backed descriptors.

Factories and tests are discoverable paths, providers remain activated by the
existing dependency-ordered lifecycle, and extension descriptors are metadata
for downstream integrations. These resources intentionally have no duplicate
runtime side effect.

## Acceptance

- A permanent workbench Module exercises every built-in plugin, including
  config and route caches, recursive console discovery, event dispatch, Blade
  rendering, policy lookup, asset input/publication, seeding, migration, and an
  actual Module-owned PHPUnit run.
- Cold and cached manifests serialize identically and contain the same enabled,
  dependency-ordered Module class set.
- Laravel config cache, route cache, and repeated provider boot retain identical
  behavior.
- A third-party package-style provider can register a custom plugin without
  modifying Moduark.
- Duplicate plugin/resource identities, unsafe paths, non-serializable data,
  unknown plugins, missing sources, and cross-Module collisions have focused
  failure or diagnostic coverage.
- Fresh Laravel 12 and 13 installations, plus matching nwidart 12 and 13
  interoperability fixtures, execute the public command and cache contracts;
  the latter also prove there is no duplicate conventional route registration
  before or after `optimize`.

## Consequences

Runtime resources, operations, diagnostics, cache, registry, architecture
analysis, graphs, providers, Capabilities, and assets now share one active
Module set. Cache schema changes remain internal and rebuildable. Dynamic
enable/disable mutation, package export, additional layout adapters, and
destructive migration operations remain 1.3 work.
