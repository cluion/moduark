# Brownfield Level 1 to Level 2

This recipe upgrades an application from provider-owned Level 1 APIs to Level 2
dependency inversion. It continues the `User` / `Order` example from the
[Level 0 to Level 1 recipe](level-0-to-level-1.md): `User` keeps its public
contract, while `Order` moves its core dependency behind a Port that it owns.

## Outcome

At the end of the recipe:

- `Order` core code depends only on `Order\Ports\UserLookup`;
- `Order\Adapters\User\UserLookupAdapter` translates that Port to the existing
  `User\Contracts\UserFinder` provider API;
- a provider-neutral `UserLookup` Capability connects `requires()` and
  `provides()` metadata;
- `Order` retains its direct dependency on `User` because its Adapter references
  the provider API;
- the Capability resolves to exactly one provider before container mutation;
- Laravel binds the consumer Port to its declared Adapter automatically;
- `module:check --level=2` evaluates all eight Level 2 rules and exits `0`;
- the shared configuration and CI gate both use Level 2.

Level 2 does not remove the provider's Level 1 Public API. It moves that API
behind consumer-owned integration code so the consumer's core no longer knows
which Module supplies the behavior.

## Starting Point

Assume the Level 1 migration is already complete:

```text
app/
└── Modules/
    ├── Order/
    │   ├── Actions/CreateOrder.php
    │   └── OrderModule.php
    └── User/
        ├── Contracts/UserFinder.php
        ├── Providers/UserServiceProvider.php
        ├── Services/UserService.php
        └── UserModule.php
```

`CreateOrder` currently uses the provider-owned contract directly:

```php
use App\Modules\User\Contracts\UserFinder;

final class CreateOrder
{
    public function __construct(private UserFinder $users)
    {
    }
}
```

`OrderModule::dependencies()` already contains `UserModule::class`, and
`UserServiceProvider` already binds `UserFinder` to `UserService`. Preserve both
contracts during this migration.

Before changing the dependency shape, add or confirm a container-backed test
that resolves `CreateOrder` and exercises its user lookup. A unit test that
constructs the action manually does not prove the runtime Port binding.

## Checkpoint 1: Keep Level 1 as the Shared Default

Use the current Moduark beta and retain Level 1 while preparing the inversion:

```bash
composer require cluion/moduark:^0.5@beta
```

```php
'architecture' => [
    'level' => 1,
    'baseline' => base_path('moduark-baseline.json'),
    'suppressions' => base_path('moduark-suppressions.json'),
    'rules' => [],
],
```

Confirm the configured contract, then probe Level 2 without changing shared
configuration:

```bash
php artisan module:check
php artisan module:check --level=2
```

The first command must exit `0`. The second command should expose the remaining
Level 2 work. In this example, the direct reference from `CreateOrder` to
`UserFinder` produces `MOD-ADAPTER-003` because consumer core code crosses into
the provider outside a declared Capability Adapter.

Do not suppress that diagnostic: it identifies the exact dependency this slice
is intended to invert. Exit `2` means the check was incomplete because of
configuration, discovery, metadata, or analysis failure; it is not accepted
architecture debt.

## Checkpoint 2: Define Capability, Port, and Adapter

Create a provider-neutral Capability identity in an application composition
namespace:

```php
<?php

declare(strict_types=1);

namespace App\Capabilities;

use Cluion\Moduark\Capability;

interface UserLookup extends Capability
{
}
```

The Capability is typed architecture vocabulary. It deliberately has no
behavioral methods and is not a service-locator key. Do not place it inside
`User` or `Order`, because either choice would make the neutral identity belong
to one side of the relationship. Keep the composition namespace curated rather
than turning it into a `Shared` dumping ground.

Define the behavioral interface below the consumer's exact, case-sensitive
`Ports/` directory:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Order\Ports;

