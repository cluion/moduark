# ADR-0009: Direct Module Dependency Graph

- Status: Accepted for Slice 6B
- Date: 2026-08-15

## Context

Architecture diagnostics and graph output need one deterministic representation
of declared Module dependencies. Runtime ordering cannot serve as that model: it
must reject missing targets and cycles before providers are registered, while a
diagnostic graph must preserve and render those invalid states.

Capability ownership and source-level imports do not yet have verified typed
indexes. Including inferred edges now would mix trusted metadata with guesses.

## Decision

- A graph builder consumes the validated discovery registry and typed Module
  descriptors. It does not scan PHP source or invoke provider lifecycle hooks.
- Discovered Modules are nodes, including isolated Modules. A declared target
  that was not discovered becomes a marked missing node instead of being
  discarded.
- Every edge retains its declaring `dependencies()` method as evidence.
- Nodes and edges have a stable name-first ordering independent of filesystem
  discovery order. Cycles remain valid diagnostic graph input and are rendered
  without topological sorting.
- `module:graph` defaults to a compact text adjacency view. The
  `--format=mermaid` option emits a standalone Mermaid flowchart from the same
  graph model.
- An optional Module name selects that Module plus direct incoming and outgoing
  neighbors. It does not imply a transitive dependency closure.
- This slice exposes only the declared Module dependency view. Capability,
  combined, JSON, and source-import views wait for their typed owner indexes and
  explicit output contracts.
- Runtime dependency ordering remains strict and unchanged.

## Consequences

- Missing dependencies, cycles, and isolated Modules can be inspected without
  weakening application boot rules.
- Text and Mermaid output cannot drift in graph meaning because both consume the
  same immutable nodes and edges.
- Mermaid output is covered by deterministic syntax snapshots. Renderer-level
  validation remains a beta release gate when a Mermaid CLI is available.
