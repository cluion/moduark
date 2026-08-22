---
name: moduark-development
description: Adopt, configure, inspect, troubleshoot, and upgrade cluion/moduark boundaries in Laravel applications. Use when working with Moduark Modules, architecture Levels, module:check diagnostics, graphs, baselines, suppressions, or PHPStan integration.
---

# Moduark Development

Use the installed Moduark package as the authority for architecture behavior.
Coordinate its existing CLI and documentation; do not recreate analyzer logic
inside the agent workflow.

## Establish the Effective State

Before recommending or making a change:

1. Read the consumer repository's agent instructions and relevant application
   code.
2. Run `git status --short` and preserve unrelated work.
3. Locate the installed package and version with
   `composer show cluion/moduark --path`.
4. Inspect `config/modules.php` when published, the package default
   `config/modules.php`, Composer PSR-4 roots, and existing Module entry classes.
5. Run the smallest read-only inventory needed, usually `module:list`,
   `module:inspect`, `module:graph`, or `module:check --format=json`.

If the package or expected commands are unavailable, stop and report the
missing prerequisite. Do not invent a Moduark contract from memory.

## Choose the Workflow

- For installation, initial boundaries, or a Level change, read
  [Adoption and Levels](references/adoption-and-levels.md).
- For a diagnostic, incomplete report, baseline, or suppression, read
  [Diagnostics and Debt](references/diagnostics-and-debt.md).
- For inspection, graphing, caching, PHPStan integration, or an upgrade, read
  [Inspection and Upgrades](references/inspection-and-upgrades.md).

Also read the relevant installed package documentation identified by the
reference. Package docs and executable output override summaries in this skill.

## Make a Bounded Change

1. State the requested architecture outcome and current effective Level.
2. Change one ownership, dependency, Port/Adapter, resource, or configuration
   boundary at a time.
3. Prefer repairing metadata or code over weakening a rule.
4. Run the focused application test plus `module:check --format=json` at the
   intended Level.
5. Report evaluated rules, blocking violations, warnings, incomplete analysis,
   and any remaining unknowns separately.

Do not move application code merely to make a directory diagram look modular.
Preserve Laravel runtime behavior and verify route/configuration caches when the
changed resource lifecycle requires them.

## Preserve the Diagnostic Contract

- Exit `0` means no blocking violation; warnings may still exist and must remain
  visible.
- Exit `1` means blocking architecture violations were found.
- Exit `2`, `complete: false`, or `status: incomplete` is a tool or analyzer
  failure. Never record it as a pass or create debt artifacts from it.
- Treat unresolved dynamic expressions as analyzer limits. Do not guess table,
  Module, Model, transaction, or ownership identities.
- Do not disable rules, create or replace a baseline, add suppressions, or widen
  selectors unless the user explicitly chooses that reviewed debt decision.
- Never add a global ignore or describe a weakened preset as a complete Level
  guarantee.

End with the exact commands run, their exit status, the effective Level, and
anything not verified. Commit, push, publish, or modify external systems only
when the user explicitly authorizes that action.
