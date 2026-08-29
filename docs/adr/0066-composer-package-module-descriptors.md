# ADR-0066: Composer Package Module Descriptor Contract

- Status: Accepted
- Date: 2026-08-29

## Context

ADR-0065 made an exported Module package independently executable, but its
portable provider owns a one-Module runtime outside the host canonical registry.
The host needs a deterministic inventory of installed package Modules before it
can merge them into discovery, activation, analysis, graph, cache, lifecycle,
Capabilities, and resources. Laravel package provider order is not a safe
discovery protocol because Moduark currently resolves its registry during
service-provider registration.

## Decision

- Exported packages declare `extra.moduark.schema_version=1` and a non-empty
  `modules` list in `composer.json`.
- Each descriptor contains the Module name, fully qualified Module class, and a
  portable package-relative PHP source path. Package identity and install root
  come from Composer's installed-package manifest rather than user configuration.
- `ComposerPackageModuleDiscoverer` reads `vendor/composer/installed.json`
  directly. It accepts Composer's object or legacy list envelope, ignores
  packages without Moduark metadata, and fails closed on malformed declared
  metadata or an unknown schema.
- Discovery verifies the package install root, rejects traversal or absolute
  descriptor paths, requires an autoloadable concrete `Module`, and confirms
  reflection resolves to the declared installed source.
- `PackageModuleCatalog` sorts descriptors by package and Module identity,
  exposes scalar schema-versioned output, and derives a stable SHA-256
  fingerprint that does not contain machine-specific install roots.
- Duplicate package Module names or classes are rejected case-insensitively
  before a catalog can be consumed.

## Boundaries

This decision establishes inventory only. The coordinated canonical-registry
adoption, package-aware cache validity, immutable installed-package activation,
and portable-provider delegation are specified by ADR-0067.

## Consequences

Package Module discovery no longer depends on Laravel provider ordering and can
be reproduced before lifecycle registration. ADR-0067 consumes the catalog and
its fingerprint while reusing the existing duplicate-identity and lifecycle
contracts.
