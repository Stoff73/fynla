---
type: handover
mode: context-clear
date: 2026-05-19
session: 1
branch: iFrames
---

# Context Clear Handover — 2026-05-19, Session 1

## Immediate state

SP3 **Task 8b implemented and committed** (`5d293659` — removed the dead native `/m/*` nav refs left by the legacy retirement). I was **mid Task-8b spec-compliance review** (subagent dispatched) when `/session-end` was invoked; that review and the subsequent code-quality review were **NOT completed**. Working tree clean, branch `iFrames` pushed to `origin/iFrames`. Nothing lost.

## The thread

Brand-new sub-project **SP3 — "Mobile-first iframe scaffold"** (item 3 of the 6-part Fynla overhaul). Full cycle done this session: brainstorming (visual companion) → spec → implementation plan → execution via **subagent-driven-development** (fresh implementer per task + two-stage review: spec-compliance then code-quality, with fix loops).

- Spec: `docs/superpowers/specs/2026-05-19-sub-project-3-mobile-iframe-scaffold-design.md` (APPROVED by CSJ).
- Plan: `docs/superpowers/plans/2026-05-19-sub-project-3-mobile-iframe-scaffold.md` (login-contract corrected; Task 8 scope-expansion + residual finding recorded inline).
- **Tasks 1–8 + 8b: COMPLETE** (each passed spec + code-quality review; fixes applied where reviewers found issues). **Task 9: NOT STARTED.**

What SP3 is: a separate, redesigned mobile frontend behind a same-origin iframe at `/m` (new isolated `resources/mobile/` Vite build → `public/m-build/`), phone-UA device routing, legacy `resources/js/mobile/` retired, Capacitor repointed. Scaffold ships disposable Login/Verify/Dashboard placeholder screens proving the seam against the real backend.

## Files touched (all committed — 16 commits today)

`37830437` spec · `f9626d58` plan · `96a351c0`+`a2cb4def` Task 1 · `ae214a62` Task 2 · `bc5d43e6` Task 3 · `26435180` Task 4 · `8fbe67fb` Task 5 · `664c9c6b` plan-contract fix · `8693d2e0` Task 6 · `5f4464de` Task 7 · `9e050d8e` Task 8 · `ac9d57f0` plan Task-8 note · `5d293659` Task 8b. HEAD = `5d293659`, clean, pushed.

## What the next Claude needs to know

- **Task-tracker state** (TaskList): #6–#13 + #15 = completed; **#14 = "SP3 Task 9: E2E browser test, README, spec fix, PR" = pending.**
- **OPEN QUESTION — Pest baseline 60 vs ~15.** Task 8b's full-suite run showed `60 failed` vs the documented ~15. Task 8b implementer's argument: its only changes are 2 **frontend JS** files (`api.js`, `preview.js`) never loaded by PHP Pest, so they cannot change backend test outcomes; the variance is **DB-contamination / test-ordering** (an isolated run of a "failing" class passed clean; exit code 0 on all runs). **Plausible and logically sound but NOT independently re-verified** — the 8b reviews were interrupted. Task 8 *did* prove its own 15-failure baseline pre-existing via stash-comparison (root cause: `app.ai_audit_hmac_key` not configured in `AuditChainService.php:53` — a local env-config gap, `AI_AUDIT_HMAC_KEY`/`APP_KEY`, unrelated to SP3).
- **Reviews owed before Task 9:** re-run Task 8b (a) spec-compliance review (2-file scope `api.js`+`preview.js`; web 401-redirect + preview-persona paths preserved verbatim; zero remaining `/m/*` SPA nav) and (b) code-quality review, **and** independently confirm the 60-vs-15 is pre-existing DB-ordering (stash-compare or isolation run) — do not take it on trust.
- **Known residual nits (non-blocking, recorded in plan Task 8 note):** unused `getItem` import in `resources/js/app.js`; stale Face-ID docblock on `auth.js` `mobileLogout` (impl deleted). Out of scope to fix unless CSJ asks.
- **No vault on this machine** (`/Users/Chris/Desktop/fynlaBrain` absent) — handover is repo-only; planning-with-files docs seeded as the fallback continuity channel.
- Subagent-driven-development discipline: **do NOT commit on `iFrames` while a background implementer is active** (its Step 7 is `git add -A`). One git-race occurred this session (recovered cleanly); avoid by holding controller commits to between-task gaps.

## Pick up from here

1. Re-dispatch the **Task 8b spec-compliance review**, then **code-quality review** (templates: `subagent-driven-development/{spec-reviewer,code-quality-reviewer}-prompt.md`). Base SHA `ac9d57f0`, head `5d293659`. Independently settle the 60-vs-15 Pest question (isolation/stash check) — confirm pre-existing, not an 8b regression.
2. On approval, mark task #15 complete and do **Task 9** (plan §"Task 9"): Playwright E2E on local dev — desktop UA unchanged; phone UA → `/m`; inside the iframe fill Login (`john@example.com`/`password`) → fetch the verify code from DB → Dashboard shows real `/api/v1/mobile/dashboard` data; `?full=1` escape hatch. Then create `resources/mobile/README.md`, correct spec §5.3 (cookie → Bearer wording), `git push`, open PR **`iFrames` → `dev`** (admin-merge pattern; csjones smoke before any dev→main).
3. Branch `iFrames` is pushed and tracks `origin/iFrames`; HEAD `5d293659`; tree clean.
