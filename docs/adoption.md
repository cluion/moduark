# Adopting Moduark

Adopt Moduark by making one boundary observable at a time. The safe sequence is
Level 0 discovery first, then an advisory Level 1 run, then repairs, and only
then changing the configured default.

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
autoloadable.

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
php artisan make:module User
php artisan make:module Order
```

The entry class and directory must match exactly:

```text
app/Modules/User/UserModule.php
app/Modules/Order/OrderModule.php
```

Run the discovery tools before moving more code:

```bash
php artisan module:list
php artisan module:check
php artisan module:graph
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
php artisan module:graph
php artisan module:graph User
php artisan module:graph --format=mermaid
```

## 5. Probe Level 1 Before Enabling It

Keep the configured Level at 0 and run:

```bash
php artisan module:check --level=1
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
php artisan module:check
```

Treat exit codes separately:

- `0`: complete check with no blocking violations;
- `1`: architecture violations found;
- `2`: command input, analyzer, or unavailable-rule failure handled by the
  command; the result is incomplete and must not be recorded as a pass.

Configuration and discovery occur during Laravel bootstrap. If they fail before
Artisan invokes `module:check`, use Laravel's rendered exception and process exit
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
per-file suppression, expiry, or baseline mechanism, so a broad override should
not be presented as a fully passing Level 1 architecture.

## Do Not Adopt Level 2 or Level 3 Yet

Level 2 now has typed Capability metadata, runtime composition, and the
`capability_contracts` rule, but its `adapter_boundaries` analyzer remains
unavailable. Selecting the normal Level 2 preset therefore returns exit code 2.
Disabling the remaining rule does not provide the intended Level 2 guarantee.

Remain on Level 1 until the complete Level 2 preset is implemented and
documented. Level 3 additionally requires database ownership, persistence
isolation, and explicit exports.

## Adoption Checklist

- [ ] Every Module entry class is autoloadable and discovered once.
- [ ] Module service providers are listed in typed metadata.
- [ ] Resource conventions preserve application behavior and cache compatibility.
- [ ] Every direct cross-Module class reference has metadata.
- [ ] The dependency graph is acyclic.
- [ ] Cross-Module references target only `Contracts/`, `Data/`, `Events/`, or
      the Module entry class.
- [ ] `module:check --level=1` completes with exit 0.
- [ ] Configuration and CI both run the same default Level.
- [ ] Any rule override has an owner, reason, and removal condition.
