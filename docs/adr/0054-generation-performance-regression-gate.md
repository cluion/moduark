# ADR-0054: Generation Performance Regression Gate

- Status: Accepted
- Date: 2026-08-23

## Context

The `1.1` completion contract requires a large-fixture Generation performance
regression gate. The existing architecture benchmark measures discovery and
source analysis without a portable timing threshold; it neither executes
Generation Plans nor proves that scaffold breadth remains practical.

A timing gate must also account for shared-runner and filesystem variance. A
single sample or a threshold copied from one developer machine would create a
flaky release signal rather than useful regression evidence.

## Decision

- `GenerationBenchmark` creates 100 disposable `full` scaffold Modules per
  sample: 14 production template targets each, or 1,400 files total.
- It uses `ModuleScaffoldPlanner`, `GenerationPreflight`, and
  `GenerationExecutor` directly. Fixture setup, correctness verification, and
  cleanup are excluded from measured time.
- Each sample records planning, preflight, execution, total milliseconds, and
  target throughput. It also proves the exact target count, zero collisions,
  zero Artisan delegates, unique paths, and complete regular-file output.
- One unmeasured warmup precedes three measured samples. The gate uses median
  total time so a single noisy filesystem sample does not decide the result.
- `composer benchmark:generation` reports text or schema-versioned JSON without
  enforcing a timing SLA. `composer test:performance` enforces a maximum
  5,000 ms median total on a dedicated PHP 8.5 Ubuntu CI job.
- Exit `0` means the benchmark and optional gate passed, exit `1` means the
  enforced timing budget failed, and exit `2` means input, planning, execution,
  verification, or cleanup could not produce complete evidence.
- The budget is a major-regression tripwire, not a latency promise. Changes to
  fixture breadth, runtime, host, or budget require explicit review here.

## Initial Evidence

On PHP 8.5.9 / Darwin, the 100 Module / 1,400 target fixture produced a
651.454 ms median total and 2,149.039 median targets/second. The three measured
totals were 651.454 ms, 429.991 ms, and 3,691.709 ms. The high third sample
demonstrates why the gate uses a median and retains substantial headroom below
the 5,000 ms budget.

## Acceptance

- Unit tests exercise a real two-Module fixture, all measurement summaries,
  exact target verification, invalid dimensions, deterministic gate boundaries,
  and runner exit codes without sleep-based assertions.
- The default 100 Module gate passes locally and in the fixed CI job.
- Package tests, PHPStan, Composer validation, and staged distribution archive
  tests remain green. Benchmark sources remain development-only and are absent
  from published archives.

## Consequences

Generation performance now has repeatable production-path evidence and a
release-blocking ceiling for major regressions. Smaller changes below that
ceiling remain observable in JSON but do not fail CI merely because of ordinary
host variance. The architecture benchmark remains threshold-free and separate.
