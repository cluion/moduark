# ADR-0049: Generator Registry Contract and Laravel Maker Inventory

- Status: Accepted and implemented through `1.1` Slice G0-C
- Date: 2026-08-23

## Context

`moduark:make` currently supports only `model` and `controller`. Its command,
target resolver, option allowlist, collision check, and delegated Laravel Maker
call are intentionally narrow. Extending that shape directly to every Laravel
Maker would concentrate framework-version branches and composite side effects
inside one command, making dry runs and all-or-nothing generation unreliable.

Laravel's Maker surface is also a moving dependency. Moduark supports Laravel
12 and 13, so a command that exists in one resolved framework version cannot be
assumed to have the same class, aliases, arguments, or options in another. The
registry design needs reviewed framework evidence before it becomes production
behavior.

G0-A established that contract and evidence without changing the current
`moduark:make` runtime. G0-B makes the boundary executable for the existing
model and controller types without claiming support for additional Makers. G0-C
adds the first composite model plan and the executor atomicity contract described
in ADR-0050.

## Decision

### Registry and descriptor boundary

- A `GeneratorDescriptor` owns one canonical, lowercase kebab-case
  generator ID such as `model` or `controller`.
- A `GeneratorRegistry` accepts descriptors during container
  registration, rejects duplicate IDs, resolves IDs case-insensitively for the
  CLI, and returns descriptors in canonical ID order. Registration order must
  not change observable plans.
- Built-in and third-party descriptors use the same registration path. A
  third-party descriptor cannot silently replace a built-in or another package's
  descriptor.
- A descriptor declares its accepted inputs and produces a complete immutable
  `GenerationPlan`; it does not write files or invoke Artisan directly.
- A plan contains every target before execution, including normalized absolute
  path, Module-relative path, generated symbol when applicable, generator ID,
  and overwrite intent. Composite generators contribute all of their targets to
  the same plan.
- The planner resolves every target within the selected Module, and centralized
  preflight reports all existing or duplicate planned paths before delegation.
  The command only begins execution after the whole plan passes. Standalone
  model/controller targets retain Laravel delegation. Model `--factory` and
  `--migration` targets use the rollback-capable executor introduced by G0-C.
  `--dry-run` renders that same plan without filesystem mutation.
- Laravel delegation is descriptor-owned and allowlisted. Unknown native or
  third-party options are never forwarded implicitly; a composite native option
  is exposed only when its related targets are represented in the same plan.
- Existing `model` and `controller` public output, options, and exit codes remain
  unchanged when they move behind the registry in G0-B.

The concrete PHP interfaces were introduced with executable contract tests in
G0-B. They remain pre-`1.1` internal extension boundaries until their public API
stability is explicitly declared.

### Laravel Maker inventory

- Store separate deterministic fixtures for Laravel 12 and Laravel 13 under
  `tests/Fixtures/Generation/`.
- Build the inventory from the command classes provided by Laravel's
  `ArtisanServiceProvider` and `MigrationServiceProvider`. This avoids
  Testbench/Canvas overrides and excludes third-party commands.
- Include every canonical `make:*` command, not only the commands Moduark plans
  to support. Record its concrete class, aliases, arguments, and command-native
  options.
- Normalize arguments as `name|requiredness|cardinality` and options as
  `name|shortcuts|value-mode|cardinality`; sort every collection so provider
  registration order cannot affect the fixture.
- Descriptions and defaults are not part of this inventory schema. They do not
  decide dispatch compatibility and would turn prose/default changes into
  unrelated contract noise.
- Select the expected fixture from `Application::VERSION`. An unsupported major
  or any structural drift fails the feature suite with a review instruction and
  PHPUnit's exact array diff.

The fixtures captured Laravel `12.67.0` and `13.25.0`. Regenerating them under
the CI lowest resolutions, Laravel `12.61.1` and `13.12.0`, produced the same
normalized inventories. Both majors contain 37 native Maker commands and are
currently identical except for `laravel_major`. Six are table-oriented commands
without a `name` argument, leaving 31 name-based Maker candidates. Inventory
membership is evidence for planning, not a support claim.

## Acceptance

- `LaravelMakerInventoryTest` passes against all four real CI dependency
  resolutions: Laravel `12.61.1` / `12.67.0` with Testbench `10.0.0` /
  `10.11.0`, and Laravel `13.12.0` / `13.25.0` with Testbench `11.0.0` /
  `11.2.0`.
- Each fixture contains the same 37 canonical commands, including
  `make:migration`, and records only Illuminate-owned command classes.
- A command, alias, argument, or option change produces a deterministic failing
  diff in the Laravel 12/13 CI matrix.
- Existing model/controller feasibility and `moduark:make` feature tests remain
  unchanged and green.
- G0-A adds no production source, command, option, output, exit-code, or
  filesystem-write behavior.
- `GeneratorRegistryTest` locks canonical IDs, deterministic order,
  case-insensitive resolution, and duplicate rejection.
- `GenerationPreflightTest` proves that all existing targets are reported before
  generation and that `--force` cannot permit duplicate planned paths.
- `ModuleMakeCommandTest` proves model/controller parity, dry-run zero mutation,
  shared collision behavior, and explicit `OVERWRITE` plans.
- Clean Laravel 12 and 13 installation gates run a dry-run before real model and
  controller generation and assert that the planned model is absent until the
  real command executes.
- G0-C feature and executor tests cover three-target dry-run parity, Module-owned
  factory/migration paths, runtime `Model::factory()` wiring, complete collision
  reporting, new-file cleanup, overwritten-file restoration, and explicit
  rollback-failure reporting.

## Consequences

- Laravel minor upgrades may now require a deliberate inventory review even
  when Composer resolution succeeds. That failure is expected compatibility
  evidence, not a reason to update fixtures blindly.
- The 31 name-based commands are a bounded research surface for later Maker
  groups; each still needs its own Module ownership, path, option, and composite
  tests before registration.
- Further composite generators must use the same executor contract and prove
  every related target, collision, and rollback path independently.
- JSON plan output, all Maker groups, native `make:* --module` bridging, resource
  plugins, and presets remain separate later slices.
