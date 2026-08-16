# Architecture Levels

Moduark Levels are preset collections of independent architecture rules. A Level
is not a conditional hidden inside the analyzer: configuration resolves the
preset plus explicit boolean overrides into an effective rule set, and
`module:check` reports whether every enabled rule has an implementation.

## Availability

| Level | Label | Beta status | Intended guarantee |
|---:|---|---|---|
| 0 | Organization | Implemented | Valid, uniquely identified Module structure |
| 1 | Modular | Implemented | Explicit dependencies, acyclic graph, provider-owned Public API |
| 2 | Decoupled | Implemented in `0.2` beta | Consumer-owned Ports, adapters, capability contracts |
| 3 | Isolated | Partially implemented, incomplete | Model, database, migration, transaction, and export boundaries |

The package default is Level 1. In the `0.2` beta, the normal Level 2 preset has
eight implemented rules and can produce a complete pass. Level 3 now has its
first five implemented rules but still returns exit code 2 because one enabled
rule remains unavailable; that is an incomplete analysis, not an
architecture pass.

## Preset Matrix

`E` means blocking error, `W` means non-blocking warning, and `—` means the
preset leaves the rule disabled.

| Rule ID | L0 | L1 | L2 | L3 | Implemented |
|---|:---:|:---:|:---:|:---:|:---:|
| `valid_module_structure` | E | E | E | E | Yes |
| `unique_module_identity` | E | E | E | E | Yes |
| `missing_dependencies` | — | E | E | E | Yes |
| `undeclared_dependencies` | — | E | E | E | Yes |
| `cycles` | — | E | E | E | Yes |
| `internal_api_access` | — | E | E | E | Yes |
| `capability_contracts` | — | — | E | E | Yes |
| `adapter_boundaries` | — | — | E | E | Yes |
| `cross_module_model_access` | — | — | — | E | Yes |
| `database_ownership` | — | — | — | E | Yes |
| `migration_ownership` | — | — | — | E | Yes |
| `cross_module_foreign_keys` | — | — | — | W | Yes |
| `cross_module_transactions` | — | — | — | W | Yes |
| `explicit_public_exports` | — | — | — | E | No |

Disabling an enabled rule weakens that preset's guarantee. The `0.2` beta
supports Level 0, Level 1, and the complete Level 2 preset. Disabling unavailable
Level 3 rules does not create a Level 3 guarantee.

## Level 0 — Organization

Level 0 validates the discovery contract without parsing every Module PHP file.
It guarantees:

- a Module directory has the matching `{Name}Module.php` entry file;
- the entry file declares the expected concrete `Module` subclass;
- Module names and entry classes are unique, including case-insensitive
  collisions;
- discovery order is deterministic.

Use Level 0 when first introducing Module folders to an existing application. It
does not enforce dependency declarations, cycles, or cross-Module source access.

```php
'architecture' => [
    'level' => 0,
    'rules' => [],
],
```

## Level 1 — Modular

Level 1 adds four enforceable relationships to Level 0:

1. Every metadata dependency must resolve to a discovered Module.
2. Every observed cross-Module class-like reference must have a corresponding
   `dependencies()` declaration.
3. The Module dependency graph must be acyclic.
4. Cross-Module references may target only the provider's Public API.

The Public API consists of the provider Module entry class and named class-like
symbols recursively below exact, case-sensitive `Contracts/`, `Data/`, and
`Events/` directories. Everything else is internal by default, including
`Ports/`.

Dependency declaration and visibility are independent:

- using a public symbol without metadata produces `MOD-DEPENDENCY-002`;
- using an internal symbol still produces `MOD-BOUNDARY-001` after metadata is
  added.

The source index uses `nikic/php-parser` name resolution and records canonical
symbols with file and line evidence. It observes attributes, declared types,
extends/implements, trait use, catch types, `new`, static access, class constants,
and `instanceof`. It does not infer PHPDoc, dynamic strings, or an unused import.

## Level 2 — Decoupled

