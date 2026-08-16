# ADR-0037: Laravel Query Table Ownership

- Status: Accepted for the third `0.4.x` Level 3 slice
- Date: 2026-08-16

## Context

The explicit Table Ownership Index answers which Module owns a canonical table,
but it cannot enforce a boundary until source analysis can identify Laravel
query operations. A generic method-name scan would misclassify unrelated domain
objects with methods such as `join()`, while regex cannot reliably distinguish
aliases, dynamic expressions, nested calls, or source locations.

Level 3 must also distinguish three outcomes: a known cross-Module table, a
known table with no declared owner, and an expression that cannot be resolved
statically. Treating the third outcome as a clean pass would overstate the
guarantee; guessing a table owner would be worse.

## Decision

- Extend the existing `nikic/php-parser` traversal with one
  `DatabaseTableAccessCollector`; no second filesystem scan or runtime database
  connection is introduced.
- Recognize imported or fully qualified `Illuminate\Support\Facades\DB` and
  `Schema` calls for:
  - `DB::table()` and `Schema::table()`;
  - `DB::connection()->table()` and `Schema::connection()->table()`;
  - `from()`, `join()`, `joinWhere()`, `leftJoin*()`, `rightJoin*()`, and
    `crossJoin()` on fluent builders rooted in `DB::table()`, `DB::query()`, or
    their connection equivalents.
- Collect an explicit `DB::table()` nested in `joinSub()`, `fromSub()`, or
  another callback independently. Do not treat a subquery alias as a physical
  table.
- Accept canonical literal table names and normalize the common literal alias
  forms `table as alias` and `table alias` to the physical table. A separate
  alias argument already leaves the first literal canonical.
- Emit `MOD-TABLE-001` with the configured rule severity when a Module directly
  accesses another Module's table. Emit `MOD-TABLE-002` when a resolved literal
  table has no owner. Same-owner access passes, with ownership comparison
  remaining case-insensitive.
- Emit non-blocking `MOD-TABLE-003` when the table argument is dynamic or a
  literal expression is outside the supported canonical/alias grammar. The
  warning carries operation, file, line, and stable expression evidence so it
  can be reviewed or narrowly suppressed; no owner is inferred.
- Include `Schema::table()` in database ownership because it is still direct
  table access. The later `migration_ownership` rule remains a separate contract
  about where and how schema changes are declared, so one migration may
  correctly violate both rules.
- Store non-empty per-file table-access candidates beside symbols and
  class-reference candidates; omit the field for files without query evidence
  to avoid inflating large warm-cache manifests. Increase
  `SourceAnalysisCache::SCHEMA_VERSION` from `2` to `3`; older or malformed
  current-schema evidence falls back to a complete cold analysis.
- Register `database_ownership` as the second implemented Level 3 rule. Level 3
  remains incomplete because four enabled rules are still unavailable.

## Deliberate limits

- Raw SQL strings, Eloquent `$table` / `getTable()` inference, query builders
  stored in variables, and callback parameters such as `$query->from()` require
  later data-flow or SQL analysis.
- Runtime facade aliases such as an unimported bare `DB` class name are not
  guessed. Imported aliases and fully qualified Facades are deterministic.
- Connection-to-schema mapping, configured table prefixes, and shared/legacy
  ownership overrides remain future policy. Qualified names are matched only
  when the query literal and metadata use the same canonical key.
- Arbitrary objects with a `join()`, `from()`, or `table()` method are ignored
  unless their fluent expression has a recognized Laravel Facade root.

## Acceptance evidence

- Source-index tests cover imported Facade aliases, connection-specific
  builders, canonical and aliased names, all supported fluent roots, nested
  subquery roots, unresolved arguments, false-positive avoidance, and
  deterministic output.
- Rule tests cover same-owner access, case-insensitive ownership,
  cross-Module access, unowned tables, and non-blocking unresolved evidence.
- Warm-cache tests prove schema `3` retains the same table evidence as cold
  analysis and rejects malformed current-schema table rows.
- Rule-runner and command tests prove Level 3 evaluates ten implemented rules
  and reports only the remaining four unavailable rules.

## Consequences

- Applications can begin enforcing explicit query ownership without waiting for
  the rest of Level 3.
- A literal query to a legacy or external table must assign one authoritative
  Module owner; the rule will not silently treat an unclaimed table as shared.
- Warning-only unresolved evidence returns exit `0` under the existing exit
  policy but remains visible in text, JSON summary counts, GitHub annotations,
  suppressions, and baselines.
- Level 3 still returns exit `2` until migration ownership, cross-Module foreign
  keys, transaction warnings, and explicit exports are implemented.

## Performance evidence

`composer benchmark -- --format=json` on PHP 8.5.9 / Darwin arm64, with one
warmup and three measured content-hash-cache iterations, reported median check
times of 191.768 ms for 50 Modules / 5,000 PHP files and 426.543 ms for 100
Modules / 10,000 PHP files. The benchmark intentionally enforces no portable
timing threshold. Empty `table_accesses` rows are omitted from schema `3`
manifests to keep the warm-cache payload bounded to actual query evidence.
