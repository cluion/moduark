# Migration Recipes

These recipes turn Moduark's architecture contracts into small, reviewable
application migrations. Each recipe keeps the configured Level unchanged until
the next Level passes as a temporary probe.

- [Brownfield Level 0 to Level 1](level-0-to-level-1.md): discover existing
  boundaries, preserve application behavior, declare direct dependencies, move
  cross-Module access to provider-owned Public APIs, and enable the first
  architecture CI gate.
- [Brownfield Level 1 to Level 2](level-1-to-level-2.md): invert consumer core
  dependencies through consumer-owned Ports, provider-scoped Adapters, and
  provider-neutral Capability metadata without removing Level 1 Public APIs.

Use [Adopting Moduark](../adoption.md) for the complete adoption policy and
[Architecture Levels](../architecture-levels.md) for the preset matrix.
