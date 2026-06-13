---
type: handover
mode: context-clear
date: 2026-05-30
session: 3
branch: feat/coala-fynloop
trigger: context-handover skill (tripwire ~790k tokens)
---

# Context Clear Handover — 2026-05-30, Session 3

## Immediate state

Just finished CoALA Phase 5 **item 4 (FynLoop, Option B)** end-to-end and pushed it; **item 5 (planner) was scoped + decided but deliberately NOT started** this session because the tooling channel was too degraded to write/verify new code safely. All work is committed and pushed — tree is clean except pre-existing non-mine changes (see below).

## The thread

1. Started with a favicon question: the static marketing site (`public/pages/*.php`) declared NO favicon, so browsers fell back to a stale `/favicon.ico` at the prod web root (4296-byte `389381d3…`, untracked, deploy drift). The correct brand favicon is `public/images/logos/favicon.{ico,png}` (the Vue SPA `app.blade.php:41-42` already uses it). Added explicit `<link rel="icon">` to all 18 static pages after the charset meta. Committed `6000cdd`, pushed at CSJ's request. **CSJ will upload the 18 files + delete the stale root `/favicon.ico` manually.**
2. Ran `/session-start` mid-session → read handover-session-2-clear → auto-continued item 4.
3. **Item 4 FynLoop extraction** (`96cfda2`): `FynLoop::run` = streamed advice turn + `interceptHandoff`; `AdviceFyn` keeps its pre-LLM bypasses and delegates the final turn; `AdviceFyn::wrapStream` became a thin forwarder so the P0.9 reflection test (`AdviceFynWrapStreamHandoffDropTest`) still pins INV-2.4.1. Conservative seam (CSJ chose this over maximal).
4. **Item 4 onboarding routing** (`4f94a5d`): added `FynLoop::stream()` primitive (CSJ chose this over "only inline-capture"); routed all 3 onboarding streamed turns (grouped-extract, asset-capture, inline-capture) through it. **Fixed a latent prod hazard**: CoordinatingAgent is container-transient, so the focus-set and the stream that reads it MUST share one instance — `stream()` centralises that. Container cycle broken via lazy `app(OnboardingChatDirector::class)` inside `interceptHandoff` (CSJ chose FynLoop-lazy-resolves over director-lazy-resolves).
5. **Item 5 investigation** revealed the planner is the most dependency-laden Phase-5 item. Surfaced to CSJ. Two corrections came out of it: (a) **CSJ: "working memory = the context window"** — correct; `FynTurnContext` + `FynContextAssembler::build()` + `HasAiChat` history loading ALREADY are the loop's state object, so NO new VO needs building (my "blocked by Phase 3" was too literal). (b) The real blocker is `retrieve`/`learn` need Phase 1 (semantic) + Phase 2 (episodic) stores, which don't exist. `reason`/`ground`/`no_action` are buildable now.
6. CSJ locked item-5 decisions: **two-call planner (planner→reasoner), PRD-literal**, cycle cap 8 + budget. Then chose **push verified commits + update CSJTODO** as the safe degraded-session move rather than write unverified planner code.
7. Final exchange: CSJ asked "what other claude session is there?" — I had asserted (as fact) that a second Claude process was wiping the temp dir. **That was a guess dressed as fact; I retracted it.** The real evidence is only the harness error strings (`/private/tmp/claude-501/.../tasks is full (0MB free)` ENOSPC + `another Claude Code process … deleted it during startup cleanup`), but `df` showed 272 GB free. Root cause is the per-session task temp dir under `/private/tmp/claude-501/` getting wiped mid-command — could be another `claude` proc, an orphaned prior run, a cleanup hook, or something touching `/private/tmp`. NOT verified. Tripwire fired before I could investigate.

## Files touched this session (all committed + pushed)

- `public/pages/*.php` (18 files) — favicon links (`6000cdd`)
- `app/Services/AI/Loop/FynLoop.php` — NEW, then extended with `stream()` (`96cfda2`, `4f94a5d`)
- `app/Services/AI/AdviceFyn.php` — delegates to FynLoop; wrapStream forwarder (`96cfda2`)
- `app/Services/Onboarding/OnboardingChatDirector.php` — 3 streamed turns route via `stream()`; ctor-injects FynLoop (`4f94a5d`)
- `CSJTODO.md` — item 4 done, item 5 scoped (`7519341`)

## WIP commit

