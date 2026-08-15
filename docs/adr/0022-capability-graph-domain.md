# ADR-0022: Capability Graph Domain

- Status: Accepted for the first post-`0.2.0-beta.1` graph slice
- Date: 2026-08-15

## Context

The existing `ModuleGraph` represents direct `dependencies()` edges. Level 2
now also has validated `requires()` and `provides()` metadata, deterministic
provider resolution, and runtime Port-to-Adapter composition. Adding Capability
and combined command output directly to the direct graph would flatten two
different architecture relationships and risk drifting from runtime resolution.

The graph needs a stable typed model before text, Mermaid, neighborhood, or
combined exporters define public CLI output.

## Decision

- `CapabilityGraph` is a separate immutable projection containing discovered
  Module nodes, typed Capability nodes, and `requires` or `provides` edges.
- Both edge kinds point from a Module endpoint to a Capability endpoint. The
  edge kind preserves the relationship instead of inferring it from direction.
- Every edge retains its declaring metadata method as evidence. A `requires`
  edge additionally retains the consumer-owned Port and Adapter classes;
  `provides` edges cannot carry consumer composition metadata.
- `CapabilityGraphBuilder` compiles descriptors and invokes the production
  `CapabilityResolver` before constructing a graph. Missing providers,
  ambiguous providers, and duplicate consumer Ports therefore use the same
  deterministic diagnostics as lifecycle preflight and never produce a
  misleading partial graph.
- Provided Capabilities remain visible even when no Module requires them.
  Multiple unused providers remain representable because provider selection is
  only required when a consumer requirement exists.
- Modules, Capabilities, and edges use stable name-first ordering independent of
  discovery order. Edges group by Capability, with `provides` before `requires`,
  then by Module name.
- The builder is registered as a Laravel singleton. CLI views, exporters,
  neighborhood filtering, the combined graph, and `module:inspect` remain
  outside this slice.

## Acceptance evidence

- `CapabilityGraphBuilderTest` proves discovery-order independence, isolated
  Module retention, provider-first edge ordering, evidence retention, consumer
  Port and Adapter retention, and visibility of an unused provided Capability.
- The same test proves missing and ambiguous providers fail through the existing
  `CapabilityResolutionFailed` contract.
- `PackageBaselineTest` proves the production Laravel container exposes the
  builder.
- The complete suite and PHPStan level max remain the acceptance gate before
  this slice is committed.

## Consequences

- Future Capability text and Mermaid exporters can share one validated model.
- A future combined graph can preserve direct, `requires`, and `provides` edge
  kinds instead of collapsing them into generic Module dependencies.
- Invalid Capability metadata remains diagnostic data for `module:check`; the
  graph command will not silently visualize an architecture that runtime
  composition cannot resolve.
- [ADR-0023](0023-capability-graph-output.md) subsequently exposes the model
  through text and Mermaid CLI views with Module neighborhood filtering.
