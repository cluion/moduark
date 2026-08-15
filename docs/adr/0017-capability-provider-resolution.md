# ADR-0017: Capability Provider Resolution

- Status: Accepted for the second `0.2` implementation slice
- Date: 2026-08-15

## Context

ADR-0016 promoted typed Capability requirements and providers into validated,
cache-safe `ModuleDescriptor` metadata. The next slice must select exactly one
provider for each consumer requirement before any Laravel lifecycle side effect,
without invoking Module methods again.

The executable spike already proved the desired missing-provider,
ambiguous-provider, ordering, and serialization behavior. Its resolver also
performed Laravel container wiring, which is outside this production slice.

## Decision

- `Cluion\Moduark\Capabilities\CapabilityResolver` consumes only a complete list
  of compiled `ModuleDescriptor` values. It does not instantiate Modules,
  register ServiceProviders, or access the Laravel container.
- Resolution is demand-driven. A Capability must have exactly one provider when
  a Module requires it. Multiple providers for a Capability with no consumer do
  not require a selection and therefore do not fail resolution.
- Provider class strings are sorted before ambiguity diagnostics. Consumer
  requirements are sorted by consumer, Capability, and Port before resolution,
  so output and the first reported failure do not depend on discovery order.
- A missing provider or more than one matching provider throws
  `CapabilityResolutionFailed` with the Capability and consumer in the message.
- A successful result is a `CapabilityPlan` containing `CapabilityBinding`
  values. Each binding preserves the Capability, selected provider Module,
  consumer Module, consumer-owned Port, and Adapter.
- Binding and plan payloads round-trip through scalar-only arrays suitable for
  configuration cache storage.
- The resolver trusts the compiler-validated contents of each descriptor. Module
  set validation and topological ordering remain the responsibility of the
  existing metadata and lifecycle components.
- This slice does not integrate resolution into `ModuleLifecycleRegistrar`, bind
  Ports in Laravel's container, implement Level 2 rules, or add Capability graph
  output. Level 2 therefore remains incomplete and still exits 2.

## Acceptance evidence

- `CapabilityResolverTest` covers deterministic success output, deterministic
  missing-provider failure, sorted ambiguous-provider diagnostics, unused
  providers, an empty graph, and scalar cache round trips.
- `LevelTwoCapabilitySpikeTest` now uses the production resolver and plan while
  retaining container wiring as a test-only composition step.
- The complete suite and PHPStan level max remain the local acceptance gate;
  Laravel 12/13 CI remains the compatibility authority after push.

## Consequences

- Later runtime composition can validate the complete Capability plan before
  registering a Module ServiceProvider or mutating a Port binding.
- Explicit multi-provider selection remains a separate decision; this resolver
  intentionally rejects ambiguity for every consumed Capability.
- `module:list`, Capability graph output, Level 2 rules, lifecycle integration,
  and container wiring remain independently testable slices.
