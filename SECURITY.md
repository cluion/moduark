# Security Policy

## Supported Versions

Moduark is still pre-release software. Security support currently follows the
latest published pre-release only:

| Version | Security support |
|---|---|
| Latest `1.0` RC (`v1.0.0-rc.2`) | Supported |
| `v0.5.0-beta.1` and earlier | Not supported |
| Unreleased `main` and future versions | Not a published support line |

`1.0.0` stable has not been published. This table must be updated again as part
of the stable release gate before a stable `1.x` support window is claimed. The
package's published Composer constraints and compatibility matrix remain the
source of truth for supported PHP and Laravel versions.

## Report a Vulnerability Privately

Use [GitHub Private Vulnerability Reporting](https://github.com/cluion/moduark/security/advisories/new)
for a suspected vulnerability. Do not open a public GitHub issue with exploit
details, proof-of-concept code, secrets, or information that identifies a
private application.

Include as much of the following as can be shared safely:

- the affected Moduark, PHP, Laravel, and Composer versions;
- the affected command, configuration, or integration path;
- impact and the conditions required to trigger it;
- minimal reproduction steps or a reduced synthetic fixture;
- logs with credentials, tokens, private paths, and application data removed;
- a suggested mitigation or fix, when known.

Examples in scope include unauthorized code execution, unsafe file access or
overwrite, sensitive-data disclosure, command or CI injection, and a boundary
bypass with a concrete security impact. Ordinary bugs, architecture-policy
disagreements, performance problems, and analyzer false positives without a
security impact belong in a public issue after the reproduction has been
reduced so it contains no private source or data.

## Handling and Disclosure

Maintainers will validate the report, determine affected supported versions,
and coordinate a fix and advisory when appropriate. This project does not
promise a numeric response or remediation SLA. Reporter and maintainer should
keep technical details private until a fix or mitigation is available and a
coordinated disclosure date has been agreed, unless immediate disclosure is
required to protect users.

An urgent security fix may shorten the normal compatibility or deprecation
window. The release notes and advisory must explain the affected versions,
impact, mitigation, and any necessary breaking change.
