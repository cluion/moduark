# ADR-0068: Package Dependency Contract

- Status: Accepted; package-set planning added by ADR-0069
- Date: 2026-08-29

## Context

ADR-0064 deliberately left Module dependencies as manual export blockers because
an application Module class does not reveal a reviewed Composer package,
compatible version range, or destination namespace. ADR-0065 can rewrite the
selected Module namespace, and ADR-0067 can merge installed packages into one
canonical runtime, but neither contract makes an exported package dependency
installable.

## Decision

- `moduark:export` accepts a repeatable explicit mapping:
  `--dependency='Module=vendor/package:constraint=>Namespace'`.
- The Module identity is resolved case-insensitively through the canonical active
  registry and must be a dependency declared by the selected Module. The plan
  records its canonical name and class.
- Package names must be lowercase Composer `vendor/name` identities. Constraints
  are parsed by `composer/semver`; namespaces must be explicit valid PHP
  namespaces.
- Each declared Module can be mapped once. A mapping cannot require the package
  being exported or replace generated `cluion/moduark` and
  `illuminate/support` runtime requirements. Mappings that target one package
  must agree on its constraint.
- A resolved mapping produces a deterministic generated Composer requirement and
  rewrites references from the dependency's application namespace to its package
  namespace in copied PHP files.
- Unmapped Module dependencies remain `manual` and preserve
  `MOD-EXPORT-DEPENDENCY-001`. Invalid mappings are input errors with exit `2`.
- The export plan advances to schema version `2`; each dependency row includes a
  nullable `namespace` field.

## Verification

A permanent clean-install fixture exports `User` and an `Order` Module that
declares `User`. The root application requires only `acme/order-module`; Composer
must install `acme/user-module` transitively through the generated requirement.
Laravel 12 and 13 then verify both Modules in the canonical registry, dependency
order, analysis owners, the exact `Order` to `User` graph edge, resource active
set, package catalog, provider loading, and the existing User runtime resources.

## Boundaries

The single-package command does not infer package names, version constraints, or
namespaces from imports, descriptors, repositories, Packagist, or
installed-package state. It does not invoke Composer, export a dependency
closure, coordinate package versions, publish packages, or prove that the mapped
constraint exists in a remote repository. The operator remains responsible for
exporting and releasing each dependency package under the declared contract.
ADR-0069 adds read-only dependency-closed set planning without changing these
installation or publication boundaries.

## Consequences

Package dependency metadata becomes reviewable and reproducible without turning
source-code guesses into public version contracts. A package set still requires
one explicit identity and target per selected Module and an external Composer
repository containing compatible releases.
