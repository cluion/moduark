# Stability and Versioning

This document defines the candidate compatibility contract for Moduark
`1.0.0`. It becomes the stable-release policy when `1.0.0` is published. The
current beta line remains pre-stable and may still make documented breaking
changes before that release.

## Contract Categories

### Stable

Stable surfaces are supported throughout the `1.x` release line. Removing or
incompatibly changing one requires a major release after a documented
deprecation period, except when an urgent security response makes that unsafe.

The Stable surface consists of:

- the supported PHP application extension points listed below;
- Laravel package discovery and the documented configuration keys;
- the Level 0 Organization, Level 1 Modular, and Level 2 Decoupled presets;
- documented Artisan command names, arguments, options, and exit codes;
- versioned JSON reports, architecture baselines, suppression manifests, rule
  IDs, and diagnostic identities.

### Preview

Level 3 Isolated is Preview in `1.0.0`. Its policy, rule IDs, severities,
diagnostic identities, and machine-readable shapes remain governed by the
contracts below, but the static detection breadth may expand in a documented
`1.x` minor release as real Laravel applications provide more evidence.

Level 3 remains opt-in. A patch release will not silently add blocking preset
scope, and unsupported dynamic behavior remains unknown instead of being
guessed.

The `1.0.0` go/no-go review keeps Level 3 in Preview. A complete public fixture
now exercises Capability wiring, owned migrations, resolved and unresolved
foreign keys, inline transactions, and all fourteen rules without blocking
false positives, but a curated fixture is not independent brownfield adoption
evidence. See [ADR-0046](adr/0046-level-three-preview-go-no-go.md) for the
remaining promotion gate.

### Internal

Anything not listed as Stable or Preview is Internal even when PHP visibility
allows direct access. In particular, descriptor compilation, capability
resolver and binding-plan objects, analyzer orchestration, caches, and
human-output formatting are implementation details.

Internal APIs may change without deprecation. Application code should declare
Module metadata and resolve its consumer-owned Ports through Laravel's
container, not construct Moduark lifecycle internals directly.

## Supported PHP Extension Points

The following PHP types are supported application-facing contracts:

- `Cluion\Moduark\Module`, including `dependencies()`, `providers()`,
  `requires()`, `provides()`, `tables()`, and `exports()`;
- `Cluion\Moduark\Capability` as a marker interface for typed Capability
  identities;
- `Cluion\Moduark\CapabilityRequirement`, including its constructor,
  `capability()`, `port()`, `adapter()`, `fromArray()`, and `toArray()`;
- automatic Laravel discovery of
  `Cluion\Moduark\ModuarkServiceProvider`.

New optional metadata methods may be added in a minor release with a
backward-compatible default. Existing method meaning, accepted metadata shape,
or required implementation behavior will not be changed incompatibly within
`1.x`.

Other namespaces are not application extension APIs unless this document or a
later stability document names them explicitly. PHP `public` visibility alone
does not promote an implementation type to Stable.

## Configuration and Architecture Presets

These configuration identities are Stable:

- `modules.path`;
- `modules.architecture.level`;
- `modules.architecture.baseline`;
- `modules.architecture.suppressions`;
- `modules.architecture.rules`.

Level numbers and labels remain `0` Organization, `1` Modular, `2` Decoupled,
and `3` Isolated. Published rule IDs remain stable machine identities. Level 0
through Level 2 preset membership and severities will not be broadened or made
stricter within `1.x`; a new rule may be introduced disabled by default.

Explicit rule overrides continue to take precedence over a selected preset.
See [Architecture Levels](architecture-levels.md) for the complete matrix and
rule semantics.

## CLI Contract

The following commands and their documented arguments and options are Stable:

```text
make:module {name}
module:make {module} {type} {name} [--force] [--invokable] [--resource] [--api]
module:list
module:inspect {module}
module:graph [{module}] [--view=module] [--format=text]
module:check [--level=] [--format=text] [--show-suppressions]
module:baseline [--level=] [--force] [--prune]
module:cache
module:clear
```