Level 2 adds consumer-owned Ports, provider-scoped Adapters, and typed Capability
requirements/providers. An application defines a typed Capability identity and
declares what a Module requires or provides.

```php
use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Module;

interface UserLookup extends Capability
{
}

final class OrderModule extends Module
{
    public function requires(): array
    {
        return [
            new CapabilityRequirement(
                UserLookup::class,
                FindUser::class,
                UserModuleAdapter::class,
            ),
        ];
    }
}
```

`FindUser` is the consumer-owned Port interface and `UserModuleAdapter` is an
instantiable Adapter implementing it. A provider declares the same Capability
identity from `provides()`. Metadata compilation validates these declarations
and serializes them using cache-safe class strings and arrays. Given the complete
compiled descriptor list, provider resolution is deterministic and side-effect
free:

```php
$plan = (new CapabilityResolver)->resolve($descriptors);
```

Each plan binding identifies the Capability, provider Module, consumer Module,
Port, and Adapter. Missing providers and consumed Capabilities with multiple
providers fail before any Module ServiceProvider or container work begins.

The Module lifecycle runs this resolution as a preflight after direct dependency
ordering and before registering any Module ServiceProvider. After every provider
registers successfully, it binds each consumer Port to its declared Adapter in
Laravel's container. A Port may belong to only one consumer requirement across
the complete Module graph; collisions fail during preflight instead of relying
on Laravel's last-binding-wins behavior.

The metadata-only `capability_contracts` rule evaluates the complete descriptor
set without parsing Module source:

- `MOD-CAPABILITY-001`: a required Capability has no provider;
- `MOD-CAPABILITY-002`: a required Capability has multiple providers;
- `MOD-CAPABILITY-003`: multiple consumer Modules declare the same Port.

Resolution is demand-driven, so multiple providers for an unused Capability do
not violate the rule. Metadata compilation separately rejects invalid Capability
identities, non-interface Ports, invalid Adapters, and duplicates inside one
Module before architecture rules run.

The source-backed `adapter_boundaries` rule enforces the accepted inversion
boundary:

- `MOD-ADAPTER-001`: the Port must be owned by the consumer below exact
  `Ports/`;
- `MOD-ADAPTER-002`: the Adapter must be owned by the consumer below exact
  `Adapters/{Provider}/`;
- `MOD-ADAPTER-003`: cross-Module code outside Module dependency metadata must
  pass through a declared Capability Adapter;
- `MOD-ADAPTER-004`: an Adapter may cross only into its selected provider;
- `MOD-ADAPTER-005`: consumer core code may not reference its concrete Adapter
  and must depend on the Port.

The provider must not reference a consumer Port or Adapter. The direct
`dependencies()` entry remains visible metadata and authorizes the consumer
Adapter's provider reference; other consumer core files cannot use that
exception. Provider selection errors remain owned by `capability_contracts`, so
the source rule defers provider-specific checks when the provider graph is
missing or ambiguous instead of emitting cascading diagnostics.

In `v0.2.0-beta.2`, inspect the direct dependency and Capability views
separately:

```bash
php artisan module:graph
php artisan module:graph --view=capability
php artisan module:graph Order --view=capability
php artisan module:graph --view=capability --format=mermaid
php artisan module:graph --view=combined
php artisan module:graph Order --view=combined --format=mermaid
php artisan module:inspect Order
```

The Capability view preserves `requires` and `provides` as different edge kinds.
A selected Module retains the complete provider/consumer relationship for each
connected Capability. The combined view adds labeled `depends` edges and uses
the union of direct and Capability neighborhoods without flattening their
meaning. JSON graph output remains separate work. See
[ADR-0022](adr/0022-capability-graph-domain.md),
[ADR-0023](adr/0023-capability-graph-output.md), and
[ADR-0024](adr/0024-combined-graph-output.md).

`module:inspect {module}` reuses those validated graphs to display one Module's
direct dependency status and resolved Capability details, together with its
ServiceProviders, explicit owned tables, and convention-based Public API. It
deliberately does not claim the Level 3 explicit exports contract. See
[ADR-0025](adr/0025-module-inspection.md).

