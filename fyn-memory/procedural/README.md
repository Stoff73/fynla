# Procedural memory — *how* Fyn does things

**You author these.** Each file is one procedure: a named playbook Fyn follows
when a situation matches. Procedural memory shapes *reasoning and sequencing* —
it is **never** a write-permission gate (that boundary lives in code, FR-M4).

## The heart: the pointer registry (v0.5)

**Memory holds pointers, not copies.** The core of procedural memory is the
**pointer registry** — typed fetch-skills that route Fyn to the *live* source for
any piece of data that has an authoritative owner, so nothing is duplicated or
goes stale. A pointer carries the *route*, never the value:

```
topic/trigger   → "ISA annual subscription allowance"
source          → tax_config | model_query | service_call | engine_run | md_fact
fetch           → TaxConfigService::getISAAllowances()
effective_dating→ owned by the source (the source must serve the current figure)
```

So the £20,000 lives only in `TaxConfigService`; the user's balance only in their
records; a recommendation only as a live engine call. Fyn fetches at the moment of
need (lazily, gated by the turn's classification). Each fetch is recorded on the
turn's **episode** for audit provenance. A pointer *fetches* — it never widens
write permission.

## What belongs here

- **Pointers** — which live source owns a piece of data + how to fetch it (the registry above).
- Multi-step playbooks ("how to talk a user through an emergency-fund gap").
- Sequencing rules ("always confirm marital status before an IHT estimate").
- Tone / framing conventions for a given topic.
- Decision heuristics the planner should apply for a query type.

## What does NOT belong here

- The **values** themselves → fetched live via a pointer; never frozen here.
- Facts about a specific user → fetched live (a pointer to their records); the durable *episode* lives in **episodic**.
- Write-safety / tool allowlists → those stay in `SurfaceAllowlist` (code).
- Tax values / numbers → those stay in `TaxConfigService` (a pointer routes to it; the number is never copied here).

## Authoring a procedure

1. Copy `_TEMPLATE.md` to `<kebab-case-id>.md`.
2. Fill the frontmatter (`id`, `title`, `applies_when`, `version`).
3. Write the steps/rules in plain language — this is read by the model, so be
   explicit and unambiguous.
4. Bump `version` whenever you change it — the cost ledger tags each turn with
   `procedural_version`, so versioned procedures stay auditable.

## How it's loaded

The procedure(s) whose `applies_when` matches the turn are injected into the
planner/reasoner context (the loop's `retrieve` step). Until the adapter is
wired, this is documentation-only; the structure is the contract.
