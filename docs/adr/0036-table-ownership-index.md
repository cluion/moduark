# ADR-0036: Explicit Table Ownership Index

- Status: Accepted for the second `0.4.x` Level 3 slice
- Date: 2026-08-16

## Context

Database query, migration, foreign-key, and transaction rules all need one
authoritative answer to: which Module owns this table? Inferring ownership only
from migrations would make renamed, legacy, shared, or externally managed tables
ambiguous. Query rules built before this index would each invent subtly different
matching and conflict behavior.

The original Level 3 plan reserved `Module::tables()` as the explicit authority,
with migration inference and configuration overrides as later inputs. Module
descriptors and their deployment cache already provide deterministic scalar
metadata transport.

## Decision

- Add an inherited `Module::tables(): list<string>` method with an empty default.
  Existing Modules require no change.
- Accept unquoted dot-separated table names whose segments start with an ASCII
  letter, `_`, or `$`, followed by letters, digits, `_`, `$`, or `-`. This
  supports normal Laravel names and qualified names such as `audit.events`
  without accepting query aliases or SQL quoting as ownership identity.
- Preserve declared spelling for inspection, but normalize lookups and conflict
  detection to lowercase. This deliberately rejects portable ambiguity such as
  `Users` and `users` being claimed by different Modules.
- Reject duplicate names inside one Module and reject a canonical table claimed
  by multiple Modules. Owner lists and table iteration are deterministic.
- Store tables in `ModuleDescriptor` scalar payloads. Increase the Module cache
  schema from `1` to `2`, causing older deployment caches to be bypassed and
  rebuilt instead of silently omitting the new metadata.
- Use one shared canonical TableName validator for source metadata and cached
  descriptors, so a malformed current-schema cache cannot bypass the compiler.
- Expose `TableOwnershipIndex` through the Laravel container and every
  `AnalysisContext`. `module:cache` validates the index before writing, and
  `module:inspect` displays the selected Module's indexed owned tables.
- Treat explicit metadata as authoritative in this slice. Migration inference,
  shared/legacy config overrides, connection scoping, and table-prefix policy are
  deferred until their rules have concrete evidence and diagnostics.
- Do not mark `database_ownership` implemented. An ownership index without a
  Laravel-aware query analyzer is infrastructure, not enforcement.

## Acceptance evidence

- Compiler tests cover empty defaults, legacy descriptor payloads, qualified
  names, malformed names, and case-insensitive duplicates.
- Index tests prove deterministic lookup, per-owner tables, single ownership,
  duplicate descriptor rejection, and `AnalysisContext` access.
- Cache tests prove schema `2` scalar round trips and safe unknown-schema
  fallback. Inspection tests prove non-empty owned tables are visible.
- The full PHPUnit, PHPStan, distribution, and Laravel 12/13 dependency gates
  remain required before this slice is committed.

## Consequences

- Future persistence rules share one owner identity and cannot silently disagree
  about case or duplicate claims.
- Declaring `tables()` changes deployable Module metadata, so deployments must
  rebuild `module:cache` or run `optimize` after changes.
- Applications with deliberately shared tables should assign one authoritative
  owner for now. A reviewed shared/legacy override contract must be designed
  before multiple ownership can be represented.
- A qualified name is one ownership key. Mapping unqualified queries to a schema
  or connection remains future analysis and must not be guessed.
