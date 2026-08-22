# Brownfield Level 2 to Level 3

This recipe upgrades a Level 2 application to Moduark's strictest preset. It
continues the `User` / `Order` example from the
[Level 1 to Level 2 recipe](level-1-to-level-2.md), adding explicit persistence
ownership and a reviewable Public API allowlist without removing the existing
Capability, Port, Adapter, or provider contract.

## Outcome

At the end of the recipe:

- `User` owns `users`, and `Order` owns `orders` and `order_items`;
- recognized schema mutations live below each owner's `Database/Migrations/`;
- `Order` neither references the `User` Eloquent Model nor queries `users`
  directly;
- cross-owner foreign keys and transactions have explicit project decisions;
- every non-Module-entry cross-Module class-like reference targets an explicit
  provider export;
- `module:check --level=3` evaluates all fourteen rules and exits `0`;
- any remaining warnings are understood rather than mistaken for proof;
- the shared configuration and CI gate both use Level 3.

Level 3 is an isolation policy, not a universal quality upgrade. A modular
monolith that deliberately shares relational integrity or atomic transactions
may remain at Level 2, or adopt Level 3 while retaining reviewed warnings. Make
that trade-off before changing the shared default.

## Starting Point

Assume the Level 2 boundary already passes:

```text
Order\Actions\CreateOrder
  -> Order\Ports\UserLookup
  -> Order\Adapters\User\UserLookupAdapter
  -> User\Contracts\UserFinder
  -> User\Services\UserService
```

`OrderModule` requires the provider-neutral `UserLookup` Capability and retains
its direct dependency on `UserModule`. `UserModule` provides that Capability.
The application still needs explicit answers for persistence ownership and the
effective cross-Module export surface.

Before starting, preserve behavior with tests for:

- a fresh test-database migration;
- migration status on an already-migrated test fixture;
- order creation and user lookup through Laravel's container;
- any cross-Module foreign-key behavior;
- rollback or retry behavior for workflows that write multiple Modules.

## Checkpoint 1: Keep Level 2 and Inventory Level 3 Evidence

Use the current beta and retain Level 2 as the shared default:

```bash
composer require cluion/moduark:^0.5@beta
```

```php
'architecture' => [
    'level' => 2,
    'baseline' => base_path('moduark-baseline.json'),
    'suppressions' => base_path('moduark-suppressions.json'),
    'rules' => [],
],
```

Confirm Level 2, then run a temporary Level 3 probe:

```bash
php artisan module:check
php artisan module:check --level=3
php artisan module:inspect User
php artisan module:inspect Order
```

The configured Level 2 command must exit `0`. Record the Level 3 diagnostics by
rule and owner before editing. Level 3 adds six rules:

| Rule | Default | Question |
|---|:---:|---|
| `cross_module_model_access` | Error | Does one Module reference another Module's Eloquent Model? |
| `database_ownership` | Error | Does every recognized literal query target belong to its source Module? |
| `migration_ownership` | Error | Is each recognized schema mutation inside its owner's migration directory? |
| `cross_module_foreign_keys` | Warning | Does a recognized FK couple tables owned by different Modules? |
| `cross_module_transactions` | Warning | Does one recognized inline transaction directly write multiple owners? |
| `explicit_public_exports` | Error | Did the provider export every referenced non-entry public symbol? |

Exit `2` means configuration, metadata, discovery, or analysis was incomplete.
Fix it before migration; it cannot be adopted as architecture debt.

Also inventory evidence outside the current analyzer contract. Moduark does not
infer every raw SQL string, runtime Model table, custom schema macro, Repository
write, builder variable, manual transaction scope, connection mapping, or
dynamic class string. These require application tests and review even when the
Level 3 command exits `0`.

## Checkpoint 2: Declare One Authoritative Table Owner

Add explicit table metadata to the provider:

```php
<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Capabilities\UserLookup;
use App\Modules\User\Providers\UserServiceProvider;
use Cluion\Moduark\Module;

final class UserModule extends Module
{
    public function providers(): array
    {
        return [UserServiceProvider::class];
    }

    public function provides(): array
    {
        return [UserLookup::class];
    }

    public function tables(): array
    {
        return ['users'];
    }
}
```