The repository also carries a connected Level 2 acceptance fixture rather than
validating these rules only as isolated units:

| Role | Modules | Capability relationships |
|---|---|---:|
| Providers | Catalog, Customer, Inventory, Notification, Payment | 5 provided Capabilities |
| Consumers | Checkout, Fulfillment, Returns | 12 consumer-owned Port/Adapter bindings |

All eight Modules pass the complete Level 2 preset. The three consumer workflow
actions are resolved through Laravel's container, and the same topology is
exercised through combined graph and Module inspection commands. See
[ADR-0026](adr/0026-large-level-two-fixture.md).

Running the normal preset demonstrates this explicitly:

```bash
php artisan module:check --level=2
```

On a valid architecture, the command evaluates all eight Level 2 rules and exits
0. Violations exit 1 using the stable diagnostics above.

## Level 3 — Isolated

Level 3 is reserved for Eloquent/model access, table ownership, migration
ownership, foreign keys, transaction warnings, and explicit export metadata.

The first implemented Level 3 rule is `cross_module_model_access`. It identifies
classes that directly extend `Illuminate\Database\Eloquent\Model` or inherit
from an indexed Module class that does, then reports every direct cross-Module
reference as `MOD-MODEL-001`. Existing AST evidence covers declared types,
inheritance, `new`, static access, `::class`, and `instanceof`; Eloquent relation
targets written as `OtherModel::class` are therefore included. Same-Module
references, non-Model symbols, PHPDoc, dynamic class strings, unused imports,
and ancestry outside indexed Module source are not inferred.

This Model rule remains independent from the Level 1 Public API rule: one direct
Model access can correctly violate both visibility and persistence-isolation
contracts. Use an identifier plus a Port or exported boundary instead of sharing
the Eloquent object. See [ADR-0035](adr/0035-cross-module-model-access.md).

Modules may now declare explicit owned tables as Level 3 metadata:

```php
public function tables(): array
{
    return ['orders', 'order_items', 'audit.events'];
}
```

Names are unquoted dot-separated identifiers. Ownership lookup and conflict
detection are case-insensitive while the declared spelling is preserved for
output. A canonical table has exactly one owner; multiple claims are invalid
metadata. `module:inspect` displays the selected Module's indexed tables.
Migration inference, shared/legacy table overrides, and connection scoping are
deliberately not inferred by this foundation. See
[ADR-0036](adr/0036-table-ownership-index.md).

`database_ownership` now compares that index with Laravel-aware AST evidence for
imported or fully qualified `DB::table()`, `Schema::table()`, their connection
variants, and table-bearing `from()` / `join*()` calls on recognized fluent
query roots. It emits blocking `MOD-TABLE-001` for another Module's table and
`MOD-TABLE-002` for an unowned literal. Dynamic or unsupported table expressions
emit non-blocking `MOD-TABLE-003` warnings because Moduark does not guess an
owner. Common literal table aliases are normalized; raw SQL, Eloquent table
inference, builder variables, callback query parameters, runtime Facade aliases,
connection/schema mapping, and table prefixes are not inferred. See
[ADR-0037](adr/0037-database-ownership-rule.md).

`migration_ownership` recognizes imported or fully qualified
`Schema::create()`, `table()`, `rename()`, `drop()`, and `dropIfExists()` calls,
including `Schema::connection()` variants. Schema mutations outside the source
Module's `Database/Migrations/` directory emit `MOD-MIGRATION-003`. Inside that
directory, another Module's table emits `MOD-MIGRATION-001`, an unowned table
emits `MOD-MIGRATION-002`, and an unresolved expression emits non-blocking
`MOD-MIGRATION-004`. Both rename operands are checked; historical names must
remain explicit metadata or use a narrow reviewed suppression. Schema macros,
custom wrappers, raw SQL, application-level migrations outside discovered
Modules, connection/schema mapping, and prefixes are not inferred. See
[ADR-0038](adr/0038-migration-ownership-rule.md).

