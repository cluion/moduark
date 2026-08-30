# ADR-0069: Package Set Export Plan

- Status: Accepted
- Date: 2026-08-30

## Context

ADR-0068 makes one exported Module dependency explicit, but operators still have
to coordinate every package plan separately. That leaves dependency closure,
package order, repeated constraints, namespaces, and targets outside the
machine-readable contract. Materializing several packages before that aggregate
plan is reviewable would make partial output and rollback semantics ambiguous.

## Decision

- Add the Preview, read-only `moduark:export-set` command. It always reports a
  plan and never writes package files.
- A repeatable `--package='Module=vendor/package:constraint=>Namespace'` option
  reuses the LC1-G package identity, Composer constraint, and namespace parser.
  Each selected Module must also have exactly one repeatable
  `--target='Module=portable/path'` mapping.
- Module names resolve case-insensitively through the canonical active registry;
  output retains canonical names and classes. Composer packages and namespaces
  must be unique across the set, and generated runtime package identities remain
  reserved.
- Every declared Module dependency of every selected Module must also be selected.
  Missing members block the set with `MOD-EXPORT-SET-CLOSURE-001` and exact
  `Consumer->Dependency` evidence.
- A ready set is ordered dependency-first with the existing Module metadata
  orderer. Input mapping order never affects JSON or text output. An invalid
  dependency graph blocks with `MOD-EXPORT-SET-ORDER-001`.
- Exact or ancestor/descendant package targets cannot coexist. Exact duplicate
  targets are input errors; overlapping targets block with
  `MOD-EXPORT-SET-TARGET-001`.
- Each package entry embeds the complete schema version `2` single-Module export
  plan and adds the selected package constraint. Direct dependencies are mapped
  from other selected package identities, so generated requirements and
  namespace rewrites remain governed by ADR-0068.
- Package-set JSON uses schema version `1`, operation `export-set`, `dry_run=true`,
  the dependency-first canonical `order`, aggregate summary, package plans, set
  blockers, exit code, and nullable error. Ready plans use exit `0`, plan blockers
  exit `1`, and malformed or inconsistent mappings exit `2`.

## Verification

A permanent `User` / `Order` fixture supplies reversed package and target input
orders and requires byte-identical JSON with `User` before `Order`. It verifies
the resolved `Order` to `User` package requirement, closure and target-overlap
blockers, nested single-package blockers, and absence of every planned target.
Laravel 12 and 13 clean installations run the same read-only set plan before the
existing two-package materialization and transitive-install adoption gate.

## Boundaries

This slice does not materialize a package set, create a transaction spanning
multiple targets, invoke Composer, choose versions, inspect remote repositories,
publish packages, or change the single-package materialization command. A later
slice must define atomic set materialization and recovery semantics before
`moduark:export-set` may write files.

## Consequences

Operators and automation can review one dependency-closed, deterministic package
set before any filesystem change. The additional explicit target mapping is
intentional: package location remains a local delivery decision and cannot be
derived safely from Composer identity.