Add the consumer's tables without changing its existing `dependencies()` or
`requires()` metadata:

```php
public function tables(): array
{
    return ['orders', 'order_items'];
}
```

Table names are unquoted, dot-separated ownership identities, not SQL snippets
or aliases. One canonical name has exactly one owner, compared
case-insensitively. Deliberately shared tables still need one authoritative
owner in the current contract.

Keep historical names in `tables()` while shipped migrations still reference
them. For example, if a migration renames `purchases` to `orders`, `Order` owns
both names until that historical migration is no longer part of supported fresh
installation:

```php
public function tables(): array
{
    return ['purchases', 'orders', 'order_items'];
}
```

Acceptance:

```bash
php artisan module:inspect User
php artisan module:inspect Order
php artisan module:cache
```

Inspection must show each table once. Conflicting ownership or malformed names
are invalid metadata and must be fixed, not suppressed.

## Checkpoint 3: Move Schema Mutations to Their Owners

Move recognized schema migrations below the exact, case-sensitive Module
directory without changing an applied migration's filename:

```text
app/Modules/
├── Order/
│   └── Database/Migrations/
│       ├── 2025_01_02_000000_create_orders_table.php
│       └── 2025_01_02_000100_create_order_items_table.php
└── User/
    └── Database/Migrations/
        └── 2025_01_01_000000_create_users_table.php
```

Moduark registers these directories directly with Laravel's migrator. Keeping
the migration filename preserves Laravel's existing migration identity; do not
rewrite already-applied migration history merely to adopt a folder convention.

An `Order` migration may create only a table owned by `Order`:

```php
Schema::create('orders', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained('users');
    $table->timestamps();
});
```

The schema mutation belongs to `Order`, so `migration_ownership` accepts the
`orders` create operation. The `users` reference is evaluated separately by the
foreign-key rule in the next checkpoint.

Validate both a fresh isolated test database and the already-applied migration
identity:

```bash
php artisan migrate:status --env=testing
php artisan migrate --env=testing
php artisan migrate:fresh --env=testing
php artisan test
```

Run `migrate:fresh` only against a disposable testing database. A global
application migration outside discovered Module roots is not indexed by the
current rule, so leaving it global does not prove ownership.

Migration diagnostics are:

| Code | Meaning |
|---|---|
| `MOD-MIGRATION-001` | A Module mutates another Module's table |
| `MOD-MIGRATION-002` | A literal mutation target has no owner |
| `MOD-MIGRATION-003` | A Module schema mutation is outside `Database/Migrations/` |
| `MOD-MIGRATION-004` | The mutation target cannot be resolved safely; warning |

Both names in `Schema::rename()` need ownership. Raw SQL and custom schema
wrappers are not inferred and require manual review.

## Checkpoint 4: Remove Cross-Module Models and Queries

Do not use the `User` Eloquent Model as `Order`'s integration contract. These
examples violate the Level 3 boundary:

```php
use App\Modules\User\Models\User;

$user = User::query()->findOrFail($userId);
$order->user()->associate($user);
```

```php
$name = DB::table('users')->where('id', $userId)->value('name');
```

Keep the scalar `user_id` in `Order` persistence and use the existing
consumer-owned Port when `Order` needs provider behavior:

```php
use App\Modules\Order\Ports\UserLookup;

final readonly class CreateOrder
{
    public function __construct(private UserLookup $users)
    {
    }

    public function handle(int $userId): void
    {
        $label = $this->users->labelForOrder($userId);

        // Persist only Order-owned state, including the scalar user identifier.
    }
}
```

The Adapter still calls `User\Contracts\UserFinder`; the provider implementation
owns any `users` query. Do not export the Eloquent Model merely to silence a
diagnostic.

Relevant diagnostics are:

- `MOD-MODEL-001`: a direct reference to another Module's indexed Eloquent
  Model;
- `MOD-TABLE-001`: a recognized query targets another Module's table;
- `MOD-TABLE-002`: a recognized literal query target has no owner;
- `MOD-TABLE-003`: a query target cannot be resolved safely; warning.

One direct Model reference can correctly violate both Level 1 visibility and
Level 3 Model isolation. Repair the dependency; do not suppress only one half
and call it resolved.

