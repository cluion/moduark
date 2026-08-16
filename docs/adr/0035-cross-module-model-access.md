# ADR-0035: Cross-Module Eloquent Model Access

- Status: Accepted for the first `0.4.x` Level 3 slice
- Date: 2026-08-16

## Context

Level 3 treats persistence objects as Module-owned implementation details. A
consumer that imports another Module's Eloquent Model couples its types,
relations, queries, and lifecycle directly to that Module's storage design.
The existing `internal_api_access` rule often catches a Model below `Models/`,
but it enforces visibility by path convention and cannot identify persistence
objects that appear in another public-looking directory or a future explicit
export list.

The source index already uses `nikic/php-parser` name resolution for concrete
class references. It did not retain class inheritance, so it could not
distinguish an Eloquent Model from an ordinary class without filename or
namespace heuristics.

## Decision

- Retain each indexed class's resolved parent class in `SourceSymbol`. Interfaces,
  traits, and enums have no class parent.
- Increase the incremental source-analysis cache schema from `1` to `2`. An old
  cache is rejected and rebuilt cold instead of being interpreted without the
  required inheritance evidence.
- Classify a symbol as an Eloquent Model when it directly extends
  `Illuminate\Database\Eloquent\Model` or when its parent chain reaches that
  class through other indexed Module symbols. Comparison is case-insensitive
  and cyclic source ancestry terminates safely.
- Emit blocking `MOD-MODEL-001` diagnostics for every cross-Module reference to
  a classified Model. Evidence includes consumer, owner, canonical Model symbol,
  file, and line.
- Existing reference positions cover parameter, property, and return types;
  extends/implements; `new`; static access; `::class`; and `instanceof`. A
  relation target such as `belongsTo(User::class)` is therefore observable.
- Same-Module Model use and references to ordinary cross-Module classes do not
  violate this rule. A `use` statement alone remains unobserved.
- Do not guess from `Models/`, a `Model` suffix, PHPDoc, or dynamic strings.
  Indirect inheritance through a parent declared outside indexed Module source
  is not classified in this slice and is documented as a limit while Level 3
  remains globally incomplete.
- The rule is independent from `internal_api_access`; both diagnostics may
  describe the same reference because visibility and persistence isolation are
  separate contracts.

## Acceptance evidence

- AST fixture tests prove aliased direct Eloquent inheritance and indexed
  indirect inheritance.
- Rule tests cover constructor/property types, return types, `new`, static calls,
  and `::class`, while excluding same-Module Models and ordinary DTOs.
- Warm-cache tests prove retained parent metadata produces the same
  classification as cold analysis.
- Rule-runner and command tests prove Level 3 evaluates this ninth implemented
  rule while continuing to report the remaining five rules as unavailable.

## Consequences

- Level 3 gains its first concrete isolation diagnostic without relying on
  Laravel runtime reflection or autoload side effects.
- A typical Model under `Models/` may produce both `MOD-BOUNDARY-001` and
  `MOD-MODEL-001`, giving teams distinct visibility and persistence evidence.
- Applications with a shared base Model outside `app/Modules` should not treat
  this first slice as complete Model-ancestry coverage. A future Level 3 slice
  must decide between explicit Model metadata and a broader bounded source
  index before the full preset can claim completeness.
