# ADR-0008: Incremental Architecture Check

- Status: Accepted for Slice 6A
- Date: 2026-08-15

## Context

`module:check` needs one execution and reporting contract for metadata rules and
future source-analysis rules. The current registry already validates Module
structure and unique identity, while typed descriptors contain enough evidence
to detect missing declared dependencies and circular dependency components.

Undeclared dependencies and internal API access require a verified namespace
and symbol owner index. Treating those rules as passing before that index exists
would produce a false architecture guarantee.

## Decision

- An immutable analysis context combines the validated registry with typed
  descriptors.
- Rules are independently registered by stable ID and receive their effective
  severity from the Level resolver.
- The first executable rules cover valid structure, unique identity, missing
  declared dependencies, and circular dependency components.
- Structure and identity results represent validation gates already enforced
  while constructing the discovery registry.
- An enabled rule without an implementation is reported as unavailable. The
  report is incomplete and `module:check` exits with code 2 even if every
  executable rule passes.
- `module:check --level=0` temporarily replaces the configured preset and still
  receives configured boolean rule overrides through the existing resolver.
- Human output uses stable violation codes, rule IDs, severity, source path when
  available, and an actionable message. JSON and Module filtering remain later
  command contracts.
- Runtime dependency ordering remains strict and unchanged. The analyzer does
  not weaken provider registration to make an invalid graph bootable.

## Consequences

- Level 0 checks are complete with the current discovery-backed rules.
- A default Level 1 check reports `undeclared_dependencies` and
  `internal_api_access` as unavailable until Slice 6B and Slice 7 provide their
  evidence sources.
- Metadata rule behavior can be tested against invalid graphs without invoking
  Module providers or causing lifecycle side effects.
- The same runner can consume graph and owner indexes later without changing
  command exit semantics.