## Checkpoint 5: Decide Cross-Owner Foreign-Key Policy

The `orders.user_id -> users.id` constraint has known owners and produces
`MOD-FK-001`. At Level 3 this is a warning, because database integrity and future
Module extraction are competing goals.

Choose one project decision:

| Decision | Consequence |
|---|---|
| Keep the FK visible | Strong database integrity; accept extraction coupling and review the warning |
| Keep it with a narrow suppression | Same coupling, but the exception has an owner and reason |
| Remove the FK and keep an index | Weaker database enforcement; validate identity through the provider boundary |
| Replace synchronous coupling with local state or events | More isolation; introduces consistency and repair workflows |

Do not disable `cross_module_foreign_keys` for one relationship. A boolean rule
override is project-wide; use a narrow suppression for an individual reviewed
exception. `MOD-FK-002` means the target could not be resolved, and
`MOD-FK-003` means a resolved table has no declared owner. Neither warning should
be interpreted as safe.

Model-based helpers whose runtime table is not explicit, Blueprint macros,
custom wrappers, raw SQL, and global migrations remain outside complete static
proof. Prefer an explicit table argument when the Laravel API supports it.

## Checkpoint 6: Put Writes Behind Their Owners

An inline `Order` transaction that writes both `orders` and `users` exposes two
issues:

```php
DB::transaction(function () use ($userId): void {
    DB::table('orders')->insert(['user_id' => $userId]);
    DB::table('users')->where('id', $userId)->increment('order_count');
});
```

- `database_ownership` blocks the direct `users` write by `Order`;
- `cross_module_transactions` reports `MOD-TRANSACTION-001` because the inline
  transaction directly mutates multiple owners.

Move the provider mutation behind a consumer Port. Then choose the consistency
contract deliberately:

- commit the `Order` transaction and notify `User` through an outbox or event;
- let an application orchestrator call both owner APIs and document partial
  failure or compensation;
- retain an intentional atomic orchestration boundary with tests and a narrow
  reviewed exception.

Moving a Repository or Port call behind an interface removes the direct table
violation, but it does not prove transaction isolation. The current analyzer
does not infer Repository writes, Eloquent writes, nested arbitrary callbacks,
or manual `beginTransaction()` scopes. Review and test those semantics
separately.

Transaction diagnostics are warnings:

| Code | Meaning |
|---|---|
| `MOD-TRANSACTION-001` | One recognized inline transaction directly writes multiple owners |
| `MOD-TRANSACTION-002` | A direct write target cannot be resolved |
| `MOD-TRANSACTION-003` | A resolved write table has no owner |

Warnings do not block exit `0`; each one still needs a recorded decision.

## Checkpoint 7: Narrow the Public API with Explicit Exports

The `Order` Adapter directly references `User\Contracts\UserFinder`, so `User`
must explicitly export that provider-owned contract:

```php
<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Capabilities\UserLookup;
use App\Modules\User\Contracts\UserFinder;
use App\Modules\User\Providers\UserServiceProvider;
use Cluion\Moduark\Module;

final class UserModule extends Module
{
    public function providers(): array
    {
        return [UserServiceProvider::class];
    }

    public function provides(): array
    {
        return [UserLookup::class];
    }

    public function tables(): array
    {
        return ['users'];
    }

    public function exports(): array
    {
        return [UserFinder::class];
    }
}
```

Add every other actually referenced `Contracts/`, `Data/`, or `Events/` symbol
to its owner's allowlist. Do not add `UserModule::class`; the Module entry is an
implicit public identity.

Explicit exports narrow the Level 1 convention rather than replacing it. A
symbol must satisfy both contracts:

```text
effective Level 3 non-entry public symbol
  = convention Public API
  ∩ owning Module's exports()
```

Listing `User\Services\UserService` or `User\Models\User` does not make either
class public. `MOD-EXPORT-001` reports an unexported reference,
`MOD-EXPORT-002` a symbol absent from indexed Module source, and
`MOD-EXPORT-003` a Module claiming another owner's symbol.

Rebuild deployable metadata and inspect both surfaces:

```bash
php artisan module:cache
php artisan module:inspect User
```

