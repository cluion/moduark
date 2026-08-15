# ADR-0020: Capability Contracts Architecture Rule

- Status: Accepted for the fifth `0.2` implementation slice
- Date: 2026-08-15

## Context

ADRs 0016 through 0019 established typed Capability metadata, deterministic
provider resolution, lifecycle preflight, and Laravel container composition.
The `capability_contracts` Level 2 rule ID was still unavailable, so
`module:check` could not express the same complete-graph requirements as
architecture violations.

The rule must preserve runtime semantics without invoking Module methods again,
parsing source code, mutating the container, or weakening strict package boot.

## Decision

- `CapabilityContractsRule` reads only compiled `ModuleDescriptor` values from
  `AnalysisContext`. Enabling it does not require a `SourceIndex`.
- Resolution remains demand-driven. Every required Capability must have exactly
  one provider, while multiple providers for an unused Capability remain valid.
- A consumer Port may appear in only one Module requirement across the complete
  graph, matching the runtime resolver's protection against Laravel's
  last-binding-wins behavior.
- The rule aggregates every detected contract violation and sorts its output
  independently of discovery order. It emits:
  - `MOD-CAPABILITY-001` for a missing provider;
  - `MOD-CAPABILITY-002` for ambiguous providers;
  - `MOD-CAPABILITY-003` for a Port shared by consumer Modules.
- `ModuleMetadataCompiler` remains responsible for validating Capability
  identities, Port interfaces, Adapter implementations, and duplicates inside
  one Module. Those failures occur before rule execution and are not duplicated
  as architecture violations.
- Runtime `CapabilityResolver` remains fail-fast because package boot cannot
  safely continue with an invalid binding plan. Since Laravel bootstraps service
  providers before invoking Artisan commands, a runtime graph failure may be
  rendered by Laravel before `module:check` can render the aggregated rule
  result. The rule still provides the architecture engine and valid-graph CLI
  with one stable enforcement contract.
- `adapter_boundaries`, Capability graph output, and provider selection remain
  outside this slice. The normal Level 2 preset is still incomplete and exits 2.

## Acceptance evidence

- `CapabilityContractsRuleTest` covers a valid three-Module graph, missing and
  ambiguous providers, shared Ports, deterministic Module ordering, and unused
  multiple providers.
- `RuleRunnerTest` proves Level 2 now evaluates seven rules and reports only
  `adapter_boundaries` as unavailable.
- `ArchitectureCheckerTest` proves enabling `capability_contracts` alone does
  not parse Module source.
- `ModuleCheckCommandTest` proves the production Laravel registration evaluates
  the new rule when `--level=2` is selected.
- The complete suite and PHPStan level max remain the local acceptance gate;
  Laravel 12/13 CI remains the compatibility authority after push.

## Consequences

- Tooling that invokes the architecture engine directly receives complete,
  stable Capability contract violations without Laravel lifecycle side effects.
- The Artisan command verifies valid Capability graphs through the same rule
  registration, while strict bootstrap failures remain intentionally earlier.
- Level 2 has one unavailable preset rule left: `adapter_boundaries`.
