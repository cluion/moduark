# Moduark

Moduark is a Laravel-native modular architecture toolkit. It keeps Modules in a
normal Laravel application while making their dependencies, lifecycle order,
resources, and architecture boundaries executable and inspectable.

> **Stable status:** `1.2.0` is the current stable release. Levels 0 through 2
> are Stable, Level 3 remains Preview, and the zero-configuration default
> remains Level 1. This minor release completes Runtime Completeness around one
> canonical resource manifest while retaining nwidart active-set
> interoperability.

## Requirements

- PHP 8.2 or later
- Laravel 12 or 13
- Composer 2.1 or later

## Installation

Install the stable line from Packagist:

```bash
composer require cluion/moduark:^1.2
```

Laravel package discovery registers `Cluion\Moduark\ModuarkServiceProvider`.
Configuration publishing is optional because package defaults are merged even
when `config/moduark.php` does not exist in the application.

When `nwidart/laravel-modules` is installed, Moduark keeps nwidart's
`module:*` commands and `config/modules.php` untouched. A `null` `moduark.path`
follows nwidart's configured Module root when that package is present and uses
`app/Modules` otherwise; set a non-empty path to override auto-detection. Moduark
discovers entry classes at either `<Module>/<Module>Module.php` or
`<Module>/app/<Module>Module.php`. When Moduark and nwidart resolve the same
Module root, nwidart's active Module set is authoritative, including after
Laravel config caching: disabling a Module removes it from Moduark's
registry, analysis, graphs, cache, providers, Capability bindings, and native
resources; re-enabling it restores those surfaces. nwidart continues to own
its conventional routes, views, translations, migrations, and direct commands,
while resources explicitly declared by `Module::resources()` remain
Moduark-owned. An explicit non-empty `moduark.path` remains independent when it
does not resolve to nwidart's Module root. Publish Moduark settings independently
with:

```bash
php artisan vendor:publish --tag=moduark-config
```

The unreleased `1.3` activation commands persist standalone state in the
configurable `moduark.activation.path` (`moduark-modules.json` by default), or
update nwidart's configured file-activator status file when both packages share
the same Module root. Custom nwidart activators remain dry-run-only unless they
can provide the same atomic contract.

nwidart-generated Module classes must already be Composer-autoloadable. Follow
nwidart's installation guidance by loading `Modules/*/composer.json` through
its Composer merge plugin, or provide equivalent explicit per-Module PSR-4
mappings, then run `composer dump-autoload`.

For nwidart's default external `Modules/` root, use nwidart's `module:make` and
`module:make-*` commands to create Modules and their Laravel classes, and place
the Moduark entry at `Modules/<Name>/app/<Name>Module.php`. Moduark's Maker
commands target Modules inside Laravel's application source root; in
particular, `moduark:make` intentionally rejects an external Module path. See
[Adopting Moduark](docs/adoption.md) for the complete setup.

See [ADR-0047](docs/adr/0047-nwidart-interoperability.md),
[ADR-0048](docs/adr/0048-nwidart-active-module-set.md), and the
[upgrade guide](UPGRADING.md) for the RC.1 namespace migration.

The optional `cluion/moduark-phpstan` `v0.2.0` companion supports the Moduark
`^1.0` line, defaults to `config/moduark.php`, and understands both
classic and nwidart `Modules/*/app` source roots. Install it as a development
dependency:

```bash
composer require --dev cluion/moduark-phpstan:^0.2
```

See [PHPStan and Larastan Integration](docs/phpstan-integration.md). The
companion extension covers only `internal_api_access`; `moduark:check` remains
authoritative for the complete rule set.

## Laravel Boost Agent Skill

The `1.0.0` release includes a Laravel Boost-compatible
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

The `1.x` release line is Stable for Levels 0 through 2. Level 3 remains an
opt-in Preview, and lifecycle internals such as capability resolver and cache
objects are not application extension points. RC.2 validated the revised
command and configuration namespaces in a real nwidart application before the
same boundary was promoted to stable.

