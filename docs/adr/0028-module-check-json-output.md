# ADR-0028: Module Check JSON Output

- Status: Accepted for the first `0.3.x` Developer Experience slice
- Date: 2026-08-15

## Context

`module:check` already has typed reports, deterministic rule ordering, complete
violation context, and stable exit codes. Its only output was formatted for
people, so CI annotations, IDE integrations, and other automation would have to
parse presentation text. The existing benchmark JSON does not describe an
architecture result and cannot serve as this command's contract.

The first machine-readable check format must distinguish a pass, blocking
violations, and an incomplete analysis without changing the established text
output or exit policy. It must also remain parseable when command validation or
source analysis fails before a `CheckReport` exists.

## Decision

- Add `module:check --format=text|json`; `text` remains the default.
- JSON uses a top-level integer `schema_version`, initially `1`. Additive or
  breaking schema changes must be deliberate rather than inferred from terminal
  presentation changes.
- Every payload contains these fields in a stable order:
  `schema_version`, `status`, `complete`, `exit_code`, `architecture`, `summary`,
  additive `baseline`, `unavailable_rules`, `results`, and `error`.
- A produced report uses status `passed`, `violations_found`, or `incomplete`.
  Its `architecture` contains configured and effective levels, labels, override
  state, and every effective rule. Its ordered `results` retain each rule's
  complete `Violation::toArray()` evidence. `error` is `null`.
- A tool failure before report creation uses status `incomplete`, exit code `2`,
  a `null` architecture, zero summary counts, empty result arrays, and a typed
  `error` object containing code, message, location, and suggestion.
- JSON output reuses the text command's exit policy: pass or warning-only is
  `0`, blocking violations are `1`, and incomplete or tool-error results are
  `2`.
- Pretty printing, unescaped slashes, UTF-8 text, and existing report ordering
  make repeated exports of the same report byte-for-byte deterministic.

## Acceptance evidence

- Feature coverage decodes two independent passing executions and asserts that
  both JSON strings and payloads are identical.
- A blocking violation fixture preserves rule, diagnostic code, severity,
  message, source location, Module endpoints, symbol, and suggestion while
  returning exit code `1`.
- A real Level 3 check reports all six unavailable rules as `incomplete` and
  returns exit code `2`.
- Invalid `--level` input and typed source-analysis failure both produce valid
  JSON tool-error payloads and retain exit code `2`.
- The clean Laravel installation runner executes the JSON command and validates
  its schema, passing status, and exit code in Laravel 12 and 13 applications.

## Consequences

- CI and IDE work can consume typed architecture data without scraping human
  output, while normal terminal behavior remains unchanged.
- `schema_version` versions the JSON document, not Moduark's package release.
- Determinism applies to the same report. Absolute source paths carried by
  violations can still differ between application environments.
- Exceptions raised during Laravel application bootstrap happen before Artisan
  invokes `module:check`; Laravel may render those failures outside this JSON
  contract.
- GitHub Actions annotations are added separately by
  [ADR-0029](0029-github-actions-annotations.md), and the later additive
  `baseline` field is defined by
  [ADR-0031](0031-architecture-baseline-adoption.md). Inline suppressions,
  incremental analysis, IDE integration, and JSON graph output remain future
  slices.
