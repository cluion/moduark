# PHPStan and Larastan Integration

Use the optional `cluion/moduark-phpstan` package when Moduark boundary
diagnostics should appear in an existing PHPStan or Larastan workflow. The
extension is a development tool; it does not replace Moduark's runtime package
or its complete architecture check.

## Responsibilities

| Package or command | Responsibility |
|---|---|
| `cluion/moduark` | Module discovery, metadata, runtime wiring, all architecture rules, baselines, and suppressions |
| `cluion/moduark-phpstan` | Optional PHPStan adapter for rules that have reached documented parity |
| `php artisan moduark:check` | Authoritative complete architecture result and warning output |
| `vendor/bin/phpstan analyse` | PHP and Laravel analysis plus the currently supported Moduark diagnostic |

The stable `0.2` extension line supports only `internal_api_access` /
`MOD-BOUNDARY-001`. Continue running `moduark:check` for dependency, cycle,
Capability, Adapter, persistence, migration, transaction, and explicit export
rules.

## Version Compatibility

The published `cluion/moduark-phpstan` `v0.2.0` requires Moduark `^1.0`,
defaults its cache input to `config/moduark.php`, and recognizes classic and
nwidart `Modules/*/app` source roots. Its release matrix covers PHP 8.2 through
8.5, Laravel 12 and 13, PHPStan `^2.2`, and optional Larastan 3.10. Published
distribution acceptance resolves `v0.2.0` with Moduark `v1.0.0` on both Laravel
12 and 13, with the same diagnostic under PHPStan alone and with Larastan.

Applications remaining on Moduark 0.4 or 0.5 beta must remain on the companion
`^0.1@beta` line and its `config/modules.php` cache input.

## Install Both Packages on the Stable 1.x Line

Install stable Moduark as an application dependency and the stable companion as
a development dependency:

```bash
composer require cluion/moduark:^1.1
composer require --dev cluion/moduark-phpstan:^0.2
```

If the application already requires Moduark `^1.1`, only the second command is
needed. Pin the exact companion version when repeatable upgrades matter.

## Load the Extension

Choose automatic or manual loading. Do not configure both for the same package.

### Automatic loading

