# ADR-0065: Export Materialization and Portable Package Runtime

- Status: Accepted
- Date: 2026-08-29

## Context

ADR-0064 made package extraction reviewable but deliberately stopped before any
write. A useful export must preserve that exact plan, avoid partial packages,
produce valid Composer metadata, and load Module-owned runtime resources without
assuming the destination application has an `app/Modules` or nwidart directory.

## Decision

- `moduark:export` materializes when `--dry-run` is omitted. The explicit target,
  package, namespace, extractability checks and blocker codes remain identical to
  the dry-run contract.
- A target must not exist. Export never merges, overwrites, or offers `--force`.
- All generated and copied files are written beneath a random staging directory
  next to the target. PHP namespace transforms and syntax parsing complete before
  a single same-filesystem rename publishes the package.
- Failure recursively removes only the private staging directory, then removes
  parent directories created by that attempt only while they remain empty. A
  cleanup failure is reported explicitly and the command returns tool error exit `2`.
- Generated `composer.json` contains deterministic PSR-4, Laravel provider,
  Moduark / Illuminate runtime, Testbench development and script metadata. Its
  license defaults to the conservative `proprietary` placeholder; consumers
  replace it with the package's actual license before publication.
- The generated provider extends `PortableModuleServiceProvider`. It validates
  the exported Module source, compiles its one-Module metadata and Capability
  graph, registers declared providers, builds the resource manifest from package
  roots, and runs the same built-in resource handlers.
- Package layout recognizes `src/` as the code root and its parent as the config,
  routes, resources, migrations, tests and asset root.

## Boundaries

This slice proves an exported package can be Composer-installed, auto-discovered,
and booted independently under Laravel / Testbench. It does not merge installed
package Modules into the host application's canonical registry, activation state,
analysis, graph, cache, or cross-package collision model. That requires a later
package registry adapter so every subsystem observes one active Module set.

## Consequences

Consumers receive either a complete package or no target. The portable provider
makes the package operational before host-wide governance adoption exists, while
the explicit boundary prevents it from being misreported by current registry
commands.
