# Migration Recipes

These recipes turn Moduark's architecture contracts into small, reviewable
application migrations. Each recipe keeps the configured Level unchanged until
the next Level passes as a temporary probe.

- [Brownfield Level 0 to Level 1](level-0-to-level-1.md): discover existing
  boundaries, preserve application behavior, declare direct dependencies, move
  cross-Module access to provider-owned Public APIs, and enable the first
  architecture CI gate.

Use [Adopting Moduark](../adoption.md) for the complete adoption policy and
[Architecture Levels](../architecture-levels.md) for the preset matrix.
