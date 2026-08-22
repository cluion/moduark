# Upgrading Moduark

This guide covers application-owned changes required when upgrading Moduark.
Read the complete changelog between the installed and target versions as well
as the section below for the target release.

> **Current status:** `1.0.0` is the current stable release. It promotes the
> reviewed RC.2 command, configuration, and `nwidart/laravel-modules`
> interoperability boundaries without another runtime or schema change.

Install or upgrade to the stable line:

```bash
composer require cluion/moduark:^1.0
```

## Upgrade Safety Checklist

Before changing the Composer constraint:

1. Start from a reviewable application branch with no unrelated changes.
2. Record the installed version with `composer show cluion/moduark`.
3. Preserve the current Moduark configuration, `moduark-baseline.json`, and
   `moduark-suppressions.json` in version control when they exist. Before this
   namespace migration, Moduark used `config/modules.php`; after it, Moduark
   uses `config/moduark.php`.
4. Run the current version's architecture check and retain its complete JSON
   result for comparison:

   ```bash
   php artisan moduark:check --format=json
   php artisan moduark:check --show-suppressions
   ```

5. Treat exit `2`, `complete: false`, or `status: incomplete` as a failed
   analysis. It is not valid before/after evidence.

The commands above use the RC.2 and stable namespace. When upgrading directly
from RC.1 or an earlier beta, run that installed version's `module:check`
commands before Composer replaces it, then use the `moduark:*` commands after
the namespace migration. The debt-file commands below use Moduark's default
filenames. Substitute the paths configured in
`moduark.architecture.baseline` and `moduark.architecture.suppressions` when the
application overrides them.

## Upgrading from `1.0.0-rc.2` to `1.0.0`

Stable promotes the reviewed RC.2 contract without changing PHP extension
points, configuration keys, command names, architecture presets, diagnostic
identities, or machine-readable schemas. Replace the exact RC constraint with
the stable line and review the lock-file change:

```bash
composer require cluion/moduark:^1.0
composer show cluion/moduark
git diff -- composer.json composer.lock
```

Clear rebuildable metadata, compare a complete architecture result, and rebuild
the production cache only after accepting it:

```bash
php artisan moduark:clear
php artisan moduark:list
php artisan moduark:check --format=json
php artisan moduark:cache
```

Applications using Laravel Boost should rerun `php artisan boost:install` and
review the installed Skill diff. No application-owned baseline or suppression
file should be rewritten merely because the stability label changed.

## Upgrading from `1.0.0-rc.1` to `1.0.0-rc.2`

RC.2 replaces candidate identities that collide with
`nwidart/laravel-modules`. No legacy Artisan aliases are registered because
those generic names may already belong to nwidart or another package.

| `1.0.0-rc.1` | `1.0.0-rc.2` |
|---|---|
| `make:module` | `moduark:make-module` |
| `module:make` | `moduark:make` |
| `module:list` | `moduark:list` |
| `module:inspect` | `moduark:inspect` |
| `module:graph` | `moduark:graph` |
| `module:check` | `moduark:check` |
| `module:baseline` | `moduark:baseline` |
| `module:cache` | `moduark:cache` |
| `module:clear` | `moduark:clear` |

If the application's `config/modules.php` was published by Moduark, rename it
to `config/moduark.php` and migrate its `modules.*` configuration references to
`moduark.*`. If `config/modules.php` belongs to `nwidart/laravel-modules`, keep
it unchanged and publish Moduark's independent file instead:

```bash
php artisan vendor:publish --tag=moduark-config
```

When nwidart is installed and `moduark.path` is `null` or not explicitly
configured, Moduark follows `modules.paths.modules`. It discovers both
`<Module>/<Module>Module.php` and `<Module>/app/<Module>Module.php`. For the
second layout, `Contracts/`, `Data/`, and `Events/` below `app/` form the
convention-based Public API; sibling implementation folders remain internal.

After Composer installs the explicitly selected target version:

```bash
php artisan moduark:clear
php artisan moduark:list
php artisan moduark:check --format=json
php artisan moduark:check --show-suppressions
```

`moduark:clear` removes rebuildable Moduark metadata and source-analysis caches.
Run the application's normal Laravel config and route cache verification as a
separate deployment check. Recreate the optional production Module cache only
after the uncached architecture result is accepted:

```bash
php artisan moduark:cache
```

