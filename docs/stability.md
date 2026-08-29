# Stability and Versioning

This document defines the stable compatibility contract for Moduark `1.x`.
`1.0.0-rc.1` exposed an interoperability defect in its command and
configuration identities; `1.0.0-rc.2` adopts the revised boundary documented
by ADR-0047. `1.0.0` promotes that reviewed boundary without another runtime or
machine-schema change. `1.1.0` adds the Generation Foundation contracts below
without changing the existing architecture or diagnostic identities. `1.2.0`
adds the Runtime Completeness resource and operation contracts while retaining
the same Stable Level 0 through 2 and Preview Level 3 boundary.

## Contract Categories

### Stable

Stable surfaces are supported throughout the `1.x` release line. Removing or
incompatibly changing one requires a major release after a documented
deprecation period, except when an urgent security response makes that unsafe.

The Stable surface consists of:

- the supported PHP application extension points listed below;
- Laravel package discovery and the documented configuration keys;
- the Level 0 Organization, Level 1 Modular, and Level 2 Decoupled presets;
- documented Artisan command names, arguments, options, and exit codes;
- versioned JSON reports, architecture baselines, suppression manifests, rule
  IDs, and diagnostic identities.

### Preview

Level 3 Isolated is Preview in `1.0.0`. Its policy, rule IDs, severities,
diagnostic identities, and machine-readable shapes remain governed by the
contracts below, but the static detection breadth may expand in a documented
`1.x` minor release as real Laravel applications provide more evidence.

Level 3 remains opt-in. A patch release will not silently add blocking preset
scope, and unsupported dynamic behavior remains unknown instead of being
guessed.

The `1.0.0` go/no-go review keeps Level 3 in Preview. A complete public fixture
now exercises Capability wiring, owned migrations, resolved and unresolved
foreign keys, inline transactions, and all fourteen rules without blocking
false positives, but a curated fixture is not independent brownfield adoption
evidence. See [ADR-0046](adr/0046-level-three-preview-go-no-go.md) for the
remaining promotion gate.

### Internal

Anything not listed as Stable or Preview is Internal even when PHP visibility
allows direct access. In particular, descriptor compilation, capability
resolver and binding-plan objects, analyzer orchestration, caches, and
human-output formatting are implementation details.

Internal APIs may change without deprecation. Application code should declare
Module metadata and resolve its consumer-owned Ports through Laravel's
container, not construct Moduark lifecycle internals directly.

## Supported PHP Extension Points

The following PHP types are supported application-facing contracts:

- `Cluion\Moduark\Module`, including `dependencies()`, `providers()`,
  `requires()`, `provides()`, `tables()`, and `exports()`;
- `Cluion\Moduark\Capability` as a marker interface for typed Capability
  identities;
- `Cluion\Moduark\CapabilityRequirement`, including its constructor,
  `capability()`, `port()`, `adapter()`, `fromArray()`, and `toArray()`;
- automatic Laravel discovery of
  `Cluion\Moduark\ModuarkServiceProvider`;
- `Cluion\Moduark\Generation\GeneratorRegistration::register()` and the
  template-backed generator value contracts: `GeneratorDescriptor`,
  `GenerationOptions`, `ModuleMakerTarget`, `GenerationPlan`,
  `GenerationTarget`, and `GenerationFileTemplate`.

New optional metadata methods may be added in a minor release with a
backward-compatible default. Existing method meaning, accepted metadata shape,
or required implementation behavior will not be changed incompatibly within
`1.x`.

Other namespaces are not application extension APIs unless this document or a
later stability document names them explicitly. PHP `public` visibility alone
does not promote an implementation type to Stable.

Third-party generators register from a Laravel service provider and must return
a complete template-backed plan. Descriptor IDs and declared options are
validated centrally; targets must remain inside the selected Module and use the
shared preflight, JSON/text output, and rollback executor. Direct registry
mutation, the plan validator, arbitrary Artisan delegation, and filesystem
writes from a descriptor are Internal or unsupported. See
[ADR-0053](adr/0053-third-party-generator-registration.md).

## Configuration and Architecture Presets

These configuration identities are Stable:

- `moduark.path`;
- `moduark.activation.path` (Unreleased `1.3` Preview);
- `moduark.architecture.level`;
- `moduark.architecture.baseline`;
- `moduark.architecture.suppressions`;
- `moduark.architecture.rules`.

Level numbers and labels remain `0` Organization, `1` Modular, `2` Decoupled,
and `3` Isolated. Published rule IDs remain stable machine identities. Level 0
through Level 2 preset membership and severities will not be broadened or made
stricter within `1.x`; a new rule may be introduced disabled by default.

