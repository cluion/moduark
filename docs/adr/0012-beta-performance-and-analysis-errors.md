# ADR-0012: Beta Performance Baseline and Analysis Errors

- Status: Accepted for Slice 8A
- Date: 2026-08-15

## Context

The beta plan requires evidence for discovery and architecture checks at 50 and
100 Modules. It also sets 100 Modules and 10,000 PHP files as the first warm-run
analyzer scenario, while explicitly deferring a millisecond budget until a
repeatable fixture exists.

Source analysis failures already produced exit code 2 and preserved parser line
numbers. The command flattened those typed failures into one generic sentence,
so users could not reliably identify the tool error, location, next action, or
whether a passing result had been produced.

## Decision

- `composer benchmark` generates disposable fixtures outside the repository and
  measures 50 and 100 Modules by default.
- Each Module has 100 PHP files. The default cases therefore exercise 5,000 and
  10,000 files through discovery and the complete six-rule Level 1 check.
- Fixture generation and cleanup are excluded from measured time. Each case has
  one unmeasured warmup followed by three measured runs.
- The benchmark reports discovery, Level 1 check, and combined durations as
  minimum, median, and maximum values. Text is the human default; `--format=json`
  provides machine-readable evidence.
- No timing threshold is enforced in this slice. Baselines describe one host and
  are not portable release gates.
- Generated source contains deterministic same-Module type references. This
  exercises symbol and reference indexing without introducing architecture
  violations or requiring generated dependency metadata.
- `SourceAnalysisFailed` carries stable code `MOD-ANALYSIS-001`, a location when
  available, and an actionable suggestion.
- `module:check` renders those fields and explicitly says the result is
  incomplete. It continues to return tool-error exit code 2. Other configuration,
  discovery, and runtime failures retain the existing generic tool-error path.

## Initial Evidence

Command:

```bash
composer benchmark
```

Environment: PHP 8.5.9, Darwin arm64. Warm run with one warmup and three measured
iterations on 2026-08-15.

| Fixture | Discovery median | Level 1 check median | Total median |
|---|---:|---:|---:|
| 50 Modules / 5,000 PHP files | 1.891 ms | 361.823 ms | 363.951 ms |
| 100 Modules / 10,000 PHP files | 3.957 ms | 730.737 ms | 734.694 ms |

These values establish a reproducible comparison point. A future threshold must
use evidence from supported PHP versions and CI hosts rather than copying this
single-machine result.

## Consequences

- Performance changes can be compared with the same source shape without
  checking thousands of generated fixtures into Git.
- The benchmark intentionally measures warm standalone analyzer behavior, not
  runtime request boot cost, cold filesystem behavior, or realistic application
  source complexity.
- JSON output can later feed CI trend collection without turning natural host
  variance into a flaky test.
- Analyzer failures are now distinguishable from architecture violations and
  successful checks in human-readable output.
