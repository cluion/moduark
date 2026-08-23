# ADR-0049: Generator Registry Contract and Laravel Maker Inventory

- Status: Accepted and implemented through `1.1` Slice G3-B
- Date: 2026-08-23

## Context

Before the registry slices, `moduark:make` supported only `model` and
`controller`. Its command,
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
in ADR-0050. G1-A adds the first new single-target group: Module-owned `class`,
`enum`, `interface`, and `trait` descriptors backed by Laravel's native Makers.
G1-B completes the PHP type group with Module-owned `cast`, `exception`, and
`scope` descriptors.
G2-A begins the HTTP group with Module-owned `request` and `resource`
descriptors, including the native Resource Collection and JSON:API variants.
G2-B adds the Module-owned `middleware` descriptor while preserving its
single-target native contract.
G2-C adds the Module-owned `policy` descriptor with explicit model and auth-user
ownership semantics.
G2-D completes the HTTP validation group with a Module-owned `rule` descriptor
and Laravel's plain or implicit native stub.
G3-A begins the Data group with standalone Module-owned `factory` and `seeder`
descriptors, separate from model composite generation.
G3-B adds Module-owned `observer` generation with explicit model ownership and
no implicit registration side effects.

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
- G1-A fixes PHP type ownership before native delegation: generic classes use
  the name-relative Module path, enums use `Enums/`, interfaces use the Level 1
  `Contracts/` Public API convention, and traits use `Concerns/`. The descriptor
  allowlists only `--invokable` for classes and `--int` / `--string` for enums.
- G1-B fixes casts below `Casts/`, exceptions below `Exceptions/`, and scopes
  below `Models/Scopes/`. Casts allowlist `--inbound`; exceptions allowlist
  `--render` and `--report`; scopes expose no additional native option. These
  three descriptors remain single-target plans and do not copy framework stubs.
- G2-A fixes requests below `Http/Requests/` and resources below
  `Http/Resources/`. Resources allowlist `--collection` and `--json-api`, but
  reject the competing modes together instead of relying on Laravel's implicit
  collection precedence. Both descriptors remain single-target native delegates.
- G2-B fixes middleware below `Http/Middleware/` and keeps native delegation.
  Laravel's Middleware Maker has no force option, so the descriptor rejects
  `--force` before execution. Native `--test`, `--pest`, and `--phpunit` modes
  remain unexposed because their related targets are not represented by the
  single-target Module plan.
- G2-C fixes policies below `Policies/` and keeps native plain/model-aware
  delegation. Relative `--model` values are validated as StudlyCase paths and
  qualified below the selected Module's `Models/` namespace; external FQCNs are
  rejected. `--guard` retains Laravel's application auth-provider lookup. These
  options change only the policy stub and never create a related model.
- G2-D fixes validation rules below `Rules/` and keeps native single-target
  delegation. `--implicit` selects Laravel's implicit-rule stub; plain and
  implicit modes do not create a request, policy, or any other related artifact.
- G3-A fixes standalone factories below `Database/Factories/` and seeders below
  `Database/Seeders/`. Laravel's commands hard-code the application `database/`
  path, so these descriptors use reviewed Module-owned templates rather than
  native delegation. Factory names receive the conventional suffix and infer a
  Module model unless `--model` supplies another Module-relative class. Both
  types reject `--force` to retain their Laravel 12 / 13 native option contract.
- G3-B fixes observers below `Observers/` and keeps native plain/model-aware
  delegation. Relative `--model` values are qualified below the selected
  Module's `Models/` namespace, while external FQCNs are rejected. Observer
  generation supports native `--force` but does not create a model, provider
  registration, or event listener.
- G3-C fixes standalone migrations below `Database/Migrations/`. Laravel's
  command hard-codes the application migration path and chooses timestamped
  output during execution, so this descriptor uses reviewed Module-owned plain,
  create, and update templates to keep dry-run, collision preflight, and writes
  on one target. `--create` and `--table` are mutually exclusive, the name-based
  table guess follows Laravel 12 / 13 patterns, and standalone `--force` is
  rejected because neither native command exposes it.
- G4-A fixes events below `Events/` and keeps native single-target delegation.
  Laravel 12 and 13 expose the same name and `--force` contract; event generation
  does not create a listener or mutate provider registration.
- G4-B fixes listeners below `Listeners/` and keeps native single-target
  delegation. Relative `--event` values are validated and qualified below the
  selected Module's `Events/` namespace; `--queued` selects the native queued
  variants without creating an event or mutating provider registration.

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
- G1-A keeps separate Laravel 12 / 13 plan fixtures and verifies all four PHP
  types against nested namespaces, native stub semantics, collision refusal,
  force overwrite, dry-run zero mutation, and clean-application installation.
- G1-B extends those versioned fixtures and gates to casts, exceptions, and
  scopes, including inbound cast interface semantics, combined exception
  render/report methods, the Module-owned scope location, and nwidart command
  ownership.
- G2-A adds separate Laravel 12 / 13 HTTP plan fixtures and verifies Form
  Request, standard JSON Resource, Resource Collection, and JSON:API stubs,
  including nested namespaces, conflict refusal, collision/force parity,
  dry-run zero mutation, clean installation, and nwidart command ownership.
- G2-B extends both HTTP fixtures with nested Middleware ownership and verifies
  the native stub, collision refusal, explicit unsupported-force behavior,
  dry-run zero mutation, clean installation, and nwidart command ownership.
- G2-C adds separate Laravel 12 / 13 Policy plan fixtures and verifies plain and
  Module-model stubs, guard-selected user types, external-model refusal,
  collision/force parity, invalid-guard cleanup, clean installation, and
  nwidart command ownership.
- G2-D adds separate Laravel 12 / 13 Rule plan fixtures and verifies plain and
  implicit native stubs, nested Module ownership, collision/force parity,
  foreign-option refusal, dry-run zero mutation, clean installation, and
  nwidart command ownership.
- G3-A adds separate Laravel 12 / 13 Data plan fixtures and verifies inferred
  and explicit factory models, conventional suffixing, nested seeder ownership,
  collision refusal, unsupported-force behavior, root-database isolation,
  clean installation, and nwidart command ownership.
- G3-B extends both Data fixtures with plain and model-aware Observer plans and
  verifies nested Module ownership, Module-relative model qualification,
  collision/force parity, foreign-option refusal, zero implicit registration,
  clean installation, and nwidart command ownership.

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
