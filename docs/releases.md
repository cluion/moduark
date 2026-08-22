# Release Policy

This policy is for Moduark maintainers preparing a release candidate or stable
release. It does not authorize a push, tag, GitHub Release, Packagist action, or
other publication. Each mutating external stage requires separate explicit
authorization and must be verified before the next stage begins.

## Current Distribution Model

Moduark currently has one GitHub Actions workflow, `tests`. Existing public
versions use annotated Git tags, GitHub Releases, and Packagist dist archives
that resolve to the tagged GitHub commit. No repository release workflow
currently publishes custom assets.

Do not claim checksums, SBOMs, provenance, or attestations unless a future
release workflow actually creates and verifies them. GitHub-generated source
archives and the Packagist dist are the current public artifacts. Re-inventory
`.github/workflows/` and the previous public release before every release in
case this model changes.

## Release States

These states are separate evidence boundaries:

| State | Required evidence |
|---|---|
| Local validation | Clean release commit and every local gate below passes |
| Exact-commit CI | The `tests` workflow succeeds for the full release commit SHA |
| Annotated tag | A new immutable annotated tag resolves to that exact SHA |
| GitHub Release | A reviewed Release exists for the verified tag |
| Packagist visibility | The exact version and dist reference are visible in Composer metadata |
| Published-dist acceptance | Fresh Laravel 12 and 13 applications pass against that exact Packagist version |

A local pass is not an exact-commit CI result. A pushed tag is not a GitHub
Release. A GitHub Release does not prove Packagist visibility, and registry
visibility does not prove that the published dist installs correctly.

Record the version, full commit SHA, CI run URL, tag object and peeled commit,
GitHub Release URL, Packagist source/dist references, and published-install
results in the release evidence.

## Release Candidate Requirement

At least one `1.0.0-rc.*` release is required before `1.0.0`. The RC freezes the
candidate Stable surfaces in `docs/stability.md`; Level 3 remains Preview and
must be described that way in release notes. A stable release reruns every gate
against its own preparation commit and tag even when no runtime code changed
after the final RC.

Use SemVer without the Git `v` prefix for Composer and installation commands:

```bash
export MODUARK_RELEASE_VERSION=1.0.0-rc.1
export MODUARK_RELEASE_TAG="v${MODUARK_RELEASE_VERSION}"
```

Replace these example values for later RCs and the stable release. Never infer
the release version from an unreviewed branch name.

## Stage 0: Prepare the Release Commit

Start from `main`, refresh remote references, and confirm that there is no
unexplained divergence before editing release files:

```bash
git fetch --tags origin
git status --short
git branch --show-current
git rev-list --left-right --count origin/main...HEAD
git tag --list "${MODUARK_RELEASE_TAG}"
git ls-remote --exit-code origin "refs/tags/${MODUARK_RELEASE_TAG}"
```

The expected branch is `main`, `git status --short` is empty, and the candidate
tag does not exist locally or remotely. Review any ahead/behind count before
continuing; do not discard or overwrite local or remote commits. A non-zero
`git ls-remote --exit-code` result is expected only when proving that a new tag
is absent.

Before committing the release preparation:

- move reviewed entries from `Unreleased` into a dated version section in
  `CHANGELOG.md`;
- update README installation constraints and current-scope wording;
- update `SECURITY.md` so its supported-version table names the RC or stable
  line actually being published;
- remove the unpublished warning from `UPGRADING.md` only for the stable
  release, and keep the beta-to-1.0 migration procedure accurate;
- confirm Levels 0 through 2 are Stable and Level 3 is Preview everywhere;
- verify that optional companion-package instructions match its published
  Composer constraint and configuration default; otherwise document the
  incompatibility instead of recommending an impossible installation;
- prepare reviewed GitHub Release notes with requirements, upgrade notes,
  limitations, and a full changelog link.

Inventory version-sensitive text instead of replacing every historical version
reference mechanically:

```bash
rg -n "0\.5\.0-beta\.1|1\.0\.0|Unreleased" \
  README.md SECURITY.md UPGRADING.md CHANGELOG.md docs resources
```

Commit the reviewed release preparation separately, then require a clean
worktree and capture its full SHA for all following evidence:

```bash
git status --short
export MODUARK_RELEASE_SHA="$(git rev-parse HEAD)"
git show --no-patch --format=fuller "${MODUARK_RELEASE_SHA}"
```

## Stage 1: Local Validation

