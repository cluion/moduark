# Moduark

Moduark is a Laravel-native modular architecture toolkit. It keeps Modules in a
normal Laravel application while making their dependencies, lifecycle order,
resources, and architecture boundaries executable and inspectable.

> **Pre-release status:** `0.5.0-beta.1` guarantees complete Level 0 through
> Level 3 presets and adds PHPStan/Larastan adoption guidance, brownfield
> migration recipes, interactive graph examples, and real-project analyzer
> hardening. The zero-configuration default remains Level 1.

## Requirements

- PHP 8.2 or later
- Laravel 12 or 13
- Composer 2.1 or later

## Installation

Install the current beta from Packagist:

```bash
composer require cluion/moduark:^0.5@beta
```

The package is pre-release software. Pin an exact beta version when an
application requires fully repeatable pre-release upgrades.

Laravel package discovery registers `Cluion\Moduark\ModuarkServiceProvider`.
Configuration publishing is optional because package defaults are merged even
when `config/modules.php` does not exist in the application.

For PHPStan or Larastan diagnostics, install the optional development-only
companion package and follow the loading and configuration guide:

```bash
composer require --dev cluion/moduark-phpstan:^0.1@beta
```

See [PHPStan and Larastan Integration](docs/phpstan-integration.md). The
companion extension currently covers `internal_api_access`; `module:check`
remains authoritative for the complete rule set.

## Laravel Boost Agent Skill

The `0.6.x` development line includes a Laravel Boost-compatible
`moduark-development` Skill in this Composer package. Applications using Boost
can run its installation flow after adding or updating Moduark:

```bash
php artisan boost:install
```

Boost discovers the packaged Skill and installs it for the coding agents chosen
by the application. No separate Moduark Codex plugin is required. The installed
Moduark CLI and package documentation remain authoritative; the Skill guides an
agent through inventory, staged Level adoption, diagnostics, reviewed debt, and
upgrade verification. See
[ADR-0044](docs/adr/0044-laravel-boost-agent-skill-distribution.md).

## Stability and Versioning

The current beta remains pre-stable. Moduark now documents the candidate
`1.0.0` compatibility boundary so applications can review it before the stable
release: Levels 0 through 2 are planned as Stable, Level 3 remains an opt-in
Preview, and lifecycle internals such as capability resolver and cache objects
are not application extension points.

See [Stability and Versioning](docs/stability.md) for the PHP, configuration,
CLI, diagnostic, and machine-schema contracts, and
[ADR-0045](docs/adr/0045-stable-contract-boundary.md) for the boundary decision.
Before changing versions, follow [Upgrading Moduark](UPGRADING.md) so caches and
application-owned architecture debt are reviewed rather than rewritten.

## Quick Start

Create the smallest valid Module:

```bash
php artisan make:module User
```

This creates one file, `app/Modules/User/UserModule.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\User;

use Cluion\Moduark\Module;

final class UserModule extends Module
{
}
```

Generate classes inside an existing Module through Moduark's single Maker entry
point:

```bash
php artisan module:make User model Profile
php artisan module:make User controller ProfileController
php artisan module:make User controller ProfileController --invokable
php artisan module:make User controller ProfileController --resource --api
```

Models are generated below `Models/`; controllers are generated below
`Http/Controllers/`. Both types support `--force`. Controllers additionally
support `--invokable`, `--resource`, and `--api`; `--invokable` cannot be combined
with the resource or API modes.

The target Module must already exist and its configured path must be inside the
Laravel application source root. Composite Laravel Maker options that create
related factories, migrations, controllers, requests, policies, seeds, or tests
are deliberately not exposed until every generated file can retain Module
ownership. Delegated Laravel Makers run non-interactively so framework prompts
cannot create undeclared related artifacts. Moduark does not inject `--module`
into Laravel or third-party `make:*` commands. See
[ADR-0032](docs/adr/0032-laravel-maker-integration-direction.md).

Inspect the discovered architecture:

```bash
php artisan module:list
php artisan module:check
php artisan module:check --format=json
php artisan module:check --format=github
php artisan module:graph
php artisan module:graph --format=mermaid
php artisan module:graph --view=capability
php artisan module:graph --view=capability --format=mermaid
php artisan module:graph --view=combined
php artisan module:inspect Order
php artisan module:cache
```