`cross_module_foreign_keys` recognizes Blueprint constraints only when they are
rooted on the first callback parameter of an imported or fully qualified
`Schema::create()` or `Schema::table()` call, including connection variants.
It covers `foreign(...)->references(...)->on(...)`, explicit or conventional
`foreignId()`, `foreignUuid()`, and `foreignUlid()` targets, and explicit
`foreignIdFor()` targets, plus Laravel 13's `foreignUuidFor()` and
`foreignUlidFor()` targets, when `constrained('table')` makes the runtime table
explicit. It emits advisory
`MOD-FK-001` for different owners, non-blocking `MOD-FK-002` for unresolved
evidence, and `MOD-FK-003` for missing ownership. The Level 3 preset uses
warning severity because cross-Module FKs can be an intentional integrity versus
extraction trade-off. Runtime model tables, macros, wrappers, raw SQL, callback
data-flow, global migrations, connection/schema mapping, and prefixes are not
inferred. See [ADR-0039](adr/0039-cross-module-foreign-keys-rule.md).

`cross_module_transactions` inspects inline `DB::transaction()` and
`DB::connection()->transaction()` callbacks for direct Query Builder writes
rooted in `DB::table()` or `DB::query()->from()`, including connection variants.
It emits advisory `MOD-TRANSACTION-001` when one transaction mutates more than
one table owner, fixed warning `MOD-TRANSACTION-002` for unresolved direct write
targets, and `MOD-TRANSACTION-003` for missing ownership. Raw DB write methods
remain unresolved evidence. Eloquent and Repository writes, builder variables,
nested arbitrary callbacks, SQL parsing, and manual transaction scopes are not
inferred. See [ADR-0040](adr/0040-cross-module-transactions-rule.md).

The remaining explicit-public-exports rule is still unavailable. Selecting
Level 3 therefore continues to exit 2 with an incomplete report after evaluating
thirteen implemented rules.

## Rule Overrides

Only boolean overrides are accepted:

```php
'architecture' => [
    'level' => 1,
    'rules' => [
        'internal_api_access' => false,
    ],
],
```

- `true` enables the rule with its default severity when the preset leaves it
  disabled.
- `false` disables the rule even when the preset enables it.
- an unknown rule ID or non-boolean value is a configuration error.
- an enabled rule without an implementation makes the report incomplete and
  returns exit 2.

Overrides are global rule switches. When the rule should remain active, prefer
one reviewed entry in `moduark-suppressions.json` over disabling it globally.
Each suppression requires a stable rule and code, a reason, and a narrow file,
symbol, or Module-pair selector; global ignores are rejected. Use a baseline for
larger reviewed brownfield debt and `module:baseline --prune` as that debt is
removed. Suppressions are applied before baseline matching and generation. See
[ADR-0034](adr/0034-auditable-architecture-suppressions.md).

## Diagnostics and Exit Policy

Architecture violations include a stable code, rule and severity, message,
location when available, Module relationship, symbol evidence, and a suggested
repair. Blocking violations return exit 1. Warnings alone return exit 0.

`module:check --show-suppressions` lists each suppression's scope, reason,
match count, and `matched`, `stale`, or `inactive` audit state. Stale or inactive
entries remain visible debt metadata but do not change the architecture exit
policy. Invalid or overlapping suppression definitions return exit 2.

Command input, parse, duplicate-symbol, filesystem, and unavailable-rule failures
handled by `module:check` return exit 2. Typed source-analysis failures use
`MOD-ANALYSIS-001`, report their source location when known, and state that no
passing result was produced. Configuration, discovery, metadata, or runtime
Capability resolution can fail during Laravel bootstrap before the command's
renderer runs; those exceptions use Laravel's process-level handling.

Use `--level` to evaluate a migration target without editing configuration:

```bash
php artisan module:check --level=1
```

When it passes, update `config/modules.php` so local runs and CI use the same
default.
