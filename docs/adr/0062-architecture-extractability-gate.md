# ADR-0062: Architecture Extractability Gate

- Status: Accepted
- Date: 2026-08-29

## Context

LC1-A verifies source layout and declared runtime ownership, but a Module can
still depend on undeclared classes, unresolved Capabilities, foreign tables, or
unexported internal APIs. Reimplementing those checks inside extraction would
create a second architecture truth and eventually produce conflicting results.

Application baselines and suppressions also answer a different question. They
permit reviewed debt while a monolith evolves; they do not prove that moving a
Module across a Composer package boundary is safe.

## Decision

- Extractability diagnostics reuse the raw Level 3 architecture report. They do
  not apply architecture baselines or suppressions.
- Six ordered extractability checks map the existing rule families:
  - `MOD-EXTRACT-DEPENDENCY-001` maps `undeclared_dependencies`;
  - `MOD-EXTRACT-CAPABILITY-001` maps `capability_contracts`;
  - `MOD-EXTRACT-TABLE-001` maps `database_ownership`;
  - `MOD-EXTRACT-FK-001` maps `cross_module_foreign_keys`;
  - `MOD-EXTRACT-TRANSACTION-001` maps `cross_module_transactions`; and
  - `MOD-EXTRACT-EXPORT-001` maps `explicit_public_exports`.
- A violation is Module-scoped when the selected Module is its consumer or one
  of its targets. Outbound evidence threatens package independence; inbound
  evidence threatens the application cutover after extraction.
- Warnings block export planning as well as errors. The ordinary architecture
  command's error-only exit policy does not weaken an extraction preflight.
- A required rule that was disabled by configuration or unavailable at runtime
  produces a blocker. An unevaluated check can never be reported as passed.
- Evidence retains the complete underlying architecture violation as
  deterministic JSON prefixed by its existing `MOD-*` code.

## Boundaries

This gate does not infer Composer dependencies, validate application-global
container bindings, prove resource namespace portability, create an export
file plan, or run package Testbench. Those remain later extraction slices.

## Consequences

Architecture analysis remains the single source of coupling evidence, while
the extractability report adds only Module selection and stricter preflight
semantics. Existing architecture configuration, baseline files, suppressions,
and `moduark:check` output remain unchanged.