Explicit rule overrides continue to take precedence over a selected preset.
See [Architecture Levels](architecture-levels.md) for the complete matrix and
rule semantics.

## CLI Contract

The following Runtime Completeness commands are Stable since `1.2.0`. Their
documented command definitions, schema version `1` fields, status identities,
and exit meanings are Stable for the remainder of `1.x`:

```text
moduark:resources [{module}] [--format=text|json]
moduark:doctor [{module}] [--format=text|json]
moduark:migrate {module} [--format=text|json]
moduark:seed {module} [--format=text|json]
moduark:test {module} [arguments...] [--runner=auto|phpunit|pest] [--list] [--format=text|json]
```

Resources and doctor use exit `0` for a healthy result, `1` for diagnosed
collisions or issues, and `2` for invalid input or tool failure. Migrate and
seed use `0` for success, including an empty declared set, and `2` for invalid
input or execution failure. Test uses `0` for a successful run/list, `1` for a
test failure or no declared tests, and `2` for invalid input or unavailable
runner. Human-readable output may be clarified; automation must use JSON and
exit codes.

The Unreleased `1.3` line also contains Preview activation commands:

```text
moduark:enable {module} [--dry-run] [--format=text|json]
moduark:disable {module} [--dry-run] [--format=text|json]
```

Their JSON schema version `1` contains `status`, `operation`, `dry_run`,
authoritative `driver`, nullable `plan`, `exit_code`, and nullable `error`.
Dry-run executable plans use status `planned`; committed changes use `applied`;
validated no-ops use `unchanged`; all use exit `0`. Dependency or Capability
blockers use `blocked` and exit `1`; invalid input, unsupported writable driver,
concurrent state change, cache failure, or state failure use `error` and exit
`2`. Mutation first invalidates Module metadata, source-analysis, route, and
event caches, then atomically replaces the authoritative state file. It does not
hot-switch an already-running application process.

Aggregate list, graph, architecture-analysis, cache, provider, Capability, and
resource execution surfaces contain active Modules only. Targeted `doctor` and
`resources` diagnostics may return a known disabled placeholder with no runtime
metadata; `inspect` requires an active Module. This semantic boundary is the
same on cold and Module-cached boot. No additional `--enabled-only` switch is
defined because aggregate surfaces are already active-only.

The Unreleased `1.3` Preview also adds the read-only form
`moduark:doctor {module} --extractable [--format=text|json]`. Its JSON schema
version `1` reports ordered checks and blockers for supported source layout,
autoload identity, provider/resource ownership, and declared application-global
metadata coupling. Status `ready_for_export_dry_run` with exit `0` authorizes
only a later export planning phase; `blocked` uses exit `1`, and invalid or
inactive Modules use `error` with exit `2`. It does not claim Composer dependency
inference, package Testbench installation, or independent test execution.

LC1-B extends that same schema with six ordered raw Level 3 architecture checks
for undeclared dependencies, Capability contracts, table ownership,
cross-Module foreign keys and transactions, and explicit public exports.
Application baselines and suppressions are intentionally not applied. Any
warning or error involving the selected Module as consumer or target blocks
export planning; a disabled or unavailable required rule also blocks rather
than being reported as passed. Existing `moduark:check` behavior is unchanged.

The following commands and their documented arguments and options are Stable:

```text
moduark:make-module {name} [--preset=minimal|web|api|domain|full] [--dry-run] [--format=text|json]
moduark:make {module} {type} {name} [--dry-run] [--format=text|json] [--force] [--command=] [--factory] [--migration] [--create=] [--table=] [--int] [--string] [--inbound] [--render] [--report] [--collection] [--json-api] [--model=] [--guard=] [--implicit] [--event=] [--queued] [--sync] [--batched] [--markdown=] [--view=] [--inline] [--path=] [--extension=] [--unit] [--test] [--pest] [--phpunit] [--invokable] [--resource] [--api]
moduark:list
moduark:inspect {module}
moduark:graph [{module}] [--view=module] [--format=text]
moduark:check [--level=] [--format=text] [--show-suppressions]
moduark:baseline [--level=] [--force] [--prune]
moduark:cache
moduark:clear
```

Maker options are descriptor-specific. In particular, `middleware` supports
`--dry-run` but rejects `--force`; Laravel's native Middleware Maker has no
force option. Its matching-test options add a Module-owned feature test to the
same atomic Generation Plan.

Module scaffold preset IDs and their ordered Module-owned target manifests are
Stable. Omitting `--preset` is equivalent to `minimal`; `--dry-run` renders the
same complete plan used for execution without mutation. Scaffold execution
preflights every collision, never overwrites an existing target, and rolls back
all targets after a write failure. Presets do not execute package managers or
create application-global frontend resources.

