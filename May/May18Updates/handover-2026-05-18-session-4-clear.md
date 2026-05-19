---
type: handover
mode: context-clear
date: 2026-05-18
session: 4
branch: fynPromptRework
trigger: context-handover skill (context tripwire ~275k / 250k budget)
---

# Context Clear Handover — 2026-05-18, Session 4

## Immediate state

A **clean authoritative parity gate is running in the background** (job id `b12uga51v`, writing `/tmp/fyn-parity-clean.log`) — full `Unit,Feature,Architecture` under `FYN_PROMPT_ARCH=legacy` then `=unified`, with the dev server stopped and no browser load (so it is uncontaminated). It was still in the LEGACY phase when the tripwire fired. This is the real deterministic PR gate. One real non-deterministic defect (tripled capture acknowledgement) is found but **not yet root-caused or determined to be unified-specific**.

## The thread

- CSJ session-start → auto-resumed session-3 handover directive: "don't delete legacy prompt builders — archive, keep both switchable for A/B". Asked CSJ the one gated decision (archived shape) → CSJ chose **"in-tree behind flag (as-is)"** → spec/plan amendment only, no code moves.
- Amended `April/April24Updates/spec/00-canonical.md` (4 edits): Delta-1 3-part dispatch predicate; removed `AdvicePromptBuilder`/`OnboardingPromptBuilder` from the "no X" deletion clause + added permanent-retention language; "archived alongside legacy code" → "retained as the legacy reference"; flag section "no cleanup/deletion sub-task". Mirrored all into vault `fynlaBrain/AI/Fyn-Unified-Prompt-Architecture/Canonical-Two-Fyn-Contract.md`. Confirmed via grep that **no sprint/post-sprint plan ever scheduled class-level deletion of the two builders** — the deletion threat lived ONLY in the canonical spec. So NO plan file needed editing.
- Marked Delta-1 RESOLVED in `May/May18Updates/fyn-canonical-vs-implementation-delta.md` + vault `Canonical-vs-Implementation-Delta.md` (verdict + recommended-actions table row 1).
- CSJ: "reconcile all three, then before pr to dev we need to test fyn locally." Confirmed live config default IS `unified` (`config/fyn.php:17` `env('FYN_PROMPT_ARCH','unified')`, not set in `.env`). Reconciled **spec + vault copy + CLAUDE.md** all to `default unified` (post-cutover 2026-05-17; legacy = emergency rollback). Also removed CLAUDE.md's now-false "(unchanged)" pointer next to the spec ref (the spec WAS changed this session).
- Browser-tested Fyn locally under live `unified` (john@example.com, user_id=11, `onboarding_completed=false` + `onboarding_fyn_step=NULL` → routes to **read-only AdviceFyn**, the exact Delta-1 fail-safe path):
  - **Journey A (advice read-only): GREEN.** Accurate profile-grounded answer (£8,000 = Nationwide ISA £5k + Barclays £3k), and Fyn **asked for missing monthly expenditure instead of advising** = Delta-2 KYC parity fix confirmed LIVE in browser. Zero financial writes (savings stayed 2).
  - **Journey B (write intent → delegate_to_capture handoff): data-GREEN but UX defect.** Monzo Savings Account created (DB id 284, easy_access, £1,500). BUT the capture SSE stream emitted the assistant preamble *"Got it — recording that now."* **THREE times** (model ran 3 narrate+tool iterations; 1 real `entity_created`, others deduped → data integrity fine). See network req #245 response body.
- The early "Send button does nothing" was a **test-harness init race** (first Playwright click hit the button while still `[disabled]` before v-model synced from `fill()`). Calling the component's `send()` directly works; later a clean `pressSequentially` + UI click also worked. NOT a product bug. Frontend is **byte-identical to origin/dev** (`git diff --stat origin/dev...HEAD -- resources/js` empty).
- Parity-gate noise: first gate run reported `unified: 1 failed`; a re-run showed MANY Protection/Retirement **cache** test failures. Proved this was **CPU/IO-starvation flake** (full suite ran concurrently with live dev server + Playwright + SavingsAccount observer chain): the re-run's failing files pass **45/45 clean** in isolation under `unified` (`ProtectionCacheInvalidationTest`, `DecumulationApiTest`, `RetirementIntegrationTest`, `RetirementModuleTest`). phpunit forces `CACHE_DRIVER=array` so it was contention, not shared-cache. → relaunched the **clean** gate with dev server stopped.

## Files touched this session

```
TRACKED (in WIP a035ab6, pushed):
  CLAUDE.md                                                 | default legacy→unified + drop "(unchanged)"
  May/May18Updates/fyn-canonical-vs-implementation-delta.md | Delta-1 RESOLVED + table row

ON DISK BUT GITIGNORED (/April/ excluded — NOT version controlled, do not assume committed):
  April/April24Updates/spec/00-canonical.md  | 4 spec edits (Delta-1 predicate, builder retention, flag section, default unified)

VAULT (non-git, edited directly):
  fynlaBrain/AI/Fyn-Unified-Prompt-Architecture/Canonical-Two-Fyn-Contract.md       | mirrors the 4 spec edits + default unified
  fynlaBrain/AI/Fyn-Unified-Prompt-Architecture/Canonical-vs-Implementation-Delta.md | Delta-1 RESOLVED + table row
```

