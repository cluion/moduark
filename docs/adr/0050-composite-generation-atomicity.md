# ADR-0050: Composite Generation Ownership and Atomicity

- Status: Accepted for `1.1` Slice G0-C
- Date: 2026-08-23

## Context

Laravel 12 and 13 expose `make:model --factory --migration`, but the nested
commands do not retain a Moduark Module target. A qualified model name is written
below the Module while Laravel's factory and migration return to application-level
`database/` paths. Forwarding those options would therefore produce a plan whose
related files have different owners.

G0-B can represent multiple targets and preflight all exact paths, but sequential
writes still risk leaving a partial Module when a later generator fails. Explicit
overwrite also requires restoration of the original contents, not only deletion
of new files.

## Decision

- Expose `--factory` and `--migration` only for the existing `model` descriptor.
  They do not register factory or migration as new top-level Maker types.
- Resolve every related target before execution:
  `Models/<Name>.php`, `Database/Factories/<Name>Factory.php`, and one timestamped
  `Database/Migrations/*_create_<table>_table.php`.
- Use Moduark-owned, package-distributed templates for Module factory/migration
  artifacts. When `--factory` is selected, use a Moduark-owned model template
  whose `newFactory()` returns the Module-owned factory. Standalone model and
  controller generation continues delegating to Laravel's native Makers.
- Derive the migration table from the short model name using Laravel's plural
  StudlyCase and snake-case rules. Reuse one existing semantic migration target;
  reject multiple matching timestamps as ambiguous before any write.
- Treat regular existing files as collisions unless `--force` is explicit.
  Never follow or overwrite a symlink or directory target, including with force.
- Snapshot every planned target before execution. On failure, remove newly
  created files, restore overwritten contents and modes, then remove empty
  directories created by the plan.
- Surface every rollback failure and return a tool error. The command must never
  claim that a failed rollback was atomic.
- `--dry-run` renders the exact same timestamped, Module-relative plan and does
  not create directories or files.

## Acceptance

- One feature fixture proves dry-run and successful model + factory + migration
  generation, including nested names, namespaces, migration table, and runtime
  `Model::factory()` resolution.
- A fixture with all three targets pre-existing reports every collision and
  leaves all contents unchanged.
- An injected final-target write failure removes the earlier model and factory.
- Executor tests prove cleanup of new files, restoration of overwritten source,
  empty-directory cleanup, and explicit rollback-failure reporting.
- Clean Laravel 12 and 13 applications run the three-target dry-run, confirm zero
  mutation, then execute the same composite plan successfully.
- The nwidart Laravel 12 and 13 matrix retains command ownership and confirms the
  independent Moduark help contract for both options.

## Consequences

- Composite templates become part of Moduark's reviewed distribution contract;
  Laravel custom application Maker stubs do not implicitly alter them.
- Model generation with `--factory` is intentionally Moduark-owned so runtime
  factory lookup does not depend on Laravel's application-level namespace guess.
- Process termination or host failure during rollback cannot be made transactional
  by the filesystem. Detected runtime rollback failures are explicit; a future
  journaled staging design remains a separate hardening slice if needed.
- Controller/request/policy/seed/test composites and new top-level Maker types
  remain out of scope.
