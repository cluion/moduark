# ADR-0060: Active Module Set Diagnostic Parity

- Status: Accepted
- Date: 2026-08-29

## Context

ADR-0059 made activation state writable and invalidated every cache that can
describe a previous Module graph. The remaining risk was semantic drift after
the next boot: registry, diagnostics, analysis, graphs, cached metadata,
providers, Capability composition, and resources could independently expose a
different Module set even when each component passed its focused tests.

Disabled Modules also need a narrow diagnostic identity without being loaded.
Treating that identity as runtime membership would reintroduce the same drift.

## Decision

- `ModuleRegistry` remains the canonical active-only runtime inventory.
- Aggregate list, graph, analysis, cache, provider, Capability, and resource
  surfaces derive exclusively from that registry.
- `moduark:doctor <module>` and `moduark:resources <module>` may return a
  known-disabled placeholder. It contains the requested name, `disabled`
  state, and zero resources, but no class, path, dependencies, providers, or
  other runtime metadata.
- `moduark:inspect <module>` inspects runtime architecture and therefore
  requires an active Module; a disabled Module is unavailable to inspection.
- Aggregate surfaces are already active-only, so LC0-D does not add a redundant
  `--enabled-only` option or change an existing command schema.
- Cold discovery and Module-cached boot must expose the same active set and the
  cache must retain the matching activation fingerprint.

## Permanent Fixture

`ActiveModuleSetParityTest` disables the resource-bearing `Order` workbench
Module while leaving `User` and `Workbench` active. On both cold and cached
boot, it binds the exact same expected set across:

- registry and ordered lifecycle descriptors;
- list, inspect, doctor, and resources diagnostics;
- Module, Capability, and combined graphs;
- source-analysis ownership and the Module cache manifest;
- Laravel provider registration; and
- runtime resource manifests.

The fixture also proves that the disabled Module retains only its targeted
diagnostic placeholder and contributes no provider or resource runtime state.

## Consequences

Future diagnostic or cache features have one regression gate for active-set
parity. A separate all-known inventory or richer disabled inspection would need
a new versioned contract and cannot be inferred from runtime registry data.
