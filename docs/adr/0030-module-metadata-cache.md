# ADR-0030: Deterministic Module Metadata Cache

- Status: Accepted for the third `0.3.x` Developer Experience slice
- Date: 2026-08-16

## Context

Every Laravel process currently scans the configured Module root, parses Module
entry files, instantiates each Module to compile its metadata, orders the
dependency graph, and validates Capability resolution. The result is
deterministic for a deployed application revision, so repeating that work on
every bootstrap is unnecessary.

Laravel packages can append their own commands to `optimize` and
`optimize:clear` through the protected `ServiceProvider::optimizes()` contract.
That contract is present in both the
[Laravel 12 ServiceProvider](https://github.com/laravel/framework/blob/12.x/src/Illuminate/Support/ServiceProvider.php#L453-L470)
and the
[Laravel 13 ServiceProvider](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Support/ServiceProvider.php#L453-L470).

## Decision

- Add `module:cache` and `module:clear`. Register them with Laravel's
  `optimize` and `optimize:clear` lifecycle.
- Write `bootstrap/cache/moduark.php` as deterministic scalar PHP with schema
  version `1` for this slice, the configured Module root, sorted discovery
  records, and dependency-ordered Module descriptors. Later metadata additions
  advance this schema through their own ADRs.
- Build the manifest from fresh discovery and a fresh metadata compiler. Before
  writing, validate dependency ordering and the complete Capability graph so a
  cache cannot preserve an architecture that runtime bootstrap would reject.
- Write through a temporary file and replace the destination, then invalidate
  its OPcache entry. Clearing is idempotent.
- At runtime, use a manifest only when its schema version and configured Module
  root match. An unknown schema or different root is bypassed in favor of fresh
  discovery; a malformed current-schema payload fails with the cache path.
- Reuse cached descriptors through `ModuleMetadataCompiler`, allowing the
  existing lifecycle, graph, inspection, and architecture services to keep one
  metadata source without a second cached execution path.
- Cache only Module discovery and typed metadata. Route, view, translation,
  migration, and Module command paths continue through Laravel's normal boot
  mechanisms.

## Acceptance evidence

- Unit coverage verifies byte-for-byte deterministic PHP, scalar-only payloads,
  schema round trips, Module-root invalidation, malformed payload diagnostics,
  and idempotent clearing.
- Feature coverage proves both commands, runtime use until explicit clearing,
  container bindings, and Laravel `optimize` / `optimize:clear` integration.
- Clean Laravel 12 and 13 installations list the commands, exercise direct cache
  lifecycle, and prove Laravel optimization creates and clears the manifest.
- PHPUnit and PHPStan verify that uncached behavior and existing command
  contracts remain intact.

## Consequences

- Deployments that run `php artisan optimize` gain cached Module discovery and
  metadata automatically.
- Adding, removing, or moving a Module, or changing `dependencies()`,
  `providers()`, `requires()`, `provides()`, `tables()`, or `exports()`, requires
  `module:cache` to be
  rerun or `module:clear` to restore fresh discovery.
- Resource discovery is intentionally still performed at boot. Broader runtime
  caching and analyzer incremental caches remain separate future work.

## Current schema evolution

- Schema `2` retained explicit `tables()` metadata for the Table Ownership Index
  in [ADR-0036](0036-table-ownership-index.md).
- Schema `3` retains explicit class-like `exports()` metadata for
  `explicit_public_exports` in
  [ADR-0041](0041-explicit-public-exports-rule.md).
- Schema `4` adds the nwidart active-set fingerprint so enable/disable changes
  bypass stale Module metadata, as recorded in
  [ADR-0048](0048-nwidart-active-module-set.md).
