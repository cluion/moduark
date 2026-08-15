# ADR-0003: Module Metadata and Lifecycle Ordering

- Status: Accepted
- Date: 2026-08-15

## Context

The plan requires typed Module metadata and dependency-ordered provider
lifecycle calls. The public metadata carrier also has to remain friendly to
static analysis, Laravel configuration caching, and empty Modules that need no
customization.

Laravel registers providers before the application boots them. Moduark must
therefore resolve and validate the complete Module dependency graph before it
registers any Module-owned provider.

## Decision

- Module entry classes expose metadata through overridable typed methods.
  Empty Modules inherit empty `dependencies()` and `providers()` results.
- A compiler validates all class references and produces immutable
  `ModuleDescriptor` values.
- Cache payloads contain only class-string lists and arrays. Descriptor objects
  are reconstructed after cache loading.
- Enabled Modules are ordered with a stable dependency-first topological sort.
- The complete graph, including missing dependencies and cycles, is validated
  before the first provider is registered.
- Providers are registered in Module dependency order. Laravel then invokes
  their `boot()` methods in the same registration order.

## Candidate evaluation

| Candidate | Result |
|---|---|
| Typed methods | Selected: no reflection, inherited empty defaults, direct extension points, and PHPStan understands the return contracts. |
| Typed properties | Rejected as the default: needs an accessor bridge and repeated property PHPDoc when subclasses replace array values. |
| PHP Attributes | Rejected as the default: needs reflection and moves nested metadata into constructor arguments without improving the verified static-analysis result. |

All three candidates can express the same dependency and pass the level-max
PHPStan sample. Typed methods have the smallest runtime and inheritance cost and
match Laravel's provider-style extension points.

## Acceptance evidence

- `MetadataCandidateTest` expresses the same dependency with method, property,
  and Attribute samples.
- `ModuleLifecycleRegistrarTest` proves the dependency-first `User -> Order ->
  Payment` register and boot order using a real Laravel application.
- The same test proves `Alpha -> Beta -> Gamma -> Alpha` fails before any
  provider lifecycle side effect.
- Descriptor payloads survive serialization, Laravel `config:cache`, and
  Laravel `optimize` while containing only scalar values.
- The same suite passes on Laravel 12.66 with Testbench 10.11 and Laravel 13.25
  with Testbench 11.2.
- The package test suite and all production, test, and workbench code pass
  PHPStan level max without a baseline.

## Consequences

- Adding metadata is backward-compatible through new default methods on
  `Module`, but changing an accepted method contract is a public API change.
- Metadata compilation may use reflection internally in future features, but
  reflection is not required to read the accepted core metadata.
- Resource discovery, generators, capabilities, exports, and table ownership
  remain separate decisions and are not implied by this ADR.