The default configuration uses Level 1, so a successful check evaluates six
rules: Module structure, identity, missing and undeclared dependencies, cycles,
and internal API access.

## Module Metadata

Dependencies, service providers, owned tables, and explicit exports are typed PHP
metadata on the Module entry class. Dependencies are registered before their
consumers.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\Order\Contracts\OrderQuery;
use App\Modules\Order\Data\OrderData;
use App\Modules\Order\Events\OrderPlaced;
use App\Modules\Order\Providers\OrderServiceProvider;
use App\Modules\User\UserModule;
use Cluion\Moduark\Module;

final class OrderModule extends Module
{
    public function dependencies(): array
    {
        return [UserModule::class];
    }

    public function providers(): array
    {
        return [OrderServiceProvider::class];
    }

    public function tables(): array
    {
        return ['orders', 'order_items'];
    }

    public function exports(): array
    {
        return [OrderQuery::class, OrderData::class, OrderPlaced::class];
    }
}
```

Class metadata must contain concrete class strings. `exports()` accepts existing
classes, interfaces, traits, and enums; ownership is verified by the Level 3
rule. `tables()` accepts unique, unquoted dot-separated names such as `orders` or
`audit.events`; one canonical table can have only one Module owner, compared
case-insensitively. Duplicate references, missing Modules, circular dependencies,
and conflicting ownership fail deterministically.

## Level 3 Database Ownership

At Level 3, `database_ownership` compares Laravel query evidence with the
explicit Table Ownership Index. It reports:

- `MOD-TABLE-001` when a Module directly queries another Module's table;
- `MOD-TABLE-002` when a literal table has no declared owner;
- warning `MOD-TABLE-003` when the table expression cannot be resolved safely.

The AST collector recognizes imported or fully qualified `DB::table()`,
`Schema::table()`, their `connection()->table()` forms, and table-bearing
`from()` / `join*()` methods on fluent builders rooted in `DB::table()` or
`DB::query()`. Common literal aliases such as `users as u` are matched to
`users`; an explicit `DB::table()` inside a subquery is collected independently.

Raw SQL, Eloquent table inference, builders stored in variables, callback query
parameters, unimported runtime Facade aliases, connection/schema mapping, and
table prefixes are not guessed. Dynamic or unsupported expressions remain
visible warnings rather than false ownership conclusions. See
[ADR-0037](docs/adr/0037-database-ownership-rule.md).

## Level 3 Migration Ownership

`migration_ownership` requires recognized Laravel schema mutations to live
below the declaring Module's `Database/Migrations/` directory and to reference
tables owned by that Module. It reports:

- `MOD-MIGRATION-001` for another Module's table;
- `MOD-MIGRATION-002` for an unowned literal table;
- `MOD-MIGRATION-003` for schema mutation code outside the migration directory;
- warning `MOD-MIGRATION-004` for a dynamic or unsupported table expression.

The analyzer recognizes imported or fully qualified `Schema::create()`,
`table()`, `rename()`, `drop()`, and `dropIfExists()`, including their
`Schema::connection()` forms. Both `rename()` operands must have explicit
ownership. Keep historical renamed or dropped table names in `tables()` while
shipped migrations still reference them, or record a narrow reviewed
suppression for a deliberate orchestration migration.

Schema macros, custom wrappers, raw SQL schema statements, application-level
migrations outside discovered Modules, connection/schema mapping, and table
prefixes are not inferred. See
[ADR-0038](docs/adr/0038-migration-ownership-rule.md).

## Level 3 Cross-Module Foreign Keys

`cross_module_foreign_keys` audits extraction coupling between tables owned by
different Modules. Its Level 3 default is a warning: a relational monolith may
intentionally keep database integrity while accepting the migration coupling.
It reports:

- `MOD-FK-001` for a resolved foreign key whose tables have different owners;
- warning `MOD-FK-002` when either table cannot be resolved safely;
- `MOD-FK-003` when either resolved table has no declared owner.

The analyzer recognizes `foreign(...)->references(...)->on(...)` and
`foreignId()`, `foreignUuid()`, or `foreignUlid()` followed by
`constrained(...)` on the first Blueprint callback parameter of recognized
`Schema::create()` and `Schema::table()` calls, including connection variants.
Laravel's conventional target-table inference is retained. Model-based
`foreignIdFor()` targets, plus Laravel 13's `foreignUuidFor()` and
`foreignUlidFor()` targets, stay unresolved unless the table is supplied
explicitly because the model table is a runtime decision.

Custom Blueprint macros or wrappers, raw SQL, global migrations, callback
data-flow, runtime model tables, connection/schema mapping, and prefixes are not
inferred. Disable the rule when cross-Module FKs are the project-wide policy, or
use a narrow reviewed suppression for an intentional exception. See
[ADR-0039](docs/adr/0039-cross-module-foreign-keys-rule.md).

## Level 3 Cross-Module Transactions

`cross_module_transactions` audits direct Query Builder writes inside inline
`DB::transaction()` and `DB::connection()->transaction()` callbacks. Its Level 3
default is a warning because an atomic cross-owner workflow can be deliberate in
a modular monolith. It reports:

- `MOD-TRANSACTION-001` when one transaction directly mutates tables owned by
  multiple Modules;
- warning `MOD-TRANSACTION-002` when a direct write target cannot be resolved;
- `MOD-TRANSACTION-003` when a resolved write table has no declared owner.

The analyzer recognizes direct mutation chains rooted in `DB::table()` or
`DB::query()->from()`, including connection variants, and Laravel Query Builder
`insert*`, `update*`, `upsert`, increment/decrement, `delete`, and `truncate`
methods. Raw `DB::insert()`, `update()`, `delete()`, and
`affectingStatement()` remain unresolved warnings because Moduark does not parse
SQL strings.

Repository or Port calls, Eloquent writes, builder variables, nested arbitrary
callbacks, raw SQL target parsing, and manual `beginTransaction()` / `commit()` /
`rollBack()` scopes are not inferred. Keep intentional atomic orchestration with
a narrow reviewed suppression, or move cross-owner writes behind Module Ports.
See [ADR-0040](docs/adr/0040-cross-module-transactions-rule.md).

## Level 3 Explicit Public Exports

`explicit_public_exports` requires every cross-Module class-like reference other
than the Module entry identity to appear in the provider's `exports()` metadata.
It reports:

- `MOD-EXPORT-001` when a consumer references a symbol the provider does not
  explicitly export;
- `MOD-EXPORT-002` when an export is not found in indexed Module source;
- `MOD-EXPORT-003` when a Module attempts to export another Module's symbol.

Level 3 composes this rule with Level 1's `Contracts/`, `Data/`, `Events/`, and
Module-entry convention. Explicit metadata narrows that convention: listing a
`Services/` class in `exports()` does not make it public while
`internal_api_access` remains enabled. The Module entry class is always an
implicit public identity and does not need to list itself.

The rule uses the existing AST symbol/reference index, so PHPDoc and dynamic
class strings are not inferred. It validates visibility and ownership, not API
backward compatibility. See
[ADR-0041](docs/adr/0041-explicit-public-exports-rule.md).

## Level 1 Public API

A declared dependency permits a relationship; it does not make every provider
symbol public. The provider-owned public surface is convention-based:

- the Module entry class;
- named class-like symbols below `Contracts/`;
- named class-like symbols below `Data/`;
- named class-like symbols below `Events/`.

Directory names are exact and case-sensitive. Symbols below `Actions/`,
`Models/`, `Ports/`, `Services/`, `Support/`, and every other directory are
internal by default.

For example, `Order` may reference `User\Contracts\UserFinder` after declaring
`UserModule::class` in `dependencies()`. A direct reference to
`User\Services\UserService` produces `MOD-BOUNDARY-001` even when the Module
dependency is declared.

The analyzer resolves named PHP class-like references from attributes, types,
inheritance, interfaces, traits, catch clauses, construction, static access,
class constants, and `instanceof`. An unused `use` statement, PHPDoc, and dynamic
string references are not treated as observed dependencies in the current beta.

## Laravel Resource Conventions

Existing paths are loaded through Laravel's native mechanisms; absent paths are
ignored.

| Module-relative path | Behavior |
|---|---|
| `routes/web.php` | Loaded as a Laravel route file |
| `routes/api.php` | Loaded as a Laravel route file |
| `resources/views/` | View namespace is the lowercase Module name |
| `resources/lang/` | Translation namespace is the lowercase Module name |
| `Database/Migrations/` | Loaded as application migrations |
| `Console/Commands/*.php` | Concrete commands are registered in console runs |

Module-specific service providers belong in `providers()` metadata. Moduark does
not generate per-Module Composer packages or manifests.

## Configuration

Publish the defaults only when the application needs to change them:

```bash
php artisan vendor:publish --tag=moduark-config
```

```php
<?php

return [
    'path' => app_path('Modules'),

    'architecture' => [
        'level' => 1,
        'baseline' => base_path('moduark-baseline.json'),
        'suppressions' => base_path('moduark-suppressions.json'),
        'rules' => [
            // 'internal_api_access' => false,
        ],
    ],
];
```

Rule overrides are booleans. Unlisted rules inherit the selected Level preset.
Use overrides as documented exceptions: disabling a rule reduces that Level's
guarantee, while enabling an unavailable rule makes the analysis incomplete.

`--level` changes one command run without changing configuration:

```bash
php artisan module:check --level=0
php artisan module:check --level=1
```

See [Architecture Levels](docs/architecture-levels.md) for the complete preset
matrix and [Adopting Moduark](docs/adoption.md) for a staged migration workflow.

## Commands

| Command | Current contract |
|---|---|
| `make:module {name}` | Create one minimal, non-overwriting Module entry class |
| `module:make {module} {type} {name}` | Generate a model or controller inside an existing application Module |
| `module:baseline [--level=0..3] [--force] [--prune]` | Adopt current violations explicitly or safely remove stale baseline debt |
| `module:cache` | Cache deterministic Module discovery and typed metadata |
| `module:clear` | Remove cached Module metadata and incremental source analysis |
| `module:list` | List discovered Modules in deterministic order |
| `module:check [--level=0..3] [--format=text\|json\|github] [--show-suppressions]` | Run the effective architecture rules, audit suppressions, and optionally emit JSON or GitHub Actions annotations |
| `module:graph [module] [--view=module\|capability\|combined] [--format=text\|mermaid]` | Render direct, Capability, or combined relationships and optionally select one neighborhood |
| `module:inspect {module}` | Inspect one Module's identity, dependencies, providers, Capabilities, owned tables, and Public API convention |

`module:check` exit codes are part of the candidate `1.0.0` stable contract:

| Exit | Meaning |
|---:|---|
| `0` | No blocking violation; warnings may exist |
| `1` | One or more blocking architecture violations |
| `2` | Command input, analyzer, or unavailable-rule tool error; result is incomplete |

Use JSON when another tool needs the complete result without parsing terminal
formatting. This option is included in `v0.3.0-beta.1`:

```bash
php artisan module:check --format=json
php artisan module:check --level=2 --format=json
```

Schema version `1` includes `status`, `complete`, `exit_code`, effective
architecture and rule configuration, summary counts, unavailable rules,
per-rule violations, additive suppression and baseline audit metadata, and an
`error` object for failures that occur before a report is produced. Status is
`passed`, `violations_found`, or `incomplete`; the exit codes remain exactly the
same as text output. See
[ADR-0028](docs/adr/0028-module-check-json-output.md).

Use GitHub output in an Actions workflow to attach each violation to its source
file and line while preserving the same exit-code contract:

```yaml
- name: Check Module architecture
  run: php artisan module:check --format=github
```

Errors and warnings become workflow annotations; a clean run emits one notice.
Incomplete analysis and command failures remain errors with exit code `2`. See
[ADR-0029](docs/adr/0029-github-actions-annotations.md).

## Architecture Suppressions

Use a suppression only for one reviewed exception that cannot be fixed yet. The
default `moduark-suppressions.json` is repository-visible and requires a stable
rule, diagnostic code, narrow scope, and non-empty reason:

```json
{
    "schema_version": 1,
    "suppressions": [
        {
            "rule": "internal_api_access",
            "code": "MOD-BOUNDARY-001",
            "file": "app/Modules/Order/Actions/CreateOrder.php",
            "line": 17,
            "reason": "Legacy integration tracked by ADR-012."
        }
    ]
}
```

The scope may select a repository-relative `file` and optional `line`, a
`symbol`, or a `consumer` plus `target` Module pair. Selectors can be combined
for a narrower match. Global ignores, absolute paths, missing reasons, unknown
fields, duplicate selectors, and overlapping matches are tool errors.

Normal text output summarizes suppression debt. Audit every entry and its reason
with:

```bash
php artisan module:check --show-suppressions
```

An entry is `matched`, `stale` when its evaluated rule no longer produces a
match, or `inactive` when that rule was not evaluated at the selected Level.
JSON always includes the structured audit, and GitHub output emits a summary
notice. Suppressions are applied before the architecture baseline, so baseline
creation and pruning never duplicate an explicitly suppressed violation. See
[ADR-0034](docs/adr/0034-auditable-architecture-suppressions.md).

## Architecture Baseline

For a brownfield application, first review the unsuppressed violations, then
create one repository-visible baseline:

```bash
php artisan module:check --level=1
php artisan module:baseline --level=1
git add moduark-baseline.json
```

Normal `module:check` runs automatically apply the configured baseline. The
identity excludes diagnostic wording and line number, but retains rule, code,
severity, file, Module endpoints, and symbol. If the number of matching current
violations grows beyond the recorded count, the whole group is reported so a
new occurrence cannot be guessed away.

Routine cleanup is one-way and cannot adopt new debt:

```bash
php artisan module:baseline --prune
```

The command refuses to overwrite an existing baseline by default. Use
`--force` only after reviewing the complete raw result because replacement can
adopt regressions. Text, JSON, and GitHub output report matched, stale, and
exceeded counts. See [ADR-0031](docs/adr/0031-architecture-baseline-adoption.md).

Starting with the `0.5.x` hardening, undeclared dependencies are reported once
per ordered consumer / provider Module pair. Review and prune stale per-symbol
`MOD-DEPENDENCY-002` baseline entries after upgrading; new entries use stable
pair identity. Migrate file- or symbol-only suppressions to explicit `consumer`
and `target` selectors, and do not carry old amplified counts forward. See
[ADR-0043](docs/adr/0043-real-project-beta-adoption.md).

The graph command defaults to direct Module dependencies. The Capability view
renders typed `requires` and `provides` edges:

```bash
php artisan module:graph --view=capability
php artisan module:graph Order --view=capability
php artisan module:graph --view=capability --format=mermaid
php artisan module:graph --view=combined
php artisan module:graph Order --view=combined --format=mermaid
```

Selecting a Module in the Capability view retains its connected Capabilities,
providers, and other consumers so the relationship remains complete. The
combined view overlays labeled `depends`, `requires`, and `provides` edges and
uses the union of direct and Capability neighborhoods. JSON graph output remains
later work. These views are included in `v0.2.0-beta.2`. `module:check` JSON and
GitHub Actions annotations are included in `v0.3.0-beta.1`; inline suppressions
are intentionally replaced by the reviewable external suppression manifest.
Per-Module check filtering remains later work.

Use `module:inspect Order` when one Module needs more detail than the graph. It
shows the effective architecture level, discovered or missing direct
dependencies, Module ServiceProviders, each required Capability's resolved
provider, consumer Port and Adapter, provided Capabilities, explicit owned
tables, explicit exports, and symbols exposed by the current `Contracts/`,
`Data/`, `Events/`, and Module-entry convention. The two Public API views remain
separate so Level 3 narrowing is directly reviewable.

Application bootstrap happens before Artisan invokes a command. A configuration,
discovery, metadata, or runtime Capability-resolution exception raised during
bootstrap may therefore be rendered by Laravel itself rather than by
`module:check`'s exit-code renderer.

## Module Cache

For deployment, cache Module discovery and typed metadata directly or through
Laravel's optimization command:

```bash
php artisan module:cache
# or
php artisan optimize
```

The versioned scalar PHP manifest is stored at
`bootstrap/cache/moduark.php`. It contains the configured Module root, sorted
discovery records, and dependency-ordered descriptors. Runtime lifecycle,
Capability validation, graphs, inspection, and checks reuse those descriptors;
routes, views, translations, migrations, and Module commands still use their
normal Laravel resource loading.

Rebuild the cache after adding, removing, or moving a Module, or after changing
`dependencies()`, `providers()`, `requires()`, `provides()`, `tables()`, or
`exports()`.
Clear it to return to fresh discovery:

```bash
php artisan module:clear
# or
php artisan optimize:clear
```

An unknown cache schema or a manifest for another configured Module root is
ignored safely. A malformed current-schema manifest fails with its exact cache
path instead of silently booting from ambiguous metadata. See
[ADR-0030](docs/adr/0030-module-metadata-cache.md). This integration is included
in `v0.3.0-beta.2`.

## Incremental Source Analysis

When an enabled rule needs the PHP source index, `module:check` stores an
internal per-file analysis manifest at `bootstrap/cache/moduark-analysis.php`.
Every run still reads each current Module PHP file and computes its SHA-256
content hash. Only entries with the same content hash, Module owner, and cache
schema reuse their symbol, unresolved class-reference, query table-access,
schema mutation, foreign-key, and inline transaction summaries; global symbol
and table ownership are resolved again on every check.

Changed files are parsed again, removed files are pruned, and a moved file is a
new cache entry. An unknown, malformed, or semantically invalid cache falls back
to a complete cold analysis. A failed analysis never replaces the previous
manifest, and an unwritable cache cannot turn a complete fresh result into a
tool error. `module:clear` and `optimize:clear` remove both the Module metadata
cache and this source-analysis cache. `module:cache` intentionally does not
pre-parse application source; the first source-enabled check creates the
incremental manifest. See
[ADR-0033](docs/adr/0033-incremental-source-analysis.md).

## Development

```bash
composer verify
composer test:dependencies
composer test:distribution
composer test:installation
composer test:installation -- --boost
composer benchmark
```

`composer verify` runs PHPUnit and PHPStan level max. The generated performance
baseline exercises cold and content-hash-cached checks over 50 Modules / 5,000
PHP files and 100 Modules / 10,000 PHP files without checking generated fixtures
into Git. See
[ADR-0012](docs/adr/0012-beta-performance-and-analysis-errors.md) for the method
and initial evidence, and
[ADR-0033](docs/adr/0033-incremental-source-analysis.md) for the incremental
comparison.

The Level 2 acceptance fixture models eight business Modules, five shared
Capabilities, and twelve consumer-owned Port/Adapter bindings. It proves all
eight Level 2 rules, runtime container composition, combined graph output, and
`module:inspect` against one connected architecture. See
[ADR-0026](docs/adr/0026-large-level-two-fixture.md).

`composer test:distribution` builds the repository's Git archive and verifies
that runtime source, configuration, stubs, license, and public documentation are
present while `tests/`, `benchmarks/`, `workbench/`, repository automation, and
development-only analysis files are absent. See
[ADR-0027](docs/adr/0027-distribution-archive-contract.md).

`composer test:dependencies` resolves the Laravel 12/13 lowest/highest matrix in
disposable Composer projects. It simulates the supported PHP floors for
dependency solving, leaves Composer's security blocking enabled, and reports
the exact framework, Testbench, and PHPUnit versions selected. It does not
replace executing the test suite on those PHP runtimes.

`composer test:installation` is the slower, networked acceptance matrix. It
creates disposable Laravel 12 and 13 applications, installs this checkout
through a Composer path repository, and exercises package discovery and the core
commands without publishing configuration. It is intentionally separate from
the default offline-friendly verification command. See
[ADR-0013](docs/adr/0013-clean-laravel-installation-matrix.md) for the matrix
contract and initial resolved versions.

Add `--boost` to install Laravel Boost in the same disposable applications,
select Codex and `cluion/moduark` through a deterministic `boost.json`, and run
the skills-only installation twice. This gate verifies that the complete
`moduark-development` Skill is copied to `.agents/skills/` without drift and
that repeated installation is idempotent.

After a version is published, pass an exact version to repeat the same Laravel
acceptance against the Packagist dist instead of the local path repository. This
mode also verifies the installed archive layout:

```bash
composer test:installation -- --package=0.5.0-beta.1
```

The GitHub Actions compatibility workflow runs PHPUnit on all four
Laravel/PHP/dependency combinations and runs the matching clean installation on
both highest-dependency jobs. A separate PHP 8.2 job runs PHPStan against the
highest resolvable tooling dependencies. See
[ADR-0014](docs/adr/0014-ci-compatibility-matrix.md) for the release-gate
contract.

## Documentation

- [Stability and Versioning](docs/stability.md)
- [Upgrading Moduark](UPGRADING.md)
- [Security Policy](SECURITY.md)
- [Contributing to Moduark](CONTRIBUTING.md)
- [Architecture Levels](docs/architecture-levels.md)
- [Adopting Moduark](docs/adoption.md)
- [Migration Recipes](docs/recipes/README.md)
- [Interactive Graph Examples](docs/graph-examples.md)
- [PHPStan and Larastan Integration](docs/phpstan-integration.md)
- [Architecture Decision Records](docs/adr/0001-package-baseline.md)
- [Changelog](CHANGELOG.md)

## Current Scope

The `v0.5.0-beta.1` release guarantees foundation plus complete Level 0 through
Level 3 presets. Level 2 includes typed Capability metadata, descriptor-only
provider resolution, lifecycle preflight, consumer-owned Port wiring,
Capability contract validation, source-enforced Adapter boundaries,
deterministic Capability and combined graphs, `module:inspect`, and the large
Level 2 acceptance fixture. Developer Experience output includes versioned JSON
reports, GitHub Actions annotations, and deterministic Module metadata caching
with Laravel optimize integration. Brownfield adoption includes a reviewable
architecture baseline with conservative count matching and safe pruning.
Reviewed architecture exceptions use an auditable external suppression manifest
with narrow selectors, mandatory reasons, and stale/inactive reporting.
Module-aware Makers generate models and controllers inside existing application
Modules, while content-hash caching reuses unchanged per-file source analysis
without persisting cross-file ownership decisions.
All six Level 3 rules audit direct cross-Module Eloquent Model, table, migration,
foreign-key, inline transaction, and explicit export access. Explicit `tables()`
metadata feeds a deterministic single-owner index; Laravel-aware AST evidence
covers literal Facade queries, Schema mutations, Blueprint constraints, and
direct Query Builder writes inside transaction callbacks, while unresolved
expressions remain reviewable warnings. Explicit `exports()` metadata narrows the
convention-based Public API. The complete fourteen-rule Level 3 preset can now
produce a complete pass. The optional `cluion/moduark-phpstan`
`v0.1.0-beta.2` companion beta integrates `internal_api_access` with PHPStan and
Larastan; suppression expiry and extension coverage for the remaining rules
remain later work. See
[ADR-0035](docs/adr/0035-cross-module-model-access.md),
[ADR-0036](docs/adr/0036-table-ownership-index.md),
[ADR-0037](docs/adr/0037-database-ownership-rule.md),
[ADR-0038](docs/adr/0038-migration-ownership-rule.md),
[ADR-0039](docs/adr/0039-cross-module-foreign-keys-rule.md),
[ADR-0040](docs/adr/0040-cross-module-transactions-rule.md), and
[ADR-0041](docs/adr/0041-explicit-public-exports-rule.md). The optional
integration boundary is defined by
[ADR-0042](docs/adr/0042-phpstan-extension-integration-boundary.md). The final
`v0.5.0-beta.1` release adopts two complete existing Laravel applications as
static corpora: 1,511 PHP files produced 807 table accesses, 413 Schema
mutations, and 156 foreign-key references. Independent token-based oracles
reached zero resolved-line misses, zero table-evidence anchoring collisions, and
complete recall across 1,077 literal Facade and fluent table operations after
hardening.
Command discovery now permits co-located interfaces, traits, enums, and abstract
classes, query evidence points at the literal table argument, and undeclared
dependencies report once per ordered Module pair. See
[ADR-0043](docs/adr/0043-real-project-beta-adoption.md).

Moduark is open-source software licensed under the [MIT License](LICENSE).