See [Stability and Versioning](docs/stability.md) for the PHP, configuration,
CLI, diagnostic, and machine-schema contracts, and
[ADR-0045](docs/adr/0045-stable-contract-boundary.md) for the boundary decision.
The Level 3 promotion review remains a documented no-go for `1.0.0`; see
[ADR-0046](docs/adr/0046-level-three-preview-go-no-go.md).
Before changing versions, follow [Upgrading Moduark](UPGRADING.md) so caches and
application-owned architecture debt are reviewed rather than rewritten.

## Quick Start

Create the smallest valid Module:

```bash
php artisan moduark:make-module User
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

Choose an additive scaffold preset when the Module needs more than its entry
class:

```bash
php artisan moduark:make-module Blog --preset=minimal
php artisan moduark:make-module Blog --preset=web
php artisan moduark:make-module Blog --preset=api
php artisan moduark:make-module Blog --preset=domain
php artisan moduark:make-module Blog --preset=full
php artisan moduark:make-module Blog --preset=full --dry-run
php artisan moduark:make-module Blog --preset=full --dry-run --format=json
```

`web` adds Module-owned routes, an invokable controller, view, English
translations, and feature test. `api` adds routes, an invokable controller,
request, resource, and feature test. `domain` adds tracked `Domain/`,
`Application/`, and `Infrastructure/` roots. `full` is their deterministic
union. The command preflights every target before writing, never overwrites an
existing target, and rolls back the complete scaffold when a write fails.
`--dry-run` displays the same ordered plan without filesystem mutation. Presets
do not run a package manager or install frontend dependencies.

Generate classes inside an existing Module through Moduark's single Maker entry
point:

```bash
php artisan moduark:make User model Profile
php artisan moduark:make User controller ProfileController
php artisan moduark:make User controller ProfileController --invokable
php artisan moduark:make User controller ProfileController --resource --api
php artisan moduark:make User model Profile --dry-run
php artisan moduark:make User model Profile --dry-run --format=json
php artisan moduark:make User model Profile --factory --migration
php artisan moduark:make User class Support/InvokableTask --invokable
php artisan moduark:make User cast Money/AmountCast --inbound
php artisan moduark:make User channel Billing/InvoiceChannel
php artisan moduark:make User command SyncOrders --command=orders:sync
php artisan moduark:make User config billing/services
php artisan moduark:make User enum Workflow/Status --string
php artisan moduark:make User event Billing/InvoicePaid
php artisan moduark:make User exception Billing/PaymentFailed --render --report
php artisan moduark:make User factory Billing/InvoiceFactory --model=Profile
php artisan moduark:make User interface Lookup/UserLookup
php artisan moduark:make User job Billing/ProcessInvoice
php artisan moduark:make User job Billing/SyncInvoice --sync
php artisan moduark:make User job Billing/ReconcileInvoices --batched
php artisan moduark:make User job-middleware Billing/WithoutOverlappingInvoices
php artisan moduark:make User listener Billing/SendInvoiceReceipt --event=Billing/InvoicePaid --queued
php artisan moduark:make User mail Billing/InvoiceReceipt
php artisan moduark:make User middleware Admin/EnsureProfileIsComplete
php artisan moduark:make User notification Billing/InvoicePaid
php artisan moduark:make User migration CreateAuditLogsTable --create=audit_logs
php artisan moduark:make User migration AddStatusToProfilesTable --table=profiles
php artisan moduark:make User observer Audit/ProfileObserver
php artisan moduark:make User observer Profile/ProfileObserver --model=Profile
php artisan moduark:make User policy Admin/ManageProfiles
php artisan moduark:make User policy Profile/ProfilePolicy --model=Profile --guard=web
php artisan moduark:make User provider Billing/BillingServiceProvider
php artisan moduark:make User request Profile/StoreProfileRequest
php artisan moduark:make User resource Profile/ProfileResource
php artisan moduark:make User resource Profile/ProfileCollection --collection
php artisan moduark:make User resource Profile/ProfileJsonApiResource --json-api
php artisan moduark:make User rule Profile/ValidDisplayName
php artisan moduark:make User rule Profile/RequiredProfile --implicit
php artisan moduark:make User scope Visibility/PublishedScope
php artisan moduark:make User seeder Billing/ProfileSeeder
php artisan moduark:make User test Billing/InvoiceFeatureTest
php artisan moduark:make User test Billing/InvoiceUnitTest --unit
php artisan moduark:make User test Billing/InvoicePestTest --pest
php artisan moduark:make User job Billing/RebuildInvoiceIndex --test
php artisan moduark:make User trait Serialization/SerializesAttributes
```

Models are generated below `Models/`; controllers are generated below
`Http/Controllers/`. Both types support `--force`. Controllers additionally
support `--invokable`, `--resource`, and `--api`; `--invokable` cannot be combined
with the resource or API modes. `--dry-run` resolves and validates the complete
generation plan, including collisions, then displays each Module-relative target
without writing files. With `--force`, an existing target is shown as
`OVERWRITE`; otherwise it remains a collision.

Models additionally support `--factory` and `--migration`. These options plan a
factory below `Database/Factories/` and a create-table migration below
`Database/Migrations/`, wire `Model::factory()` to the Module-owned factory, and
commit the complete plan through one rollback-capable executor. Any preflight
collision prevents every write. If a later write fails, newly created targets
are removed and overwritten targets are restored; an incomplete rollback is
reported as a tool error rather than claimed as atomic success.

The PHP type Makers delegate to Laravel's native stubs while fixing their Module
ownership before execution. Generic classes use the name-relative Module path;
casts use `Casts/`, enums use `Enums/`, exceptions use `Exceptions/`, interfaces
use the Level 1 Public API convention `Contracts/`, scopes use `Models/Scopes/`,
and traits use `Concerns/`. Classes support `--invokable`; casts support
`--inbound`; enums support `--int` and `--string`; exceptions support `--render`
and `--report`. All seven PHP types support nested names, `--force`, and
`--dry-run` through the same plan and collision preflight.

Application/framework Makers complete the 31-name Laravel 12 / 13 inventory.
Commands are direct classes below `Console/Commands/`, use Laravel's native
stub, and accept `--command=`, `--force`, and Module-owned matching-test options.
The Maker retains its 1.1 direct-class contract; hand-written nested commands
can opt into recursive runtime discovery through `resources()`. Config files are
template-backed targets below the Module's lowercase `config/` tree; generating
one does not write to the application's `config/` directory, and runtime merge
or publication remains an explicit resource declaration. Providers are
template-backed below `Providers/`; generation
never invokes Laravel's native provider Maker because that command mutates
`bootstrap/providers.php`. Add generated providers explicitly to the Module's
`providers()` metadata. See
[ADR-0055](docs/adr/0055-application-framework-maker-ownership.md).

HTTP request and resource Makers also retain Laravel's native stubs while fixing
ownership first. Requests use `Http/Requests/`; resources use
`Http/Resources/`. Resources support standard JSON resources,
`--collection`, and `--json-api` modes. The two specialized resource modes are
mutually exclusive so one requested stub cannot silently override the other.
Both types support nested names, `--force`, and `--dry-run`.

Middleware uses the Module-owned `Http/Middleware/` path and Laravel's native
stub. It supports nested names and `--dry-run`. Laravel's Middleware Maker does
not expose `--force`, so Moduark rejects that option instead of emulating an
overwrite. `--test`, `--pest`, and `--phpunit` add a Module-owned matching test
to the same preflighted, rollback-safe plan.

Policies use the Module-owned `Policies/` path and Laravel's native plain or
model-aware stubs. A relative `--model=Profile` is intentionally resolved as
the selected Module's `Models\Profile`; external fully qualified model names
are rejected. `--guard` selects Laravel's application auth user provider and
does not create another user or model. Policy generation remains a single-file
plan and supports `--force` and `--dry-run`.

Validation rules use the Module-owned `Rules/` path and Laravel's native
`ValidationRule` stub. `--implicit` selects Laravel's implicit-rule variant;
both modes remain single-file plans and support nested names, `--force`, and
`--dry-run` without creating requests, policies, or other related artifacts.

Standalone factories and seeders stay below the selected Module's
`Database/Factories/` and `Database/Seeders/` directories. They use
Moduark-owned templates because Laravel's native commands hard-code the
application-level `database/` path. Factory names receive the conventional
`Factory` suffix and infer a same-name Module model unless `--model` supplies a
different Module-relative model. Neither Maker supports `--force`, matching its
Laravel 12 / 13 native option contract, and neither changes a model or root
`DatabaseSeeder`.

Observers use the Module-owned `Observers/` path and Laravel's native plain or
model-aware stub. A relative `--model=Profile` resolves only inside the selected
Module's `Models\Profile`; external fully qualified model names are rejected.
Observers support nested names, `--force`, and `--dry-run`, but do not create a
model or register themselves with a provider or event listener.

Standalone migrations use the Module-owned `Database/Migrations/` path and
Moduark-owned copies of Laravel's plain, create-table, and update-table stubs.
The StudlyCase input name is normalized to Laravel's snake_case timestamped
filename. `--create=table` and `--table=table` select the corresponding stub;
without either option, Laravel-compatible name patterns infer the mode or fall
back to the plain stub. The two options are mutually exclusive. Standalone
migrations reject `--force`, duplicate logical names, nested names, and invalid
table identifiers, and never write to the application-level `database/` tree.

Events use the Module-owned `Events/` path and Laravel's native event stub.
They support nested names, `--force`, and `--dry-run` through the shared
single-target plan, and never create listeners or provider registrations.

Listeners use the Module-owned `Listeners/` path and Laravel's native plain,
typed, queued, or typed-queued stub. A relative `--event=Billing/InvoicePaid`
is validated and qualified below the selected Module's `Events/` namespace;
external event classes are rejected. Listener generation supports `--force`
and `--dry-run`, but never creates the referenced event or provider registration.

Jobs use the Module-owned `Jobs/` path and Laravel's native queued, synchronous,
or batched queued stub. The default is queued; `--sync` selects the synchronous
stub and `--batched` selects the batch-aware queued stub. The two modes are
mutually exclusive. Job generation supports `--force` and `--dry-run`, but does
not create matching tests, queue infrastructure, or batch migrations.

Job middleware uses the Module-owned `Jobs/Middleware/` path and Laravel's
native middleware stub. It supports `--force` and `--dry-run`, but does not
create jobs, matching tests, queue infrastructure, or registration.

Notifications use the Module-owned `Notifications/` path and Laravel's native
plain notification stub. They support `--force` and `--dry-run`. Laravel's
`--markdown` mode also writes an application-global view below `resources/views/`,
so Moduark explicitly rejects it and never creates a related view.

Mailables use the Module-owned `Mail/` path and Laravel's native plain mail
stub. They support `--force` and `--dry-run`. Laravel's `--markdown` and
`--view` modes also write application-global views below `resources/views/`,
so Moduark rejects both before generation.

Broadcast channels use the Module-owned `Broadcasting/` path and Laravel's
native channel stub. The generated `join` method references the application's
configured authentication-provider model, but generation does not create that
model, matching tests, routes, providers, or channel registration.

Blade components support class, inline, anonymous-view, and custom-view-path
modes. Class components live below `View/Components/`; related Blade files live
below the same Module's `resources/views/` tree and use the lowercase Module
view namespace. Default class-and-view generation is planned, preflighted, and
rolled back as one atomic operation. `--inline` creates no view, while a
value-less `--view` creates only an anonymous Blade view. `--path=` accepts only
Module-relative lowercase kebab-case directory segments.

Standalone Blade views accept nested dot, slash, or backslash names and write a
single deterministic target below the selected Module's `resources/views/`.
Names normalize to lowercase kebab-case paths; `--extension=` defaults to
`blade.php` and accepts only lowercase alphanumeric dot segments.

Verification targets live below each Module's fixed `Tests/Feature/` or
`Tests/Unit/` root and use the Module namespace. `test` defaults to a feature
test, `--unit` selects the unit root, and `--pest` / `--phpunit` select the
runner syntax. When neither runner flag is explicit, an installed Pest
application is detected using Laravel's native convention; explicit
`--phpunit` takes precedence. Laravel Makers with matching-test support accept
`--test`, `--pest`, or `--phpunit` and add the matching Module-owned feature
test to the same preflighted, rollback-safe plan. No mode writes to the
application-global `tests/` tree.

The target Module must already exist and its configured path must be inside the
Laravel application source root. Other composite Laravel Maker options that
create controllers, requests, policies, or seeds remain deliberately unexposed
until every generated file can retain Module ownership. Delegated
Laravel Makers run non-interactively so framework prompts
cannot create undeclared related artifacts. Moduark does not inject `--module`
into Laravel or third-party `make:*` commands. See
[ADR-0032](docs/adr/0032-laravel-maker-integration-direction.md). The reviewed
Laravel 12/13 Maker inventory and executable `1.1` registry boundary are recorded
in [ADR-0049](docs/adr/0049-generator-registry-contract.md). Composite ownership
and rollback semantics are recorded in
[ADR-0050](docs/adr/0050-composite-generation-atomicity.md); they do not add a
new top-level Maker type. Human-readable and JSON plan output share the same
immutable plan. JSON schema version `1` reports `planned`, `collisions_found`,
or `incomplete`, the compatible exit code, ordered Module-relative targets,
generator IDs, create/overwrite operations, overwrite intent, and collision
state. JSON is available only with `--dry-run`, so normal Laravel delegate output
cannot corrupt the machine document. See
[ADR-0052](docs/adr/0052-generation-plan-output.md).

Third-party packages may add a template-backed Maker by implementing
`GeneratorDescriptor` and registering its class from a Laravel service
provider:

```php
use Cluion\Moduark\Generation\GeneratorRegistration;