interface UserLookup
{
    public function labelForOrder(int $userId): string;
}
```

The method is written in `Order` language. It does not mirror every method from
the provider contract.

Add the concrete Adapter below `Adapters/{Provider}/`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Order\Adapters\User;

use App\Modules\Order\Ports\UserLookup;
use App\Modules\User\Contracts\UserFinder;

final readonly class UserLookupAdapter implements UserLookup
{
    public function __construct(private UserFinder $users)
    {
    }

    public function labelForOrder(int $userId): string
    {
        return $this->users->displayName($userId);
    }
}
```

Only this consumer-owned Adapter crosses from `Order` to `User`. It may use the
provider's Level 1 Public API, but it must not reference another provider or the
provider's internal implementation.

At this checkpoint, keep `CreateOrder` unchanged so the application remains
runnable until metadata can wire the new Port.

## Checkpoint 3: Declare the Provider and Requirement

Declare that `User` provides the neutral Capability while retaining its existing
ServiceProvider:

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
}
```

Map the Capability to the consumer Port and Adapter in `OrderModule::requires()`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Capabilities\UserLookup as UserLookupCapability;
use App\Modules\Order\Adapters\User\UserLookupAdapter;
use App\Modules\Order\Ports\UserLookup as UserLookupPort;
use App\Modules\User\UserModule;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;

final class OrderModule extends Module
{
    public function dependencies(): array
    {
        return [UserModule::class];
    }

    public function requires(): array
    {
        return [new CapabilityRequirement(
            UserLookupCapability::class,
            UserLookupPort::class,
            UserLookupAdapter::class,
        )];
    }
}
```

Do not remove `UserModule::class` from `dependencies()`. The direct edge
authorizes the Adapter's reference to `User\Contracts\UserFinder`; the
Capability edge separately describes what behavior `Order` requires and which
Module provides it.

Do not add a manual `UserLookupPort` binding to an application ServiceProvider.
Moduark first resolves every Capability, then registers Module ServiceProviders,
then binds each declared consumer Port to its Adapter. Missing or ambiguous
providers fail before partial Port bindings are applied.

Acceptance:

```bash
php artisan module:inspect Order
php artisan module:graph --view=capability
```

The inspection must show `UserLookupCapability`, `UserModule`,
`UserLookupPort`, and `UserLookupAdapter` as one resolved requirement.

## Checkpoint 4: Move Consumer Core to Its Port

Change `CreateOrder` only after the Capability metadata is complete:

```diff
-use App\Modules\User\Contracts\UserFinder;
+use App\Modules\Order\Ports\UserLookup;

 final class CreateOrder
 {
-    public function __construct(private UserFinder $users)
+    public function __construct(private UserLookup $users)
     {
     }
 }
```

Update calls to use the consumer Port's language:

```diff
-$name = $this->users->displayName($userId);
+$name = $this->users->labelForOrder($userId);
```

Run both focused and container-backed regression tests:

```bash
php artisan optimize:clear
php artisan test --filter=CreateOrder
php artisan test
```

Verify that Laravel resolves `CreateOrder` without a test-only binding. The
runtime chain should be:

```text
CreateOrder
  -> Order\Ports\UserLookup
  -> Order\Adapters\User\UserLookupAdapter
  -> User\Contracts\UserFinder
  -> User\Services\UserService
```

The provider must not import the consumer Port, Adapter, action, or Module.

## Checkpoint 5: Verify Both Graphs and All Level 2 Rules

Inspect direct and inverted relationships separately before enabling Level 2:

```bash
php artisan module:graph
php artisan module:graph Order
php artisan module:graph --view=capability
php artisan module:graph Order --view=capability
php artisan module:graph --view=combined
php artisan module:graph Order --view=combined --format=mermaid
php artisan module:inspect Order
php artisan module:check --level=2
```

The direct graph must still show `Order -> User`. The Capability graph must show
`Order -> UserLookup -> User`; it must not flatten the Capability into another
direct dependency edge.

Level 2 evaluates the six Level 1 rules plus:

- `capability_contracts`, which requires exactly one provider for each consumed
  Capability and unique consumer Port ownership;
