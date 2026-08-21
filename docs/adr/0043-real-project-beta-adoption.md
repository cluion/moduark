# ADR-0043: Real-project Beta Adoption and False-positive Hardening

- Status: Accepted for the `0.5.x` beta
- Date: 2026-08-22

## Context

Moduark's rule fixtures intentionally isolate syntax and policy cases, but they
cannot establish that the combined analyzer stays useful on existing Laravel
applications with hundreds of files, long fluent queries, dynamic table names,
legacy migrations, and application-specific command organization. The final
`0.5.x` slice therefore needs evidence from complete projects before treating
the existing diagnostics as beta-ready.

The adoption run must not copy private source into this repository, mutate an
application checkout, or judge analyzer correctness with the analyzer's own AST
visitors. It must also distinguish a false positive from a correct but noisy
diagnostic and from an explicit dynamic-analysis limit.

## Corpus and method

The repository-only harness in [`tools/corpus`](../../tools/corpus/README.md)
copies selected source roots into a disposable synthetic-Module projection. It
then runs the production `SourceIndexBuilder` twice: once cold and once through
the content-hash cache. The target checkout is not booted or modified.

Two fixed inputs were reviewed:

- an anonymized Laravel 11 query-heavy application at a clean local revision,
  using the generic manifest that contains no private identity or source data;
- Firefly III at public revision
  `46728cb71e55fbd137ee7edfdee2c217dfadcc34`, using the pinned public manifest.

Immediate `app/` directories are projected as synthetic Modules only to expose
cross-boundary diagnostic cardinality. These technical-directory groups are not
claimed to be either application's intended domain architecture. Migrations are
projected as one additional group.

Three oracles remain independent from Moduark's AST collectors:

1. **Resolved-line precision** verifies that every resolved table name appears
   on its reported source line.
2. **Anchoring** rejects table-evidence collisions that map multiple formatted
   fluent-chain operations to the same `(file, line)` location.
3. **Literal recall** uses PHP tokens, not `nikic/php-parser`, to locate literal
   `DB` / `Schema` Facade calls and rooted fluent table operations. Comments and
   docblocks are removed before matching.

The full unresolved list and inventory differences were then reviewed against
the original source. Reports normalize paths relative to each corpus root and
remain local; only aggregate evidence is committed.

## Evidence

| Metric | Laravel 11 application | Firefly III | Total |
|---|---:|---:|---:|
| `app/` PHP files | 217 | 1,172 | 1,389 |
| migration PHP files | 62 | 60 | 122 |
| analyzed PHP files | 279 | 1,232 | 1,511 |
| resolved in-corpus class references | 479 | 10,630 | 11,109 |
| table accesses | 597 | 210 | 807 |
| Schema mutations | 132 | 281 | 413 |
| foreign-key references | 53 | 103 | 156 |
| inline transaction scopes | 0 | 0 | 0 |
| unique unresolved locations | 2 | 16 | 18 |

Before hardening, 116 of 1,196 resolved table/Schema records pointed to a line
that did not contain the recorded table. The same root-line behavior placed 191
table records into 68 collision locations. The independent recall oracle missed
the corresponding 121 fluent operations. After anchoring evidence on the table
argument, precision had zero misses, anchoring had zero collisions, and all
1,077 literal Facade and rooted fluent operations were matched.

All 18 unresolved locations are genuinely dynamic expressions: raw subqueries,
loop or parameter-selected tables, and validation-selected tables. They remain
reviewable analyzer limits rather than guessed ownership. The inventory review
also exposed a real application defect: three queries used a singularized table
spelling while the migration and all other call sites used the plural spelling.
No private table identity or source is retained here.

Firefly III also exposed a false-positive boot failure. Its direct
`Console/Commands/` directory contains one concrete command and two support
traits. The old convention rejected the first trait as a non-command; the
hardened discovery registers the command and ignores the source-verified
support traits.

Finally, the synthetic boundaries showed diagnostic amplification rather than
additional architectural information. The two corpora produced 9,054
cross-Module source references but only 214 ordered consumer / provider pairs.
Reporting one representative per pair preserves the missing-dependency decision
while reducing `MOD-DEPENDENCY-002` from 9,054 violations to 214. The earliest
deterministic source reference remains attached as actionable evidence.

One representative local run on PHP 8.5.9 measured the private corpus at about
0.23 seconds cold / 0.02 seconds warm and Firefly III at about 1.25 seconds cold
/ 0.12 seconds warm. These observations guard against an obvious regression;
they are not a cross-machine performance SLA.

## Decision

- Direct files in `Console/Commands/` may declare interfaces, traits, enums, or
  abstract classes beside commands. Every symbol must still autoload from the
  exact discovered file. Instantiable concrete non-command classes remain an
  error, and nested command directories remain outside the convention.
- Table access evidence uses the first argument expression's source line. The
  enclosing call line is only a fallback when no argument exists. Source
  analysis cache schema `7` invalidates evidence produced with the old line
  semantics.
- `undeclared_dependencies` emits one violation per ordered source / target
  Module pair. Deterministic source ordering chooses the representative file,
  line, and symbol. Baselines store only the stable Module pair for this code;
  suppressions must explicitly select both consumer and target Modules before
  they may additionally narrow by representative evidence.
- Keep the corpus harness and manifests out of Composer distribution archives.
  They are repository verification tools, not runtime package API.
- A pre-hardening beta baseline can contain now-redundant
  `MOD-DEPENDENCY-002` identities, and its suppressions may select only a file or
  symbol. Adoption guidance must tell users to review and prune or regenerate
  baselines and migrate suppressions to explicit Module-pair selectors.

## Limitations

- Neither corpus declares Moduark Capabilities or uses an inline
  `DB::transaction()` callback. `capability_contracts`, `adapter_boundaries`,
  and `cross_module_transactions` still rely on focused and large Level 2/3
  fixtures rather than these projects.
- The token oracle covers the supported literal Facade and rooted fluent-query
  contract. It does not claim recall for raw SQL, macros, arbitrary builder
  data flow, Repository writes, or runtime Eloquent table inference.
- Synthetic technical-directory boundaries are suitable for measuring
  diagnostic precision and repetition, not for recommending how either project
  should be modularized.
- Firefly III command validation covers the direct command convention only;
  its nested command tree remains intentionally outside discovery.

## Consequences

- Real-project evidence now backs the hottest AST collectors and the console
  resource convention without adding application source to the repository.
- Table diagnostics lead developers to the literal that produced the evidence,
  and missing-dependency output scales with architecture edges rather than raw
  syntax occurrences.
- Existing fixture coverage remains necessary for unobserved Capabilities,
  transactions, and dynamic-analysis limits. A zero-miss corpus run is not a
  claim that unsupported dynamic behavior has become statically knowable.
