# ADR-0001: Laravel Package Baseline

- Status: Accepted for Phase 0
- Date: 2026-08-15

## Context

Moduark must support Laravel 12 and 13 without depending on the full framework
at runtime. Its package tests still need a real Laravel application
lifecycle, package discovery, cache commands, and a workbench.

Laravel 11 reached the end of security support on 2026-03-12. During this PoC,
Composer 2.10 rejected both the lowest and highest Laravel 11/Testbench 9
dependency resolutions because every matching framework version was affected
by active security advisories.

## Decision

- Production requires PHP `^8.2` and only the Illuminate contracts/components
  imported by package source.
- Composer package discovery registers `ModuarkServiceProvider`.
- Testbench 10 and 11 cover Laravel 12 and 13 respectively.
- CI selects an explicit Laravel/Testbench pair for each job. Branch names are
  not treated as evidence that the lowest dependency combination resolves.
- Each Laravel major has a lowest and highest dependency job.
- Laravel 13 jobs start at PHP 8.3; the package source remains PHP 8.2 syntax.
- Laravel 11 is outside the beta support contract. CI must not disable
  Composer's insecure-package blocking to create a nominal compatibility job.
- `cluion/moduark` and the `proprietary` license marker are provisional until
  the package name, vendor, and public license are formally approved.

## Consequences

- The local PHP 8.5 environment can validate the Laravel 13/highest path.
- Laravel 12 and lower PHP combinations are CI evidence, not assumed local
  evidence.
- A release cannot claim compatibility until all four jobs resolve and pass.
