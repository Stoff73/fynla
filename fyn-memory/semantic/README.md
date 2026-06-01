# Semantic memory — global domain-knowledge corpus

A git-tracked corpus of durable, category-organised domain knowledge that Fyn
may draw on when answering user questions. Phase 1 builds the global corpus;
per-user distilled facts are a reserved future layer (see below).

## What this is

Each file in the corpus encodes **one discrete fact** about the financial
planning domain: FCA rule narrative, product-reference information, internal
house-view guidance, or the shape of a UK tax/allowance regime. Facts are plain
prose — never raw numbers (numeric tax values come from `TaxConfigService`,
CLAUDE.md Rule #3).

Retrieval is sparse and keyword-based (no vector embeddings in Phase 1). At
each turn the loop's `retrieve` action scores corpus files against the turn
context; matching facts are injected into the `<knowledge>` block of the
system/per-turn prompt before the planner runs.

## What belongs here

- FCA compliance narrative ("advised vs non-advised", suitability obligations,
  consumer duty framing).
- Product-reference facts ("ISAs are individual-only under UK law", pension
  input period rules, protection product categories).
- Internal house-view statements ("Fynla recommends six-month emergency fund
  before investment").
- Tax-regime shape and rule descriptions — NOT the values themselves.
- Allowance regime descriptions (carry-forward mechanics, taper logic narrative).

## What does NOT belong here

- Numeric tax values or rate tables → `TaxConfigService` / `TaxConfigSeeder`.
- User-specific facts ("retires 2041", "risk-averse") → per-user episodic or
  the reserved semantic/user layer below.
- Write-safety / tool allowlists → code (`SurfaceAllowlist`).
- Procedural playbooks (multi-step reasoning sequences) → `procedural/`.

## Layout

```
semantic/
  _TEMPLATE.md          frontmatter contract + authoring guidance (you copy this)
  fca/                  FCA regulatory and compliance narrative
  product/              product-reference facts (ISA rules, pension types, etc.)
  house_view/           internal Fynla house-view guidance
  tax/                  UK tax-regime descriptions (shape, not values)
  allowance/            UK allowance regime descriptions
```

Each fact file is named with its `fact_id` (kebab-case), e.g.
`fca/advised-vs-non-advised.md`.

## Frontmatter contract

Every corpus file must include valid YAML frontmatter conforming to this
contract. The `_TEMPLATE.md` file is the canonical starting point.

| Field | Required | Rules |
|---|---|---|
| `fact_id` | Always | Unique kebab-case identifier, matches filename |
| `category` | Always | One of: `fca`, `product`, `house_view`, `tax`, `allowance` |
| `title` | Always | Human-readable description of the fact |
| `source` | For `fca`, `product` | Citation — FCA handbook ref or internal house-view owner |
| `version` | Always | Integer >= 1; bump on every substantive edit |
| `valid_from` | For `fca`, `tax`, `allowance` | ISO date (YYYY-MM-DD); onset of the rule or tax year |
| `valid_to` | Always | ISO date or `null`; `null` means currently in force |

## Authoring a fact

1. Copy `_TEMPLATE.md` to `<category>/<fact-id>.md`.
2. Fill in all required frontmatter fields.
3. Write the body in plain prose — one fact per file, kept focused.
4. Bump `version` on every substantive edit so the cost ledger can track
   which corpus version was in play for any given turn.

## Reserved future layer — per-user distilled facts

The original semantic-memory design also sketched a per-user facts layer:

```
semantic/
  <user_id>/
    profile.md      distilled durable facts
    preferences.md  durable preferences / constraints
```

This layer is **not built in Phase 1**. It is reserved for a later phase in
which the episodic-memory retention sweep promotes durable facts (such as
"retires 2041" or "risk-averse") here, deletes the raw episode, and makes
them available to the loop's `retrieve` action alongside corpus recall.

Until that phase lands, the loop's `retrieve` over per-user semantic memory
is a no-op.
