# ADR-0024: Combined Graph Output

- Status: Accepted for the third post-`0.2.0-beta.1` graph slice
- Date: 2026-08-15

## Context

The direct Module graph describes declared `dependencies()`, while the
Capability graph describes inverted `requires()` and `provides()` metadata.
ADR-0023 deliberately exposed these as separate views first so their meanings
could not be conflated. The `0.2.x` roadmap also requires a combined view for
reviewing both relationships together.

A combined projection must reuse the validated source graphs, retain missing
direct targets, and define neighborhood behavior without recursively expanding
to the entire application.

## Decision

- `CombinedGraphBuilder` composes the existing `ModuleGraphBuilder` and
  `CapabilityGraphBuilder`. It does not compile metadata or resolve Capability
  providers through a third path.
- `CombinedGraph` retains both immutable source graphs and verifies that every
  Capability Module node matches a discovered direct-graph Module node.
- `module:graph --view=combined` supports the existing text and Mermaid formats.
  The default remains `--view=module`.
- Text output labels direct edges as `depends` and keeps Capability `requires`
  and `provides` labels. Mermaid output uses the same three labels, rounded
  Capability nodes, and the existing missing-Module style.
- A selected Module first includes its direct incoming and outgoing neighbors,
  then all providers and consumers incident to its Capabilities. Direct edges
  whose endpoints are both in that union remain visible. The projection does
  not recursively add dependencies outside the selected union.
- Isolated Modules remain visible. Missing direct targets remain marked; missing
  or ambiguous Capability providers continue to fail through production
  provider resolution before any partial graph is returned.
- `combined` is now a valid `--view` value. JSON remains outside this slice.

## Acceptance evidence

- `CombinedGraphExporterTest` covers discovery-order independence, all three
  edge kinds, deterministic text and Mermaid output, missing direct targets,
  union neighborhoods, isolated Modules, unknown Modules, and inconsistent
  source-graph Module sets.
- `ModuleGraphCommandTest` covers combined text, Mermaid, neighborhood routing,
  unknown Modules, and invalid view handling while preserving the other views.
- `PackageBaselineTest` proves the builder and both exporters are registered in
  Laravel's container.
- `CleanApplicationRunner` verifies the combined view through package
  auto-discovery in clean Laravel 12 and 13 applications.
- The complete suite, PHPStan level max, and clean installation matrix remain
  the acceptance gate before release.

## Consequences

- Reviewers can compare direct coupling and dependency inversion in one graph
  without losing edge semantics.
- A selected combined neighborhood is bounded and deterministic; it is not a
  recursive dependency closure.
- Future JSON output can serialize the same two validated graphs and explicit
  edge kinds instead of reverse-engineering text or Mermaid output.
- [ADR-0025](0025-module-inspection.md) subsequently reuses the combined graph
  to inspect one Module without rebuilding relationship semantics.
