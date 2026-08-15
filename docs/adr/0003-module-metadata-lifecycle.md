# ADR-0003: Module Metadata and Lifecycle Ordering

- Status: Proposed; blocked on the next Phase 0 PoC
- Date: 2026-08-15

## Context

The plan requires typed Module metadata and dependency-ordered provider
lifecycle calls. Choosing methods, properties, or PHP Attributes without a
representative Module PoC would prematurely freeze the public API.

Laravel registers all providers before the application boots them. Moduark
therefore has to resolve and validate the Module dependency graph before it
registers Module-owned providers.

## Proposed direction

- Keep metadata on a minimal Module entry class and compile it into a
  serializable descriptor.
- Prefer overridable typed methods unless a three-Module PoC demonstrates that
  properties or Attributes materially improve static analysis and ergonomics.
- Topologically order enabled Modules before provider registration.
- Reject dependency cycles before invoking any Module provider.
- Preserve the same order for `register()` and `boot()` unless the lifecycle
  PoC finds a documented Laravel constraint requiring a different order.

## Acceptance evidence still required

- Method, property, and Attribute samples checked with the chosen static
  analysis baseline.
- Dependency and provider ordering fixtures with at least three Modules.
- Cycle failure that proves no partial Module lifecycle has executed.
- Config and optimize-cache round trips using only serialized descriptors.

No production metadata API is accepted by this ADR yet.
