# ADR-0025: Module Inspection

- Status: Accepted for the post-graph `0.2.x` inspection slice
- Date: 2026-08-15

## Context

The graph views expose application-wide relationships, but reviewing one Module
still requires reading its entry class, matching discovered dependencies,
resolving Capability providers, and applying the Level 1 Public API convention
by hand. The `0.2.x` roadmap calls for `module:inspect {module}` to make those
details directly observable.

Explicit `exports()` metadata is deliberately reserved for Level 3 and remains
undecided. Inspection cannot imply that the convention-based Public API is an
accepted explicit exports contract.

## Decision

- `ModuleInspectionBuilder` selects one discovered Module by case-insensitive
  Module name and returns a typed immutable `ModuleInspection`.
- Inspection reuses `CombinedGraphBuilder`, `ModuleMetadataCompiler`,
  `SourceIndexBuilder`, and the configured `PublicApi` classifier. It does not
  independently infer dependency or Capability relationships.
- `module:inspect {module}` displays name, class, path, namespace, enabled state,
  effective architecture level, direct dependencies and missing status, Module
  ServiceProviders, required and provided Capabilities, and Public API symbols.
- Every required Capability includes the production-resolved provider plus the
  consumer-owned Port and Adapter. Provider resolution errors use the same
  preflight contract as runtime composition and graph generation.
- The Public API row is explicitly labeled `Public API (convention)`. It uses
  the current Module entry, `Contracts/`, `Data/`, and `Events/` convention and
  does not add `exports()` metadata.
- Human-readable table output is the only format in this slice. JSON remains a
  later developer-experience decision.
- Unknown Modules and inspection failures return the existing tool-error exit
  code `2` when the command is reached.

## Acceptance evidence

- `ModuleInspectionBuilderTest` covers case-insensitive selection, Level 2
  direct and Capability details, ServiceProviders, convention-based Public API
  filtering, missing direct dependencies, and unknown Modules.
- `ModuleInspectCommandTest` covers the complete deterministic table and stable
  unknown-Module error handling.
- `PackageBaselineTest` proves the builder is registered in Laravel's container.
- `CleanApplicationRunner` proves command registration, generated-Module
  inspection, and configuration-cache compatibility in clean Laravel 12 and 13
  applications.
- The complete suite, PHPStan level max, and clean installation matrix remain
  the acceptance gate before release.

## Consequences

- Users can audit one Module without manually joining metadata, graph, source,
  and effective-level information.
- Inspection performs source indexing because Public API visibility is part of
  its contract; source syntax or filesystem analysis failures remain explicit.
- Laravel boots before Artisan invokes the command. A discovery, metadata,
  dependency-order, or Capability-preflight failure can therefore still be
  rendered by Laravel before `module:inspect` handles input.
- Future explicit exports can replace or compose with the `PublicApi` classifier
  without changing dependency or Capability inspection semantics.
- [ADR-0026](0026-large-level-two-fixture.md) subsequently exercises inspection
  together with the full Level 2 contract on a connected eight-Module fixture.