## WIP commit

- SHA: `a035ab6` — `wip: context-handover snapshot`
- Pushed: **yes** (`8299d51..a035ab6 fynPromptRework`)
- Squash target before any PR: `9c19dcc` (session-3 WIP) per prior CSJTODO.

## Open decisions

- **Is the tripled "Got it — recording that now." a PR blocker?** Default direction of travel: it IS a real user-visible defect; resolve before the dev PR per CSJ "test fyn locally before pr" + Rule #15 — BUT first determine if it is `unified`-specific (→ parity regression, must fix, mirror legacy) or pre-existing on `dev` live (→ separate logged issue, not this branch's blocker). Deterministic capture tests pass under BOTH flags, so it is live-model tool-loop behaviour, not a broken code path.

## Pick up from here (auto-continue contract)

1. **Read `/tmp/fyn-parity-clean.log`** (background job `b12uga51v` — survives /clear). If absent/incomplete, re-run clean with dev server stopped & nothing else running: `FYN_PROMPT_ARCH=legacy ./vendor/bin/pest --testsuite=Unit,Feature,Architecture --compact` then same with `=unified`. Baseline: legacy ~3728 passed / 1 skipped. **GREEN = identical pass/skip counts both flags.** If unified shows a *consistent* failure (re-run in isolation to rule out starvation flake first), that's a parity regression → fix per Rule #15.
2. **Settle the tripled-ack** (the Journey-B defect). Reproduce the SAME capture turn in the browser under BOTH flags, dev server only (NO concurrent test suite — that caused the contamination). To run dev under legacy: start dev server with `FYN_PROMPT_ARCH=legacy` in env (config is NOT cached locally so `env()` is read live) — do NOT edit `.env` (memory `feedback_never_touch_env_or_db`). Login john@example.com / password (local: fetch code from DB yourself via tinker `EmailVerificationCode`). Send "Add a savings account: <new provider>, balance <n> pounds". Inspect the `/api/ai-chat/conversations/{id}/messages` SSE response body. If legacy ALSO triples → pre-existing, log as separate issue, NOT a blocker for this prompt-rework PR. If only unified triples → parity regression, root-cause in the capture tool loop (`HasAiChat::chat` while(true) @ ~243, stop at ~685; `OnboardingChatDirector::handleInlineCapture` @ 2361 is a pure pass-through; the loop continuation is model re-emitting tool_use — likely the assembled unified context lacks legacy's "stop after create" signal). Fix per Rule #15, re-verify in browser.
3. Only after parity clean-GREEN AND tripled-ack resolved: proceed toward the dev PR (squash `9c19dcc`, open `fynPromptRework → dev`, no self-approve). Do NOT open the PR before both.

## What the next Claude needs to know

- **`/April/` is gitignored** — spec edits to `00-canonical.md` are on disk only, NOT in git. The canonical-spec source of truth is therefore unversioned + at data-loss risk (long-flagged in CSJTODO). The vault copy `fynlaBrain/AI/Fyn-Unified-Prompt-Architecture/Canonical-Two-Fyn-Contract.md` is the durable mirror — it has the same 4 edits. If verifying the spec, `cat` it from disk; don't `git show`.
- There is ONE Fyn, ONE unified prompt. Parity (`unified` ≡ `legacy`) is the contract; any unified-only gap is a BUG to fix by mirroring legacy, never an optimisation. Spec: `April/April24Updates/spec/00-canonical.md`. Parity record: `May/May16Updates/fyn-prompt-rework-parity.md`.
- Dev server contaminates the full Pest suite via CPU/IO starvation (cache/auth/TTL tests flake). NEVER run the parity gate while the dev server or Playwright is active. Stop dev server first (`lsof -ti :8000 :5173 | xargs kill`).
- Vite canonical port 5173; do NOT `pkill -f vite` (kills sibling project) — see memory `feedback_vite_canonical_port_5173`.
- Browser test = click/fill/submit/verify in Playwright + DB check (memory `critical_browser_testing_law`). Never `browser_close`.
- CSJ directive (session-3, now actioned): legacy builders retained permanently in-tree behind `FYN_PROMPT_ARCH` for A/B — NOT to be deleted by any future cleanup step.

## Branch / deploy state

- Branch: `fynPromptRework`
- Behind origin: 0
- Ahead of origin: 0 (WIP `a035ab6` pushed)
- Deploy status: Not deployed (feature branch; no dev PR yet — gated on parity-clean + tripled-ack per "Pick up from here")
