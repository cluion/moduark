# Brownfield Level 0 to Level 1

This recipe introduces Moduark to an existing Laravel application without
claiming a boundary is enforced before it passes. The example separates `User`
and `Order`, then replaces one direct internal service dependency with a
provider-owned contract.

## Outcome

At the end of the recipe:

- `User` and `Order` are discovered as valid Modules;
- `Order` declares its direct dependency on `User`;
- `Order` consumes `User\Contracts\UserFinder`, not an internal service;
- the Module graph is acyclic;
- `moduark:check --level=1` exits `0` and reports a complete result;
- the shared configuration and CI gate both use Level 1.

This is a Level 1 provider-owned API. It does not introduce Level 2
consumer-owned Ports or Adapters.

## Starting Point

Assume the existing application has an order action that depends directly on a
user service. Its exact original folders do not matter; the architectural
problem is the direct implementation dependency:

```php
use App\Services\UserService;

final class CreateOrder
{
    public function __construct(private UserService $users)
    {
    }
}
```

Protect this behavior with application tests before moving files. Record route,
queue, schedule, container binding, migration, and configuration cache behavior
that the affected code currently relies on.

## Checkpoint 1: Install Without Enforcing Level 1

Install the current Moduark beta and publish configuration:

```bash
composer require cluion/moduark:^0.5@beta
php artisan vendor:publish --tag=moduark-config
```

Set the shared default to Level 0 while source is being organized:

```php
'architecture' => [
    'level' => 0,
    'baseline' => base_path('moduark-baseline.json'),
    'suppressions' => base_path('moduark-suppressions.json'),
    'rules' => [],
],
```

Do not disable Level 1 rules individually during this phase. Level 0 already
expresses the intended temporary contract without making future exceptions look
permanent.

Acceptance:

```bash
php artisan moduark:list
php artisan moduark:check
```

The check must exit `0`. At Level 0 that proves only Module structure and unique
identity; it does not approve cross-Module access.

## Checkpoint 2: Create the Module Entries

Create the two entry classes before moving implementation files:

```bash
php artisan moduark:make-module User
php artisan moduark:make-module Order
```

Move one behavior-preserving vertical slice at a time until the relevant layout
resembles:

```text
app/Modules/
├── Order/
│   ├── Actions/CreateOrder.php
│   └── OrderModule.php
└── User/
    ├── Providers/UserServiceProvider.php
    ├── Services/UserService.php
    └── UserModule.php
```

Update namespaces through normal Composer PSR-4 autoloading. Do not add a
per-Module `composer.json` or another autoload root.

After every move, run the affected application tests and the Laravel caches the
slice depends on. A typical checkpoint is:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan moduark:list
php artisan moduark:check
```

Keep Level 0 configured until application behavior and discovery both pass.

## Checkpoint 3: Probe Level 1

Run Level 1 temporarily without changing shared configuration:

```bash
php artisan moduark:check --level=1
```

The example direct reference should reveal two independent decisions:

- `MOD-DEPENDENCY-002`: `Order` observes `User` without declaring the direct
  Module dependency;
- `MOD-BOUNDARY-001`: `User\Services\UserService` is internal and cannot be a
  cross-Module API.

Adding metadata fixes only the first diagnostic. Moving the class name into a
public folder without defining a stable contract merely hides ownership intent.
Resolve both decisions explicitly.

Exit `2` is not architecture debt. It means input, configuration, discovery, or
analysis failed and must be repaired before continuing.

## Checkpoint 4: Declare the Direct Dependency

Add `UserModule` to the consumer's metadata:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\User\UserModule;
use Cluion\Moduark\Module;

final class OrderModule extends Module
{
    public function dependencies(): array
    {
        return [UserModule::class];
    }
}
```

Inspect the direction before proceeding:

```bash
php artisan moduark:graph
php artisan moduark:graph Order
```

If `User` already depends on `Order`, do not add a reverse dependency as a
temporary workaround. Move the shared contract, invert one relationship, or
keep the configured Level at 0 until ownership is decided. A cycle is a design
decision, not a suppression candidate.

## Checkpoint 5: Publish a Provider-Owned Contract

Create the public contract below the exact, case-sensitive `Contracts/`
directory:

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Contracts;

interface UserFinder
{
    public function displayName(int $userId): string;
}
```

Make the existing implementation satisfy that contract without moving the
implementation out of its internal folder:

```diff
+use App\Modules\User\Contracts\UserFinder;
+
-final class UserService
+final class UserService implements UserFinder
```

Bind the contract in a provider owned by the `User` Module:

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Providers;

use App\Modules\User\Contracts\UserFinder;
use App\Modules\User\Services\UserService;
use Illuminate\Support\ServiceProvider;

final class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserFinder::class, UserService::class);
    }
}
```