public function register(): void
{
    GeneratorRegistration::register($this->app, ValueObjectGenerator::class);
}
```

The descriptor declares its canonical ID, target namespace, supported
`moduark:make` options, and complete immutable plan. All targets must use
`GenerationFileTemplate` and remain below the selected Module; the common
planner owns JSON/text output, collision preflight, `--force`, execution, and
rollback. Third-party Artisan delegation and direct filesystem writes are not
part of this extension contract. See
[ADR-0053](docs/adr/0053-third-party-generator-registration.md) and the
[permanent package fixture](tests/Fixtures/Generation/ExtensionPackage).

Inspect the discovered architecture:

```bash
php artisan moduark:list
php artisan moduark:check
php artisan moduark:check --format=json
php artisan moduark:check --format=github
php artisan moduark:graph
php artisan moduark:graph --format=mermaid
php artisan moduark:graph --view=capability
php artisan moduark:graph --view=capability --format=mermaid
php artisan moduark:graph --view=combined
php artisan moduark:inspect Order
php artisan moduark:cache
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
string references are not treated as observed dependencies in the current RC.

## Laravel Resource Conventions

Existing paths are loaded through Laravel's native mechanisms; absent paths are
ignored. Additive 1.2 resource behavior is opt-in through pure-data
`Module::resources()` metadata.

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

