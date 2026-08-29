# ADR-0058: Activation Driver Identity and CLI Dry-run

- Status: Accepted
- Date: 2026-08-29

## Context

ADR-0057 introduced a pure activation planner but deliberately did not expose a
command or select who owns activation state. A command that plans from the
active-only runtime registry cannot see disabled Modules, while a command that
silently assumes a writable file could conflict with nwidart's configured
activator.

State mutation and cache invalidation still require failure-recovery design. The
CLI needs a way to expose useful plans before those write semantics are accepted,
without implying that a successful preview changed the application.

## Decision

- Identify the authoritative state driver as `standalone` or `nwidart` in an
  immutable `ModuleActivationState` bound beside the existing activation set.
- Add `moduark:enable` and `moduark:disable`, but require `--dry-run`. A call
  without the flag is a tool error and never falls through to mutation.
- Build command inventory from unfiltered discovery with
  `ModuleActivationSet::all()`. Never plan from the active-only runtime registry
  or its cache manifest.
- Use a fresh metadata compiler with the existing `ModuleOrderer` and
  `CapabilityResolver`, then render the ADR-0057 plan unchanged.
- Return JSON schema version `1` with status `planned`, `blocked`, or `error`;
  operation, dry-run marker, driver, nullable plan, exit code, and nullable
  error are always present.
- Use exit `0` for an executable plan, `1` for dependency or Capability blockers,
  and `2` for invalid input or command/tool failure.

## Safety Boundary

Neither command writes standalone or nwidart state, invokes nwidart mutation
commands, clears or rebuilds caches, dispatches events, or modifies the active
Laravel process. The driver identity is descriptive in this slice; it is not a
writable driver interface.

## Acceptance

- Testbench proves valid, blocked, no-op, unknown-target, invalid-format, and
  missing-dry-run behavior with deterministic text/JSON contracts.
- Repeated dry runs preserve the activation fingerprint and active registry.
- Fresh Laravel 12 and 13 applications expose both commands and prove a
  standalone dry run leaves the Module active.
- Matching nwidart 12 and 13 applications report the `nwidart` driver, block a
  dependency-invalid plan, and preserve the exact status-file hash before and
  after valid enable/disable dry runs.
- Existing external nwidart `module:disable` and `module:enable` transitions
  remain the only mutation path in the interoperability fixture.

## Consequences

Applications can inspect the exact activation decision before write semantics
exist. The next slice must add atomic driver-specific persistence, complete cache
invalidation, and recovery tests before the CLI can run without `--dry-run`.
