---
type: vault-sync-flag
priority: HIGH
date: 2026-05-18
branch: fynPromptRework
---

# VAULT-SYNC PENDING — read this before/at the next vault-sync

The next `vault-sync` (or `session-end`) run MUST carry the items below into the
fynlaBrain vault. This flag exists because the canonical-spec carry is **overdue 5+
sessions** and the source files live in **gitignored trees** (`/April/`, `/May/`) — they
are NOT in git and are lost on the next tree change. This is the single highest-risk
outstanding doc item (carried from `handover-2026-05-18-session-1.md`).

## Carry into the vault (all three, as a set)

| # | File | What it is | Why it must not be lost |
|---|---|---|---|
| 1 | `April/April24Updates/spec/00-canonical.md` | The Fyn two-Fyn **canonical contract** (source of truth, rewritten on disk) | Gitignored `/April/`; overdue 5 sessions; everything references it |
| 2 | `May/May18Updates/fyn-prompt-and-response-process-map.md` | **As-built** verified trace of the unified prompt/response pipeline (file:line grounded) | First complete as-built map of the unified architecture |
| 3 | `May/May18Updates/fyn-canonical-vs-implementation-delta.md` | **Delta** analysis: design ↔ implementation (4 deltas, 0 breaches) | Captures the 1 doc-fix + 1 CSJ-decision item before they're forgotten |

## The delta (summary — full detail in file #3)

Comparison run 2026-05-18: **canonical (design) vs map (as-built) vs live code**.
Result — **4 deltas, 0 functional contract breaches**:

1. **Dispatch predicate** — canonical says "keyed purely on `onboarding_completed`"; code
   is a 3-part predicate (`onboarding_completed===false && onboarding_fyn_step!==null &&
   flow_on`). **Action: amend canonical wording. Code is correct.**
2. **KYC gate dead text under unified** — `KycGateChecker::check()` runs every turn but its
   prompt_text is discarded; gate re-derived softly via READINESS bucket. **Action:
   CSJ decision (single gate source vs telemetry-only). Not breached, not auto-fixed.**
3. **Double `QueryClassifier::classify()` per advice turn** — micro-inefficiency, backlog.
4. **Model default `grok-4.3`** — informational, deliberate, do not "fix".

## What vault-sync should do with these

- Place the canonical contract under the vault's Fyn AI architecture area (it is the
  source of truth — link, do not paraphrase).
- File the as-built map + delta alongside it as the current-state reference for the
  unified prompt architecture; wikilink them to the canonical doc and to `Auth.md` /
  the AI Chat current-state docs.
- Surface delta items #1 and #2 in the relevant current-state / decisions doc so the
  doc-fix and the open CSJ decision are not lost.

## Status

- [x] Carried to vault by vault-sync run on: **2026-05-18** (session 2, manual carry after vault-sync)
- Durable vault home created: `fynlaBrain/AI/Fyn-Unified-Prompt-Architecture/` — `Canonical-Two-Fyn-Contract.md`, `Prompt-Response-Process-Map.md`, `Canonical-vs-Implementation-Delta.md`, plus index `fynlaBrain/AI/Fyn-Unified-Prompt-Architecture.md`. Wikilinked to each other, `Current State/Auth.md`, and `Architecture/v083/10-NEW-SYSTEMS.md` (back-links added in both). Delta #1 (doc-fix) and #2 (open CSJ decision) surfaced in the index "Open items" section.
- Flag retained (not deleted) as the audit record of the carry; safe to delete once the canonical Delta-1 wording fix is applied on disk.
