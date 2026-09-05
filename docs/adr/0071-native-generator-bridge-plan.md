# ADR-0071: Native Generator Bridge Plan

- Status: Accepted for the `1.3.0-rc.1` Preview candidate
- Date: 2026-08-30

## Context

Moduark's canonical generator entry point is `moduark:make`. It owns Module
path and namespace resolution, complete related-target planning, collision
preflight, and rollback. Some Laravel developers nevertheless prefer the
native shape `make:model Post --module=Blog`.

Adding `--module` directly to Laravel commands is not a local signature change.
Laravel may add or change Maker options between supported majors, and a package
such as Orchestra Canvas may already replace a command. A bridge that decorates
the command selected only by name could silently take ownership from Laravel or
another package. It could also diverge from Moduark's Generator Registry and
Generation Plan.

ADR-0032 therefore rejected native command injection for the original
Generation Foundation. Before reconsidering mutation, `1.3` needs a read-only
capability and collision contract that can prove the current application is
safe to decorate and that disabled applications remain untouched.

## Decision

- Add the Preview configuration key
  `moduark.generation.native_bridge`, defaulting to `false` and accepting only a
  boolean.
- Add the read-only command
  `moduark:native-bridge [--format=text|json]`. It never decorates, replaces, or
  invokes a Laravel Maker in LC2-A, including when opt-in is `true`.
- Derive the 31 bridge candidates from the built-in `ModuleMakerType` cases.
  Third-party generator registrations do not gain a native command implicitly.
- Map every candidate to the exact Laravel-owned command class frozen by the
  reviewed Laravel 12 and 13 Maker inventories.
- A candidate is ready only when the command exists, its concrete class is the
  reviewed Laravel owner, it has exactly one required non-array `name`
  argument, and it does not already define `--module`.
- Fail closed with stable diagnostic identities:
  - `MOD-NATIVE-BRIDGE-COMMAND-001` for a missing command;
  - `MOD-NATIVE-BRIDGE-OWNER-001` for a replaced command class;
  - `MOD-NATIVE-BRIDGE-SIGNATURE-001` for an incompatible `name` argument;
  - `MOD-NATIVE-BRIDGE-OPTION-001` when `--module` already exists.
- JSON schema version `1` reports `disabled`, `planned`, or `blocked`, explicit
  `opt_in` and `mutation=false`, aggregate counts, and every ordered command
  candidate with its expected and actual owner plus diagnostics.
- With opt-in disabled, diagnostics remain visible but the command exits `0`:
  no bridge was requested and no application behavior is affected. With opt-in
  enabled, a complete collision-free plan exits `0`; any collision uses
  `blocked` and exit `1`. Invalid output format is a tool error with exit `2`.
- A later slice must reuse this plan and the existing Generator Registry /
  Generation Plan if it introduces command mutation. LC2-A does not authorize
  that mutation.

## Acceptance evidence

- One unit contract maps all 31 built-in generator IDs to both reviewed Laravel
  12 and Laravel 13 Maker inventory fixtures.
- Testbench proves that Orchestra Canvas replacements are reported as ownership
  collisions and that a pre-existing `--module` option has its own stable code.
- The disabled command snapshot proves no native command class or option changes
  before and after planning.
- Laravel 12 and 13 clean installations require all 31 candidates to be ready,
  then set the opt-in and require `status=planned` while `make:model` help remains
  byte-for-byte unchanged and contains no `--module` option.
- Matching nwidart 12 and 13 fixtures require the same 31-candidate disabled plan
  and confirm that neither Laravel nor nwidart command ownership changes.

## Consequences

- Applications can assess bridge feasibility before any command decoration is
  shipped or enabled.
- Strict concrete-class ownership may reject a compatible decorator. This is a
  deliberate safety boundary; compatibility must be reviewed and represented
  explicitly rather than inferred from inheritance or matching options.
- Setting `native_bridge=true` in LC2-A records opt-in intent and changes plan
  status only. Developers must continue using `moduark:make` until a later
  mutation slice is accepted.
- ADR-0032 remains authoritative for actual Maker execution in the current
  release. This ADR supersedes only its assumption that no native-bridge
  planning surface should exist.