Install the Composer plugin
[PHPStan Extension Installer](https://github.com/phpstan/extension-installer):

```bash
composer require --dev phpstan/extension-installer
```

Composer 2.2 or later asks whether the plugin may run. Approve it only after
reviewing the dependency, which records the decision in `allow-plugins`.
`cluion/moduark-phpstan` and Larastan both publish PHPStan include metadata, so
the plugin discovers them without a manual `includes` entry.

The installer depends on Composer script events. Do not pass `--no-scripts` to
`composer install` in a workflow that relies on automatic extension loading.

### Manual PHPStan loading

Without the extension installer, add the Moduark include to `phpstan.neon` or
`phpstan.neon.dist`:

```neon
includes:
    - vendor/cluion/moduark-phpstan/extension.neon
```

### Manual Larastan loading

Keep the application's existing Larastan includes and add Moduark's extension:

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - vendor/nesbot/carbon/extension.neon
    - vendor/cluion/moduark-phpstan/extension.neon
```

[Larastan](https://github.com/larastan/larastan) remains optional. Moduark
produces the same boundary diagnostic with base PHPStan and with Larastan
loaded.

## Configure the Application Boundary

The extension does not boot Laravel and does not import values from the Laravel
configuration file. The following values are the current `1.x`-compatible
companion defaults:

```neon
parameters:
    moduark:
        basePath: %currentWorkingDirectory%
        modulesPath: %currentWorkingDirectory%/app/Modules
        rootNamespace: App\Modules
        configPath: %currentWorkingDirectory%/config/moduark.php
        baselinePath: %currentWorkingDirectory%/moduark-baseline.json
        suppressionsPath: %currentWorkingDirectory%/moduark-suppressions.json
        internalApiAccess:
            enabled: true
            severity: error
```

`configPath` participates in PHPStan result-cache invalidation; it is not
executed as PHPStan configuration. Keep the explicit NEON values aligned with
the effective Laravel configuration:

- Level 0 or an `internal_api_access => false` rule override requires
  `internalApiAccess.enabled: false`.
- The normal Level 1, 2, and 3 presets use `enabled: true` and
  `severity: error`.
- Custom Module paths, namespaces, baseline paths, and suppression paths must be
  changed in both configurations.
- `rootNamespace` must match the Module path's Composer PSR-4 mapping.

For example, an application using `src/Domain/Modules` can override only the
values that differ:

```neon
parameters:
    moduark:
        modulesPath: %currentWorkingDirectory%/src/Domain/Modules
        rootNamespace: Domain\Modules
```

The application must also include its Module path in PHPStan's normal `paths`
configuration.

## Run Both Gates

Run PHPStan for editor/static-analysis feedback and Moduark for the complete
architecture contract:

```bash
vendor/bin/phpstan analyse --memory-limit=1G
php artisan moduark:check --format=github
```

The current mapping is:

| Moduark rule | Diagnostic code | PHPStan identifier | PHPStan behavior |
|---|---|---|---|
| `internal_api_access` | `MOD-BOUNDARY-001` | `moduark.internalApiAccess` | Non-ignorable blocking error |

Configuration failures use `moduark.configuration`; integration or policy
failures use `moduark.analysisFailure`. They are non-ignorable so an analyzer
failure cannot become an empty pass.

Warnings remain non-blocking. When the extension severity is `warning`, it does
not emit a PHPStan error; use `moduark:check` to retain the warning in text, JSON,
or GitHub output.

## Baselines and Reviewed Suppressions

The extension applies the configured Moduark baseline and audited suppression
manifest before producing PHPStan errors. Use the same repository-visible files
for both tools.

Adopt reviewed brownfield debt with Moduark:

```bash
php artisan moduark:check --level=1
php artisan moduark:baseline --level=1
git add moduark-baseline.json
```

Use `moduark-suppressions.json` for a narrow, explained exception. Do not add a
Moduark boundary identifier to PHPStan `ignoreErrors`: remaining extension
diagnostics are deliberately non-ignorable so architecture exceptions stay in
Moduark's auditable policy.

See [Adopting Moduark](adoption.md) for baseline and suppression recipes.

## Continuous Integration Recipe

Keep PHPStan and the complete Moduark check as separate CI steps so their
responsibilities remain visible:

```yaml
- name: Install dependencies
  run: composer install --no-interaction --no-progress --prefer-dist

- name: Run PHPStan and Moduark extension
  run: vendor/bin/phpstan analyse --memory-limit=1G

- name: Check complete Module architecture
  run: php artisan moduark:check --format=github
```

If automatic loading is used, keep Composer scripts enabled in the install
step. Treat PHPStan configuration errors, `moduark:check` exit `2`, and incomplete
JSON reports as tool failures rather than successful architecture checks.

## Troubleshooting

- If PHPStan reports an unknown `moduark` parameter, the extension was not
  loaded. Verify the Composer plugin or the manual include.
- If services or diagnostics appear twice, remove either automatic or manual
  loading for the duplicate extension.
- If a clean install cannot resolve `cluion/moduark`, declare
  `cluion/moduark:^1.1` in the application root before requiring the extension.
- If custom Modules are not analysed, align `modulesPath`, `rootNamespace`, and
  PHPStan `paths`.
- If CLI and PHPStan results differ, compare the effective Level, rule override,
  baseline, and suppression paths first. Remember that the current extension
  implements only `internal_api_access`.

The integration architecture and parity requirements are recorded in
[ADR-0042](adr/0042-phpstan-extension-integration-boundary.md). Extension source,
issues, and release notes are maintained in the
[`cluion/moduark-phpstan`](https://github.com/cluion/moduark-phpstan) repository.
The released package is available on
[Packagist](https://packagist.org/packages/cluion/moduark-phpstan).
