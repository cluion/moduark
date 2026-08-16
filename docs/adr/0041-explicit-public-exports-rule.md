# ADR-0041: Explicit Public Exports

- Status: Accepted for the seventh and final `0.4.x` Level 3 slice
- Date: 2026-08-16

## Context

Level 1 defines a provider-owned Public API through the Module entry class and
the `Contracts/`, `Data/`, and `Events/` directories. This convention is easy to
adopt, but Level 3 needs a reviewable allowlist so a symbol does not become a
long-lived external contract merely because of its directory.

The existing AST source index already resolves named class-like symbols and
cross-Module references. Explicit exports should reuse that evidence, preserve
Level 1 and Level 2 behavior, and complete Level 3 without introducing a second
parser or silently broadening the established convention.

## Decision

- Add `Module::exports(): array` returning a unique list of existing class,
  interface, trait, or enum class-strings. Empty remains the backward-compatible
  default.
- Retain exports in `ModuleDescriptor` and deterministic Module-cache schema `3`.
  Older schemas are bypassed; malformed current-schema export rows fail with the
  cache path.
- Treat the Module entry class as an implicit public identity. It need not list
  itself in `exports()` because dependencies and discovery use that identity.
- Implement `explicit_public_exports` as the sixth available Level 3 isolation
  rule with blocking error severity.
- Emit `MOD-EXPORT-001` for each direct cross-Module AST reference whose target
  is not in the provider's explicit exports.
- Emit `MOD-EXPORT-002` when an export is not found in indexed Module source.
  This rejects framework, vendor, stale-cache, and otherwise external symbols as
  Module exports even when the runtime class exists.
- Emit `MOD-EXPORT-003` when a Module attempts to export an indexed symbol owned
  by another Module. Only the source owner can publish that contract.
- Compose explicit exports with the existing `internal_api_access` convention.
  The allowlist narrows the Public API; it does not make a `Services/` or other
  convention-internal class public. This is the conservative migration contract
  for `0.4`.
- Show `Explicit exports` and `Public API (convention)` as separate
  `module:inspect` rows so the effective intersection is auditable.
- Keep source-cache schema `6`: the new rule reuses existing symbols and
  references without changing per-file source evidence.
- Register the final rule in the normal runner. Level 3 evaluates all fourteen
  rules, reports no unavailable rules, and can produce a complete exit-0 pass.

## Deliberate limits

- PHPDoc, string class names, service-container keys, configuration values, and
  other dynamic references remain outside the named AST reference index.
- Explicit exports validate visibility and ownership, not semantic-versioning or
  backward-compatible API changes. API diffing requires a separate contract.
- File-level functions, constants, routes, views, translations, and data schemas
  are not class-like exports in this metadata.
- An unused export is allowed. Usage auditing and export pruning can be added
  later without changing the access rule.
- Explicit metadata deliberately cannot broaden the Level 1 convention in this
  release. A future policy that replaces rather than composes classifiers would
  be a separate breaking architecture decision.

## Acceptance evidence

- Metadata tests cover default-empty behavior, class/interface/trait/enum
  exports, deterministic descriptor round trips, and duplicate rejection.
- Module-cache tests prove schema `3` deterministic round trips, schema `2`
  bypass, malformed export rejection, and retained exports.
- Rule tests cover exported and unexported references, implicit Module entry
  access, non-indexed exports, cross-owner export claims, deterministic source
  evidence, and blocking severity.
- Architecture-checker, runner, JSON command, inspection, and ServiceProvider
  integration tests prove the rule requires source analysis and completes the
  fourteen-rule Level 3 preset.
- Full dependency and clean-installation release gates verify the public
  metadata and inspection additions against supported Laravel 12 and 13.

## Consequences

- Level 3 teams gain a reviewable provider-owned allowlist without weakening the
  familiar convention used by lower Levels.
- Moving a symbol into `Contracts/`, `Data/`, or `Events/` no longer exposes it
  at Level 3 until the owning Module explicitly exports it.
- Adding or removing exports requires rebuilding Module metadata cache, while the
  incremental source-analysis cache remains reusable.
- Level 3 is now a complete implemented preset rather than an incomplete future
  configuration.

## Performance evidence

`composer benchmark -- --format=json` on PHP 8.5.9 / Darwin arm64, with one
warmup and three measured content-hash-cache iterations, reported median check
times of 191.382 ms for 50 Modules / 5,000 PHP files and 427.747 ms for 100
Modules / 10,000 PHP files. The benchmark intentionally enforces no portable
timing threshold. Level 1 benchmark fixtures inherit empty exports, so the public
metadata addition does not add source-analysis work.
