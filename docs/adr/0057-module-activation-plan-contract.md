# ADR-0057: Module Activation Plan Contract

- Status: Accepted
- Date: 2026-08-29

## Context

Moduark 1.2 made registry, analysis, graphs, cache, providers, Capabilities, resources,
operations, and assets consume one active Module set. The nwidart interoperability
fixture proves that Moduark correctly adopts an externally changed enabled state on
the next process boot, but Moduark does not yet own a safe state-change operation.

An active-only runtime registry is insufficient for planning activation changes: a
disabled Module and its metadata would look absent. Writing a status file before
validating the complete proposed graph could therefore leave enabled dependents
without dependencies or Capability consumers without a valid provider.

## Decision

- Introduce a read-only `ModuleActivationPlanner`. Its inventory must come from
  unfiltered discovery and contain enabled and disabled Modules.
- Apply one enable or disable intent to an immutable `ModuleActivationSet`, then
  validate the complete proposed active set before any mutation is allowed.
- Reuse `ModuleMetadataCompiler`, `ModuleOrderer`, and `CapabilityResolver` as the
  metadata, dependency, and Capability authorities.
- Return an immutable `ModuleActivationPlan` containing canonical before/after names,
  dependency order, activation fingerprint, no-op state, and stable blockers.
- Treat an already-satisfied intent as a no-op but still validate its graph. Treat an
  unknown Module as invalid input rather than a successful no-op.
- Add structured reason and context accessors to `CapabilityResolutionFailed` so
  activation diagnostics do not parse exception messages.

## Safety Boundary

This decision does not add commands, write activation state, invalidate caches,
register providers, load routes or resources, dispatch lifecycle events, or switch a
running Laravel application in place. A blocked plan is diagnostic only and is never
executable.

State drivers, CLI dry-run output, atomic persistence, cache invalidation recovery,
and enabled-only runtime diagnostics require later independently verified slices.

## Acceptance

- A permanent fixture includes a required dependency, Capability consumer, unique
  provider, safe leaf Module, and alternative provider.
- Disabling a required dependency, disabling the unique provider, enabling a Module
  with a disabled dependency, and enabling an ambiguous provider all produce stable
  blockers.
- A valid leaf transition and a valid no-op produce executable deterministic plans.
- Case and input ordering do not change the canonical payload or fingerprint.
- Plan payloads round-trip without objects or closures.
- Existing lifecycle, Capability, and nwidart active-set adoption behavior remains
  unchanged.

## Consequences

Future activation drivers and commands have one preflight contract and cannot bypass
the existing dependency or Capability graph rules. Until persistence and invalidation
are implemented, Moduark can plan activation changes but cannot perform them.
