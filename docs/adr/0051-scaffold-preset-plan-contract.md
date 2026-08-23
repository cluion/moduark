# ADR-0051: Scaffold Preset Plan Contract

- Status: Accepted and implemented through `1.1` Slice G6-A
- Date: 2026-08-23

## Context

`moduark:make-module` currently creates one Module entry class. Version `1.1`
adds broader scaffolds, but preset selection cannot bypass the complete plan,
collision, dry-run, and rollback guarantees already used by Module Makers.
Presets must also remain generic: they cannot install frontend dependencies,
run package managers, or assume a particular domain framework.

## Decision

- `minimal`, `web`, `api`, `domain`, and `full` are canonical preset IDs.
- Omitting a preset remains equivalent to `minimal`; the existing command is not
  changed by G6-A.
- Every preset is an ordered collection of package-owned target descriptors.
  Each descriptor owns one generator ID, Module-relative path, generated
  identity, and deterministic template.
- Every preset starts with the Module entry target. `web` adds web routes, an
  invokable controller, a view, English translations, and a feature test. `api`
  adds API routes, an invokable controller, request, resource, and feature test.
  `domain` adds tracked `Domain`, `Application`, and `Infrastructure` roots
  without imposing base classes or a DDD framework.
- `full` is exactly the deterministic union of `web`, `api`, and `domain`, with
  the shared Module entry target appearing once.
- Preset targets stay below the selected Module root and never allow overwrite.
  The common `GenerationPreflight` therefore reports every collision before a
  later execution slice can mutate the filesystem.
- Package templates contain no frontend or package-manager side effects. The
  existing application `stubs/` override convention remains available.
- A permanent fixture freezes generator IDs, target paths, ordering, additive
  membership, rendered PHP syntax, collision ownership, and planning purity.

## Consequences

- G6-A creates an executable planning object but does not expose incomplete
  preset execution through the CLI.
- G6-B can connect the same immutable plan to `--preset`, `--dry-run`, complete
  preflight, and rollback-safe execution without inventing a second generator.
- Adding, removing, or reordering a preset target is an observable contract
  change and must update the fixture intentionally.
