# Inspection and Upgrades

Use this reference for read-only architecture inspection, graph output, cache
behavior, PHPStan integration, or a Moduark package upgrade.

## Select the Smallest Inspection

- `php artisan module:list` lists discovered Modules deterministically.
- `php artisan module:inspect {module}` shows one Module's dependencies,
  providers, Capabilities, table ownership, and public API convention.
- `php artisan module:graph [module]` shows direct Module relationships.
- `php artisan module:graph --view=capability` shows Capability relationships.
- `php artisan module:graph --view=combined` shows the union of direct and
  Capability neighborhoods.
- Add `--format=mermaid` when a reviewable diagram is useful.

Do not infer an application's intended domain boundaries solely from current
directories or a generated graph. Report observed metadata separately from a
recommended redesign.

## Cache Lifecycle

Use `module:cache` and `module:clear` according to the installed package's
README and cache ADRs. Discovery and effective configuration happen during
Laravel bootstrap, so configuration or source changes can require normal
Laravel configuration, route, or optimization cache verification as well as a
Moduark check.

Do not delete caches as a substitute for explaining a reproducible metadata or
analysis defect. Confirm the same source/configuration state before comparing
cold and cached results.

## Optional PHPStan Integration

The companion `cluion/moduark-phpstan` package is optional and development-only.
Read the installed Moduark `docs/phpstan-integration.md` and the companion
package version before changing PHPStan or Larastan configuration.

`module:check` remains authoritative for the complete Moduark rule set. Do not
claim that a PHPStan pass covers rules the installed companion extension does
not implement, and do not maintain two conflicting Level, baseline, or
suppression configurations.

## Upgrade Workflow

1. Record `composer show cluion/moduark` and the current Git state.
2. Run application tests and `module:check --format=json` before the upgrade.
3. Read the target package `CHANGELOG.md`, `README.md`, relevant adoption docs,
   recipes, and ADRs. Treat beta diagnostic identity changes as migrations.
4. Update the Composer constraint only within the user's requested scope.
5. Re-run package discovery, application tests, the same Moduark check, and any
   relevant cache paths.
6. Compare blocking violations, warnings, incomplete rules, baseline audit, and
   suppression audit separately.

Do not silently regenerate a baseline, broaden suppressions, disable a newly
available rule, or describe changed warnings as irrelevant. Present those as
explicit upgrade decisions with their diffs and consequences.
