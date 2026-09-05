# ADR-0072: Native Generator Bridge Mutation

- Status: Accepted for the unreleased `1.3` LC2-B slice
- Date: 2026-08-30

## Context

ADR-0071 established a read-only inventory for 31 reviewed Laravel 12 and 13
Maker commands. LC2-B must add the requested `make:* --module` syntax without
creating a second Module generation implementation, changing default Laravel
behavior, or partially decorating the application when a command owner drifts.

Laravel registers attributed commands through a lazy command-owner map and
installs its container command loader only after Artisan starting callbacks.
Consequently, inspecting `Application::all()` during those callbacks is too
early for an atomic ownership decision.

## Decision

- Keep `moduark.generation.native_bridge=false` as the zero-mutation default.
- At application boot completion, resolve and validate the exact 31 reviewed
  Laravel owners, clone their native definitions, and prepare decorators without
  registering them.
- At the final Artisan starting callback, inspect Laravel's command-owner map.
  Treat the map as a reviewed compatibility seam: if it is unavailable or any
  owner differs, activate no decorators and report a stable registration or
  ownership diagnostic.
- Register all 31 prepared decorators eagerly and atomically. If any add or
  verification fails, restore every original command and report
  `MOD-NATIVE-BRIDGE-REGISTER-001`.
- Preserve the original command object. When raw input does not contain
  `--module`, delegate the exact input and output to it after attaching the same
  Symfony and Laravel application context.
- When `--module` is present, translate only reviewed native options and call
  `moduark:make` through Laravel's Console Kernel. This reuses the canonical
  Generator Registry, Generation Plan, preflight, and rollback executor.
- Reject any explicitly supplied native option that has no reviewed Moduark
  mapping with exit `2` before calling the generator.
- Advance the report to schema version `2`, add `active` candidate and plan
  status, `summary.active`, actual mutation state, registration failure, and
  activated-decoration drift diagnostics.

## Acceptance evidence

- Unit tests prove exact no-module delegation, `moduark:make` reuse,
  unsupported-option refusal, and full rollback after an injected partial add.
- Feature tests preserve disabled zero-mutation behavior and stable collision
  diagnostics.
- Laravel 12 and 13 clean applications require 31 active decorators, visible
  `--module` help, successful Module-owned generation, unchanged unqualified
  native generation, unsupported-option zero-write behavior, and configuration
  cache survival.
- The same installation matrix continues through package export, installed
  package runtime, architecture, graph, cache, provider, Capability, and
  resource acceptance after the bridge probes are removed.

## Consequences

- Native syntax is a thin opt-in adapter over Moduark generation, not a second
  implementation.
- Exact Laravel owner-map access is intentionally fail-closed and covered by the
  supported-major clean-install matrix. A Laravel internal change blocks the
  Preview bridge instead of guessing compatibility.
- Third-party Generator Registry extensions remain available through
  `moduark:make`; they do not receive an implicit native command.
- Composite Laravel Maker switches not represented by one Moduark plan, such as
  `make:model --all`, are rejected instead of partially emulated.
