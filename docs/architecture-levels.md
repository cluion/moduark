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
| 2 | Decoupled | Reserved, incomplete | Consumer-owned Ports, adapters, capability contracts |
| 3 | Isolated | Reserved, incomplete | Model, database, migration, transaction, and export boundaries |

The package default is Level 1. Selecting Level 2 or Level 3 with their normal
presets returns exit code 2 because additional enabled rules are unavailable.
That is an incomplete analysis, not an architecture pass.

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
| `capability_contracts` | — | — | E | E | No |
| `adapter_boundaries` | — | — | E | E | No |
| `cross_module_model_access` | — | — | — | E | No |
| `database_ownership` | — | — | — | E | No |
| `migration_ownership` | — | — | — | E | No |
| `cross_module_foreign_keys` | — | — | — | W | No |
| `cross_module_transactions` | — | — | — | W | No |
| `explicit_public_exports` | — | — | — | E | No |

Overrides may disable unavailable rules, but doing so does not create a Level 2
or Level 3 guarantee. The beta supports Level 0 and Level 1 as complete products.

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

Level 2 is reserved for consumer-owned Ports, adapter boundaries, and typed
capability requirements/providers. The unreleased `0.2` work includes the first
metadata contract: an application can define a typed Capability identity and
declare what a Module requires or provides.

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
on Laravel's last-binding-wins behavior. Capability graph edges and Level 2
structural rules remain unavailable. The preset and rule IDs exist so
configuration can evolve without renaming the model, but their analyzers are not
part of the current implementation.

Running the normal preset demonstrates this explicitly:

```bash
php artisan module:check --level=2
```

The command lists `capability_contracts` and `adapter_boundaries` as unavailable
and exits 2.

## Level 3 — Isolated

Level 3 is reserved for Eloquent/model access, table ownership, migration
ownership, foreign keys, transaction warnings, and explicit export metadata.
Those rules need Laravel-aware AST and ownership indexes and are not implemented.
Selecting Level 3 therefore exits 2 with an incomplete report.

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

Overrides are global rule switches. The beta does not provide per-file
suppressions or a baseline file, so record why a Level guarantee is intentionally
weakened and remove the override as soon as the migration permits.

## Diagnostics and Exit Policy

Architecture violations include a stable code, rule and severity, message,
location when available, Module relationship, symbol evidence, and a suggested
repair. Blocking violations return exit 1. Warnings alone return exit 0.

Command input, parse, duplicate-symbol, filesystem, and unavailable-rule failures
handled by `module:check` return exit 2. Typed source-analysis failures use
`MOD-ANALYSIS-001`, report their source location when known, and state that no
passing result was produced. Configuration or discovery can fail during Laravel
bootstrap before the command's renderer runs; those exceptions use Laravel's
process-level handling.

Use `--level` to evaluate a migration target without editing configuration:

```bash
php artisan module:check --level=1
```

When it passes, update `config/modules.php` so local runs and CI use the same
default.
