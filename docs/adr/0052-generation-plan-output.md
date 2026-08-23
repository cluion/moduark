# ADR-0052: Generation Plan Output

- Status: Accepted for `1.1` Slice G7-A
- Date: 2026-08-23

## Context

`moduark:make` and `moduark:make-module` already resolve complete immutable
plans before mutation, but each command previously rendered only its own text
preview. Automation could not consume target operations, generator ownership,
overwrite intent, or collisions without parsing terminal prose.

Normal generation may delegate to Laravel Artisan commands whose output is not
owned by Moduark. A machine-readable plan therefore cannot remain valid if it
is mixed with execution output.

## Decision

- Both generation commands expose `--format=text|json`; the default remains
  `text`, preserving existing output.
- JSON is a plan-preview format and requires `--dry-run`. Requesting JSON during
  execution fails before planning or filesystem mutation.
- One `GenerationPlanExporter` derives text operations and JSON targets from the
  same immutable `GenerationPlan` used by preflight and execution.
- JSON schema version `1` contains `status`, `complete`, `exit_code`, command,
  Module, primary generator ID, optional preset, summary counts, ordered targets,
  and an optional error object.
- Every target contains `operation`, `generator_id`, Module-relative `path`,
  `overwrite` intent, and `collision`. Absolute filesystem paths and delegated
  command parameters are not exposed.
- Successful plans use status `planned`. A complete plan blocked by preflight
  uses `collisions_found`, remains complete, and returns exit code `1`. Input or
  planning failures use `incomplete`, `complete=false`, the command's compatible
  error exit code, an empty target list, and a stable diagnostic code.
- A permanent fixture freezes successful Maker and full-scaffold payloads.
  Laravel 12 and 13 clean applications parse both outputs as JSON, and nwidart
  interoperability keeps the independent command options visible.

## Consequences

- Automation can inspect a deterministic, portable plan without scraping text.
- Text output remains human-oriented and may be clarified without changing the
  versioned JSON schema.
- Machine-readable execution results remain outside this slice because Laravel
  delegate output cannot be made pure without a separate execution-report
  boundary.