Register the provider through Module metadata:

```php
<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\User\Providers\UserServiceProvider;
use Cluion\Moduark\Module;

final class UserModule extends Module
{
    public function providers(): array
    {
        return [UserServiceProvider::class];
    }
}
```

Change the consumer to depend on the contract:

```diff
-use App\Modules\User\Services\UserService;
+use App\Modules\User\Contracts\UserFinder;

 final class CreateOrder
 {
-    public function __construct(private UserService $users)
+    public function __construct(private UserFinder $users)
     {
     }
 }
```

`Contracts/`, `Data/`, `Events/`, and the Module entry are the Level 1 Public
API. `Services/`, `Models/`, `Actions/`, `Ports/`, and other directories remain
internal. The contract belongs to the provider at Level 1; consumer-owned Ports
begin at Level 2.

## Checkpoint 6: Decide How to Handle Remaining Debt

Run the complete Level 1 probe again:

```bash
php artisan moduark:check --level=1
```

Prefer repairs. When the rest of a brownfield application cannot migrate in one
change, use the narrowest reviewable mechanism:

| Situation | Action |
|---|---|
| One intentional, temporary exception with an owner and reason | Add one narrow `moduark-suppressions.json` entry |
| Many reviewed existing violations that need gradual repayment | Create `moduark-baseline.json` with `moduark:baseline --level=1` |
| A new violation introduced by the current change | Fix it; do not expand the baseline |
| A configuration, discovery, or analyzer failure | Fix the tool failure; never record it as debt |
| A dependency cycle | Redesign or postpone the Level change; do not suppress it |

For a reviewed baseline:

```bash
php artisan moduark:baseline --level=1
git add moduark-baseline.json
php artisan moduark:check --level=1
```

A baseline means the debt was adopted, not resolved. Prune it as code is fixed:

```bash
php artisan moduark:baseline --prune
```

See [Adopting Moduark](../adoption.md) for exact suppression selectors, audit
states, baseline matching, and safe pruning.

## Checkpoint 7: Enable Level 1

Change the shared default only after the temporary Level 1 run is complete and
exits `0`:

```php
'architecture' => [
    'level' => 1,
    'baseline' => base_path('moduark-baseline.json'),
    'suppressions' => base_path('moduark-suppressions.json'),
    'rules' => [],
],
```

Run the configured contract and application regression tests:

```bash
php artisan moduark:check
php artisan moduark:graph
php artisan config:cache
php artisan route:cache
php artisan test
```

Use the caches that are relevant to the application; do not delete a failing
cache step merely to complete the migration.

## Checkpoint 8: Add the CI Gate

Add the complete Moduark check as a separate CI step:

```yaml
- name: Check Module architecture
  run: php artisan moduark:check --format=github
```

The process contract is:

- exit `0`: complete check with no blocking violation; warnings may remain;
- exit `1`: blocking architecture violations were found;
- exit `2`: input, configuration, discovery, analyzer, or unavailable-rule
  failure; the result is incomplete.

If the project also uses the optional PHPStan extension, keep both gates. The
extension currently reports only `internal_api_access`; see
[PHPStan and Larastan Integration](../phpstan-integration.md).

## Reviewable Commit Sequence

Keep the migration reversible by separating concerns:

1. Install Moduark and configure Level 0.
2. Add Module entries and move one tested resource or workflow slice.
3. Add direct dependency metadata and provider-owned Public APIs.
4. Add a reviewed baseline or narrow suppressions only if required.
5. Enable Level 1 and add the CI gate.

Do not enable Level 1 in the first commit and weaken its rules in later commits.
If a checkpoint fails, retain the last passing Level 0 state while fixing that
specific slice.

## Acceptance Checklist

- [ ] Existing application behavior is covered before files move.
- [ ] `UserModule` and `OrderModule` are each discovered exactly once.
- [ ] Runtime resources and providers still load through Laravel.
- [ ] `OrderModule::dependencies()` records `UserModule::class`.
- [ ] The direct dependency graph is acyclic.
- [ ] `CreateOrder` depends on `User\Contracts\UserFinder`.
- [ ] No consumer references `User\Services\UserService`.
- [ ] Any suppression is narrow, reasoned, and reviewable.
- [ ] Any baseline is committed and does not include a new regression.
- [ ] `moduark:check --level=1` is complete and exits `0`.
- [ ] Shared configuration uses Level 1 only after the probe passes.
- [ ] CI preserves exit `1` and exit `2` as failures.

The broader policy is documented in [Adopting Moduark](../adoption.md), and the
Level 1 rule contract is documented in
[Architecture Levels](../architecture-levels.md#level-1--modular).
