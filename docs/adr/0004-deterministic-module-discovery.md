# ADR-0004: Deterministic Module Discovery

- Status: Accepted
- Date: 2026-08-15

## Context

Moduark needs zero-config discovery for application Modules without assuming a
consumer's root namespace or recursively scanning every PHP file. The result
must be stable across filesystems and Laravel cache commands, and malformed
entry files must fail with actionable diagnostics.

## Decision

- Scan only `<root>/*/*Module.php` candidates.
- A Module named `Order` must use
  `<root>/Order/OrderModule.php`, a namespace ending in `Order`, and a class
  named `OrderModule`.
- Read the declared namespace and class with PHP's tokenizer. Do not derive the
  namespace from `App\\` or another assumed PSR-4 prefix.
- Require the declared class to be Composer-autoloadable, concrete, extend
  `Module`, and resolve back to the discovered source file.
- Treat a missing Module root as an empty registry so a new Laravel application
  remains zero-config.
- Reject duplicate names and class identities case-insensitively.
- Sort registry entries case-insensitively by Module name, using the exact name
  as the deterministic tie-breaker.
- Resolve the registry lazily from the validated `modules.path` configuration.

## Acceptance evidence

- Unit fixtures prove repeated discovery produces the same ordered snapshot.
- Invalid filename, namespace, entry type, missing class, and duplicate name
  fixtures produce explicit errors.
- A Testbench workbench discovers three real Modules in the same order before
  and after Laravel `config:cache` and `route:cache`.
- Production, test, fixture, and workbench code pass PHPStan level max without a
  baseline.

## Consequences

- Recursive discovery, multiple roots, arbitrary entry filenames, and custom
  layouts are outside this contract. They require a separate decision.
- Discovery is deterministic but still reads entry files. A precomputed Module
  metadata cache remains a later production optimization.
- Routes, views, translations, migrations, commands, and other Module resources
  are not discovered or loaded by this ADR.
