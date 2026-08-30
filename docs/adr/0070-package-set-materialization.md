# ADR-0070: Package Set Materialization and Rollback

- Status: Accepted
- Date: 2026-08-30

## Context

ADR-0069 makes a dependency-closed package set deterministic and reviewable, but
operators still have to run one materialization per package. That can publish an
incomplete set when a later package fails. A normal filesystem has no atomic
transaction spanning several sibling directories, so the contract must separate
successful publication from failure recovery and report any rollback residue.

## Decision

- `moduark:export-set` remains read-only unless the explicit `--materialize`
  option is present. Existing invocations therefore retain the ADR-0069 plan-only
  behavior.
- Materialization accepts only a ready package-set plan. It prepares every
  package in a private sibling staging directory before publishing any target.
- The command rechecks every target after preparation. Existing targets,
  symlinks, late collisions, overlapping targets, or a blocked nested package
  plan prevent publication; targets are never merged or overwritten.
- Prepared packages publish in the dependency-first order already fixed by the
  set plan. Each publish is a same-filesystem directory rename.
- If any preparation or publish step fails, private staging directories are
  removed and newly published targets are removed in reverse order. Parent
  directories created by the attempt are removed only while empty.
- Successful JSON adds `dry_run=false` and dependency-ordered
  `published_targets`. Failure JSON uses tool-error exit `2` and reports
  `published_before_rollback`, `remaining_targets`, and absolute
  `rollback_failures`. A blocked materialization uses exit `1`, writes nothing,
  and reports empty publication evidence.
- Single-package and package-set materialization share the package preparer and
  target guard so copy, generation, namespace rewrite, PHP parsing, target-root,
  symlink, and collision behavior cannot drift.

## Verification

Permanent `User` / `Order` regressions cover full-set success, dependency-order
publication, second-publish failure with successful rollback, a late external
target collision that is preserved, rollback cleanup failure with explicit
remaining-target evidence, and blocked-set no-write behavior. Laravel 12 and 13
clean installations materialize the set, strictly validate both generated
Composer packages, require only `Order`, install `User` transitively, and run the
canonical registry and runtime probes.

## Boundaries

This is failure-atomic orchestration, not a filesystem transaction. During a
successful multi-rename publish, another process may briefly observe a prefix of
the set. Process termination, power loss, or a rollback cleanup failure can leave
a newly published target; the command reports cleanup failures it can observe
but does not keep a durable recovery journal. It does not invoke Composer during
materialization, publish packages, select versions, overwrite targets, or offer
`--force`.

## Consequences

Ordinary failures no longer leave a silently partial package set, and automation
has machine-readable evidence when cleanup is incomplete. The explicit option
preserves backward compatibility and keeps planning safe by default. Consumers
that require isolation from concurrent readers still need an external release
directory or deployment-level pointer swap around the complete set.