Run every gate from the release preparation commit:

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
composer audit --locked
composer verify
composer test:dependencies
composer test:distribution
composer test:installation
composer test:installation -- --boost
composer test:interop
```

`composer test:dependencies` resolves all four Laravel 12 / 13 lowest and
highest dependency cases. It does not execute those four runtime combinations;
the blocking GitHub Actions matrix does. The two installation commands run the
current checkout on Laravel 12 and 13, first without and then with Laravel
Boost Skill synchronization.

`composer test:interop` creates a fresh Laravel 13 application with
`nwidart/laravel-modules`, installs the current checkout, and verifies that the
two packages retain independent command/configuration namespaces while sharing
the nwidart Module root safely.

The full suite must include passing documentation-link, public-contract,
repository-policy, upgrade-policy, Boost Skill, and Level 3 go/no-go tests. Run
their focused forms when diagnosing a failure, but do not use a focused pass as
a substitute for `composer verify`.

Do not waive an error, incomplete architecture result, failed compatibility
case, security advisory, installation failure, or archive mismatch. A known
warning must be recorded and shown to be unrelated to release behavior.

## Stage 2: Push and Verify Exact-commit CI

Pushing the preparation commit requires explicit authorization. After the push,
refresh `origin/main`, require it to resolve to the full release SHA, and
identify that commit's workflow rather than relying on a green badge or the
latest run on another commit:

```bash
git fetch origin main
git rev-parse origin/main
git rev-list --left-right --count origin/main...HEAD
gh run list --repo cluion/moduark --workflow=tests.yml \
  --commit "${MODUARK_RELEASE_SHA}" \
  --json databaseId,headSha,status,conclusion,url
gh run view <run-id> --repo cluion/moduark
```

`git rev-parse origin/main` must equal `MODUARK_RELEASE_SHA`, and the
ahead/behind counts must be `0 0`. The exact SHA must pass all four compatibility
jobs and static analysis. The highest-dependency Laravel 12 and 13 jobs must
also pass clean installation. Do not create a tag while the run is queued, in
progress, failed, cancelled, or for a different SHA.

## Stage 3: Create and Verify the Annotated Tag

Creating and pushing a tag is a separate authorized action. Recheck that the
name is absent, then bind it to the already verified full SHA:

```bash
git tag -a "${MODUARK_RELEASE_TAG}" "${MODUARK_RELEASE_SHA}" \
  -m "Moduark ${MODUARK_RELEASE_TAG}"
git show --no-patch --format=fuller "${MODUARK_RELEASE_TAG}"
git rev-parse "${MODUARK_RELEASE_TAG}^{commit}"
git push origin "${MODUARK_RELEASE_TAG}"
```

Wait for the tag-triggered `tests` workflow and verify that it resolves to the
same commit. Do not move, replace, or delete a public tag. If a defect is found
after the tag is pushed, correct it in a new commit and publish the next RC or
patch version.

## Stage 4: Create and Verify the GitHub Release

Creating the GitHub Release requires another explicit authorization. Prepare
and review `/tmp/moduark-release-notes.md` before running either command.

For an RC:

```bash
gh release create "${MODUARK_RELEASE_TAG}" --repo cluion/moduark \
  --verify-tag --prerelease \
  --title "Moduark ${MODUARK_RELEASE_VERSION}" \
  --notes-file /tmp/moduark-release-notes.md
```

For a stable release, omit `--prerelease`. Verify the public state and the tag's
peeled commit:

```bash
gh release view "${MODUARK_RELEASE_TAG}" --repo cluion/moduark \
  --json tagName,name,isDraft,isPrerelease,publishedAt,url,assets
git ls-remote origin \
  "refs/tags/${MODUARK_RELEASE_TAG}" \
  "refs/tags/${MODUARK_RELEASE_TAG}^{}"
```

Do not upload placeholder assets or claim artifacts that this repository does
not build. Release notes must distinguish current guarantees from planned work
and must link the exact changelog comparison.

## Stage 5: Verify Packagist Visibility

GitHub publication does not prove that Packagist has indexed the version. Wait
until Composer exposes the exact version, then inspect its source and dist
references:

```bash
composer show cluion/moduark "${MODUARK_RELEASE_VERSION}" \
  --all --format=json
```

Both references must equal `MODUARK_RELEASE_SHA`. If the version is missing or
points elsewhere, stop and inspect the GitHub/Packagist integration. Do not
recreate the tag or repeat publication blindly. Any manual Packagist mutation
requires separate authorization.

## Stage 6: Verify the Published Dist

Run the networked acceptance matrix against the exact Packagist version, not a
path repository or `dev-main`:

```bash
composer test:installation -- --package="${MODUARK_RELEASE_VERSION}"
composer test:installation -- --package="${MODUARK_RELEASE_VERSION}" --boost
composer test:interop -- --package="${MODUARK_RELEASE_VERSION}"
```

Laravel 12 and 13 must pass the clean installation matrix, and Laravel 13 with
`nwidart/laravel-modules` must pass the interoperability fixture. Together they
cover package discovery, command behavior and ownership, archive layout,
configuration and Module caches, machine output, baseline/suppression audit,
optimization behavior, and Laravel Boost Skill synchronization. Confirm the
installed archive contains public policy and Skill files while excluding
repository-only tests, tools, workbench files, and automation.

Only after this stage may the release be described as publicly verified.

## Failure and Recovery

- Before a tag is pushed, fix the release commit and rerun local plus
  exact-commit CI gates.
- After a tag is public, never repoint it. Publish a new RC or patch from a new
  reviewed commit.
- If GitHub is public but Packagist is delayed, investigate indexing without
  changing the tag.
- If the published dist fails, document the affected version immediately and
  prepare a corrected release; do not describe the failed version as verified.
- Security incidents follow `SECURITY.md` and may shorten the normal window,
  but still require exact affected-version and mitigation evidence.
