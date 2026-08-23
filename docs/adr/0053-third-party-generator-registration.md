# ADR-0053: Third-Party Generator Registration

- Status: Accepted
- Date: 2026-08-23

## Context

The Generator Registry, immutable Generation Plan, shared collision preflight,
rollback executor, and schema-versioned plan output are complete. The remaining
extension gap is a provider-order-independent public API that lets another
Composer package contribute a Module-owned generator without bypassing those
safety contracts.

## Decision

- A package service provider calls
  `GeneratorRegistration::register($this->app, Descriptor::class)` from
  `register()`. Descriptor instances are also accepted.
- Registration uses a Laravel container extender, so it works before or after
  Moduark's provider and before or after the registry has first been resolved.
- Built-in and third-party descriptors enter the same registry. Canonical IDs,
  duplicate IDs, reserved built-in IDs, duplicate options, and unknown command
  options are rejected centrally.
- A descriptor declares `id()`, `targetNamespace()`, `supportedOptions()`, and
  `plan()`. Its plan must be nonempty and contain every target before execution.
- Third-party targets must use `GenerationFileTemplate`, remain exactly below
  the selected Module, retain the descriptor's generator ID, and request
  overwrite only when `--force` is present. Linked path traversal and Artisan
  delegate commands are rejected.
- The common planner, JSON/text exporter, collision preflight, and rollback
  executor own the lifecycle. A descriptor never writes files directly.
- The promoted Stable types are listed in `docs/stability.md`. Registry and plan
  validator implementations remain Internal.

## Acceptance

- Unit contracts prove provider and registry resolution order independence,
  ID/option validation, and unsafe-plan rejection.
- A permanent feature fixture proves JSON dry-run zero mutation, real template
  execution, collision/force parity, central option rejection, and composite
  rollback.
- A Composer path package is auto-discovered in clean Laravel 12 and Laravel 13
  applications and exercises the same public registration API.
- PHPStan, package tests, Composer validation, distribution tests, and nwidart
  interoperability remain green.

## Consequences

Third-party packages can add deterministic Module-owned files without owning a
parallel command or duplicating safety logic. The template-only restriction is
intentional: native or third-party Artisan delegation cannot prove complete
targets, portable JSON output, collision preflight, or rollback from the public
contract alone. A broader execution mechanism requires a separate reviewed ADR.
