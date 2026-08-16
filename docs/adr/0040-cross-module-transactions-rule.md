# ADR-0040: Cross-Module Transaction Audit

- Status: Accepted for the sixth `0.4.x` Level 3 slice
- Date: 2026-08-16

## Context

Explicit table ownership and per-query ownership checks identify direct table
boundary violations, but they do not expose when one atomic workflow couples the
persistence of multiple Module owners. Such a transaction may be intentional in
a relational modular monolith, yet it is extraction and orchestration evidence
that Level 3 should make reviewable.

Laravel documents closure-based `DB::transaction()` and supports both Query
Builder and Eloquent operations within transactions. Static analysis needs a
narrow contract that recognizes direct table evidence without claiming to follow
arbitrary application data-flow or runtime model behavior. See Laravel's
[database transaction documentation](https://laravel.com/docs/12.x/database#database-transactions).

## Decision

- Implement `cross_module_transactions` as the fifth available Level 3 rule with
  the preset's existing warning severity. It audits direct cross-owner writes;
  it does not prohibit atomic workflows.
- Analyze only inline Closure or ArrowFunction callbacks passed to imported or
  fully qualified `DB::transaction()` and
  `DB::connection()->transaction()`, including named callback arguments.
- Collect direct Query Builder mutation chains rooted in `DB::table()` or
  `DB::query()->from()`, including `DB::connection()` variants. Recognize
  `insert`, `insertOrIgnore`, `insertGetId`, `insertUsing`,
  `insertOrIgnoreUsing`, `update`, `updateFrom`, `updateOrInsert`, `upsert`,
  `increment`, `incrementEach`, `decrement`, `decrementEach`, `delete`, and
  `truncate`.
- Normalize common literal aliases before table-ownership lookup. Preserve a
  dynamic table expression as unresolved evidence rather than guessing.
- Retain direct `DB::insert()`, `update()`, `delete()`, and
  `affectingStatement()` calls, including connection variants, as unresolved
  write evidence because raw SQL target parsing is outside this slice.
- Emit one configured-severity `MOD-TRANSACTION-001` per transaction that
  directly mutates tables owned by more than one Module. Same-owner transactions
  pass this rule; the independent `database_ownership` rule still evaluates
  whether the source Module may access each table.
- Emit one fixed warning `MOD-TRANSACTION-002` per transaction with unresolved
  direct write targets. Emit one configured-severity `MOD-TRANSACTION-003` when
  resolved write tables have no declared owner. Diagnostics retain deterministic
  owner and table evidence.
- Store non-empty per-file transaction scopes and direct writes in source-cache
  schema `6`. Older schemas and malformed current-schema rows fall back to
  complete cold analysis.
- Register the rule in the normal runner. Level 3 evaluates thirteen implemented
  rules and remains incomplete only because `explicit_public_exports` is
  unavailable.

## Deliberate limits

- Repository, Port, service, and Eloquent writes are not inferred. Those calls
  require interprocedural and runtime model analysis beyond direct AST evidence.
- Builders stored in variables, returned by helpers, or passed into callbacks are
  not followed. Nested arbitrary closures are not attributed to an outer
  transaction merely because they are lexically declared inside it.
- Raw SQL strings, connection-to-schema mapping, configured table prefixes, and
  current database metadata are not parsed or inferred.
- Callable variables passed to `transaction()` are not followed.
- Manual `beginTransaction()`, `commit()`, and `rollBack()` scopes are not paired;
  control-flow and exceptional exits require a separate analysis contract.
- A cross-owner warning does not prescribe removal. Intentional atomic
  orchestration should use a narrow reviewed suppression so its reason remains
  auditable.

## Acceptance evidence

- Collector tests cover Closure and ArrowFunction callbacks, named arguments,
  Facade aliases, connection variants, table aliases, `DB::table()` and
  `DB::query()->from()` roots, dynamic tables, raw write methods, reads, unrelated
  objects, nested closures, callable callbacks, and manual transaction calls.
- Rule tests cover same-owner transactions, case-insensitive ownership,
  cross-owner warnings, de-duplicated table evidence, unresolved direct writes,
  missing ownership, deterministic diagnostics, and severity elevation while
  unresolved evidence remains a warning.
- Warm-cache tests prove schema `6` retains identical transaction evidence and
  malformed current-schema rows trigger a complete cold fallback.
- Rule-runner and JSON command tests prove Level 3 evaluates thirteen rules and
  reports only `explicit_public_exports` as unavailable.
- The supported Query Builder mutation names were checked against local Laravel
  `13.25.0` source. The dependency and clean-installation release gates verify the
  same package against the supported Laravel 12 and 13 matrix.

## Consequences

- Direct multi-owner transaction coupling becomes visible at the orchestration
  source without a database connection or query execution.
- Same-owner transactions remain valid, while unresolved or unowned direct
  writes cannot silently become a false pass.
- The narrow callback and fluent-root contract limits false positives but does
  not claim complete transaction coverage. Documentation must keep these limits
  explicit.
- Level 3 still exits `2` until explicit public exports are implemented.

## Performance evidence

`composer benchmark -- --format=json` on PHP 8.5.9 / Darwin arm64, with one
warmup and three measured content-hash-cache iterations, reported median check
times of 190.937 ms for 50 Modules / 5,000 PHP files and 425.557 ms for 100
Modules / 10,000 PHP files. The benchmark intentionally enforces no portable
timing threshold. Empty `transaction_scopes` rows are omitted from schema `6`
manifests, so the warm-cache payload grows only for recognized transactions with
direct writes.
