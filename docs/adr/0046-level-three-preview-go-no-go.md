# ADR-0046: Keep Level 3 Preview at the 1.0 Boundary

- Status: Accepted for the `1.0.0` candidate
- Date: 2026-08-22

## Context

[ADR-0043](0043-real-project-beta-adoption.md) reviewed 1,511 PHP files from two
complete Laravel applications and hardened table, Schema, command-discovery,
and dependency evidence. Neither application declared Moduark Capabilities or
contained an inline transaction scope recognized by the analyzer. Their
technical-directory projections also could not provide real Module table
ownership, migration placement, explicit exports, or Capability metadata.

The `1.0.0` stability review therefore needs a full-preset integration fixture
that closes those executable coverage gaps. It must not be treated as
independent brownfield adoption evidence: a fixture designed around the known
contract can prove regressions, but cannot establish the false-positive rate of
unknown application structures.

## Public Adoption Fixture

The committed `DiverseLevelThree` fixture models three business Modules:

- Customer and Payment each provide a Capability and export one provider-owned
  Contract;
- Order declares both dependencies and consumes the Capabilities through two
  Order-owned Ports and provider-scoped Adapters;
- the Modules declare five owned tables and keep five Schema mutations in their
  own `Database/Migrations/` directories;
- four foreign-key references cover same-owner, cross-Module, and model-based
  unresolved targets;
- two inline transaction scopes cover two resolved same-owner writes and one
  dynamic unresolved write;
- local Eloquent Models, owned table queries, and explicit exports exercise the
  remaining Level 3 boundary without introducing a deliberate blocking error.

The fixture's complete Level 3 preset runs all fourteen rules with no
unavailable rule and no error. It exits `0` while retaining exactly four
reviewable warnings:

- `MOD-TABLE-003` for the dynamic table access;
- `MOD-FK-001` for the intentional cross-Module `orders -> customers` foreign
  key;
- `MOD-FK-002` for model-based target-table inference that remains a runtime
  decision;
- `MOD-TRANSACTION-002` for the dynamic write inside an inline transaction.

The same acceptance test asserts the source inventory independently of rule
output: three table accesses, five Schema mutations, four foreign-key
references, two transaction scopes, and two runtime Capability bindings. The
bound workflow resolves and executes through both consumer-owned Ports.

## Observation

No supported-syntax false positive or false negative was observed in this
fixture. The resolved same-owner migrations, foreign keys, table writes, local
Models, Capability contracts, Adapters, and explicit exports produce no
blocking diagnostic. The intentionally dynamic cases remain visible warnings,
and the cross-Module foreign key remains advisory as documented.

No analyzer implementation or diagnostic semantics are changed by this review.
Changing a rule merely to make the fixture pass would invalidate the evidence;
hardening remains evidence-driven.

## Decision

Level 3 remains **Preview** in `1.0.0`. This is a no-go for promotion to Stable,
not a rejection of the current implementation. The fixture closes a combined
regression gap, but the missing evidence is independent application diversity:

- both real-project corpora still lack actual Moduark Capability and ownership
  metadata;
- no independently developed adoption has exercised inline transaction policy
  together with real repository or orchestration patterns;
- raw SQL, repository writes, macros, callback data flow, connection mapping,
  table prefixes, and runtime Eloquent table decisions remain explicit limits;
- advisory foreign-key and transaction findings need more team-level review
  experience before their policy can be described as broadly stable.

Promotion requires reviewed aggregate evidence from at least two independently
maintained Laravel applications using actual Moduark Level 3 metadata. Across
that evidence, the applications must exercise Capability wiring, owned Module
migrations, resolved and unresolved foreign keys, and inline transaction
orchestration. Reports must distinguish correct diagnostics, false positives,
false negatives, and documented unknowns without committing private source.

## Consequences

- Levels 0 through 2 remain the Stable `1.0.0` contract and Level 2 remains the
  recommended default target for modular applications.
- Level 3 machine identities, exit semantics, and documented Preview policy
  remain versioned; detection breadth may expand only under the minor-release
  and changelog rules in `docs/stability.md`.
- The new fixture becomes a blocking regression gate for the combined fourteen
  rules, source collectors, metadata compiler, and runtime Capability binding.
- Future adoption may harden observed behavior, but promotion cannot be based
  only on this repository's own curated fixtures.
