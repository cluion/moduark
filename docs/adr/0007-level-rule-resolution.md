# ADR-0007: Level and Rule Resolution

- Status: Accepted for `0.1.0-beta`
- Date: 2026-08-15

## Context

Architecture Levels are adoption presets, not maturity scores. Rules need stable
identities and independent switches so a team can keep deliberate exceptions
while temporarily evaluating another Level from the CLI.

The Level 3 preset also needs a default for cross-Module foreign keys. Treating
every such foreign key as an error would make initial adoption brittle before
the analyzer can distinguish all valid database integration trade-offs.

## Decision

- `Level` is a typed value with the Organization, Modular, Decoupled, and
  Isolated cases represented by values 0 through 3.
- Each Level resolves to the complete documented rule matrix. Disabled rules
  remain visible in the effective configuration.
- Resolution order is the configured Level, an optional temporary Level, then
  the configured per-rule overrides.
- Beta rule overrides accept booleans only. Unknown rule IDs and structured
  values fail configuration validation instead of being ignored.
- Level 3 enables `cross_module_foreign_keys` and
  `cross_module_transactions` as warnings. Other enabled preset rules are
  errors.
- Warnings do not fail the process. Any error violation produces exit code 1;
  invalid configuration or incomplete tool execution is reserved as exit code
  2 for the command layer.
- Effective configuration can be exported as scalar arrays for deterministic
  diagnostics and cache-safe transport.

## Consequences

- `--level` will not erase exceptions committed by the team when
  `module:check` is implemented.
- The foreign-key warning gives teams evidence before they choose to promote or
  disable the rule through a future structured override format.
- Defining Level 2 and Level 3 presets does not claim that their analyzers are
  implemented. Enforcement remains limited to rules with concrete analyzers.
- Supporting severity and rule options later requires extending one override
  parser without changing precedence.
