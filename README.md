# Moduark

Moduark is a Laravel-native modular architecture toolkit. It keeps Modules in a
normal Laravel application while making their dependencies, lifecycle order,
resources, and architecture boundaries executable and inspectable.

> **Pre-release status:** `0.3.0-beta.1` guarantees Level 0, Level 1, and Level
> 2. It also adds versioned JSON check reports and native GitHub Actions
> annotations. Level 3 remains incomplete.

## Requirements

- PHP 8.2 or later
- Laravel 12 or 13
- Composer 2.1 or later

## Installation

Install the current beta from Packagist:

```bash
composer require cluion/moduark:^0.3@beta
```

The package is pre-release software. Pin an exact beta version when an
application requires fully repeatable pre-release upgrades.

Laravel package discovery registers `Cluion\Moduark\ModuarkServiceProvider`.
Configuration publishing is optional because package defaults are merged even
when `config/modules.php` does not exist in the application.

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
```

The default configuration uses Level 1, so a successful check evaluates six
rules: Module structure, identity, missing and undeclared dependencies, cycles,
and internal API access.

## Module Metadata

Dependencies and service providers are typed PHP metadata on the Module entry
class. Dependencies are registered before their consumers.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Order;

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
}
```

The metadata must contain concrete class strings. Duplicate references, missing
Modules, and circular dependencies fail before application Module providers are
registered.

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
| `module:list` | List discovered Modules in deterministic order |
| `module:check [--level=0..3] [--format=text\|json\|github]` | Run the effective architecture rules and optionally emit JSON or GitHub Actions annotations |
| `module:graph [module] [--view=module\|capability\|combined] [--format=text\|mermaid]` | Render direct, Capability, or combined relationships and optionally select one neighborhood |
| `module:inspect {module}` | Inspect one Module's identity, dependencies, providers, Capabilities, and Public API convention |

`module:check` exit codes are stable within the beta contract:

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
per-rule violations, and an `error` object for failures that occur before a
report is produced. Status is `passed`, `violations_found`, or `incomplete`;
the exit codes remain exactly the same as text output. See
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
GitHub Actions annotations are included in `v0.3.0-beta.1`; suppressions and
per-Module filtering remain later work.

Use `module:inspect Order` when one Module needs more detail than the graph. It
shows the effective architecture level, discovered or missing direct
dependencies, Module ServiceProviders, each required Capability's resolved
provider, consumer Port and Adapter, provided Capabilities, and symbols exposed
by the current `Contracts/`, `Data/`, `Events/`, and Module-entry convention.
This is an inspection of today's Public API convention, not the future Level 3
explicit `exports()` contract. The command is included in `v0.2.0-beta.2`.

Application bootstrap happens before Artisan invokes a command. A configuration,
discovery, metadata, or runtime Capability-resolution exception raised during
bootstrap may therefore be rendered by Laravel itself rather than by
`module:check`'s exit-code renderer.

## Development

```bash
composer verify
composer test:dependencies
composer test:distribution
composer test:installation
composer benchmark
```

`composer verify` runs PHPUnit and PHPStan level max. The generated performance
baseline exercises 50 Modules / 5,000 PHP files and 100 Modules / 10,000 PHP
files without checking generated fixtures into Git. See
[ADR-0012](docs/adr/0012-beta-performance-and-analysis-errors.md) for the method
and initial evidence.

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

After a version is published, pass an exact version to repeat the same Laravel
acceptance against the Packagist dist instead of the local path repository. This
mode also verifies the installed archive layout:

```bash
composer test:installation -- --package=0.3.0-beta.1
```

The GitHub Actions compatibility workflow runs PHPUnit on all four
Laravel/PHP/dependency combinations and runs the matching clean installation on
both highest-dependency jobs. A separate PHP 8.2 job runs PHPStan against the
highest resolvable tooling dependencies. See
[ADR-0014](docs/adr/0014-ci-compatibility-matrix.md) for the release-gate
contract.

## Documentation

- [Architecture Levels](docs/architecture-levels.md)
- [Adopting Moduark](docs/adoption.md)
- [Architecture Decision Records](docs/adr/0001-package-baseline.md)
- [Changelog](CHANGELOG.md)

## Current Scope

The `v0.3.0-beta.1` release guarantees foundation plus complete Level 1 and
Level 2 presets. Level 2 includes typed Capability metadata, descriptor-only
provider resolution, lifecycle preflight, consumer-owned Port wiring,
Capability contract validation, source-enforced Adapter boundaries,
deterministic Capability and combined graphs, `module:inspect`, and the large
Level 2 acceptance fixture. Developer Experience output includes versioned JSON
reports and GitHub Actions annotations. Database or migration ownership, raw
SQL analysis, explicit exports, baseline files, suppressions, incremental
analysis, and IDE integration remain later work. Level 3 rule names in
configuration are not claims of enforcement.

Moduark is open-source software licensed under the [MIT License](LICENSE).
