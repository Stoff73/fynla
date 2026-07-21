---
owner: CSJ
version: 0            # 0 = scaffold/draft. Bump to 1 when you've made it yours.
status: draft
---

# Episodic capture rubric

**This is yours to author.** The Fyn agent applies this rubric at the end of a
turn to decide *whether* to write an episode, *what* to capture, and *how long*
to keep it. Everything below is a SCAFFOLD with sensible defaults — replace the
`TODO(CSJ)` blocks. The salience score here is **internal only** (never shown to
a user — it's a retention/recall signal, not a user-facing rating).

---

## 1. When to record (salience triggers)

Record an episode when **any** of these fire. _TODO(CSJ): tune this list._

- The user stated a **goal, intention or life event** ("we're planning to
  retire at 60", "we just had a baby").
- The user revealed a **durable preference or constraint** ("I never want to
  touch the ISA", "we're risk-averse").
- A **decision or commitment** was reached ("I'll increase the pension by £200").
- Fyn **asked a clarifying question that wasn't answered** (pairs with the
  resumption surface — the episode is the thread to pick back up).
- The turn **resolved a confusion** the user had had before.

Do **not** record:
- Pure factual read-backs ("what's my net worth") with no new signal.
- Turns already fully captured as structured data (onboarding writes).
- Anything the user asked to forget.

_TODO(CSJ): add/remove triggers; this is the heart of the rubric._

## 2. Salience score (0–5)

Score the turn; only write an episode at or above the threshold. _TODO(CSJ): set
the weights + threshold._

| Signal | Points |
|--------|--------|
| New goal / life event | +2 |
| Durable preference / constraint | +2 |
| Decision / commitment | +2 |
| Unanswered clarifying question | +1 |
| Emotional weight (worry, relief) | +1 |
| Pure factual read-back | −2 |

**Threshold:** record when score ≥ **3**. _TODO(CSJ)._

## 3. What to capture

For a recorded episode, the agent fills `_TEMPLATE.md`:

- `summary` — one or two sentences, **third person, factual, no PII beyond what's
  needed** ("User wants to retire at 60; risk-averse on the pension").
- `signals` — the triggers that fired (from §1).
- `salience` — the §2 score.
- `references` — module / record ids touched, if any.
- `verbatim` — _TODO(CSJ): decide if a short verbatim quote is allowed, or
  summary-only (PII minimisation)._

## 4. Retention

- Default keep: **TODO(CSJ) — e.g. 18 months**, then summarise into semantic
  memory (Phase 1) and delete the raw episode.
- A user's erasure request deletes their whole episode tree (enforced in code).

## 5. Agent instructions (applied verbatim)

> At end of turn, score the turn against §2. If below threshold, do nothing.
> If at/above threshold, write ONE episode using `_TEMPLATE.md`, summarising per
> §3 in plain third-person prose with minimal PII. Never invent signals not
> present in the turn. Never record a turn the user asked to forget.

_TODO(CSJ): refine the agent instructions to match your final §1–§4._
