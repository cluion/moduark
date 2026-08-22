# Adopting Moduark

Adopt Moduark by making one boundary observable at a time. The safe sequence is
Level 0 discovery first, then an advisory Level 1 run, then repairs, and only
then changing the configured default.

For a concrete `User` / `Order` migration with review checkpoints, container
bindings, debt decisions, and a CI gate, follow the
[Brownfield Level 0 to Level 1 recipe](recipes/level-0-to-level-1.md).

## 1. Inventory the Existing Application

Before moving source, record:

- current Composer PSR-4 roots;
- candidate business areas and their entry namespaces;
- service providers and their registration dependencies;
- route, view, translation, migration, and console command locations;
- known cross-area class references and dependency cycles;
- code shared intentionally versus implementation details exposed accidentally.

Moduark's default path is `app/Modules`. A custom path must remain inside a
registered Composer PSR-4 mapping so generated and discovered entry classes are
autoloadable. When `nwidart/laravel-modules` is installed and `moduark.path`
is `null` or absent, Moduark follows nwidart's `modules.paths.modules` root.

For a nwidart layout, place the Moduark entry below the generated `app` source
root and keep its namespace in the application's Composer mapping:

```text
Modules/User/app/UserModule.php
Modules/User/app/Contracts/UserDirectory.php
Modules/User/app/Events/UserCreated.php
Modules/User/app/Services/InternalUserService.php
```

nwidart generates a `composer.json` inside each Module, but those mappings are
not loaded automatically. Configure nwidart's Composer merge plugin in the
application root `composer.json`:

```json
{
    "extra": {
        "merge-plugin": {
            "include": [
                "Modules/*/composer.json"
            ]
        }
    },
    "config": {
        "allow-plugins": {
            "wikimedia/composer-merge-plugin": true
        }
    }
}
```

Preserve any existing entries in `extra` and `config`; the fragment shows only
the keys relevant to Module autoloading. An equivalent explicit root mapping,
such as `"Modules\\User\\": "Modules/User/app/"`, is also valid. After
creating a Module or changing either form of mapping, rebuild Composer's loader:

```bash
composer dump-autoload
```

Create the nwidart Module and its Laravel classes with nwidart-owned commands:

```bash
php artisan module:make User
php artisan module:make-model Profile User
php artisan module:make-controller ProfileController User
```

Then add the minimal Moduark entry class at
`Modules/User/app/UserModule.php`. The `moduark:make-module` and
`moduark:make` workflow documented below targets Modules inside Laravel's
application source root. `moduark:make` deliberately rejects nwidart's default
external `Modules/` root rather than delegating a Laravel Maker across an
unowned path; continue using nwidart's `module:make-*` family there.

`Contracts`, `Data`, and `Events` are Public API conventions relative to the
entry class. `Services` and other sibling implementation folders remain
internal. Moduark does not replace nwidart's Module provider or `module.json`.

## 2. Start at Level 0

Publish configuration only if the defaults need changing:

```bash
php artisan vendor:publish --tag=moduark-config
```

Set Level 0 while establishing structure:

```php
'architecture' => [
    'level' => 0,
    'rules' => [],
],
```

Create or hand-write one minimal entry class per Module:

```bash
php artisan moduark:make-module User
php artisan moduark:make-module Order
```

The entry class and directory must match exactly:

```text
app/Modules/User/UserModule.php
app/Modules/Order/OrderModule.php
```

Run the discovery tools before moving more code:

```bash
php artisan moduark:list
php artisan moduark:check
php artisan moduark:graph
```

At Level 0, a passing check proves structure and identity only. It does not mean
cross-Module dependencies are declared or safe.

## 3. Move Laravel Resources Without Rewriting Them

Use the supported Module-relative paths:

```text
Order/
├── OrderModule.php
├── Providers/OrderServiceProvider.php
├── Console/Commands/
├── Database/Migrations/
├── resources/lang/
├── resources/views/
└── routes/
    ├── api.php
    └── web.php
```

List Module service providers in `providers()` metadata. Routes, views,
translations, migrations, and concrete console commands are loaded only when
their conventional paths exist. Keep the application behavior covered while
moving each resource group; route and configuration caches should be verified
after relocation.

## 4. Describe Direct Dependencies

Add typed Module entry classes to consumer metadata:

```php
use App\Modules\User\UserModule;

public function dependencies(): array
{
    return [UserModule::class];
}
```

The graph must remain acyclic. If `Order` depends on `User`, adding the reverse
dependency is not a temporary workaround: startup ordering rejects the cycle.
Move the shared contract, invert the dependency, or postpone that boundary until
the ownership decision is clear.

Inspect the effective graph after each change:

```bash
php artisan moduark:graph
php artisan moduark:graph User
php artisan moduark:graph --format=mermaid
```

## 5. Probe Level 1 Before Enabling It

Keep the configured Level at 0 and run:

