# ADR-0019: Capability Runtime Composition

- Status: Accepted for the fourth `0.2` implementation slice
- Date: 2026-08-15

## Context

ADR-0018 made Capability resolution a lifecycle preflight, but discarded the
valid `CapabilityPlan` and left each consumer Port unbound. The runtime now needs
to apply that plan without exposing global mutable composition state or allowing
Laravel's last-binding-wins behavior to hide conflicting declarations.

Module ServiceProviders must register provider-owned Public API services before
consumer Adapters can use them. Conversely, a failure in any provider must not
leave Capability Port bindings that imply composition completed successfully.

## Decision

- `ModuleLifecycleRegistrar` temporarily owns the immutable `CapabilityPlan`
  returned by preflight. The plan is not stored in Laravel's container or any
  package-global registry.
- The lifecycle compiles, orders, and resolves the complete Module graph before
  invoking a ServiceProvider. It then registers every Module ServiceProvider and
  applies Capability bindings only after the complete provider loop succeeds.
- Each binding uses Laravel's transient `bind(Port, Adapter)` behavior. The
  declared Capability requirement is authoritative and therefore replaces an
  earlier binding for that Port made by a Module ServiceProvider.
- A Port class may appear in only one consumer requirement across the complete
  resolved graph. `CapabilityResolver` rejects duplicate Ports deterministically
  during preflight, before any provider side effect, rather than silently
  selecting whichever Laravel binding ran last.
- If a ServiceProvider throws, no Capability Port bindings from the plan are
  applied. Already completed provider side effects are not rolled back; this is
  sequencing, not a transaction over arbitrary ServiceProvider behavior.
- This slice does not add global plan storage, provider selection, Level 2
  structural rules, `module:list` data, or Capability graph output.

## Acceptance evidence

- `CapabilityResolverTest` proves one Port cannot be declared by multiple
  consumer Modules and that the diagnostic is stable regardless of input order.
- `ModuleLifecycleRegistrarTest` proves successful registration binds and
  resolves the Adapter, while missing, ambiguous, and duplicate declarations
  remain preflight failures.
- A provider-failure test proves earlier provider events may have occurred but
  no Capability Port binding is applied when the provider loop does not finish.
- `LevelTwoCapabilitySpikeTest` resolves both consumer-owned Ports through the
  production registrar without test-only wiring.
- The complete suite, PHPStan level max, and clean Laravel 12/13 installation
  matrix remain the local acceptance gate before push.

## Consequences

- Applications declaring valid Capability metadata receive working Laravel
  container composition during normal package boot.
- Provider-owned services exist before consumer Adapters are resolved, while
  Capability bindings remain absent on provider-registration failure.
- A shared Port interface cannot represent consumer-specific Adapter choices in
  the same container. Consumers that need different Adapters must own distinct
  Port types.
- Level 2 remains incomplete until its structural analysis rules and graph
  observability are implemented.
