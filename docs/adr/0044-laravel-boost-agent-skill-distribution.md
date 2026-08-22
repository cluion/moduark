# ADR-0044: Laravel Boost Agent Skill Distribution

- Status: Accepted for the `0.6.x` development line
- Date: 2026-08-22

## Context

Moduark's package CLI and documentation already define installation, staged
Level adoption, diagnostics, graphs, baselines, suppressions, and upgrades.
Coding agents can use those contracts, but without focused instructions they
may skip effective configuration, infer unsupported dynamic architecture, hide
violations with broad exceptions, or treat incomplete analysis as a pass.

Laravel Boost supports third-party package skills at
`resources/boost/skills/{skill-name}/SKILL.md` and installs discovered skills
for the coding agents selected by the application. Codex supports repository
skills below `.agents/skills/`. A separate Codex plugin could add another
discovery channel, but it would introduce an independent installation and
versioning surface before the package-native workflow has adoption evidence.

The distribution contract follows the official
[Laravel Boost documentation](https://laravel.com/docs/12.x/boost) and the
[OpenAI Agent Skills guidance](https://learn.chatgpt.com/docs/build-skills).

## Decision

- Ship the canonical skill source in this Composer package at
  `resources/boost/skills/moduark-development/`.
- Let Laravel Boost discover and install that source for consumer-selected
  agents. Moduark does not write directly to `.agents/skills/` or another
  consumer agent directory.
- Version the skill with `cluion/moduark`. The installed package's CLI and
  documentation remain authoritative for its supported behavior.
- Keep the skill cross-agent and dependency-free. Its frontmatter contains only
  `name` and `description`; focused Markdown references provide progressive
  disclosure. Add scripts only when a repeated deterministic task cannot be
  delegated safely to Moduark's existing machine-readable CLI.
- Do not add Laravel Boost as a Moduark runtime dependency. Applications that
  use Boost can discover the packaged resources; applications that do not use
  Boost receive inert documentation files only.
- Defer a standalone OpenAI/Codex plugin until Boost-native adoption shows a
  concrete discovery or installation gap. Plugin delivery is not a `0.6.x`
  acceptance requirement.
- Do not add `agents/openai.yaml` unless a later standalone Codex/plugin surface
  needs agent-specific display metadata.

## Safety contract

The skill must direct an agent to inspect the consumer's installed Moduark
version, Git state, effective configuration, existing Module metadata, and
current diagnostics before changing architecture. It must also preserve these
rules:

- `module:check` exit `0` may still contain non-blocking warnings that remain
  visible and reviewable;
- exit `1` means blocking architecture violations;
- exit `2`, `complete: false`, or `status: incomplete` is a tool/analyzer result,
  never an architecture pass;
- unsupported dynamic behavior stays unknown instead of being guessed;
- a baseline or suppression is an explicit reviewed debt decision, never an
  automatic way to make a change green.

## Acceptance

- The skill passes OpenAI's `quick_validate.py` structural validator.
- Repository tests verify the frontmatter, reference routing, safety language,
  and complete Composer archive inclusion.
- A pre-release archive contains the whole skill while development-only trees
  remain excluded.
- Forward tests install Moduark into fresh Laravel 12 and 13 applications with
  Laravel Boost, confirm the discovered skill layout for selected agents, and
  prove repeated installation is idempotent.
- Separate behavior reviews exercise representative Level adoption and
  diagnostic prompts before the `0.6.x` line is accepted.

The installation gate was exercised on 2026-08-22 against Laravel Framework
12.67.0 and 13.26.1 with Laravel Boost 2.5.5. In both disposable applications,
Boost discovered `moduark-development`, installed its complete source tree for
Codex at `.agents/skills/moduark-development/`, and produced identical
configuration and file hashes on a second skills-only installation.

An independent read-only behavior review on the same date exercised three
representative requests without providing the evaluators with intended answers:

- a Level 1 to Level 2 adoption request caused the evaluator to inspect Git,
  configuration, package context, Module metadata, graphs, and an explicit
  Level 2 JSON probe before proposing one bounded configuration change;
- a warning-only shard paired with an exit `2`, incomplete shard was not
  reported as an architecture pass or sufficient merge evidence;
- a request to blanket-baseline violations or add broad suppressions was
  refused until an exact Level-specific debt snapshot could be reviewed and
  explicitly approved.

All three cases preserved the intended contract, so no instruction was changed
solely to fit these prompts. The adoption case used the package Testbench
workbench and therefore proved correct decision-making around a small Level 2
probe, not a production application's dependency inversion. Fixture-backed
Port/Adapter generation, Level 3 repair, and upgrade behavior remain separate
forward-test coverage.

A second independent read-only review then closed those remaining behavior
gaps:

- a fraud-risk addition against the Large Level 2 fixture preserved the
  consumer-owned Checkout Port and provider-scoped Customer Adapter, reused the
  existing Capability only with an explicit cohesion trade-off, and did not
  expose Customer internals;
- a Level 3 repair request recognized that the supplied source was an
  intentionally violating analyzer fixture, proposed a production Port repair
  instead of rewriting test evidence, kept a resolved foreign-key warning
  visible, and refused to guess dynamic table ownership;
- an upgrade request did not treat `dev-main` as a published `0.6.x` release,
  invent a debt schema migration, or regenerate baselines and suppressions
  without an exact target version and changelog evidence.

These cases also required no prompt-specific instruction change. Together with
the installation matrix and repository contract tests, they satisfy the
`0.6.x` package-native Skill acceptance. No discovery or installation gap was
observed that would justify a separate Codex plugin at this stage.

## Consequences

- One Composer update keeps the Moduark runtime, CLI, docs, and agent guidance
  on the same version boundary.
- Boost users can install the skill without a second Moduark-specific plugin;
  non-Boost users incur no runtime behavior or dependency.
- Packaging, instructions, and Boost-native installation now have automated
  evidence. Real agent behavior and false-positive prompt evidence remain a
  separate forward-testing slice.
