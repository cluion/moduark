# ADR-0026: Large Level Two Acceptance Fixture

- Status: Accepted for the final `0.2.x` architecture-contract slice
- Date: 2026-08-15

## Context

Level 2 had unit and focused integration coverage for every rule, Capability
resolution, runtime binding, graph projection, and Module inspection. Those
small fixtures did not yet demonstrate that the contracts remain coherent when
several consumers share several provider Capabilities in one connected modular
monolith.

The `0.2.x` roadmap calls for a larger fixture to test the claim that Level 2 is
a practical middle ground: explicit enough to enforce dependency inversion,
without requiring Level 3 persistence isolation.

## Decision

- Add a tracked acceptance fixture below `tests/Fixtures/LargeLevelTwo/` with
  eight business Modules. It is test data, not application code shipped as a
  runtime example.
- Catalog, Customer, Inventory, Notification, and Payment each provide one
  typed Capability through a provider-owned public contract.
- Checkout requires four Capabilities, Fulfillment requires three, and Returns
  requires five. Every requirement has a distinct consumer-owned Port and an
  Adapter below the exact `Adapters/{Provider}/` boundary, producing twelve
  runtime bindings.
- The three consumers declare matching direct dependencies. Provider Modules do
  not reference consumer Ports or Adapters, and consumer workflow actions use
  Ports rather than concrete Adapters.
- Fixture discovery input is deliberately not alphabetical. Registry, graph,
  provider resolution, and inspection must retain their deterministic contracts.
- The fixture's configured level is 2 and no rule override weakens the preset.

| Consumer | Providers | Bindings |
|---|---|---:|
| Checkout | Catalog, Customer, Inventory, Payment | 4 |
| Fulfillment | Customer, Inventory, Notification | 3 |
| Returns | Catalog, Customer, Inventory, Notification, Payment | 5 |

## Acceptance evidence

- `LargeLevelTwoFixtureTest` proves all eight rules complete without violations,
  all twelve Ports bind through Laravel's container, and three workflow actions
  execute through their declared Adapters.
- The same test proves eight discovered Module nodes, twelve direct edges, five
  Capability nodes, seventeen Capability edges, and a five-requirement Returns
  inspection with the resolved Payment provider.
- `LargeLevelTwoCommandsTest` exercises `module:check --level=2`, the combined
  graph, and `module:inspect Returns` through their real Artisan renderers.
- The complete suite, PHPStan level max, Composer validation, and clean Laravel
  12/13 installation matrix remain the acceptance gate before release.

## Consequences

- The Level 2 guarantee is now tested as one connected architecture rather than
  only as a collection of independent feature claims.
- Regressions that flatten Capability semantics, share consumer Ports, bypass
  Adapters, drift direct dependencies, or lose command observability have a
  realistic fixture on which to fail together.
- This fixture demonstrates contract coherence, not performance at scale. The
  separate 100 Module / 10,000 PHP file benchmark remains the performance gate.
- The fixture does not claim that every application should use eight Modules or
  this domain split; those numbers are acceptance dimensions, not design rules.