`Public API (convention)` and `Explicit exports` must remain separate reviewable
rows.

## Checkpoint 8: Resolve Debt and Run the Complete Probe

Run the complete Level 3 contract after every blocking issue is repaired:

```bash
php artisan module:check --level=3
php artisan module:graph --view=combined
php artisan module:inspect User
php artisan module:inspect Order
```

The command must evaluate fourteen rules and exit `0`. Exit `0` may include
foreign-key, transaction, or unresolved-evidence warnings; list their decisions
in the change review instead of claiming a warning-free architecture.

Prefer repairs. If unrelated existing blocking violations cannot move in the
same change, create a reviewed Level 3 baseline only after ownership is
understood:

```bash
php artisan module:baseline --level=3
git add moduark-baseline.json
php artisan module:check --level=3
```

Use a narrow suppression for one intentional exception, and prune debt as it is
repaired:

```bash
php artisan module:baseline --prune
```

Do not baseline invalid metadata, exit `2`, unresolved ownership decisions, or
new violations introduced by this migration. See
[Adopting Moduark](../adoption.md) for suppression selectors and baseline audit
states.

## Checkpoint 9: Enable Level 3 and Preserve CI Semantics

Change the shared default only after the temporary Level 3 probe is complete and
exits `0`:

```php
'architecture' => [
    'level' => 3,
    'baseline' => base_path('moduark-baseline.json'),
    'suppressions' => base_path('moduark-suppressions.json'),
    'rules' => [],
],
```

Run application and deployment gates:

```bash
php artisan module:check
php artisan module:cache
php artisan config:cache
php artisan route:cache
php artisan migrate:status --env=testing
php artisan test
```

Keep CI on the shared default:

```yaml
- name: Check Module architecture
  run: php artisan module:check --format=github
```

CI must preserve exit `1` and exit `2` as failures while allowing reviewed
warnings to remain non-blocking. Do not pin CI to Level 2 or disable a Level 3
rule merely to make the migration appear complete.

## Reviewable Commit Sequence

Keep each persistence decision independently reversible:

1. Declare authoritative table owners and rebuild Module metadata.
2. Move unchanged migration files to their owners and prove fresh plus existing
   migration behavior.
3. Remove direct cross-Module Model and table access through existing Ports.
4. Record foreign-key and transaction policy decisions; add only narrow reviewed
   exceptions.
5. Add explicit exports and inspect the effective Public API intersection.
6. Repair or explicitly adopt unrelated Level 3 debt.
7. Enable Level 3 only after the temporary probe exits `0`.

If a checkpoint fails, keep Level 2 configured while repairing that slice. Do
not combine migration relocation, database relationship changes, and Level
activation into one irreversible deployment.

## Acceptance Checklist

- [ ] The configured Level 2 check passes before migration begins.
- [ ] Fresh and already-applied migration behavior is protected by tests.
- [ ] Every recognized literal query and schema table has one owner.
- [ ] Historical migration names remain declared while shipped code uses them.
- [ ] Module schema mutations live below `Database/Migrations/`.
- [ ] No Module directly references another Module's Eloquent Model.
- [ ] No Module directly queries or mutates another Module's table.
- [ ] Cross-owner FK warnings have explicit integrity/extraction decisions.
- [ ] Cross-owner transaction warnings have explicit atomicity decisions.
- [ ] Analyzer limits such as raw SQL and Repository writes were reviewed.
- [ ] Every non-Module-entry cross-Module class-like reference targets an
      explicit owner export.
- [ ] Every export also satisfies the Level 1 Public API convention.
- [ ] Module entry classes are not redundantly listed in `exports()`.
- [ ] Metadata and configuration caches rebuild successfully.
- [ ] Any baseline or suppression is narrow, reviewed, and committed.
- [ ] `module:check --level=3` evaluates fourteen rules and exits `0`.
- [ ] Remaining warnings are documented rather than described as safe.
- [ ] Shared configuration uses Level 3 only after the probe passes.
- [ ] CI preserves exit `1` and exit `2` as failures.

The complete isolation and analyzer contract is documented in
[Architecture Levels](../architecture-levels.md#level-3--isolated).
