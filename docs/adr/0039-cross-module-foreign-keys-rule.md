# ADR-0039: Cross-Module Foreign-Key Audit

- Status: Accepted for the fifth `0.4.x` Level 3 slice
- Date: 2026-08-16

## Context

Explicit table ownership identifies each Module's persistence boundary, while
the migration rule identifies who may mutate a table. Neither exposes a
database foreign key that couples two owners' migration and extraction
lifecycle. The same constraint may still be intentional in a relational modular
monolith because database-enforced integrity has real value.

Laravel offers both explicit Blueprint constraints and convenience methods that
infer a target table. Static analysis must preserve those documented method
semantics without executing migrations or guessing a runtime Eloquent model's
table.

## Decision

- Implement `cross_module_foreign_keys` as the fourth available Level 3 rule
  with the preset's existing warning severity. Enabling the rule means audit the
  coupling; it does not globally forbid Laravel foreign keys.
- Collect calls only from the first Blueprint parameter of recognized imported
  or fully qualified `Schema::create()` and `Schema::table()` callbacks,
  including `Schema::connection()` variants. This avoids treating methods on an
  arbitrary object as schema evidence.
- Recognize `foreign(...)->references(...)->on(...)` and `foreignId()`,
  `foreignUuid()`, or `foreignUlid()` chains ending in `constrained(...)`.
  Preserve Laravel's conventional table derivation from the foreign column and
  referenced column, including named arguments.
- Recognize `foreignIdFor()`, plus Laravel 13's `foreignUuidFor()` and
  `foreignUlidFor()`, when `constrained('table')` makes the table explicit.
  Without that argument, retain the model class as unresolved evidence because
  `Model::getTable()` is runtime-configurable.
- Compare the resolved source and target tables through the explicit Table
  Ownership Index. The Module containing the migration is evidence provenance,
  not a substitute for either table owner.
- Emit configured-severity `MOD-FK-001` when the two owners differ. The default
  warning exposes extraction coupling while allowing teams to retain deliberate
  database integrity.
- Emit fixed warning `MOD-FK-002` when either table cannot be resolved. Emit
  configured-severity `MOD-FK-003` when a resolved table lacks ownership.
- Store non-empty per-file foreign-key candidates in source-cache schema `5`.
  Older schemas and malformed current-schema rows fall back to complete cold
  analysis.
- Register the rule in the normal runner. Level 3 evaluates twelve implemented
  rules and remains incomplete only because transaction and explicit-export
  rules are unavailable.

## Deliberate limits

- Custom Blueprint macros and wrappers, raw SQL constraints, application-level
  migrations outside discovered Modules, and callback values reached through
  variables or other data-flow are not inferred.
- Runtime Facade aliases and runtime Eloquent table names are not guessed.
- Connection-to-schema mapping, configured table prefixes, current database
  metadata, and migration execution order remain outside static analysis.
- Composite constraints are classified by their two tables; individual column
  compatibility and referential actions are database-schema concerns.
- One warning does not prescribe removal. A project-wide policy may disable the
  rule; an intentional individual constraint should use a narrow reviewed
  suppression so its reason remains auditable.

## Acceptance evidence

- Collector tests cover explicit constraints, all three supported fluent ID
  methods, Laravel convention inference, named arguments, explicit and runtime
  Model-based targets, connection calls, arrow functions, dynamic evidence,
  nested closures, and unrelated objects.
- Rule tests cover same-owner constraints, case-insensitive lookup,
  cross-owner warnings, missing ownership, unresolved runtime model evidence,
  deterministic diagnostics, and fixed warning behavior.
- Warm-cache tests prove schema `5` retains identical foreign-key evidence and
  malformed current-schema rows trigger a complete cold fallback.
- Rule-runner and JSON command tests prove Level 3 evaluates twelve rules and
  reports only two unavailable rules.
- The supported call mapping was checked against Laravel `12.66.0` source and
  the local Laravel `13.25.0` dependency before implementation; the two extra
  typed Model helpers are correctly documented as Laravel 13-only APIs.

## Consequences

- Cross-owner database constraints become visible at their migration source
  without requiring a database connection or migration execution.
- Same-owner foreign keys remain valid, while missing or dynamic ownership can
  no longer disappear into a false pass.
- Teams can choose integrity or extraction flexibility explicitly and retain a
  reviewable record for exceptions.
- Level 3 still exits `2` until cross-Module transaction warnings and explicit
  exports are implemented.

## Performance evidence

`composer benchmark -- --format=json` on PHP 8.5.9 / Darwin arm64, with one
warmup and three measured content-hash-cache iterations, reported median check
times of 193.936 ms for 50 Modules / 5,000 PHP files and 435.837 ms for 100
Modules / 10,000 PHP files. The benchmark intentionally enforces no portable
timing threshold. Empty `foreign_keys` rows are omitted from schema `5`
manifests, so the warm-cache payload grows only for recognized constraints.