- `adapter_boundaries`, which enforces consumer-owned Ports,
  `Adapters/{Provider}/`, Adapter-only provider access, and core dependence on
  Ports instead of concrete Adapters.

Common diagnostics are:

| Code | Decision |
|---|---|
| `MOD-CAPABILITY-001` | Add the missing provider or remove the invalid requirement |
| `MOD-CAPABILITY-002` | Resolve provider ambiguity; do not depend on discovery order |
| `MOD-CAPABILITY-003` | Give each consumer requirement its own Port |
| `MOD-ADAPTER-001` | Move the interface below the consumer's `Ports/` directory |
| `MOD-ADAPTER-002` | Move the Adapter below `Adapters/{Provider}/` |
| `MOD-ADAPTER-003` | Move cross-Module access out of core and through the Adapter |
| `MOD-ADAPTER-004` | Restrict the Adapter to its selected provider |
| `MOD-ADAPTER-005` | Inject the Port instead of the concrete Adapter |

Fix provider-resolution and placement failures rather than baselining the
relationship being introduced. If unrelated pre-existing Level 2 violations
must be adopted temporarily, use the narrow baseline and suppression policy in
[Adopting Moduark](../adoption.md), then prune repaired entries. Never record an
exit `2` failure as debt.

## Checkpoint 6: Enable Level 2 and Keep the CI Gate Complete

Change the shared default only after `module:check --level=2` is complete and
exits `0`:

```php
'architecture' => [
    'level' => 2,
    'baseline' => base_path('moduark-baseline.json'),
    'suppressions' => base_path('moduark-suppressions.json'),
    'rules' => [],
],
```

Run the configured check and application regression gates:

```bash
php artisan module:check
php artisan module:graph --view=combined
php artisan config:cache
php artisan route:cache
php artisan test
```

Keep the normal CI command; it now inherits Level 2 from shared configuration:

```yaml
- name: Check Module architecture
  run: php artisan module:check --format=github
```

CI must preserve exit `1` and exit `2` as failures. Do not pin CI to
`--level=1`, disable either Level 2 rule, or add a separate manual Port binding
that hides a broken Capability graph.

## Reviewable Commit Sequence

Keep the migration behavior-preserving and reversible:

1. Add the provider-neutral Capability, consumer Port, and Adapter without
   changing consumer core.
2. Add `provides()` and `requires()` metadata while retaining the direct Module
   dependency and provider Public API binding.
3. Move the consumer action from the provider contract to its local Port and run
   container-backed tests.
4. Repair or explicitly adopt unrelated existing Level 2 debt.
5. Enable Level 2 only after the temporary probe exits `0`.

If a checkpoint fails, retain the last passing Level 1 configuration while
fixing that slice. Do not revert the provider Public API: the consumer Adapter
still needs a stable provider-facing contract.

## Acceptance Checklist

- [ ] The configured Level 1 check passes before migration begins.
- [ ] A container-backed test covers the affected workflow.
- [ ] The Capability identity extends `Cluion\Moduark\Capability`.
- [ ] The Capability identity is separate from provider API and consumer Port.
- [ ] `Order\Ports\UserLookup` is an interface owned by `Order`.
- [ ] `Order\Adapters\User\UserLookupAdapter` implements that Port.
- [ ] Only the Adapter references `User\Contracts\UserFinder`.
- [ ] `UserModule::provides()` declares the Capability exactly once.
- [ ] `OrderModule::requires()` maps Capability, Port, and Adapter.
- [ ] `OrderModule::dependencies()` retains `UserModule::class`.
- [ ] `CreateOrder` depends on the Port, not the provider or concrete Adapter.
- [ ] The provider has no reference to `Order` source.
- [ ] Direct, Capability, and combined graph views preserve distinct edge kinds.
- [ ] `module:check --level=2` evaluates eight rules and exits `0`.
- [ ] Shared configuration uses Level 2 only after the probe passes.
- [ ] CI preserves exit `1` and exit `2` as failures.

The full Level 2 contract is documented in
[Architecture Levels](../architecture-levels.md#level-2--decoupled).
