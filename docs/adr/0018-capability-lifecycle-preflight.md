# ADR-0018: Capability Lifecycle Preflight

- Status: Accepted for the third `0.2` implementation slice
- Date: 2026-08-15

## Context

ADR-0017 introduced deterministic Capability provider resolution from compiled
`ModuleDescriptor` values, but the package lifecycle did not invoke it. An
application could therefore register Module ServiceProviders even when a
Capability provider was missing or ambiguous.

The lifecycle must fail before the first provider side effect while preserving
the existing direct-dependency validation, dependency-first registration order,
and `ModuleLifecycleRegistrar::registerProviders()` return contract.

## Decision

- `ModuleLifecycleRegistrar` compiles all Module metadata, validates and orders
  direct dependencies, then resolves the complete Capability plan before
  entering the ServiceProvider registration loop.
- Missing and ambiguous Capability providers therefore fail after metadata and
  direct graph validation but before any Module-owned provider is invoked.
- Successful preflight does not change the ordered descriptor return value or
  provider registration order.
- `CapabilityResolver` is registered as a package singleton and injected by
  Laravel. The registrar constructor accepts it as an optional fourth argument,
  preserving existing three-argument manual construction during the pre-release
  transition.
- The resolved plan is intentionally not stored globally by this slice. The
  preflight proves validity; a later composition slice will decide the ownership
  and timing of container bindings.
- This slice does not bind consumer Ports, implement Level 2 rules, change
  `module:list`, or add Capability graph output. At this slice's acceptance,
  Level 2 remained incomplete and exited 2.

## Acceptance evidence

- `ModuleLifecycleRegistrarTest` proves successful Capability preflight
  preserves dependency-first registration. Port binding was deliberately absent
  from this slice and is now governed by
  [ADR-0019](0019-capability-runtime-composition.md).
- The same test proves missing and ambiguous providers fail before any
  ServiceProvider `register()` event.
- `PackageBaselineTest` proves Laravel registers the production
  `CapabilityResolver` service alongside the lifecycle registrar.
- The complete suite and PHPStan level max remain the local acceptance gate;
  Laravel 12/13 CI remains the compatibility authority after push.

## Consequences

- This slice made package boot enforce a resolvable Capability provider graph
  whenever Modules declare `requires()` metadata. ADR-0019 subsequently added
  Port wiring, and [ADR-0020](0020-capability-contracts-rule.md) subsequently
  added the metadata-only contract rule. [ADR-0021](0021-adapter-boundaries-rule.md)
  subsequently added source-backed Adapter boundary enforcement.
- Direct dependency failures and cycles retain precedence because ordering is
  completed before Capability resolution; every failure remains side-effect
  free.
- Runtime composition and registrar-local plan ownership are defined by
  [ADR-0019](0019-capability-runtime-composition.md) without reopening provider
  selection semantics.
