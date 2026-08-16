# ADR-0033: Incremental Source Analysis

- Status: Accepted for the fifth `0.3.x` Developer Experience slice
- Date: 2026-08-16

## Context

`SourceIndexBuilder` previously parsed every PHP file below every discovered
Module whenever an enabled rule required source analysis. The existing
performance fixture showed that parsing dominated a Level 1 check: 5,000 files
took a median 346.271 ms and 10,000 files took 720.596 ms on the comparison
host, while Module discovery itself remained below 5 ms.

Incremental analysis must improve repeated checks without trusting timestamps,
masking changed syntax, persisting final ownership decisions that depend on
other files, or turning an optional optimization into a new source of false
passes or tool errors.

## Decision

- Store an internal versioned PHP manifest at
  `bootstrap/cache/moduark-analysis.php` when a check actually builds a source
  index. No user configuration or public cache DTO is introduced.
- Continue scanning, reading, and hashing every current Module PHP file. Reuse a
  file analysis only when its SHA-256 content hash, owning Module class, and
  cache schema all match.
- Cache only per-file symbols and unresolved class-reference candidates. Rebuild
  the global symbol index and resolve reference ownership on every check so a
  change in one declaration correctly affects unchanged consumers.
- Reparse changed or ownership-moved files and omit removed files from the next
  manifest. Absolute paths make a moved project cold naturally.
- Treat an unknown schema, malformed payload, invalid owner, or invalid cached
  symbol/reference as a cache miss. Complete cold analysis is authoritative.
- Write the manifest atomically only after parsing and global index validation
  succeed. Syntax, duplicate-symbol, or invalid-reference failures never replace
  the previous cache.
- Treat cache writing as best effort. A fresh complete `SourceIndex` remains
  valid when the cache directory is unwritable.
- `module:clear` and Laravel's `optimize:clear` remove both the Module metadata
  cache and the incremental source-analysis cache. `module:cache` does not parse
  application source; the first source-enabled check creates this manifest.
- Increment `SourceAnalysisCache::SCHEMA_VERSION` whenever visitor semantics or
  the stored per-file contract changes.

## Acceptance evidence

- `SourceAnalysisCacheTest` proves unchanged-result identity, content changes,
  removed files, Module-owner changes, cross-file ownership re-resolution,
  invalid-schema cold fallback, best-effort write failure, and that a changed
  syntax error neither reuses nor replaces the previous cache.
- `ModuleCacheCommandTest` proves `module:clear` and `optimize:clear` remove both
  cache files.
- The benchmark uses one persistent source cache per generated fixture. Its
  normal one-warmup mode measures content-hash cache hits; zero warmups measures
  first-run parsing and manifest creation.

## Performance evidence

Command: `composer benchmark -- --format=json`

Environment: PHP 8.5.9, Darwin arm64, one warmup and three measured iterations
on 2026-08-16. No timing threshold is enforced.

| Fixture | Full parse median | Incremental median | Change |
|---|---:|---:|---:|
| 50 Modules / 5,000 PHP files | 346.271 ms | 176.538 ms | 49.0% faster |
| 100 Modules / 10,000 PHP files | 720.596 ms | 398.360 ms | 44.7% faster |

A separate zero-warmup, one-iteration run measured 380.821 ms and 770.602 ms
respectively while building the first manifest. Those cold values document the
write cost but are not portable release thresholds.

## Consequences

- Warm checks avoid repeated PHP parsing while preserving content-based
  correctness and cross-file ownership resolution.
- Hashing remains linear in source bytes. This slice optimizes parsing, not file
  discovery or content reads.
- The cache contains absolute local paths and derived source metadata. It is a
  disposable Laravel bootstrap artifact and must not be committed or shipped in
  the package archive.
- Concurrent checks may replace the same manifest atomically; either complete
  manifest is safe because future runs validate every file hash and owner.
- A later cache-hit diagnostic or CI trend format requires a separate additive
  contract; this slice does not change `module:check` text or JSON schemas.
