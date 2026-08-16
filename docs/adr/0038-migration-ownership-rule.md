# ADR-0038: Laravel Migration Table Ownership

- Status: Accepted for the fourth `0.4.x` Level 3 slice
- Date: 2026-08-16

## Context

The Table Ownership Index and query boundary establish who owns a table and who
may query it. They do not prevent one Module's migration from creating,
altering, renaming, or dropping another Module's table. Laravel migration files
are ordinary PHP, commonly returned as anonymous `Migration` classes, so the
rule must identify Schema calls without relying on a named migration class.

Migration history adds another constraint: a shipped `rename()` or `drop()` may
refer to a historical table name that is no longer live. Inferring its owner
from migration order would conflict with the explicit ownership authority and
would require database-state simulation. The current rule must keep unknown and
dynamic evidence visible instead of silently guessing.

## Decision

- Extend the existing `nikic/php-parser` traversal with one
  `SchemaMutationCollector`; anonymous migration classes require no special
  execution or reflection.
- Recognize imported or fully qualified `Illuminate\Support\Facades\Schema`
  calls for `create()`, `table()`, `rename()`, `drop()`, and `dropIfExists()`,
  including the same methods directly on `Schema::connection()`.
- Record `rename()` as two operands, `from` and `to`. Every literal operand is
  checked independently against the explicit Table Ownership Index.
- Require recognized schema mutations to be below the source Module's exact
  `Database/Migrations/` directory. Emit configured-severity
  `MOD-MIGRATION-003` once per call when the mutation is elsewhere in Module
  source.
- Inside the migration directory, emit configured-severity
  `MOD-MIGRATION-001` for another Module's table and `MOD-MIGRATION-002` for a
  literal table with no owner. Same-owner lookup remains case-insensitive.
- Emit non-blocking `MOD-MIGRATION-004` for a dynamic argument or unsupported
  string literal. The evidence retains the operation, operand, file, and line;
  no owner is inferred.
- Treat `Module::tables()` as authoritative for current and historical names.
  A shipped rename or drop therefore keeps its historical name declared, or
  uses one narrow reviewed suppression for intentional orchestration.
- Keep `Schema::table()` in both the query and migration collectors. A call may
  correctly violate direct database access and migration placement/ownership at
  the same source location.
- Store non-empty schema-mutation candidates beside existing per-file evidence
  and omit empty rows. Increase `SourceAnalysisCache::SCHEMA_VERSION` from `3`
  to `4`; older or malformed current-schema evidence falls back to complete
  cold analysis.
- Register `migration_ownership` as the third implemented Level 3 rule. Level 3
  remains incomplete because three enabled rules are still unavailable.

## Deliberate limits

- Schema macros, custom wrapper methods, raw SQL schema statements, and calls
  through runtime Facade aliases are not guessed.
- Blueprint column operations are attributed to the table named by their
  recognized enclosing Schema call. Foreign table targets expressed through
  Blueprint constraints belong to the later cross-Module foreign-key rule.
- Application-level migrations outside discovered Module roots are not indexed
  by this Module-source rule. Applications adopting Level 3 should first move
  owned migrations into the supported Module convention.
- Connection-to-schema mapping, configured table prefixes, migration execution
  order, and live database state remain outside static ownership analysis.
- Data migrations through recognized DB queries remain subject to
  `database_ownership`; arbitrary raw SQL and cross-Module orchestration require
  explicit review and narrow suppression.

## Acceptance evidence

- Source-index tests cover imported Schema aliases, anonymous classes, all five
  operations, connection variants, both rename operands, invalid literals,
  dynamic arguments, deterministic output, and unrelated-object avoidance.
- Rule tests cover same-owner and case-insensitive access, cross-Module and
  unowned tables, historical rename names, outside-directory mutations, and
  non-blocking unresolved evidence.
- Warm-cache tests prove schema `4` retains identical mutation evidence and
  malformed current-schema rows fall back to complete cold analysis.
- Rule-runner and command tests prove Level 3 evaluates eleven implemented
  rules and reports only the remaining three unavailable rules.
- The full PHPUnit, PHPStan, distribution, dependency, and Laravel 12/13 clean
  installation gates remain required before this slice is committed.

## Consequences

- A Module can no longer mutate another Module's table through the recognized
  Laravel Schema API without a blocking diagnostic.
- Current and historical ownership stay deterministic and reviewable without
  replaying migrations or connecting to a database.
- Deliberate cross-Module orchestration remains possible through the existing
  suppression manifest, with source location and reason visible in review.
- Level 3 still exits `2` until cross-Module foreign keys, transaction warnings,
  and explicit exports are implemented.

## Performance evidence

`composer benchmark -- --format=json` on PHP 8.5.9 / Darwin arm64, with one
warmup and three measured content-hash-cache iterations, reported median check
times of 200.972 ms for 50 Modules / 5,000 PHP files and 439.991 ms for 100
Modules / 10,000 PHP files. The benchmark intentionally enforces no portable
timing threshold. Empty `schema_mutations` rows are omitted from schema `4`
manifests so the warm-cache payload grows only when source contains recognized
Schema operations.