An opt-in Module may declare runtime resources like this:

```php
public function resources(): array
{
    return [
        'routes' => [
            ['path' => 'routes/admin.php', 'group' => ['prefix' => 'admin']],
        ],
        'config' => [
            ['path' => 'config/order.php', 'key' => 'order', 'publish' => true],
        ],
        'commands' => ['recursive' => true],
        'factories' => true,
        'seeders' => [OrderDatabaseSeeder::class],
        'policies' => [Order::class => OrderPolicy::class],
        'listeners' => [OrderPlaced::class => [SendReceipt::class]],
        'components' => true,
        'assets' => [
            'resources/js/order.js',
            ['path' => 'resources/public/icon.svg', 'type' => 'public', 'publish_to' => 'vendor/order/icon.svg'],
        ],
        'tests' => true,
        'extensions' => ['frontend' => ['driver' => 'vite']],
    ];
}
```

Metadata must contain only scalar, null, and nested array values. New resource
types are never activated merely because a matching directory exists. Events
are represented separately from listeners in the manifest; providers retain
the dependency-ordered `providers()` lifecycle. Generic Vite inputs are
available from `ModuleAssetManifest::inputs()`, and public assets can be
published with `php artisan vendor:publish --tag=moduark-assets`.

Third-party packages can add a discover/handle pair by registering a
`ResourcePlugin` through `ResourcePluginRegistration::register()` from their
service provider. See [ADR-0056](docs/adr/0056-resource-plugin-manifest-runtime.md).

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
php artisan moduark:check --level=0
php artisan moduark:check --level=1
```

See [Architecture Levels](docs/architecture-levels.md) for the complete preset
matrix and [Adopting Moduark](docs/adoption.md) for a staged migration workflow.

## Commands

| Command | Current contract |
|---|---|
| `moduark:make-module {name} [--preset=minimal\|web\|api\|domain\|full] [--dry-run] [--format=text\|json]` | Plan or create a deterministic, non-overwriting, rollback-safe Module scaffold; JSON is dry-run only and omitted preset remains minimal |
| `moduark:make {module} {type} {name} [--dry-run] [--format=text\|json]` | Plan or generate supported Module-owned artifacts and tests, with descriptor-specific options, atomic related targets, and dry-run JSON output |
| `moduark:baseline [--level=0..3] [--force] [--prune]` | Adopt current violations explicitly or safely remove stale baseline debt |
| `moduark:cache` | Cache deterministic Module discovery and typed metadata |
| `moduark:clear` | Remove cached Module metadata and incremental source analysis |
| `moduark:enable {module} [--dry-run] [--format=text\|json]` | Validate and enable a Module, or preview the exact plan with `--dry-run` |
| `moduark:disable {module} [--dry-run] [--format=text\|json]` | Validate and disable a Module, or preview the exact plan with `--dry-run` |
| `moduark:list` | List discovered Modules in deterministic order |
| `moduark:check [--level=0..3] [--format=text\|json\|github] [--show-suppressions]` | Run the effective architecture rules, audit suppressions, and optionally emit JSON or GitHub Actions annotations |
| `moduark:graph [module] [--view=module\|capability\|combined] [--format=text\|mermaid]` | Render direct, Capability, or combined relationships and optionally select one neighborhood |
| `moduark:inspect {module}` | Inspect one Module's identity, dependencies, providers, Capabilities, owned tables, and Public API convention |
| `moduark:resources [module] [--format=text\|json]` | Inspect the canonical enabled resource manifest and deterministic collisions |
| `moduark:doctor [module] [--format=text\|json]` | Diagnose framework support, Module state, dependencies, resources, sources, handlers, and collisions |
| `moduark:migrate {module} [--format=text\|json]` | Run only the selected active Module's forward migrations |
| `moduark:seed {module} [--format=text\|json]` | Run only seeders declared by the selected active Module |
| `moduark:test {module} [arguments...] [--runner=auto\|phpunit\|pest] [--list] [--format=text\|json]` | Run or list the selected active Module's declared test paths |

The activation commands are an unreleased `1.3` preview surface. `--dry-run`
reports the authoritative `standalone` or `nwidart` driver and complete proposed
plan without mutation. Without `--dry-run`, an executable non-no-op plan clears
Module metadata, source-analysis, route, and event caches before atomically
committing the authoritative file state. The running process is never
hot-switched; start a new application process to consume the committed set.

`moduark:check` exit codes are part of the Stable `1.x` contract:

| Exit | Meaning |
|---:|---|
| `0` | No blocking violation; warnings may exist |
| `1` | One or more blocking architecture violations |
| `2` | Command input, analyzer, or unavailable-rule tool error; result is incomplete |

Use JSON when another tool needs the complete result without parsing terminal
formatting. This option is included in `v0.3.0-beta.1`:

```bash
php artisan moduark:check --format=json
php artisan moduark:check --level=2 --format=json
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
  run: php artisan moduark:check --format=github
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
php artisan moduark:check --show-suppressions
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
php artisan moduark:check --level=1
php artisan moduark:baseline --level=1
git add moduark-baseline.json
```

Normal `moduark:check` runs automatically apply the configured baseline. The
identity excludes diagnostic wording and line number, but retains rule, code,
severity, file, Module endpoints, and symbol. If the number of matching current
violations grows beyond the recorded count, the whole group is reported so a
new occurrence cannot be guessed away.

Routine cleanup is one-way and cannot adopt new debt:

```bash
php artisan moduark:baseline --prune
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
php artisan moduark:graph --view=capability
php artisan moduark:graph Order --view=capability
php artisan moduark:graph --view=capability --format=mermaid
php artisan moduark:graph --view=combined
php artisan moduark:graph Order --view=combined --format=mermaid
```

Selecting a Module in the Capability view retains its connected Capabilities,
providers, and other consumers so the relationship remains complete. The
combined view overlays labeled `depends`, `requires`, and `provides` edges and
uses the union of direct and Capability neighborhoods. JSON graph output remains
later work. These views are included in `v0.2.0-beta.2`. `moduark:check` JSON and
GitHub Actions annotations are included in `v0.3.0-beta.1`; inline suppressions
are intentionally replaced by the reviewable external suppression manifest.
Per-Module check filtering remains later work.

Use `moduark:inspect Order` when one Module needs more detail than the graph. It
shows the effective architecture level, discovered or missing direct
dependencies, Module ServiceProviders, each required Capability's resolved
provider, consumer Port and Adapter, provided Capabilities, explicit owned
tables, explicit exports, and symbols exposed by the current `Contracts/`,
`Data/`, `Events/`, and Module-entry convention. The two Public API views remain
separate so Level 3 narrowing is directly reviewable.

Application bootstrap happens before Artisan invokes a command. A configuration,
discovery, metadata, or runtime Capability-resolution exception raised during
bootstrap may therefore be rendered by Laravel itself rather than by
`moduark:check`'s exit-code renderer.

## Module Cache

For deployment, cache Module discovery and typed metadata directly or through
Laravel's optimization command:

```bash
php artisan moduark:cache
# or
php artisan optimize
```

The versioned scalar PHP manifest is stored at
`bootstrap/cache/moduark.php`. It contains the configured Module root, sorted
discovery records, dependency-ordered descriptors, and schema-versioned runtime
resource manifest. Runtime lifecycle, Capability validation, graphs, inspection,
checks, operations, and resource handlers reuse the same enabled Module set.
Cached boot consumes the serialized descriptors without repeating filesystem
resource discovery.

Rebuild the cache after adding, removing, or moving a Module, or after changing
`dependencies()`, `providers()`, `requires()`, `provides()`, `tables()`,
`exports()`, or `resources()`.
Clear it to return to fresh discovery:

```bash
php artisan moduark:clear
# or
php artisan optimize:clear
```

An unknown cache schema, a manifest for another configured Module root, or a
manifest for another nwidart active Module set is ignored safely. A malformed
current-schema manifest fails with its exact cache path instead of silently
booting from ambiguous metadata. See
[ADR-0030](docs/adr/0030-module-metadata-cache.md). This integration is included
in `v0.3.0-beta.2`.

## Incremental Source Analysis

When an enabled rule needs the PHP source index, `moduark:check` stores an
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
tool error. `moduark:clear` and `optimize:clear` remove both the Module metadata
cache and this source-analysis cache. `moduark:cache` intentionally does not
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
composer benchmark:generation
composer test:performance
```

