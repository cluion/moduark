# ADR-0032: Laravel Maker Integration Direction

- Status: Accepted for the fourth `0.3.x` Developer Experience slice
- Date: 2026-08-16

## Context

Moduark needs a Laravel-like way to generate classes inside an application
Module. The preferred research syntax was `make:model Profile --module=User`,
with `module:make User model Profile` retained as the fallback when extending
Laravel's commands could not be made into a stable package contract.

Laravel 12.66.0 and 13.25.0 share the relevant behavior: `GeneratorCommand`
accepts an application-qualified class name and derives the destination below
the Laravel application path. The path and namespace hooks remain protected,
and individual Maker commands own different signatures and related-generation
flows.

A direct qualified name therefore works for a standalone model or controller,
and native options such as `--invokable` still work. It does not preserve Module
context across nested Maker calls. For example, asking `make:model` for a
Module-qualified model together with `--controller` creates the model inside the
Module but creates the related controller in the application's normal
`Http/Controllers` directory.

Injecting `--module` into Laravel's existing command definitions would also
create boot-order and third-party option collision risks. Rewriting only the
first command input cannot correct the nested calls owned by each Maker, while
replacing or subclassing every Maker would copy framework behavior into
Moduark's compatibility surface.

## Decision

- Do not add or inject `--module` into Laravel's `make:*` commands.
- Use one Moduark-owned entry point with the Laravel-like shape
  `module:make {module} {type} {name}`.
- Start the production implementation with `model` and `controller`; do not
  create a family of `module:make-model` or `module:make-controller` commands.
- Resolve the configured Module path and namespace through Moduark before
  invoking a Maker. The target Module must already exist.
- Delegate standalone generation to Laravel's original Maker with a qualified
  class name when the configured Module path is inside Laravel's application
  source root. This keeps Laravel's stubs, output, overwrite behavior, and
  supported native options authoritative.
- Reject a configured Module path outside the application source root in the
  initial production slice with an actionable error. Writing to an inferred or
  incorrect path is not an acceptable fallback.
- Expose only options whose generated files have verified Module ownership.
  Composite options that create related artifacts are rejected until Moduark
  explicitly orchestrates every generated target.
- Do not forward unknown or third-party options as raw tokens. A future Maker
  adapter extension contract requires a separate compatibility decision.

## Acceptance evidence

- `LaravelMakerFeasibilityTest` proves that model and controller FQCN inputs
  generate the expected Module path and namespace while a native controller
  option remains effective.
- The same test proves that `make:model --controller` loses Module context for
  the related controller, preventing an accidental claim that input rewriting
  is sufficient.
- The package's Laravel 12 and 13 CI matrix runs this feasibility contract
  against both supported framework majors.
- The framework source inspection used the exact supported high versions,
  Laravel 12.66.0 and Laravel 13.25.0, rather than assuming the two majors share
  Maker internals.

## Consequences

- The selected command is slightly longer than a native `--module` option, but
  it has an owned and versionable contract without modifying framework commands.
- Existing Laravel and third-party Maker commands remain untouched.
- The first implementation remains intentionally narrow. Additional Maker
  types, external PSR-4 roots, related artifacts, and third-party adapters can
  be added only with independent compatibility tests.
- The feasibility test remains useful after implementation because it records
  which behavior is provided by Laravel and which behavior Moduark must own.