```bash
php artisan moduark:check --level=1
```

Repair diagnostics in this order:

1. `MOD-DEPENDENCY-001`: discover the missing Module or remove the metadata.
2. `MOD-DEPENDENCY-002`: add the direct Module dependency or remove the observed
   cross-Module reference.
3. `MOD-CYCLE-001`: remove or invert at least one dependency in the cycle.
4. `MOD-BOUNDARY-001`: replace the internal symbol with a provider-owned public
   contract, data object, or event.

Declaring a dependency does not fix an internal API violation. For example,
change a consumer from:

```php
use App\Modules\User\Services\UserService;
```

to a provider-owned contract:

```php
use App\Modules\User\Contracts\UserFinder;
```

The provider implementation can remain under `Services/` and bind the public
contract in its service provider. Public folders are exact and case-sensitive:
`Contracts/`, `Data/`, and `Events/`.

## 6. Make Level 1 the Shared Default

After the temporary Level 1 check passes, update configuration:

```php
'architecture' => [
    'level' => 1,
    'rules' => [],
],
```

Run the same command in CI:

```bash
php artisan moduark:check
```

Treat exit codes separately:

- `0`: complete check with no blocking violations;
- `1`: architecture violations found;
- `2`: command input, analyzer, or unavailable-rule failure handled by the
  command; the result is incomplete and must not be recorded as a pass.

Configuration and discovery occur during Laravel bootstrap. If they fail before
Artisan invokes `moduark:check`, use Laravel's rendered exception and process exit
status rather than expecting the command's exit-code summary.

## Temporary Rule Overrides

If one global rule must be deferred, use an explicit boolean override and record
its migration reason beside the configuration:

```php
'rules' => [
    // Remove after legacy service consumers use Contracts/UserFinder.
    'internal_api_access' => false,
],
```

This weakens the Level 1 guarantee for the entire application. The beta has no
global suppression escape hatch. When the rule itself should remain enabled,
prefer the narrow reviewed suppression workflow below; a broad override should
not be presented as a fully passing Level 1 architecture.

## Suppress One Reviewed Exception

For an individual exception with a clear owner and migration reason, create the
configured `moduark-suppressions.json` at the application root:

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

Every entry needs a stable rule and diagnostic code, a non-empty reason, and a
narrow selector: repository-relative file with optional line, symbol, or both
consumer and target Modules. Selectors may be combined. Moduark rejects global
ignores, unknown fields, non-portable paths, duplicate selectors, and any
violation that matches overlapping entries.

Audit the debt in text output:

```bash
php artisan moduark:check --show-suppressions
```

`matched` entries suppress current violations. `stale` entries belong to rules
that ran but no longer match and should be deleted after review. `inactive`
entries could not be audited at the selected Level because their rules did not
run. These states are also present in JSON and GitHub output. Expiry metadata is
not implemented; removal remains an explicit code-review change.

## Adopt Existing Debt with a Baseline

When the unsuppressed Level 1 result is too large to fix in one change, keep the
rules enabled and capture the reviewed starting point:

```bash
php artisan moduark:check --level=1
php artisan moduark:baseline --level=1
git add moduark-baseline.json
```

The default file is `moduark-baseline.json` at the application root. Set
`moduark.architecture.baseline` to a different non-empty path when necessary.
The JSON is deterministic and should be reviewed like source code.

After creation, normal checks ignore matching existing counts but continue to
report new identities. If an existing identity grows from one occurrence to
two, Moduark reports both current occurrences rather than guessing which one is
new. Line-number and message changes do not invalidate an otherwise stable
identity; file, rule, diagnostic code, severity, Module endpoints, and symbol
remain part of the match.

As debt is repaired, safely remove stale counts:

```bash
php artisan moduark:baseline --prune
```

Prune never adds an identity or raises an allowance. A normal creation refuses
to overwrite an existing file; `moduark:baseline --force` is deliberately
separate because it replaces the file with all current unsuppressed violations
and can adopt regressions.

Suppressions run before the baseline. Consequently `moduark:baseline` never
captures a violation already covered by an explicit suppression, and prune sees
the same suppression-aware, unbaselined report as normal baseline creation.

The `0.5.x` hardening reports `MOD-DEPENDENCY-002` once per ordered consumer /
provider Module pair, using the first deterministic source reference as
evidence. After upgrading a baseline created by an earlier beta, review stale
per-symbol entries and regenerate or prune them; new entries use stable Module
pair identity. Migrate file- or symbol-only suppressions to explicit `consumer`
and `target` selectors. Do not preserve inflated counts only to keep the old
files unchanged. See
[ADR-0043](adr/0043-real-project-beta-adoption.md).

## Adopt Level 2

Level 2 became complete in `v0.2.0-beta.2`; `1.0.0` makes its eight-rule preset
Stable: typed Capability metadata, provider
preflight, runtime Port-to-Adapter wiring, Capability contracts, source-backed
Adapter boundaries, Capability and combined graphs, and focused Module
inspection. Install the stable line from Packagist before adopting this
contract:

