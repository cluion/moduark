# ADR-0061: Extractability Diagnostics Contract

- Status: Accepted
- Date: 2026-08-29

## Context

Copying a Module directory into a Composer package can produce a plausible but
unusable artifact. Before `moduark:export --dry-run` can plan any files, it needs
a deterministic preflight contract that identifies unsupported layouts and
application-owned runtime inputs without changing the application.

The first gate must not claim that a package is independently installable. That
requires later Composer dependency inference and a real Testbench installation.

## Decision

- `moduark:doctor <module> --extractable` inspects one active Module without
  writing files or activation state.
- JSON schema version `1` contains `mode`, `status`, the Module identity, ordered
  checks, blocking checks, the exit code, and a nullable error.
- A report with no blocker uses `ready_for_export_dry_run`, not `extractable`.
  It authorizes only the next planning phase.
- The first five stable diagnostic codes are:
  - `MOD-EXTRACT-LAYOUT-001` for supported standalone or nwidart source layout;
  - `MOD-EXTRACT-AUTOLOAD-001` for entry-class/source identity;
  - `MOD-EXTRACT-PROVIDER-001` for Module-owned ServiceProviders;
  - `MOD-EXTRACT-RESOURCE-001` for Module-owned file resources; and
  - `MOD-EXTRACT-COUPLING-001` for declared metadata classes that live outside
    every active Module and Composer vendor tree.
- Exit `0` means ready for export dry-run, `1` means blockers were found, and
  `2` means invalid input or tool failure.
- Reports and evidence are sorted deterministically. Disabled or unknown
  Modules are not inspectable because export planning must use the committed
  active runtime inventory.
- Ownership distinguishes code from package layout: providers and declared
  classes use the Module source root, while file-backed resources use the full
  Module root. In nwidart layouts, the latter includes conventional siblings of
  `app/` such as `routes/`, `Database/`, and `resources/`.

## Boundaries

LC1-A checks declared Module metadata and the canonical resource manifest. It
does not yet infer Composer requirements, prove tests under package Testbench,
or replace the existing architecture rules for undeclared dependencies,
Capability completeness, table ownership, foreign keys, transactions, and
explicit exports. Those signals must be composed into later export preflight
slices before files can be emitted.

## Consequences

Automation can consume stable blocker identities before an export command
exists. Future checks can be added without changing the meaning of existing
codes, while actual package generation remains unavailable until its dry-run
plan and standalone installation gate are implemented.