`composer verify` runs PHPUnit and PHPStan level max. The generated performance
baseline exercises cold and content-hash-cached checks over 50 Modules / 5,000
PHP files and 100 Modules / 10,000 PHP files without checking generated fixtures
into Git. See
[ADR-0012](docs/adr/0012-beta-performance-and-analysis-errors.md) for the method
and initial evidence, and
[ADR-0033](docs/adr/0033-incremental-source-analysis.md) for the incremental
comparison.

`composer benchmark:generation` builds 100 disposable `full` scaffold Modules
and measures 1,400 real production-template targets through planning, collision
preflight, and execution. It reports evidence without enforcing a portable SLA.
`composer test:performance` runs the same fixture with a 5,000 ms median-total
budget in the fixed PHP 8.5 Ubuntu CI job. The deliberately generous threshold
blocks major regressions while tolerating shared-runner and filesystem variance;
it is not a cross-machine performance promise. See
[ADR-0054](docs/adr/0054-generation-performance-regression-gate.md).

The Level 2 acceptance fixture models eight business Modules, five shared
Capabilities, and twelve consumer-owned Port/Adapter bindings. It proves all
eight Level 2 rules, runtime container composition, combined graph output, and
`moduark:inspect` against one connected architecture. See
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
composer test:installation -- --package=1.2.0
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
- [Release Process](docs/releases.md)
- [Architecture Levels](docs/architecture-levels.md)
- [Adopting Moduark](docs/adoption.md)
- [Migration Recipes](docs/recipes/README.md)
- [Interactive Graph Examples](docs/graph-examples.md)
- [PHPStan and Larastan Integration](docs/phpstan-integration.md)
- [Architecture Decision Records](docs/adr/0001-package-baseline.md)
- [Changelog](CHANGELOG.md)

