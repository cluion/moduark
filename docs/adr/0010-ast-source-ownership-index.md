# ADR-0010: AST Source Ownership Index

- Status: Accepted for Slice 6C
- Date: 2026-08-15

## Context

The `undeclared_dependencies` rule must compare declared Module metadata with
observed PHP references. Namespace prefixes and regular expressions cannot
reliably distinguish aliases, fully qualified names, type positions, comments,
strings, or imports that are never used. A false pass or a noisy false positive
would both undermine Level 1 enforcement.

The package previously received `nikic/php-parser` only through development
dependencies, which is not a valid production runtime contract.

## Decision

- `nikic/php-parser` 5.8 is a direct production dependency and provides parsing,
  name resolution, and source lines.
- One source index builder scans PHP files below each discovered Module. Symlinked
  files are skipped so a Module cannot silently claim external source trees.
- A first pass assigns every named class, interface, trait, and enum to the
  Module whose directory contains its file. Class-like identity is
  case-insensitive; ambiguous duplicate declarations are a tool error.
- A second pass records resolved class references from attributes, parameter,
  property and return types, extends/implements, trait use, catch types,
  `new`, static access, class constants, and `instanceof`.
- A `use` statement alone is not an observed dependency. PHPDoc and dynamic
  string references are not inferred in this slice.
- Every retained reference contains source Module, target Module, canonical
  symbol, file, and line. Same-Module references remain in the index for future
  boundary rules but do not violate `undeclared_dependencies`.
- `MOD-DEPENDENCY-002` is emitted for each cross-Module reference whose target
  Module is absent from the consumer's typed `dependencies()` metadata.
- Source parsing runs only when `undeclared_dependencies` is enabled. Level 0
  keeps its discovery-only behavior.
- `internal_api_access` remains unavailable until the Public API convention is
  decided and implemented in Slice 7.

## Consequences

- Aliases and fully qualified names resolve to the same owner without treating
  unused imports, comments, or strings as dependencies.
- Analyzer parse, duplicate-symbol, and filesystem failures produce exit code 2
  instead of an incomplete pass.
- The symbol and reference index can be reused by the Level 1 boundary rule
  without parsing the filesystem again inside each rule.
- Runtime indexes contain objects and AST-derived data but are never written to
  Laravel's configuration cache; config cache compatibility remains scalar.
