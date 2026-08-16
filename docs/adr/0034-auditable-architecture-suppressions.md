# ADR-0034: Auditable Architecture Suppressions

- Status: Accepted for the final `0.3.x` Developer Experience slice
- Date: 2026-08-16

## Context

Some architecture violations need a narrow temporary exception while a team
keeps the underlying rule active. A global rule override weakens the selected
Level for the whole application, while a baseline intentionally adopts a larger
set of existing debt and does not record why one specific exception exists.

An inline PHP Attribute would place analyzer policy in production application
source and couple suppression syntax to a particular statement or symbol shape.
Moduark instead needs reviewable policy that can cover file, line, symbol, and
Module relationship diagnostics consistently.

## Decision

- Store explicit exceptions in a versioned JSON manifest. The default path is
  `moduark-suppressions.json` at the Laravel application root and can be changed
  with `modules.architecture.suppressions`.
- Every entry requires a recognized stable rule ID, diagnostic code, and
  non-empty reason.
- Every entry must select a repository-relative file, a symbol, or both consumer
  and target Modules. A file can additionally select one positive line, and
  selectors can be combined to narrow the match. Rule-and-code-only global
  ignores are rejected.
- The manifest rejects unknown fields, absolute or parent-traversing paths,
  duplicate selectors, invalid schema versions, and malformed JSON. If one
  violation matches more than one entry, the overlapping policy is a tool error
  instead of depending on declaration order.
- Suppression matching is exact. Each entry is audited as `matched`, `stale`
  when its rule ran without a match, or `inactive` when its rule was not
  evaluated at the selected Level.
- Normal text output always summarizes matched, stale, and inactive debt;
  `module:check --show-suppressions` lists scopes, reasons, and match counts.
  JSON schema version `1` gains an additive top-level `suppressions` field, and
  GitHub output gains a summary notice.
- Suppressions run after the raw architecture check but before baseline
  filtering. `module:baseline` consumes that suppression-aware, unbaselined
  report, so explicitly explained exceptions cannot also enter the baseline.
- Suppression expiry is deferred. Stale and inactive reporting makes current
  auditability explicit without inventing date semantics in this slice.

## Acceptance evidence

- Unit tests prove exact file-and-line matching, stale versus inactive states,
  overlap detection, mandatory reasons, portable paths, narrow scope, and typo
  rejection.
- Feature tests prove text detail output, additive JSON metadata, GitHub notices,
  malformed-manifest tool errors, and suppression-aware baseline generation.
- Existing architecture, baseline, config-cache, distribution, compatibility,
  and clean-installation gates remain unchanged.

## Consequences

- Exceptions are visible in code review without adding analyzer Attributes to
  application classes.
- Moving a file or symbol deliberately resurfaces an exact exception for review.
- Stale suppressions do not fail a valid architecture check, but remain visible
  until explicitly removed. Inactive entries are not mislabeled stale when a
  lower Level simply did not evaluate their rule.
- Teams should use suppressions for a small number of explained exceptions,
  baselines for reviewed brownfield adoption, and global rule overrides only
  when they intentionally accept a weaker application-wide Level guarantee.
