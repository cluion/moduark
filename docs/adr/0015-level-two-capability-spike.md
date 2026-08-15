# ADR-0015: Level Two Capability and Adapter Spike

- Status: Accepted for the `0.2` design spike
- Date: 2026-08-15

## Context

Level 2 adds dependency inversion to the Level 1 Module boundary. A consumer's
core should depend on a Port it owns, an Adapter should translate that Port to a
provider Public API, and Module metadata should expose a Capability graph that
can be validated before Laravel mutates the service container.

The unresolved design risk was Capability identity. Using the consumer Port as
identity forces every provider to reference a consumer-owned interface. Using
the provider Public API as identity avoids that reverse dependency for one
provider, but makes alternative providers depend on the original provider's
contract. String identifiers avoid both source dependencies but lose typed
refactoring and are too easy to duplicate silently.

## Decision

- A Capability has its own typed identity, separate from both the consumer Port
  and provider Public API. The PoC uses a marker interface in an application
  composition namespace; it is architecture vocabulary, not a service-locator
  key and not a replacement for a behavioral interface.
- A provider declares `provides()` as Capability class strings. It does not
  reference any consumer Module, Port, Adapter, or use case.
- A consumer requirement maps one Capability to a consumer-owned Port and a
  concrete Adapter. The mapping is represented by a typed value before it is
  compiled to scalar class-string metadata.
- Consumer core code references only its local Port. The Adapter lives below
  `Adapters/{Provider}/`, implements that Port, and may reference the provider's
  Level 1 Public API.
- Direct Module dependency and Capability edges both remain observable. The
  direct edge authorizes the Adapter's provider reference; the Capability edge
  describes `requires` and `provides`. Future graph output must distinguish an
  Adapter edge from a core dependency edge.
- The complete Capability graph is resolved before any Port binding. A missing
  provider or more than one provider is a deterministic error, and neither case
  leaves partial container bindings.
- After Module-owned providers register their Public API implementations,
  composition wiring binds each consumer Port to its Adapter through Laravel's
  container.
- Provider selection is intentionally not part of this slice. Multiple
  providers remain an ambiguity until a deterministic, config-cache-safe
  selection policy is accepted separately.

Provider resolution and composition classes under `Tests\Fixtures\LevelTwo`
remain executable design evidence rather than published package API. The
Capability metadata portion was promoted to package API by
[ADR-0016](0016-capability-metadata-contract.md).

## Candidate evaluation

| Candidate | Result |
|---|---|
| Consumer Port class is the Capability | Rejected: providers acquire a reverse source dependency on a consumer. |
| Provider Public API is the Capability | Rejected: competing providers depend on the original provider's contract identity. |
| String Capability name | Rejected: not typed or safely refactorable. |
| Separate typed Capability identity | Selected: provider-neutral, refactorable, and compatible with independent consumer Ports. |

## Acceptance evidence

- `LevelTwoCapabilitySpikeTest` models `User` as provider and `Order` plus
  `Checkout` as consumers of one `UserLookup` Capability.
- Both consumers own distinct Ports and Adapters, and Laravel resolves their use
  cases through the provider Public API.
- Source-index assertions prove `User` has no consumer reference and only files
  below each consumer's `Adapters/` directory cross into the provider. Consumer
  `Actions/` and `Ports/` remain provider-independent.
- Missing and ambiguous providers fail deterministically before any Port binding.
- An Adapter that does not implement its declared consumer Port is rejected.
- The resolved binding plan serializes to a scalar-only payload suitable for a
  future configuration or descriptor cache.

## Consequences

- Capability metadata can be added without changing the accepted Level 1 Public
  API convention.
- Capability compilation must happen before provider registration or any
  composition binding so graph failures remain side-effect free.
- The production resolver needs stable diagnostics and evidence for
  `provides()` and `requires()` declarations.
- Combined graph and cycle analysis must preserve direct, Capability, and
  Adapter edge kinds rather than flattening them into one dependency.
- A dedicated decision is still required for explicit multi-provider selection.