Generation Plan JSON schema version `1` is Stable. It contains `status`,
`complete`, `exit_code`, `command`, `module`, `generator_id`, nullable `preset`,
summary counts, ordered `targets`, and nullable `error`. Every target contains
`operation`, `generator_id`, Module-relative `path`, `overwrite`, and
`collision`. Status is `planned`, `collisions_found`, or `incomplete`.
`--format=json` requires `--dry-run` and fails before mutation otherwise;
absolute paths and Laravel delegate output are never included.

Policy `--model` values are Module-relative class names below `Models/`;
`--guard` retains Laravel's application auth-provider semantics. Neither option
creates a related model.
Validation rules are generated below `Rules/`; `--implicit` changes only the
native rule stub and does not create related HTTP artifacts.
Standalone factories and seeders are generated below `Database/Factories/` and
`Database/Seeders/`. Factory `--model` is Module-relative; both types reject
`--force` and never mutate application-level database resources.
Observers are generated below `Observers/`. Observer `--model` values are
Module-relative below `Models/`; generation supports `--force` but never creates
a model, provider registration, or event listener.
Standalone migrations are generated below `Database/Migrations/`. Their
StudlyCase names become timestamped snake_case filenames; `--create=` and
`--table=` select the create/update stubs and cannot be combined. They reject
`--force`, duplicate logical names, nested names, and application-global paths.
Events are generated below `Events/` with Laravel's native stub. They support
nested names, `--force`, and `--dry-run`, but never create listeners or provider
registrations.
Listeners are generated below `Listeners/` with Laravel's native plain, typed,
queued, or typed-queued stub. `--event=` accepts only a Module-relative event
below `Events/`; generation never creates that event or provider registration.
Jobs are generated below `Jobs/` with Laravel's native queued, synchronous, or
batched queued stub. `--sync` and `--batched` cannot be combined; generation
never creates matching tests, queue infrastructure, or batch migrations.
Job middleware is generated below `Jobs/Middleware/` with Laravel's native
single-target stub. It never creates a job, matching test, queue infrastructure,
or registration.
Notifications are generated below `Notifications/` with Laravel's native plain
stub. Laravel's `--markdown` mode also creates an application-global view, so
Moduark rejects it before generation and never writes below `resources/views/`.
Mailables are generated below `Mail/` with Laravel's native plain stub.
Laravel's `--markdown` and `--view` modes create application-global views, so
both options are rejected before generation.
Broadcast channels are generated below `Broadcasting/` with Laravel's native
stub and application auth-provider model reference. Generation never creates
that model, matching tests, routes, providers, or channel registration.
Blade components are generated below `View/Components/` and Module-owned
`resources/views/`. Default class-and-view generation is an atomic composite;
`--inline` creates only the component class, a value-less `--view` creates only
an anonymous view, and `--path=` remains constrained to the Module view tree.
Standalone Blade views are deterministic single targets below Module-owned
`resources/views/`. Dot, slash, and backslash names normalize to lowercase
kebab-case paths; `--extension=` is constrained to safe dot-separated suffixes.
Standalone verification targets use fixed Module-owned `Tests/Feature/` and
`Tests/Unit/` roots with Module namespaces and PHPUnit or Pest syntax. Makers
that expose Laravel matching-test semantics add the test to the same complete
collision preflight and rollback-safe plan. No verification target may be
written below the application-global `tests/` root.
Console commands are generated directly below `Console/Commands/` with
Laravel's native stub and a validated lowercase `--command=` name. Command
classes are deliberately non-recursive until the runtime resource contract
supports recursive discovery.
Config files are deterministic template targets below the selected Module's
lowercase `config/` tree. Generation does not load, merge, publish, or write the
application's config tree.
Service providers are deterministic template targets below `Providers/`.
Generation never invokes Laravel's application-mutating provider Maker and
does not edit `bootstrap/providers.php`; activation remains explicit Module
`providers()` metadata.

Architecture checks use these process exit codes:

- `0`: analysis completed without blocking violations; warnings may still be
  present;
- `1`: analysis completed and found blocking violations;
- `2`: the tool or analyzer could not produce a complete architecture result.

Human-readable text and Mermaid graph layout may be clarified or reformatted in
a minor or patch release. Automation should use JSON, exit codes, and stable
identities instead of matching prose.

## Machine-Readable and Persistent Contracts

