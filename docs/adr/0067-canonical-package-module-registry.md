# ADR-0067: Canonical Composer Package Module Registry

- Status: Accepted
- Date: 2026-08-29

## Context

ADR-0066 introduced a deterministic catalog of Composer-installed Module
descriptors, but the catalog was not part of the host application's canonical
active Module set. An exported package could therefore run through its portable
provider while remaining absent from Moduark registry, analysis, graphs, cache,
activation planning, Capabilities, and resource diagnostics.

Those surfaces must not infer package Modules independently. They need one
registry assembled before lifecycle registration, and Laravel package-provider
order must not cause the same Module provider or resource to run twice.

## Decision

- `CanonicalModuleRegistryBuilder` merges the application or nwidart active
  registry with every descriptor in `PackageModuleCatalog` before metadata,
  lifecycle, Capability, analysis, graph, or resource work begins.
- The existing `ModuleRegistry` duplicate-name and duplicate-class rules apply
  across application, nwidart, and Composer package origins.
- Composer-installed package Modules are active for as long as the package is
  installed. `moduark:enable` and `moduark:disable` reject package targets and
  direct the operator to Composer; local activation plans still compile package
  Modules so dependency and Capability blockers see the complete proposed set.
- Module cache schema version `6` stores the package-catalog fingerprint. A
  changed installed-package inventory bypasses the old cache and rebuilds the
  canonical registry and resource manifest.
- A descriptor-aware `PortableModuleServiceProvider` delegates registration and
  boot to the host canonical runtime. Packages without `extra.moduark`
  descriptors retain the LC1-E one-Module fallback for compatibility.

## Consequences

`moduark:list`, analysis, module and combined graphs, cache, lifecycle ordering,
providers, Capabilities, and resources now observe the same active Module set.
Package install or removal is the activation boundary for package Modules, and
cache validity follows that boundary. Provider order is no longer allowed to
produce duplicate package runtime effects.

The package descriptor remains the source of package Module identity. This
decision does not add Composer dependency inference, remote package publishing,
or a mutable host-side activation state for installed package Modules.
