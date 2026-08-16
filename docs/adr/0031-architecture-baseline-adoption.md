# ADR-0031: Architecture Baseline Adoption

- Status: Accepted for the third `0.3.x` Developer Experience slice
- Date: 2026-08-16

## Context

Brownfield applications can have enough existing architecture debt that enabling
`module:check` as a CI gate is not immediately practical. A baseline can separate
known debt from regressions, but an imprecise or casually regenerated baseline
would permanently hide new violations.

Moduark violations already have stable rule identifiers, diagnostic codes,
severity, source files, Module endpoints, and symbol evidence. Source line numbers
and diagnostic prose are presentation details and can change without the
underlying violation changing.

## Decision

- Store the baseline in a reviewable, deterministic JSON file. The default path
  is `moduark-baseline.json` at the Laravel application root and can be changed
  with `modules.architecture.baseline`.
- `module:baseline [--level=0..3]` creates the first baseline from the raw,
  complete architecture report. It refuses to replace an existing file unless
  the operator explicitly passes `--force`.
- `module:baseline --prune` can only reduce counts or remove stale entries. It
  never captures a new identity or increases an existing allowance.
- Normal `module:check` automatically loads the configured file when it exists.
  Baseline generation always uses the raw checker so a baseline can never copy
  an already-filtered result.
- A violation identity consists of rule, diagnostic code, severity, normalized
  repository-relative file, consumer Module, target Module, and symbol. It does
  not contain line, message, or suggestion.
- Equal or lower current counts are matched by the baseline. If a current
  identity exceeds its recorded count, Moduark reports the entire current group
  instead of guessing which occurrence is new.
- Text, JSON, and GitHub output expose matched, stale, and exceeded counts. Stale
  entries do not change the architecture exit policy; they remain visible debt
  metadata and can be safely removed with `--prune`.
- The existing check JSON schema remains version `1`; the new top-level
  `baseline` field is a deliberate additive change and is `null` when no file is
  active.

## Acceptance evidence

- Unit tests prove line and wording drift still match, application paths become
  portable, count growth reports the full group, and prune cannot adopt new
  debt.
- Invalid JSON, unknown schema versions, and incomplete raw reports are tool
  errors rather than silent baseline skips.
- Feature tests cover initial creation, overwrite refusal, filtering in
  `module:check`, additive JSON metadata, GitHub summaries, and safe pruning.
- The complete PHPUnit, PHPStan, distribution, and clean Laravel 12/13 matrices
  remain release gates.

## Consequences

- Teams can turn on CI enforcement before all historical architecture debt is
  fixed, while baseline changes remain visible in code review.
- `--force` is intentionally explicit because it can adopt regressions. Routine
  cleanup should use `--prune`.
- Moving a file changes identity and resurfaces the violation for review. Moving
  only a line does not.
- Inline suppressions, reasons, expiry, incremental analysis, and IDE integration
  remain separate decisions.
