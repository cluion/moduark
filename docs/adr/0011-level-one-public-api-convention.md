# ADR-0011: Level One Public API Convention

- Status: Accepted for Slice 7A
- Date: 2026-08-15

## Context

Level 1 allows a consumer Module to reference a provider-owned Public API while
blocking direct access to implementation details. The source ownership index can
resolve the exact target symbol and source line, but it still needs a stable rule
for deciding which provider symbols are public.

An explicit `exports()` metadata contract remains intentionally undecided. Adding
it only for this rule would prematurely define the Level 3 API and its migration
semantics. Treating every namespace or import as public would provide no boundary
at all.

## Decision

- Level 1 uses an interchangeable Public API classifier. Its initial convention
  is path-based, not namespace-prefix based.
- Named symbols below a Module's `Contracts/`, `Data/`, and `Events/` directories
  are public recursively. Directory names are exact and case-sensitive.
- The Module entry class is also public as architecture identity, so another
  Module can reference it from typed `dependencies()` metadata.
- All other directories, including `Actions/`, `Models/`, `Ports/`, `Services/`,
  and `Support/`, are internal by default.
- Same-Module references never cross a boundary.
- Dependency declaration and API visibility are independent. A public reference
  can still violate `undeclared_dependencies`, and a declared dependency can
  still violate `internal_api_access`.
- `MOD-BOUNDARY-001` reports each internal cross-Module reference with consumer,
  provider, canonical symbol, file, line, and the allowed convention folders.
- Source indexing runs when either `undeclared_dependencies` or
  `internal_api_access` is enabled. Level 0 still skips AST analysis.
- Level 2 Ports/Capabilities and Level 3 explicit exports do not broaden this
  convention until their typed contracts are accepted separately.

## Consequences

- The complete Level 1 preset now has executable implementations for structure,
  identity, missing and undeclared dependencies, cycles, and internal access.
- Aliases, fully qualified names, attributes, types, inheritance, interfaces,
  traits, static access, and construction all receive the same ownership and
  visibility decision from one source index.
- Moving a provider symbol into or out of a Public API directory is an
  architecture-facing change and can change consumer check results.
- The classifier interface allows future explicit exports to replace or compose
  with the convention without changing the boundary rule or diagnostics.
