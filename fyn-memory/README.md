# Fyn memory stores (CoALA Phase 5)

Fyn's long-term memory, as plain markdown so it is human-readable, diff-able, and
editable without a database. The CoALA decision loop (`app/Services/AI/Loop/`)
reads these stores during a turn (`retrieve`) and writes to them (`learn`).

CoALA defines four memory types. Working memory is the live context window
(already built — `FynTurnContext` + `FynContextAssembler`). The three durable
stores live here:

| Store | What it holds | Who writes it | State |
|-------|---------------|---------------|-------|
| **procedural/** | *How* Fyn does things — the rules, playbooks and procedures it follows | **CSJ authors** (committed markdown) | scaffolded here |
| **episodic/** | *What happened* — salient per-interaction episodes | **The Fyn agent writes**, guided by `episodic/RUBRIC.md` (which CSJ authors) | scaffolded here |
| **semantic/** | *Facts* — durable knowledge distilled from episodes | Phase 1 (future) | placeholder |

## Layout

```
fyn-memory/
  procedural/            CSJ-authored procedures (committed)
    README.md
    _TEMPLATE.md
    <procedure>.md       ← you write these
  episodic/
    README.md
    RUBRIC.md            ← you author the rubric + rules the agent applies
    _TEMPLATE.md
    episodes/            ← Fyn writes episodes here at runtime (gitignored)
  semantic/
    README.md            Phase 1 placeholder
```

## How the loop uses these

- `retrieve` (planner action) — reads `procedural/` (always in scope per the
  active procedure) and recalls relevant `episodic/episodes/` for the user.
- `learn` (planner action) — appends a new episode under `episodic/episodes/`,
  applying `episodic/RUBRIC.md`.

Both actions are **no-op until these stores exist + the read/write adapters are
wired** (the deferred half of FR-M2). This tree is that foundation.

## Authoring boundary

- **Procedural is yours.** You write the procedures Fyn follows. Never
  agent-generated — these are the rules, not observations.
- **Episodic is Fyn's**, but on *your* terms: you author `RUBRIC.md` (what is
  worth recording, how to summarise it, retention). The agent only writes
  episodes that pass your rubric.
- **The write-safety boundary stays in code**, not in procedural memory
  (per FR-M4) — procedural memory shapes *reasoning*, never *write permissions*.

Paths are configured in `config/fyn.php` under `memory`.
