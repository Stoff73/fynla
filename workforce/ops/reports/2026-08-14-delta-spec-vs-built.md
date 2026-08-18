# Delta — spec vs built

**Date:** 2026-08-14 · **Measured**, not recalled. Counts from disk.
**Spec:** `August/Aug13Updates/2026-08-13-fynla-workforce-design.md` (v3) + the nine
ratified onboarding sessions.

---

## The headline

**The workforce is a capability, not a process. Nothing runs on its own.**

Every action so far has been manual dispatch. There is no process that wakes up, no
cadence, no event emission, no observation loop, and no Chief of Staff instance that
persists between turns. The design assumes a running Chief of Staff that observes,
assigns, judges and reports. **That thing does not exist yet.**

Second, quieter gap: the agent definitions in `.claude/agents/` are Claude Code
subagents. They work in CSJ's Claude Code sessions. The "workforce operating"
demonstrations in this build were **general-purpose agents with the charter pasted
into their prompt** — the discipline was real and the output was real, but it was a
simulation of the mechanism, not the mechanism.

---

## Phase status against the spec's own gates

| Phase | Spec gate | Status |
|---|---|---|
| **0 — Interview week** | Sessions 1 and 2 signed off | ✅ **Complete.** All nine ratified. |
| **1 — Spine** | *Three leads run one mission end-to-end, including intake, without CSJ touching the board* | ⚠️ **Artefacts built, gate NOT met.** No lead has claimed an item from the board. No mission intake has run. The board was populated by hand. |
| **2 — Reporting** | Five consecutive briefs CSJ finds worth reading | ❌ **Not started.** Zero briefs, zero checkpoint reports. |
| **3 — Slack** | A gate raised, approved in Slack, honoured, twice | ❌ **Blocked** — connector unauthorised (`PR-0001`). |
| **4 — Triage** | Ten confirm-backs, acted-on ratio >50% | ❌ **Blocked** — same. |
| **5 — Full roster + WhatsApp** | Zero standing gaps for a week | ❌ Not started. |
| **6 — Autonomy** | — | ❌ The goal. Not started. |

---

## Built, and working

| Component | Evidence |
|---|---|
| Constitution `00`–`08` | 9 files |
| Registry | 9 files |
| Charter, `index.md` | Written; index inside its 3k budget |
| Workforce agents | **8/8** defined |
| `FORMATS.md` | All nine artefact formats |
| `sweep.sh` | **73 checks**, orphan check green |
| `workforce-guard.sh`, `oversight-guard.sh` | **46 tests, 0 failures.** Bypass-hardened after adversarial review |
| `mission-control.sh` | Generates from disk. **No connector needed** — better than the spec's GitHub-connector design |
| Board, missions, gates | 5 items · 3 missions · 1 gate · 1 provisioning request |

**Ahead of spec:** local mission control (spec assumed GitHub OAuth); hooks cover
`Bash` as well as `Write|Edit`, which the spec never anticipated; the sweep does six
checks against the three specced.

---

## Specced, not built

| Designed | Built | Why |
|---|---|---|
| **Event log** — the data source for liveness, observation, mission control | **0 entries** | Nothing emits events. Without this, liveness monitoring, the daily brief and the agent grid have no input. **The single most load-bearing gap.** |
| **Checkpoint reports** | 0 | No agent has completed an item through the board |
| **Daily brief** (17:30, ≤300 words) | 0 | No Chief of Staff process to generate one |
| **Monday plan / Friday delta** | 0 | Never run |
| **Handoff notes** | 0 | The protocol has never been exercised |
| **Branch documents** | 0 | Trunk-and-branch is central to the design; no branch doc exists, so the consistency sweep's orphan/staleness checks have nothing to check |
| **Channel triage** | 0 | Slack unauthorised |
| **Confirm-back** | 0 | Same |
| **Evidence pack** | 0 | The merge gate has never been exercised. `W-0001` sits at `review` with `evidence: partial` |
| **Prior-art check as a mechanical gate** | Recorded as a field; **not enforced** | Nothing prevents an item reaching `claimed` without it |
| **Interview batching, divergence register** | Register exists, empty | Correct — only CSJ interviewed so far |
| **Quartermaster liveness / probe** | 0 | Requires the event log and a running observer |

---

## Agreed, not applied

| Agreed | Status |
|---|---|
| Remove `CODEOWNERS` | ✅ Done — `ab339ebc5` |
| Remove GitHub branch protection on `dev`/`main` | ❌ **CSJ only** — repository settings |
| `CLAUDE.md`: Rule 9 acronym exception; service count 446→462 | ❌ Queued (`00-precedence.md` §2.6) |
| `release` skill: drop the go-ahead requirement | ❌ Queued |
| Slack authorisation | ❌ `PR-0001` open |
| GitHub connector | ❌ Open |
| Create `#fyn-brief`, `#fyn-decisions`; rename `#social`→`#marketing` | ❌ Blocked / CSJ |
| Merge `fix/widow-persona-cleanup` | ❌ Needs `pint` + `php -l` locally, then merge |
| `@claude` trigger removal | ❌ `GATE-0001` — diff prepared, awaiting decision |
| `main` blast-radius list · out-of-hours P0 · non-code verification | ❌ Three open rulings |

---

## What would actually close the Phase 1 gate

The gate is *three leads run one mission end-to-end, including intake, without CSJ
touching the board.* Four things are missing, in dependency order:

1. **Event emission.** Something must append to `ops/log/*.jsonl` on every state
   change. Everything observational depends on it and nothing else can be built
   honestly until it exists.
2. **A claim mechanism.** Agents currently cannot take an item — the board is edited
   by hand. Needs a script or convention an agent can execute.
3. **A Chief of Staff that runs.** A scheduled task, a session-start hook, or a
   command. Without persistence there is no observer, no judge, and no brief.
4. **One real mission, worked through the board**, not dispatched around it.

**Estimate: 1 and 2 are small — a helper script and a claim command. 3 is the real
work.** It is also the difference between a designed workforce and a running one.
