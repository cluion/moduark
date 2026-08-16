# ADR-0029: GitHub Actions Annotations

- Status: Accepted for the second `0.3.x` Developer Experience slice
- Date: 2026-08-16

## Context

ADR-0028 added a complete JSON report for general automation, but a GitHub
Actions job would still need a separate parser to turn violations into source
annotations. Moduark already owns the severity, diagnostic code, source file,
line, evidence, and suggestion required for those annotations.

GitHub defines workflow commands written to standard output using forms such as
`::error file=path,line=12,title=title::message`. The
[workflow-command contract](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-commands)
also requires reserved characters in messages and properties to be escaped.

## Decision

- Add the explicit `module:check --format=github` output format. Text remains
  the default and JSON remains the general machine-readable report.
- Emit one `error` command for each error violation and one `warning` command
  for each warning violation, retaining deterministic report order.
- Include a repository-relative `file`, positive `line` when available, and a
  title containing the diagnostic code and rule identifier. Messages retain
  Module endpoints, symbol evidence, and remediation suggestions.
- Emit one `notice` for a complete report without violations. Emit one final
  `error` for an incomplete report, including every unavailable rule.
- Render command-validation and typed source-analysis failures as `error`
  commands. A terminal `:line` suffix is converted to annotation location
  metadata without confusing a Windows drive-letter colon.
- Escape `%`, carriage return, and newline in command messages. Escape those
  characters plus `:` and `,` in command properties, matching GitHub's command
  protocol.
- Write commands as raw output so Symfony formatting cannot alter the protocol.
  Reuse the existing exit policy: pass or warning-only is `0`, blocking
  violations are `1`, and incomplete or tool-error results are `2`.

## Acceptance evidence

- Unit coverage asserts exact notice, error, warning, incomplete, and tool-error
  commands, including property and multiline-message escaping.
- Feature coverage proves the Artisan command accepts the format, emits a
  passing notice, renders option failures as annotations, and preserves exit
  code `2`.
- The clean Laravel installation runner executes the GitHub format and validates
  its passing notice in Laravel 12 and 13 applications.
- PHPUnit and PHPStan verify the exporter and command integration without
  changing the text or JSON contracts.

## Consequences

- A workflow can use `php artisan module:check --format=github` directly; no
  bundled workflow or GitHub-specific runtime dependency is required.
- Source locations below the application base path are portable repository
  paths. External paths are preserved because silently rewriting them would
  lose evidence.
- GitHub output is presentation-specific and intentionally is not a replacement
  for the complete versioned JSON schema.
- Architecture baseline notices are defined separately by
  [ADR-0031](0031-architecture-baseline-adoption.md), and suppression notices by
  [ADR-0034](0034-auditable-architecture-suppressions.md). IDE integration and
  JSON graph output remain separate future slices.
