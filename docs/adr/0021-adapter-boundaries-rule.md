# ADR-0021: Adapter Boundaries Architecture Rule

- Status: Accepted for the sixth `0.2` implementation slice
- Date: 2026-08-15

## Context

ADRs 0015 through 0020 established the Level 2 design, typed Capability
metadata, deterministic provider resolution, lifecycle preflight, runtime
composition, and descriptor-level contract enforcement. The remaining
`adapter_boundaries` rule needed to turn the executable Port and Adapter PoC
into source-backed architecture enforcement without duplicating provider graph
errors.

Level 2 preserves both direct Module dependencies and Capability edges. The
Module entry must therefore be able to name a declared provider Module, while
ordinary consumer code must not use that metadata exception to bypass its Port
and Adapter.

## Decision

- `AdapterBoundariesRule` reuses the existing `SourceIndex`; enabling the rule
  causes source analysis even when the Level 1 source rules are disabled.
- Every requirement's Port must be owned by the consumer below exact,
  case-sensitive `Ports/`. Violations use `MOD-ADAPTER-001`.
- Every requirement's Adapter must be owned by the consumer below exact,
  case-sensitive `Adapters/{Provider}/`. Violations use `MOD-ADAPTER-002`.
- A Module entry may reference the target Module class for direct
  `dependencies()` metadata. Every other observed cross-Module class-like
  reference must originate from a declared Capability Adapter and target that
  Adapter's selected provider. Core bypasses use `MOD-ADAPTER-003`.
- A declared Adapter that crosses into an unrelated Module uses
  `MOD-ADAPTER-004`.
- Consumer core code that references its concrete declared Adapter instead of
  the local Port uses `MOD-ADAPTER-005`. The Module entry and the Adapter's own
  implementation file are excluded from this leakage check.
- The generic cross-Module check also rejects provider reverse references into
  consumer Ports, Adapters, or use cases.
- Missing and ambiguous providers remain `capability_contracts` concerns. A
  structurally valid declared Adapter defers provider-specific reference checks
  when no unique provider can be selected, preventing cascading Adapter
  diagnostics for the same graph failure.
- The rule does not require an Adapter to contain a provider reference. An
  Adapter may legitimately implement a Port using framework or external
  infrastructure that is not owned by another discovered Module.
- Capability graph output, explicit multi-provider selection, Level 3 rules,
  and runtime behavior remain outside this slice.

## Acceptance evidence

- `AdapterBoundariesRuleTest` covers valid metadata references, exact Port and
  Adapter placement, core bypasses, unrelated provider access, concrete Adapter
  leakage, provider reverse references, and deterministic diagnostics.
- `LevelTwoCapabilitySpikeTest` runs the production rule against the real
  three-Module PoC and proves both consumers pass through their own Adapters.
- `ArchitectureCheckerTest` proves the rule independently triggers source
  indexing.
- `RuleRunnerTest` proves all eight Level 2 rules have implementations.
- `ModuleCheckCommandTest` proves production Laravel registration produces a
  complete Level 2 pass for the workbench application.
- The complete suite, PHPStan level max, and clean Laravel 12/13 installation
  matrix remain the local acceptance gate before push.

## Consequences

- The normal Level 2 preset is complete in `v0.2.0-beta.1`; a valid architecture
  evaluates eight rules and exits 0.
- Port and Adapter directory names are architecture contract, not descriptive
  conventions. Case or provider-directory mismatches are blocking violations.
- Capability graph observability remains useful for inspection but is not an
  enforcement prerequisite and does not keep Level 2 incomplete.
- This contract is delivered as part of `v0.2.0-beta.1`.
