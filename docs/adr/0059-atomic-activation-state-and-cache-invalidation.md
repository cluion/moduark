# ADR-0059: Atomic Activation State and Cache Invalidation

- Status: Accepted
- Date: 2026-08-29

## Context

ADR-0058 exposed validated activation plans but required `--dry-run`. Persisting
a valid plan introduces a consistency boundary: state must never change while a
cache still describes the previous enabled graph, and a failed write must not
destroy the last valid state. The currently running Laravel process also cannot
safely unload providers, routes, commands, or bindings in place.

## Decision

- Standalone applications persist a schema-versioned complete boolean map in
  `moduark.activation.path`, defaulting to `moduark-modules.json`. A missing file
  retains the existing all-enabled behavior.
- Applications following nwidart's file activator update the same configured
  flat status map. Moduark does not create a second activation source.
- Custom nwidart activators remain readable for planning, but mutation is
  rejected unless an atomic store is available.
- Every non-no-op mutation acquires an application activation lock, reloads the
  current state, and rejects a fingerprint that changed after planning.
- Under that lock, Moduark first removes Module metadata, source-analysis,
  Laravel route, and Laravel event caches. It then writes a complete state map
  to a same-directory temporary file, flushes it, and commits with one rename.
- The writer never unlinks the authoritative state before rename. If cache
  invalidation or commit fails, the previous state remains authoritative. Empty
  caches are a safe recovery state and rebuild on a later process.
- A successful command changes the next application boot only. It does not
  hot-switch the process executing the command.

## CLI Contract

JSON schema version `1` is retained. Dry-run plans use `planned`, successful
mutations use `applied`, validated no-ops use `unchanged`, graph blockers use
`blocked`, and concurrency, unsupported-driver, cache, state, or input failures
use `error`. Their exit codes remain `0`, `1`, and `2` for success, blocked, and
tool error respectively.

## Acceptance

- Permanent tests cover deterministic standalone and nwidart file formats,
  concurrent changes, cache failure, atomic-write failure, no-op behavior, and
  unsupported stores.
- Testbench proves all four graph cache files are removed before state commit
  and a new application boot consumes disable and enable mutations.
- Fresh Laravel 12 and 13 applications prove standalone disable / enable across
  separate Artisan processes.
- Matching nwidart 12 and 13 applications prove Moduark changes the same status
  file and the next process aligns nwidart, registry, graph, providers,
  Capabilities, routes, resources, analysis, and rebuilt cache.

## Consequences

Activation is now deploy-safe for standalone and nwidart file state, but it is
not a production hot-reload mechanism. Enabled-only diagnostic presentation and
formal parity across every read surface remain a separate LC0-D slice.
