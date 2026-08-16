# ADR-0042: PHPStan Extension Integration Boundary

- Status: Accepted for the `0.5.x` scope lock
- Date: 2026-08-16

## Context

Moduark already has a standalone architecture analyzer built on `nikic/php-parser`,
incremental source evidence, stable diagnostic codes, baselines, and auditable
suppressions. The `0.5.x` roadmap proposes optional PHPStan and Larastan
integration so the same boundaries can appear in an existing static-analysis
workflow and editor tooling.

Running the standalone `ArchitectureChecker` from a PHPStan rule would parse the
project twice, maintain two result caches, and duplicate diagnostics. Requiring
PHPStan from `cluion/moduark` would also turn development tooling into a runtime
dependency for every application. Larastan adds useful Laravel-aware type
resolution, but it boots an application and has its own compatibility lifecycle;
base Moduark analysis must not require it.

PHPStan 2 provides Collectors for whole-codebase rules. Collector data is grouped
by analysed file and delivered to a `CollectedDataNode` rule in the main process.
PHPStan also requires rule-error identifiers and exposes file, line, tip,
metadata, and non-ignorable diagnostics through `RuleErrorBuilder`.

## Decision

- Implement the integration as an optional development-only Composer package,
  provisionally named `cluion/moduark-phpstan`. It will require both
  `cluion/moduark` and a supported PHPStan 2 range. The main package will not add
  PHPStan or Larastan to its production requirements.
- Depend on PHPStan directly. Larastan is an optional compatibility target, not
  a required dependency. The same Moduark extension must run with PHPStan alone
  and when Larastan is also loaded.
- Gather architecture evidence with PHPStan Collectors from PHPStan's already
  parsed and resolved AST. Aggregate it in `CollectedDataNode` rules; do not
  invoke the standalone filesystem parser from inside PHPStan.
- Keep Moduark's PHPStan-independent architecture domain as the shared contract.
  Collector-specific scalar evidence is adapted into domain inputs, and active
  Moduark `Violation` objects are adapted into PHPStan `RuleError` objects.
- Preserve the stable `MOD-*` code in the message and metadata. Add a valid,
  stable PHPStan identifier such as `moduark.internalApiAccess`, and preserve the
  original file, line, rule, and remediation tip.
- Apply Moduark baseline and suppression policy before producing PHPStan errors.
  Remaining blocking violations are non-ignorable in PHPStan so `ignoreErrors`
  does not become a second, unaudited architecture-suppression mechanism.
- Do not convert Moduark warnings into blocking PHPStan errors by default.
  `module:check` remains the complete source for warning-level findings. A future
  opt-in warnings-as-errors policy must be explicit and tested.
- Treat metadata or integration failures as failures, never as an empty pass.
  They require their own stable identifier and a non-ignorable diagnostic.
- Include extension source in cache dependency tracking. Hash external Moduark
  inputs such as effective configuration and Module metadata through PHPStan's
  result-cache metadata extension so a cached pass cannot survive architecture
  changes.

## First implementation slice

The first `0.5.x` implementation slice is deliberately narrow:

- scaffold the separate PHPStan extension package and automatic/manual NEON
  loading contract;
- implement `internal_api_access` end to end with Collector evidence and the
  shared `Violation` adapter;
- define explicit base-PHPStan configuration for Module path and root namespace
  without assuming a booted Laravel application;
- verify PHPStan-only and PHPStan-plus-Larastan matrices on Laravel 12 and 13;
- prove result-cache invalidation for source, `config/modules.php`, and Module
  metadata changes;
- compare the extension result with `module:check --format=json` on the same
  fixtures before promoting additional rules.

Expanding the rule inventory is a later slice. It requires parity evidence per
rule and may not copy the standalone analyzer or silently weaken dynamic limits.

## PoC evidence

A local-only external PoC used `cluion/moduark` at `1617bf1`, PHPStan `2.2.8`,
Larastan `3.10.0`, Testbench `10.11.0`, and Laravel `12.66.0`.

- A `New_` Collector gathered source class, target class, file, and line.
- A project-wide `CollectedDataNode` rule reused Moduark's `Violation` value
  object and emitted one `MOD-BOUNDARY-001` diagnostic at the consumer line.
- PHPStan-only and PHPStan-plus-Larastan runs produced the same file, line,
  message, remediation tip, and `moduark.internalApiAccess` identifier.
- The fixture and extension source passed PHPStan level `max`; the sole finding
  was the intentional cross-Module internal reference.

The PoC proves the integration seam, not production readiness. It does not prove
complete rule parity, Laravel 13 compatibility, metadata cache invalidation, or
packaging and release automation.

## Rejected alternatives

| Alternative | Reason |
|---|---|
| Require PHPStan from `cluion/moduark` | Adds development tooling and its version pressure to production installs. |
| Require Larastan | Couples base architecture analysis to Laravel application boot and Larastan's lifecycle. |
| Run `ArchitectureChecker` inside a PHPStan rule | Parses source twice and duplicates cache and diagnostic ownership. |
| Reimplement all Moduark rules in PHPStan | Creates two drifting rule models and inconsistent codes or suppressions. |
| Emit warnings as ordinary PHPStan errors | Changes Moduark warning semantics into a blocking exit code. |

## Consequences

- Runtime consumers keep the current lightweight dependency contract.
- Teams opt into editor and PHPStan integration with a separate development
  dependency and can keep using Larastan when desired.
- Collector adapters and cache metadata become explicit maintenance surfaces,
  but PHP parsing and whole-project scheduling remain PHPStan's responsibility.
- `module:check` remains authoritative for the complete rule set until each
  extension rule reaches documented parity.
- The `0.5.x` implementation can proceed as small rule-by-rule slices with a
  clear compatibility and correctness gate.
