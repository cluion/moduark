# ADR-0002: Beta Configuration Defaults

- Status: Accepted for `0.1.0-beta`
- Date: 2026-08-15

## Context

Level 2 is the recommended long-term architecture target, but the first beta
only implements Level 0 and Level 1 rules. Laravel's `mergeConfigFrom()` also
merges only the first level of a package configuration array, while Moduark's
rule overrides are nested.

## Decision

- The zero-config beta default is Level 1.
- Documentation may call Level 2 the recommended target only when it also says
  that Level 2 enforcement is not part of the beta.
- `ModulesConfig` recursively applies user overrides on top of package defaults
  and validates the baseline shape.
- The Service Provider writes the normalized result back to Laravel's config
  repository before other Moduark services consume it.
- Configuration and metadata stored in cached configuration must contain no
  closures or active runtime objects.

## Consequences

- A partial `architecture.rules` override cannot accidentally remove the level
  default.
- All future Moduark services should consume `ModulesConfig` rather than
  duplicating merge behavior.
- Structured rule options can be added later without changing the precedence
  contract.
