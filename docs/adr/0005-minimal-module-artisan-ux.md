# ADR-0005: Minimal Module Artisan UX

- Status: Accepted
- Date: 2026-08-15

## Context

Moduark needs Laravel-native commands for creating and inspecting Modules while
preserving its minimal-structure and deterministic-discovery contracts. A
generator cannot assume that `modules.path` belongs to the application's root
namespace: package workbenches and consumer applications may use another
Composer PSR-4 mapping.

## Decision

- `make:module {name}` accepts only ASCII StudlyCase identities and rejects PHP
  reserved names, separators, traversal segments, and other unsafe input.
- Generation creates only `<root>/<Name>/<Name>Module.php`.
- Resolve the root namespace from registered Composer PSR-4 mappings. Prefer
  the longest matching base path and fail when no mapping or multiple equally
  specific namespaces match.
- Create the entry file with exclusive-create semantics. An existing file is
  never overwritten, and no `--force` option is provided by this contract.
- Use a package stub by default and allow a consumer override at
  `stubs/module.stub`; publish it through the `moduark-stubs` tag.
- `module:list` reads the deterministic registry and typed metadata compiler.
  It displays application-wide level and declared Module dependencies.
- State is `enabled` until a source-controlled enable/disable design exists.
  Requires and Provides remain empty placeholders until their typed API is
  accepted; the command does not infer unsupported metadata.

## Acceptance evidence

- Feature tests prove one-file generation, exact source, Composer
  autoloadability, invalid-name rejection, unmapped-path rejection, and
  no-overwrite behavior.
- Unit tests prove longest-prefix and ambiguous PSR-4 resolution.
- `module:list` has a deterministic table snapshot before and after Laravel
  `config:cache`.
- The same command suite is exercised on Laravel 12 and Laravel 13.

## Consequences

- Non-Composer and ambiguous PSR-4 layouts require configuration changes before
  generation; Moduark will not guess a namespace.
- Nested Module identities and `--force` overwrite semantics are outside this
  command contract.
- Laravel maker integration for models, controllers, and other resources
  remains a separate feasibility spike.
