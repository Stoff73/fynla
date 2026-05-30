# Procedural memory — *how* Fyn does things

**You author these.** Each file is one procedure: a named playbook Fyn follows
when a situation matches. Procedural memory shapes *reasoning and sequencing* —
it is **never** a write-permission gate (that boundary lives in code, FR-M4).

## What belongs here

- Multi-step playbooks ("how to talk a user through an emergency-fund gap").
- Sequencing rules ("always confirm marital status before an IHT estimate").
- Tone / framing conventions for a given topic.
- Decision heuristics the planner should apply for a query type.

## What does NOT belong here

- Facts about a specific user → that's **episodic** / semantic.
- Write-safety / tool allowlists → those stay in `SurfaceAllowlist` (code).
- Tax values / numbers → those stay in `TaxConfigService`.

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
