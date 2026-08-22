# Adoption and Levels

Use this reference for first installation, brownfield adoption, Module metadata,
or a change between architecture Levels.

## Read the Installed Contract

Locate the installed package with:

```bash
composer show cluion/moduark --path
```

From that package root, read:

- `README.md` for supported versions, commands, and current scope;
- `docs/adoption.md` for the staged adoption workflow;
- `docs/architecture-levels.md` for the effective rule matrix;
- the matching file below `docs/recipes/` for a Level migration.

Do not assume the Skill version matches an application until the Composer path
and version confirm it.

## Adopt Incrementally

1. Inventory Composer PSR-4 roots, current business areas, Laravel resources,
   providers, known dependencies, and existing Module entry classes.
2. Use Level 0 to establish valid Module structure and identity. A Level 0 pass
   says nothing about cross-Module dependencies or visibility.
3. Probe the next Level with `module:check --level=N --format=json` before
   changing the shared default.
4. Repair one diagnostic class at a time and rerun the relevant application
   tests.
5. Change `modules.architecture.level` only after the temporary check is
   complete and accepted.
6. Put the same effective check in CI.

Do not jump directly to the strictest Level. Level 3 is an opt-in persistence
and export policy, not a universal Laravel requirement.

## Preserve Metadata Semantics

- `dependencies()` records direct Module dependency edges. It does not make an
  internal provider implementation public.
- The default public convention includes the provider Module entry class and
  symbols below exact `Contracts/`, `Data/`, and `Events/` directories.
- Level 2 uses provider-neutral Capability identities, consumer-owned Ports,
  and consumer-owned provider-scoped Adapters. Read the installed Level 1 to 2
  recipe before changing those ownership relationships.
- Level 3 requires explicit persistence and export decisions. Read the installed
  Level 2 to 3 recipe before moving migrations or adding `tables()` and
  `exports()` metadata.

Run `module:inspect {module}` and the relevant module, capability, or combined
graph after metadata changes. Keep runtime application tests authoritative for
behavior that static architecture checks do not execute.

## Debt Decisions

A new violation introduced by the current change should normally be fixed.
When existing debt prevents a staged Level adoption, follow
[Diagnostics and Debt](diagnostics-and-debt.md) and obtain an explicit decision
before creating a baseline or suppression.