Architecture checks use these process exit codes:

- `0`: analysis completed without blocking violations; warnings may still be
  present;
- `1`: analysis completed and found blocking violations;
- `2`: the tool or analyzer could not produce a complete architecture result.

Human-readable text and Mermaid graph layout may be clarified or reformatted in
a minor or patch release. Automation should use JSON, exit codes, and stable
identities instead of matching prose.

## Machine-Readable and Persistent Contracts

`module:check --format=json` schema version `1` has these required top-level
fields: `schema_version`, `status`, `complete`, `exit_code`, `architecture`,
`summary`, `suppressions`, `baseline`, `unavailable_rules`, `results`, and
`error`. The status vocabulary is `passed`, `violations_found`, and
`incomplete`.

Architecture baseline schema version `1` and suppression manifest schema
version `1` are persistent application files. Existing required fields and
their meaning remain compatible within their schema version. A minor release
may add fields that older consumers can ignore. Removing a field or changing
its meaning requires a new schema version plus upgrade guidance.

Published rule IDs and `MOD-*` diagnostic codes are stable identities for
baselines, suppressions, CI, and reporting. Fixing a false positive may remove
an incorrect finding without retaining that finding artificially. Reusing an
existing identity for unrelated semantics is not compatible.

Module metadata and source-analysis caches are explicitly excluded from this
contract. They are rebuildable and may receive a new internal schema or be
invalidated by any release. Do not edit or consume them as application data.

## Deprecation Policy

This policy applies to Stable surfaces. A replacement must ship in at least one
released `1.x` minor before the old surface can be removed in the next major
release. Every deprecation must:

- name the supported replacement and its first available version;
- be recorded in the changelog and [Upgrading Moduark](../UPGRADING.md);
- keep the old and replacement paths under compatibility tests during the
  deprecation window;
- avoid silently changing application configuration, architecture debt, or
  machine-readable files.

Deprecated PHP APIs receive an `@deprecated` annotation with a replacement
reference. A runtime `E_USER_DEPRECATED` warning is optional because boot and
analysis hot paths must not gain uncontrolled CI noise. Config keys and CLI
inputs keep their documented behavior throughout `1.x`; a warning cannot alter
the established exit-code semantics.

An incompatible persistent-schema change requires an explicit new schema
version. The old form remains readable for at least one released minor when a
safe dual reader is possible, and no migration may silently rewrite a baseline
or suppression file. Diagnostic codes are never repurposed: a replacement uses
a new identity plus an upgrade mapping.

Internal APIs do not receive this window. Preview Level 3 detection breadth may
expand under its minor-release policy, but its existing machine identities are
not repurposed. An urgent security response may shorten a compatibility window;
the release must document the reason, impact, and safe replacement.

## Release Compatibility

The package's `composer.json` and CI matrix are the source of truth for
supported PHP and Laravel versions. Dropping a supported runtime or framework
line is a breaking change for the stable line unless continued support is
impossible because of an upstream security requirement.

Patch releases contain compatible fixes, including false-positive corrections,
without expanding a preset silently. Minor releases may add optional APIs,
commands, disabled rules, and additive schema fields. Preview Level 3 detection
coverage may also expand in a minor release when documented in the changelog.

Published security support and the verified private reporting channel are
defined in the [Security Policy](../SECURITY.md). The policy distinguishes
security-impacting boundary bypasses from ordinary analyzer false positives.

Maintainer release stages, RC requirements, exact-commit CI, Packagist
visibility, and published-dist acceptance are defined in the
[Release Policy](releases.md). A local pass, tag, GitHub Release, registry
version, and verified public installation are separate evidence states.

See [ADR-0045](adr/0045-stable-contract-boundary.md) for the decision and
acceptance criteria behind this boundary.