Compare the before/after effective Level, enabled rules, diagnostics, warnings,
suppression audit, and baseline audit. A successful exit `0` may still contain
warnings. Do not hide a new diagnostic by changing the Level, disabling its
rule, creating a baseline, or broadening a suppression unless that debt change
is separately reviewed.

If the application uses Laravel Boost, rerun the application's normal Boost
installation after updating Moduark and review the installed Skill diff:

```bash
php artisan boost:install
```

## Upgrading from the Beta Line to 1.0

### Stable and Preview boundaries

The `1.0.0` contract makes Levels 0 through 2 Stable and keeps Level 3 Preview.
Level 3 remains opt-in: existing rule, severity, diagnostic, and
machine-schema identities are versioned, while documented detection breadth
may expand in a later `1.x` minor release.

Application code should extend `Cluion\Moduark\Module`, declare
`Capability` / `CapabilityRequirement` metadata, and resolve consumer-owned
Ports through Laravel's container. Remove direct application construction of
`CapabilityResolver`, descriptor, plan, or binding objects; those lifecycle
types are Internal. See [Stability and Versioning](docs/stability.md) and
[ADR-0045](docs/adr/0045-stable-contract-boundary.md).

The stable release keeps `moduark:check` JSON schema version `1`, architecture
baseline schema version `1`, and suppression manifest schema version `1`.
There is no general schema rewrite for beta applications. The targeted
`MOD-DEPENDENCY-002` identity migration below still requires review.

### `MOD-DEPENDENCY-002` identity migration

The `0.5.x` hardening changed undeclared-dependency reporting from one finding
per referenced symbol to one deterministic finding per ordered consumer /
provider Module pair. A current baseline entry for this diagnostic has pair
identity and does not retain source evidence:

```json
{
    "rule": "undeclared_dependencies",
    "code": "MOD-DEPENDENCY-002",
    "severity": "error",
    "file": null,
    "consumer": "Order",
    "target": "User",
    "symbol": null,
    "count": 1
}
```

Do not carry an old amplified count forward. One pair now produces one
diagnostic even when many source symbols cross the same undeclared boundary.

For suppressions, replace a file- or symbol-only selector with the explicit
Module pair while preserving and re-reviewing the reason:

```json
{
    "rule": "undeclared_dependencies",
    "code": "MOD-DEPENDENCY-002",
    "consumer": "Order",
    "target": "User",
    "reason": "Legacy dependency tracked by ADR-012."
}
```

Determine the pair from the old version's structured diagnostic or application
Module ownership. Do not guess it from a class name. If the pair cannot be
confirmed, remove the suppression and let the current diagnostic become
visible.

After updating suppression selectors, run the current check. Old per-symbol
baseline entries become stale because they intentionally do not match the new
pair identity. Remove only stale debt with:

```bash
php artisan moduark:baseline --prune
git diff -- moduark-baseline.json moduark-suppressions.json
```

Prune never adopts the newly visible pair diagnostic. Prefer fixing the missing
dependency or boundary. If an application must retain the reviewed pair as
baseline debt, first inspect the complete current JSON result. Only then may a
maintainer deliberately replace the baseline with:

```bash
php artisan moduark:baseline --force
git diff -- moduark-baseline.json
```

`moduark:baseline --force` captures every current unsuppressed violation and
must not be run automatically. Reject the replacement if its diff adopts an
unrelated regression, preserves old inflated counts, or was produced from an
incomplete analysis.

### Cache rebuild

Module metadata and source-analysis cache schemas are Internal. They are not
application data and do not receive migration scripts. Clear them after the
package update and rebuild them only from the accepted target version:

```bash
php artisan moduark:clear
php artisan moduark:check --format=json
php artisan moduark:cache
```

An unknown old cache schema is normally bypassed safely; explicit clearing
keeps deployment behavior deterministic and prevents a malformed current-schema
cache from being mistaken for upgrade evidence.

## Deprecation Expectations for 1.x

Stable replacements must follow the policy in
[Stability and Versioning](docs/stability.md): the replacement ships in at least
one released `1.x` minor, both old and new paths remain covered, and removal
waits for the next major release. Each deprecation must identify the replacement
and appear in the changelog and this guide.

No PHP API is deprecated merely because this guide exists. Internal APIs do not
receive the Stable deprecation window, and Preview Level 3 detection growth is
governed by its documented minor-release policy.
