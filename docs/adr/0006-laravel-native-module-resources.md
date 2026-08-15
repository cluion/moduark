# ADR-0006: Laravel-native Module Resources

- Status: Accepted
- Date: 2026-08-15

## Context

Application Modules may own Laravel routes, views, translations, migrations,
commands, and service providers. Moduark has to connect those resources to the
framework without introducing a second router, translator, migration runner,
or console kernel. Resource loading must remain deterministic and compatible
with Laravel configuration and route caches.

## Decision

- Module-owned service providers remain explicit typed metadata from
  `Module::providers()`. Moduark validates the complete dependency graph before
  registering providers in dependency order.
- A resource integration service provider is registered after all Module-owned
  providers. Laravel therefore boots the ordered providers before it boots
  conventional Module resources.
- Only paths that exist are registered. Modules with only an entry class have
  no resource-loading side effects.
- `routes/web.php` and `routes/api.php` are loaded in that order through
  Laravel's cache-aware route loader. Both files are self-contained: Moduark
  does not add an API prefix or middleware implicitly.
- `resources/views/` and `resources/lang/` share the lowercase Module identity
  as their Laravel namespace, for example `Order` becomes `order::`.
- `Database/Migrations/` is registered directly with Laravel's migrator.
  Application Module migrations are not published or copied.
- During console execution, direct PHP files in `Console/Commands/` are sorted,
  resolved through Composer, and required to be instantiable Laravel commands
  whose autoloaded source matches the discovered file. Nested command folders
  are outside the beta convention.
- Resource registration follows the dependency-ordered Module sequence and
  uses Laravel `ServiceProvider` helpers exclusively.

## Acceptance evidence

- A real workbench Order Module declares its provider and owns web/API routes,
  a namespaced Blade view, a namespaced translation, a migration, and an
  Artisan command.
- HTTP assertions render the namespaced view and translation through both route
  files.
- The workbench migration is registered and executed against the Testbench
  database, and the Module command is resolved by Artisan.
- Provider register and boot markers prove the explicit provider lifecycle is
  active in the package integration path.
- Configuration-cache and cold route-cache tests repeat the HTTP, view,
  translation, migration-path, command, and provider assertions.
- The same suite is exercised on Laravel 12 and Laravel 13.

## Consequences

- API route prefix and middleware policy stays visible in each Module route
  file and can evolve without a hidden package default.
- Resource namespace collisions are prevented by the registry's existing
  case-insensitive Module identity uniqueness rule.
- Commands are discovered only for console applications, avoiding command
  class loading during ordinary HTTP boot.
- Config files, JSON translation paths, resource publishing, nested command
  discovery, and Module enable/disable state remain separate contracts.
