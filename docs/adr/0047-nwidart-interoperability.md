# ADR-0047: nwidart Interoperability Boundary

- Status: Accepted for the next `1.0.0` release candidate
- Date: 2026-08-22

## Context

Validation of `1.0.0-rc.1` in an application using
`nwidart/laravel-modules` exposed three collisions that isolated package tests
did not catch:

- both packages used generic `module:*` Artisan command names;
- both packages published `config/modules.php` and read the `modules.*`
  configuration namespace;
- nwidart places Module application code below `Modules/<Name>/app`, while
  Moduark discovered only a root-level Module entry and interpreted Public API
  folders relative to that root.

A command-existence assertion can be false-green when the command belongs to
the other package. A publish assertion can also pass while one package
overwrites the other's configuration. These behaviors must be tested in one
real Laravel application before the candidate Stable contract is promoted.

## Decision

- All Moduark commands use the `moduark:*` namespace:
  `moduark:make-module`, `moduark:make`, `moduark:list`, `moduark:inspect`,
  `moduark:graph`, `moduark:check`, `moduark:baseline`, `moduark:cache`, and
  `moduark:clear`.
- Moduark publishes `config/moduark.php` and reads the `moduark.*` namespace.
  It does not register legacy generic aliases because those names may belong
  to nwidart or another installed package.
- When nwidart is installed and `moduark.path` is `null` or absent, Moduark
  follows nwidart's `modules.paths.modules` root. A non-empty configured path
  remains an explicit override.
- Discovery accepts both `<Module>/<Module>Module.php` and
  `<Module>/app/<Module>Module.php`. Convention-based `Contracts`, `Data`, and
  `Events` Public API folders remain relative to the selected entry class
  source root, so nwidart implementations beside them under `app` stay
  internal.
- A fresh Laravel 13 interoperability fixture installs both packages and proves
  command ownership, independent configuration publishing, shared root
  resolution, discovery, Public API enforcement, architecture reporting,
  Module caching, and route/config optimization behavior.
- This compatibility slice is validated in another RC. It does not authorize
  or justify publishing `1.0.0` stable directly.

## Acceptance

- Publishing Moduark configuration leaves nwidart's `config/modules.php`
  byte-identical.
- `module:make` remains owned by nwidart and `moduark:make` remains owned by
  Moduark in the same Artisan application.
- Both packages discover the same generated `User` and `Order` Modules.
- Level 1 accepts cross-Module references to `app/Contracts` and `app/Events`
  while reporting an `app/Services` reference as `MOD-BOUNDARY-001`.
- Module cache creation and Laravel `optimize` / `optimize:clear` retain the
  discovered graph and route identities without duplicates.
- Local and published-dist release gates execute the interoperability fixture.

## Consequences

- Applications upgrading from RC.1 must migrate command and configuration
  identities explicitly; the upgrade guide contains the exact mapping.
- Moduark and nwidart can coexist without claiming each other's generic
  command or configuration namespace.
- The separately released `moduark-phpstan` companion must update its Composer
  constraint and `configPath` default before it can claim 1.0 RC compatibility.
- Supporting the nwidart directory layout does not make nwidart Module
  providers, routes, resources, or `module.json` part of Moduark's public API.
- Stable publication remains gated on a reviewed RC, exact-commit CI, and
  published-dist verification.
