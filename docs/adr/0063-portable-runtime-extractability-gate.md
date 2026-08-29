# ADR-0063: Portable Runtime Extractability Gate

- Status: Accepted
- Date: 2026-08-29

## Context

Source ownership and raw Level 3 architecture evidence do not prove that a
Module's Laravel runtime contract can move into a package. Resource namespaces
may remain application-global, active Modules may claim the same runtime key,
publish destinations may escape or collide, and a Module provider may bind an
application-owned class or unscoped string key.

The active `ResourceManifest` already contains the authoritative plugin,
runtime namespace, collision key, class attributes, asset type, and publish
target evidence. Re-discovering those values during extraction would create a
second runtime truth.

## Decision

- Extractability diagnostics append five ordered checks:
  - `MOD-EXTRACT-PLUGIN-001` validates built-in resource contracts and their
    class-valued attributes;
  - `MOD-EXTRACT-NAMESPACE-001` requires Module-scoped config keys, view and
    translation namespaces, component prefixes, and route-group namespaces;
  - `MOD-EXTRACT-COLLISION-001` blocks active manifest collisions involving the
    selected Module;
  - `MOD-EXTRACT-PUBLISH-001` validates config and public-asset destinations
    while retaining asset-input evidence; and
  - `MOD-EXTRACT-BINDING-001` validates detected provider container bindings.
- Resource checks consume the same active-only `ResourceManifest` used by
  runtime, cache, and diagnostics. Unknown plugins block because their package
  runtime dependency and handler portability have not been proven.
- Publish targets must be non-empty relative forward-slash paths without dot
  traversal, absolute roots, Windows drive roots, or active target collisions.
- Declared providers are parsed without execution. Direct `bind`, `singleton`,
  `scoped`, `instance`, `alias`, `extend`, and matching `*If` calls are accepted
  only when their receiver and operands are statically resolvable. Class
  operands may be owned by an active Module or vendor dependency. Application
  classes, unscoped string keys, dynamic receivers or operands, and contextual
  bindings block extraction planning.
- Evidence remains sorted and deterministic. Existing report schema and exit
  semantics do not change.

## Boundaries

This gate does not parse every route declaration for URI or route-name
collisions, infer Composer or frontend dependencies, inspect arbitrary provider
side effects, execute vendor publishing, inspect existing destination files, or
create an export file plan.

## Consequences

The extractability report now covers the known Laravel runtime surface without
mutating the application. Conservative blockers may require a provider binding
or third-party resource plugin to expose a more explicit portable contract
before export planning can proceed.