## Current Scope

The `1.2.0` minor release completes Runtime Completeness with a serializable
Resource Plugin manifest shared by cold discovery, cached boot, diagnostics,
runtime registration, generic assets, and Module-scoped test/migrate/seed
operations. Laravel resources remain metadata opt-in, database operations are
forward-only, and nwidart's enabled Module set remains authoritative whenever
both packages share its Module root.

The `1.1.0` minor release retains the Stable command, configuration, diagnostic,
and architecture boundaries while completing the Generation Foundation.
Thirty-one Module-owned Maker types, additive scaffold presets, immutable
Generation Plans, shared collision preflight, atomic rollback, text/JSON dry
runs, Laravel 12 / 13 parity fixtures, and the Stable template-backed
third-party registration API now form one verified generation surface. Config
and provider Makers create Module-owned artifacts without silently activating
runtime resources or mutating application bootstrap state. Levels 0 through 2
remain Stable and the complete Level 3 preset remains Preview.
Level 2 includes typed Capability metadata, descriptor-only
provider resolution, lifecycle preflight, consumer-owned Port wiring,
Capability contract validation, source-enforced Adapter boundaries,
deterministic Capability and combined graphs, `moduark:inspect`, and the large
Level 2 acceptance fixture. Developer Experience output includes versioned JSON
reports, GitHub Actions annotations, and deterministic Module metadata caching
with Laravel optimize integration. Brownfield adoption includes a reviewable
architecture baseline with conservative count matching and safe pruning.
Reviewed architecture exceptions use an auditable external suppression manifest
with narrow selectors, mandatory reasons, and stale/inactive reporting.
Content-hash caching reuses unchanged per-file source analysis without
persisting cross-file ownership decisions.
All six Level 3 rules audit direct cross-Module Eloquent Model, table, migration,
foreign-key, inline transaction, and explicit export access. Explicit `tables()`
metadata feeds a deterministic single-owner index; Laravel-aware AST evidence
covers literal Facade queries, Schema mutations, Blueprint constraints, and
direct Query Builder writes inside transaction callbacks, while unresolved
expressions remain reviewable warnings. Explicit `exports()` metadata narrows the
convention-based Public API. The complete fourteen-rule Level 3 preset can now
produce a complete pass. The optional `cluion/moduark-phpstan` `v0.2.0` stable
companion integrates `internal_api_access` with PHPStan and Larastan across the
Moduark `1.x` line and nwidart-compatible source layouts;
suppression expiry and extension coverage for the remaining rules remain later
work. See
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
