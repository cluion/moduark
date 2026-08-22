# Diagnostics and Debt

Use this reference when interpreting `moduark:check`, investigating an analyzer
failure, or reviewing architecture baselines and suppressions.

## Prefer Machine-readable Output

Run the effective configured Level or an explicit probe:

```bash
php artisan moduark:check --format=json
php artisan moduark:check --level=2 --format=json
```

Read `status`, `complete`, `exit_code`, effective architecture configuration,
rule results, violations, unavailable rules, suppression audit, baseline audit,
and the error object together. Do not infer success from an empty violation list
when analysis is incomplete.

Interpret the process result exactly:

- exit `0`: no blocking violation; warnings may remain;
- exit `1`: at least one blocking violation;
- exit `2`: invalid input, unavailable rule, source-analysis failure, or another
  handled tool error; the result is incomplete.

Laravel bootstrap can fail before `moduark:check` renders JSON. In that case,
use Laravel's exception and process status as the evidence.

## Diagnose Before Editing

1. Confirm the effective Level and rule overrides.
2. Separate errors, warnings, incomplete rules, and dynamic-analysis limits.
3. Inspect the reported file, line, symbol, consumer, provider, table, or Module
   pair in application source.
4. Use `moduark:inspect` and graphs to confirm metadata context.
5. Read the installed package ADR or adoption section for the reported rule.
6. Repair the narrowest responsible code or metadata boundary and rerun both the
   focused application test and architecture check.

Moduark does not infer PHPDoc, raw SQL, arbitrary builder data flow, macros,
runtime Eloquent table selection, or other unsupported dynamic behavior. Keep
those cases reviewable instead of fabricating static ownership.

## Baseline Policy

A baseline adopts reviewed existing violations without disabling active rules.
It is not evidence that the debt is fixed.

- Show and review current unsuppressed violations first.
- Use `moduark:baseline --level=N` only after the user approves adopting that
  exact debt at that Level.
- Do not use `--force` unless replacing the complete existing baseline is the
  explicit requested outcome and the diff is reviewed.
- Use `moduark:baseline --prune` for safe stale-debt removal, then review the
  resulting file.
- Never create or update a baseline after exit `2` or incomplete analysis.

## Suppression Policy

A suppression represents one intentional reviewed exception. It requires a
stable rule and diagnostic code, a non-empty reason, and a narrow selector.

- Do not add global ignores or vague directory-wide selectors.
- Prefer the most stable identity supported by the diagnostic. Some rules
  require both consumer and target Modules before evidence can narrow further.
- Audit matched, stale, and inactive entries with
  `moduark:check --show-suppressions` and JSON output.
- Remove stale entries after review. Do not report inactive entries as verified.
- Suppressions apply before baselines; do not duplicate the same debt in both.

Read the installed `README.md`, `docs/adoption.md`, and changelog before editing
an older beta baseline or suppression. Diagnostic identity can require an
explicit migration during a beta upgrade.