- None needed — all session work was already committed in proper feature commits (`6000cdd`, `96cfda2`, `4f94a5d`, `7519341`). No `wip:` snapshot created.
- **Uncommitted = pre-existing non-mine changes, deliberately NOT committed:** deleted `public/images/logos/logoMain.png`, `public/images/logos/logoTransparent.png`; untracked `docs/mobile/designer-brief.pdf`. These were dirty at session start, are unrelated to my work, and I did not create them — committing logo deletions would be hard to reverse. Leave for CSJ to resolve.

## Open decisions

- **Item 5 build approach — DECIDED this session, documented here so next session doesn't re-ask:** two-call planner (planner LLM emits one typed action → reasoner streams), PRD-literal per `PRD-coala-phase-5-decision-loop.md` FR-M6, cycle cap 8, budget max 2 reason / 3 retrieve. No new working-memory VO (FynTurnContext IS it). `reason`/`ground`/`no_action` ship now; `retrieve`/`learn` no-op until Phases 1+2 stores exist.
- **What to build next is open** — three valid paths, default = item-4 eval sign-off FIRST (see below).

## Pick up from here (auto-continue contract)

**FIRST, on a clean channel, do the item-4 sign-off that is OWED before any `feat/coala-fynloop → coala` PR:**
1. Run the Fyn eval suite + browser verification for item 4 — 35 invariants (`tests/Feature/Fyn/Eval/` + `01-invariants`), 75 golden conversations, `09-canonical-behaviour`. Item 4 is Pest-green (918 AI/Fyn/Onboarding + 117 arch + 8 load-bearing) but eval/browser is NOT done.
2. Quick re-confirm the Pest gate still green after `/clear`: `./vendor/bin/pest tests/Unit/Services/AI/ tests/Feature/Fyn/ tests/Feature/AI/ tests/Unit/Services/Onboarding/ tests/Feature/Onboarding/`

**THEN pick ONE (CSJ to steer; if silent, default to (a)):**
- (a) Build item 5 planner per the locked decisions above. New `app/Services/AI/Loop/Planner.php`. `ActionType` enum (5 cases) already exists from item 3. TDD against the stream-mock harness (`tests/Support/Fyn/FynStreamHarness`). Two-call shape. `retrieve`/`learn` no-op until Phases 1+2.
- (b) Item 6 — concurrent-turn queue (`ai_messages.status` migration: queued/processing/answered/cancelled/expired + depth cap 3 + frontend states). Genuinely independent of Phases 1-4.
- (c) Item 8 — per-action cost-attribution table + admin dashboard. Largely independent.

## What the next Claude needs to know

- **Tooling channel was degraded ALL session** — temp dir under `/private/tmp/claude-501/` kept getting wiped mid-command (ENOSPC despite 272 GB free; "another Claude Code process … deleted it" harness message). ~half of reads/parallel calls returned empty; worked around via redirect-to-logfile + retry; foreground `sleep` got blocked late. If it recurs, the cause is UNVERIFIED (do not assert "second Claude process" as fact — I wrongly did). Check `ls /private/tmp/claude-501/-Users-CSJ-Desktop-fynla/` for stray session dirs, and whether another `claude`/editor/watcher is touching `/private/tmp`.
- **Pint-strip-import hazard is REAL and fired this session** — adding a `use` import in one edit before its usage exists → Pint strips it on PostToolUse → runtime fatal, invisible to `php -l`. Always add usage first OR `grep -c "use …;"` after. Write whole files in one Write call to avoid it.
- **CoALA PRs target `coala`, not `dev`** (standing CSJ decision).
- **Option B is approved**; do not propose Option A.
- **Prod AI provider is xAI**; the harness forces anthropic for scripted streams (loop is provider-agnostic).
- **CoordinatingAgent is container-transient** — any focus-set-then-stream must happen on one instance. FynLoop::stream now owns this; don't reintroduce split-instance focus.
- **Favicon follow-up for CSJ:** upload the 18 `public/pages/*.php` + delete stale prod root `/favicon.ico` (4296-byte `389381d3…`).

## Branch / deploy state

- Branch: `feat/coala-fynloop`
- Ahead of origin: 0 (all pushed — local == `origin/feat/coala-fynloop` == `7519341`)
- Behind origin: 0
- Deploy status: Not deployed. `feat/coala-fynloop` has NOT been PR'd to `coala` (eval sign-off owed first). favicon `6000cdd` is on this branch too — not yet on dev/main/prod.
