# ADR-0045: Stable Contract Boundary

- Status: Accepted for the `1.0.0` development line
- Date: 2026-08-22

## Context

Moduark's beta releases already expose Module metadata, architecture Levels,
rule identities, Artisan commands, versioned JSON reports, baselines,
suppressions, and Laravel package discovery. The implementation also contains
descriptor compilation, capability resolution plans, source-analysis caches,
and presentation helpers that application code does not need to call directly.

A stable release needs a precise boundary. Treating every public PHP class as a
supported extension point would freeze lifecycle internals accidentally. A
vague promise limited to Semantic Versioning would leave application and CI
authors unable to tell which config keys, command options, diagnostic
identities, and machine-readable files they can safely automate against.

Level 3 also has a different adoption profile from Levels 0 through 2. Its
Laravel-aware persistence analysis deliberately leaves dynamic behavior
unknown, and real-project evidence may justify broader static detection after
`1.0.0` without changing the underlying isolation policy.

## Decision

The candidate `1.0.0` contract is divided into three categories:

- **Stable:** the documented application extension points, package discovery,
  configuration identities, Level 0 through Level 2 presets, command names and
  documented options, exit codes, diagnostic identities, and versioned
  persistent or machine-readable schemas.
- **Preview:** the Level 3 preset and the detection breadth of its six
  isolation rules. Level 3 remains opt-in and may gain documented detection
  coverage in a `1.x` minor release.
- **Internal:** descriptor compilation, capability resolver and binding-plan
  objects, cache representations, analyzer orchestration, and human-oriented
  rendering details unless another public document explicitly promotes them.

The supported PHP application extension points are:

- `Cluion\Moduark\Module` and its metadata methods;
- `Cluion\Moduark\Capability` as a typed identity marker;
- `Cluion\Moduark\CapabilityRequirement`, including its constructor,
  accessors, `fromArray()`, and `toArray()`;
- Laravel package discovery through
  `Cluion\Moduark\ModuarkServiceProvider`.

The following identities are the candidate Stable contract for the `1.x` line.
ADR-0047 replaces the RC.1 command and configuration identities before the
stable release because real-package interoperability invalidated them:

- the `moduark.path` and `moduark.architecture.*` configuration keys;
- Levels `0` through `3`, all published rule IDs, and the Level 0 through Level
  2 preset membership and severities;
- documented Artisan command names, arguments, options, and exit codes `0`,
  `1`, and `2`;
- `moduark:check --format=json` schema version `1`, architecture baseline schema
  version `1`, suppression manifest schema version `1`, and published `MOD-*`
  diagnostic identities.

Additive machine-readable fields may be introduced in a minor release. Removing
or changing an existing required field requires a new schema version and a
documented migration path. Human-readable text and Mermaid layout are intended
for people and are not byte-stable contracts. Module and source-analysis caches
are rebuildable implementation details and can be invalidated between releases.

Level 3's Preview label does not make its output disposable: existing schema,
rule, severity, and diagnostic identities still follow the machine-contract
rules. Preview permits detection breadth to expand in a documented minor
release; it does not permit a patch release to silently add blocking preset
scope.

The complete user-facing policy is maintained in
[Stability and Versioning](../stability.md). A focused public-contract test
guards the executable portions of this decision.

## Acceptance

- Documentation distinguishes Stable, Preview, and Internal surfaces without
  claiming that the beta line is already stable.
- Application examples use Module metadata and Laravel's container instead of
  directly constructing internal capability lifecycle objects.
- Automated tests lock the supported PHP extension points, configuration keys,
  Level/rule matrix, command definitions, exit codes, and schema identities.
- The Composer archive contains the stability policy.
- Future compatibility or deprecation work references this boundary instead of
  inferring support from PHP visibility alone.

## Consequences

- Application and CI authors have a concrete automation boundary for `1.x`.
- Moduark can refactor discovery, compilation, analysis, resolution, caching,
  and rendering internals without treating every class move as a breaking
  change.
- Level 3 remains usable and machine-readable while further real-project
  hardening can land in documented minor releases.
- Promoting another surface to Stable requires documentation and executable
  contract coverage; removing one requires the later deprecation policy and a
  major release, except for an urgent security response.
- Candidate identities changed between RC.1 and RC.2 are documented as
  a pre-stable migration in `UPGRADING.md`; no stable `1.x` contract has been
  broken.
