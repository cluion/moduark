# ADR-0023: Capability Graph Output

- Status: Accepted for the second post-`0.2.0-beta.1` graph slice
- Date: 2026-08-15

## Context

ADR-0022 introduced a validated, deterministic Capability graph domain while
deliberately leaving its public output contract undefined. `module:graph`
already renders direct Module dependencies in text or Mermaid and accepts an
optional Module neighborhood. Capability output needs to extend that command
without changing existing scripts or flattening inverted relationships into
direct dependencies.

## Decision

- `module:graph` gains `--view=module|capability`; `module` remains the default
  so existing invocations and output stay compatible.
- Both views support the existing `--format=text|mermaid` contract. Combined
  and JSON values remain invalid until their separate output contracts exist.
- Text output renders one deterministic Module-to-Capability relationship per
  line as `Module -[requires|provides]-> Capability`. Discovered Modules without
  Capability metadata remain visible as `Module -> —`.
- Mermaid output renders Module nodes, distinct rounded Capability nodes, and
  labeled `requires` or `provides` edges from Module to Capability.
- Selecting a Module in the Capability view includes every Capability connected
  to it, then retains all provider and consumer edges incident to those
  Capabilities. This two-hop projection keeps each inverted relationship
  complete instead of hiding the provider or sibling consumers.
- Selecting an isolated Module returns that Module alone. Unknown Modules and
  invalid views use the existing tool-error exit code and actionable messages.
- Capability graph construction still invokes production provider resolution;
  the command cannot export a partial graph for missing, ambiguous, or
  duplicate-Port metadata.

## Acceptance evidence

- `CapabilityGraphExporterTest` covers deterministic text and Mermaid snapshots,
  isolated Modules, complete Capability neighborhoods, and unknown Modules.
- `ModuleGraphCommandTest` covers both Capability formats, neighborhood routing,
  invalid views, Capability-view errors, and continued default Module output.
- `PackageBaselineTest` proves both exporters are registered in Laravel's
  container.
- `CleanApplicationRunner` verifies the Capability view through package
  auto-discovery in clean Laravel 12 and 13 applications.
- The complete suite, PHPStan level max, and clean installation matrix remain
  the acceptance gate before release.

## Consequences

- Users can inspect Level 2 dependency inversion without confusing Capability
  edges with direct Module dependencies.
- Text and Mermaid views consume the same graph model and neighborhood
  projection, preventing format-specific graph meaning.
- A future combined view must preserve direct, `requires`, and `provides` edge
  kinds; it cannot merge them into unlabeled Module edges.
- [ADR-0024](0024-combined-graph-output.md) subsequently adds that combined
  view while preserving the three edge kinds and union neighborhood semantics.
