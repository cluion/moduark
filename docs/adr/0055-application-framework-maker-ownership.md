# ADR-0055: Application and Framework Maker Ownership

- Status: Accepted
- Date: 2026-08-23

## Context

The Laravel 12 / 13 inventory contains 31 native Maker commands with a `name`
argument. The initial Generation slices registered 28 Module-owned types and
left `make:command`, `make:config`, and `make:provider` unresolved. Treating the
roadmap's approximate count as complete would leave a real release-scope gap.

The three native commands do not share the same safety profile. Console command
generation can be qualified into a Module before native execution. Config
generation hard-codes Laravel's application config directory. Provider
generation also edits `bootstrap/providers.php`, a side effect outside any
single Module and outside the declared Generation Plan.

## Decision

- Register `command`, `config`, and `provider` as built-in descriptors, bringing
  the canonical registry to exactly 31 IDs.
- Generate commands as direct `Console/Commands/*.php` classes through
  Laravel's native Maker. Allow `--force`, a validated lowercase `--command`
  name, and Module-owned matching tests. Reject nested command classes because
  the current runtime command discovery contract is deliberately non-recursive.
- Generate config files from a reviewed Moduark template below `config/` in the
  selected Module. Config names use portable lowercase path segments. This
  creates an owned artifact but does not imply runtime loading, merging, or
  publishing before the 1.2 Resource Plugin contract exists.
- Generate providers from a reviewed Moduark template below `Providers/` in the
  selected Module. Never invoke Laravel's provider Maker, and never mutate the
  application bootstrap provider list. Activation remains an explicit entry in
  the Module's `providers()` metadata.
- Route all three descriptors through the same complete plan, collision
  preflight, text/JSON dry run, overwrite intent, and rollback-capable executor
  used by the other built-in generators.

## Acceptance

- Separate Laravel 12 and 13 fixtures lock command, config, provider, and
  command matching-test target paths.
- Feature tests prove exactly 31 sorted built-in IDs, native command semantics,
  runtime command discovery, valid generated PHP, safe config paths, provider
  inheritance, dry-run zero mutation, collision refusal, force overwrite, and
  descriptor-specific option rejection.
- Clean Laravel 12 / 13 applications generate all three targets after dry runs,
  execute the generated command, and retain unchanged application config and
  `bootstrap/providers.php` state.
- nwidart Laravel 12 / 13 applications retain independent command ownership and
  expose the three types plus `--command` in Moduark help.

## Consequences

Generation Foundation now covers every reviewed name-based Laravel Maker
candidate. Runtime config loading and provider activation remain explicit and
honest rather than being smuggled into generation as undeclared global writes.
ADR-0056 completes the 1.2 runtime contract. Nested commands are now supported
when the Module explicitly opts into recursive discovery; the Maker's nested
name restriction remains the compatible 1.1 generation contract.