`moduark:check --format=json` schema version `1` has these required top-level
fields: `schema_version`, `status`, `complete`, `exit_code`, `architecture`,
`summary`, `suppressions`, `baseline`, `unavailable_rules`, `results`, and
`error`. The status vocabulary is `passed`, `violations_found`, and
`incomplete`.

Architecture baseline schema version `1` and suppression manifest schema
version `1` are persistent application files. Existing required fields and
their meaning remain compatible within their schema version. A minor release
may add fields that older consumers can ignore. Removing a field or changing
its meaning requires a new schema version plus upgrade guidance.

Published rule IDs and `MOD-*` diagnostic codes are stable identities for
baselines, suppressions, CI, and reporting. Fixing a false positive may remove
an incorrect finding without retaining that finding artificially. Reusing an
existing identity for unrelated semantics is not compatible.

Module metadata and source-analysis caches are explicitly excluded from this
contract. They are rebuildable and may receive a new internal schema or be
invalidated by any release. Do not edit or consume them as application data.

Resource manifest schema version `1` and Module asset manifest schema version
`1` are Stable machine contracts introduced in `1.2.0`. The resource manifest
contains the dependency-ordered enabled Module class list and ordered resource
descriptors. Each descriptor contains `module`, `plugin`, `identity`, nullable
`source`, nullable `namespace`, normalized `attributes`, and nullable
`collision_key`. The asset manifest contains `schema_version`, `modules`, and
sorted `inputs`. A minor release may add ignorable fields but cannot remove or
reinterpret existing fields without a schema change.

## Resource Extension Contract

The following additive PHP extension points are Stable since `1.2.0`:

- overridable parameterless `Module::resources(): array`;
- `ResourceDiscoverer` and `ResourceHandler` interfaces;
- immutable `ResourcePlugin`, `ResourceDescriptor`, `ResourceManifest`, and
  `ModuleAssetManifest` value objects;
- `ResourcePluginRegistration::register()` as the package-provider registration
  entry point.

`Module::resources()` and discovery output must remain pure serializable data.
The built-in metadata keys and their current meanings are additive 1.x
contracts. Existing conventions remain enabled; config, custom routes,
recursive commands, factories, seeders, policies, listeners/components,
assets, tests, and extensions require explicit metadata. Third-party plugins
must register before application booting so cold and cached manifest binding
remain deterministic. See
[ADR-0056](adr/0056-resource-plugin-manifest-runtime.md).

## Deprecation Policy

This policy applies to Stable surfaces. A replacement must ship in at least one
released `1.x` minor before the old surface can be removed in the next major
release. Every deprecation must:

- name the supported replacement and its first available version;
- be recorded in the changelog and [Upgrading Moduark](../UPGRADING.md);
- keep the old and replacement paths under compatibility tests during the
  deprecation window;
- avoid silently changing application configuration, architecture debt, or
  machine-readable files.

Deprecated PHP APIs receive an `@deprecated` annotation with a replacement
reference. A runtime `E_USER_DEPRECATED` warning is optional because boot and
analysis hot paths must not gain uncontrolled CI noise. Config keys and CLI
inputs keep their documented behavior throughout `1.x`; a warning cannot alter
the established exit-code semantics.

An incompatible persistent-schema change requires an explicit new schema
version. The old form remains readable for at least one released minor when a
safe dual reader is possible, and no migration may silently rewrite a baseline
or suppression file. Diagnostic codes are never repurposed: a replacement uses
a new identity plus an upgrade mapping.

Internal APIs do not receive this window. Preview Level 3 detection breadth may
expand under its minor-release policy, but its existing machine identities are
not repurposed. An urgent security response may shorten a compatibility window;
the release must document the reason, impact, and safe replacement.

## Release Compatibility

The package's `composer.json` and CI matrix are the source of truth for
supported PHP and Laravel versions. Dropping a supported runtime or framework
line is a breaking change for the stable line unless continued support is
impossible because of an upstream security requirement.

Patch releases contain compatible fixes, including false-positive corrections,
without expanding a preset silently. Minor releases may add optional APIs,
commands, disabled rules, and additive schema fields. Preview Level 3 detection
coverage may also expand in a minor release when documented in the changelog.

Published security support and the verified private reporting channel are
defined in the [Security Policy](../SECURITY.md). The policy distinguishes
security-impacting boundary bypasses from ordinary analyzer false positives.

Maintainer release stages, RC requirements, exact-commit CI, Packagist
visibility, and published-dist acceptance are defined in the
[Release Policy](releases.md). A local pass, tag, GitHub Release, registry
version, and verified public installation are separate evidence states.

See [ADR-0045](adr/0045-stable-contract-boundary.md) for the decision and
acceptance criteria behind this boundary.
