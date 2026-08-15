# ADR-0016: Capability Metadata Contract

- Status: Accepted for the first `0.2` implementation slice
- Date: 2026-08-15

## Context

ADR-0015 selected a provider-neutral typed Capability identity, consumer-owned
Ports, and consumer-side Adapters. The executable spike proved Laravel container
wiring and failure behavior without exposing experimental package API.

The first production slice needs to preserve existing Level 0 and Level 1
Modules while establishing validated, cache-safe metadata for later provider
resolution, graph, rule, and runtime slices.

## Decision

- `Cluion\Moduark\Capability` is a marker interface. An application defines a
  distinct interface extending it for each Capability identity. The base marker
  itself is not a valid identity.
- `Module::provides()` returns Capability class strings and inherits an empty
  default, so existing Modules require no change.
- `Module::requires()` returns `CapabilityRequirement` values and also inherits
  an empty default.
- Each requirement contains one Capability identity, one consumer-owned Port
  interface, and one instantiable Adapter implementing that Port.
- A Module may require a Capability once and may bind a consumer Port once.
  Duplicate requirements fail during metadata compilation instead of relying on
  Laravel's last-binding-wins behavior.
- `ModuleMetadataCompiler` is the runtime validation boundary. It validates
  dynamic metadata even when an application does not run PHPStan.
- `ModuleDescriptor` carries typed requirements and provided Capabilities. Its
  cache payload contains only class strings and arrays; payloads created before
  these fields existed remain readable with empty defaults.
- This slice does not resolve providers, mutate Laravel container bindings,
  implement `capability_contracts` or `adapter_boundaries`, or add Capability
  graph output. Level 2 therefore remains incomplete and still exits 2.

## Acceptance evidence

- `CapabilityMetadataTest` covers empty Module compatibility, legacy descriptor
  payloads, typed round trips, scalar-only serialization, invalid base identity,
  duplicate provided and required Capabilities, duplicate Ports, and non-interface
  Ports.
- `LevelTwoCapabilitySpikeTest` now consumes the production `Capability`,
  `CapabilityRequirement`, `Module::requires()`, `Module::provides()`, and
  compiler validation contracts while keeping provider resolution and wiring
  test-only.
- The complete suite and PHPStan level max remain the local acceptance gate;
  Laravel 12/13 CI remains the compatibility authority after push.

## Consequences

- Capability metadata is now a pre-release public API and requires changelog or
  migration notes if its shape changes.
- The next slice can build provider resolution entirely from compiled
  `ModuleDescriptor` values instead of invoking Module methods again.
- Provider resolution must complete before Module provider registration or any
  Port binding so missing and ambiguous graphs remain side-effect free.
- `module:list`, Capability graph output, Level 2 rules, and runtime composition
  remain separate independently testable slices.