```bash
composer require cluion/moduark:^1.0
```

For a concrete continuation of the `User` / `Order` example, including runtime
Port wiring, graph inspection, rollback boundaries, and acceptance checkpoints,
follow the [Brownfield Level 1 to Level 2 recipe](recipes/level-1-to-level-2.md).

Before selecting Level 2, give every consumer its own interface below `Ports/`,
place each declared Adapter below `Adapters/{Provider}/`, and keep provider API
references out of consumer core code. Run `moduark:check --level=2`; only change
the shared default after the complete eight-rule check exits 0.

Inspect both the direct and inverted relationships before enabling Level 2:

```bash
php artisan moduark:graph
php artisan moduark:graph --view=capability
php artisan moduark:graph Order --view=capability
php artisan moduark:graph --view=capability --format=mermaid
php artisan moduark:graph --view=combined
php artisan moduark:graph Order --view=combined --format=mermaid
php artisan moduark:inspect Order
```

The Capability neighborhood keeps the selected Module's providers and sibling
consumers visible. It does not flatten these edges into direct Module
dependencies. The combined view retains all three labeled edge kinds and uses
the union of direct and Capability neighborhoods. `moduark:inspect` adds the
selected Module's resolved Capability provider, Port, Adapter, ServiceProviders,
dependency status, explicit owned tables, current convention-based Public API,
and explicit exports as separate reviewable rows.

## Adopt Level 3 with `1.0`

`v1.0.0` retains the complete fourteen-rule Level 3 preset while its stability
classification remains Preview. Install the stable line from Packagist before
adopting this contract:

```bash
composer require cluion/moduark:^1.0
```

For a staged `User` / `Order` migration with explicit rollback boundaries and
separate decisions for Models, tables, migrations, foreign keys, transactions,
and exports, follow the
[Brownfield Level 2 to Level 3 recipe](recipes/level-2-to-level-3.md).

Its six isolation rules
audit direct cross-Module Eloquent Model references, literal Laravel table
access, Laravel Schema mutations, Blueprint foreign keys, direct Query Builder
writes inside inline Laravel transactions, and explicit Public API exports.
Declare every queried, migrated, referenced, or directly mutated table in one
authoritative `tables()` owner. Keep historical renamed
or dropped names while shipped migrations reference them. Move schema mutations
into the owning Module's `Database/Migrations/`; cross-Module orchestration
requires a narrow reviewed suppression. Foreign-key diagnostics default to
warnings because retaining database integrity can be an intentional modular
monolith trade-off. Disable that rule only for a project-wide FK policy; use a
narrow suppression for individual reviewed constraints. Review each transaction
that directly writes multiple owners; retain deliberate atomic orchestration with
a narrow suppression or move it behind Module Ports. Unresolved expressions and
raw DB writes remain explicit warnings rather than guessed owners. Add every
cross-Module class-like boundary to the provider's `exports()` list; Module entry
classes remain implicit identities. Explicit exports narrow the convention, so a
symbol must also remain below `Contracts/`, `Data/`, or `Events/`. Enable Level 3
only after `php artisan moduark:check --level=3` completes with exit 0.

## Adoption Checklist

- [ ] Every Module entry class is autoloadable and discovered once.
- [ ] Module service providers are listed in typed metadata.
- [ ] Resource conventions preserve application behavior and cache compatibility.
- [ ] Every direct cross-Module class reference has metadata.
- [ ] The dependency graph is acyclic.
- [ ] Cross-Module references target only `Contracts/`, `Data/`, `Events/`, or
      the Module entry class.
- [ ] `moduark:check --level=1` completes with exit 0.
- [ ] Before Level 2 adoption, consumer Ports and provider-scoped Adapters pass
      `moduark:check --level=2` with all eight rules enabled.
- [ ] Configuration and CI both run the same default Level.
- [ ] Any architecture baseline is committed, reviewed, and routinely pruned.
- [ ] Every suppression has a narrow selector and current reason; stale entries
      are removed and inactive entries are reviewed at their applicable Level.
- [ ] Every literal Laravel query table has one authoritative `tables()` owner;
      unresolved table warnings are reviewed rather than assumed safe.
- [ ] Every recognized Blueprint foreign key has two declared owners, and each
      cross-owner warning is removed, narrowly suppressed, or explicitly kept.
- [ ] Every inline Laravel transaction with direct Query Builder writes has
      resolved ownership, and each cross-owner warning is removed, narrowly
      suppressed, or explicitly kept.
- [ ] Every cross-Module class-like reference targets both the convention Public
      API and the provider's explicit `exports()` metadata.
- [ ] `moduark:check --level=3` evaluates all fourteen rules and exits 0 before
      Level 3 becomes the configured default.
- [ ] Any rule override has an owner, reason, and removal condition.
