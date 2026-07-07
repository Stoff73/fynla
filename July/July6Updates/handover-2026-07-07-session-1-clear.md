---
type: handover
mode: context-clear
date: 2026-07-07
session: 1
branch: main (docs) / audit-fixes-jul6 (PR #613) / life-events-allocations (PR #614)
---

# Context Clear Handover — 2026-07-07, Session 1

## Immediate state

Full-app audit + all fixes are done and shipped as **two open PRs to `dev`** (nothing merged, nothing deployed — CSJ said stay in development). One design decision is outstanding (#10 below) that also determines the fix for #9. Working tree clean apart from the audit report + this handover (committed here).

## The thread

1. **Full-app audit** (CSJ asked for whole-app code review + syntax/API + security + tax + E2E web AND /m). Report: `July/July6Updates/full-app-audit-2026-07-06.md` (repo + vault). ~28 findings across dashboard aggregation, tax calculation logic, security, models, frontend. Ran via parallel subagents + a live Playwright E2E on csjones (fresh user `e2e.fullaudit.jul6@example.com`, id 189 — left on csjones).
2. **Fixed all actionable findings** → branch `audit-fixes-jul6`, **PR #613** (off `origin/dev` 9c9e7d2). 79 files. Full suite **5,550 passed / 30 skipped** (was 5,506; +44 new tests), Pint clean; the one "failure" is a pre-existing load-sensitive perf benchmark that passes uncontended. Covers: dashboard £0 Protection/Retirement cards (shared aggregator key bug + pot binding), tax calc logic (MPAA never applied, IHT RNRB direct-descendant + residence cap + charitable-legacy exemption incl. the 2027 projection, Gift Aid band extension, employee-contribution double-deduction, additional-rate £137,710 boundary, stale/hardcoded dividend rates), security (joint-owner PII leak → MinimalUserResource ×8, advisor mass-assignment, PreviewWriteInterceptor login-MFA routes, User $guarded, 8 PII-in-logs sites), duplicate/dead-code consolidation, frontend stale tax display values + hex→tokens + acronyms + 3 dead views.
3. **Implemented life-event allocations** → branch `life-events-allocations`, **PR #614**. Finding #5 (the "Tax Optimised Allocation" tab 404'd). Turned out the whole backend existed (table/model/742-line service/controller) — only 3 routes were unwired. Wired them + added a preview-user no-persist guard (generate-on-read rolled back for preview) + 8 feature tests (all green).
4. **Explained #9/#10 to CSJ** and asked the one blocking design question.

## Files touched (all committed + pushed)

- `audit-fixes-jul6` @ `a86c414` (pushed, PR #613) — the audit fixes.
- `life-events-allocations` @ `f585a3f` (pushed, PR #614) — the allocation routes + test.
- `main` — audit report `full-app-audit-2026-07-06.md` + this handover (committed this wrap).
- Worktrees: `fynla-audit-fix` (audit-fixes-jul6) + `fynla-le` (life-events-allocations) — both hold committed+pushed branches; safe to `git worktree remove` after the PRs merge. `fynla-coala` + `fynla-fixes` untouched.
- Housekeeping: removed the disabled `explanatory-output-style` plugin from `~/.claude/plugins` (registry entry + cache) since its disable wasn't taking — the injected output-style should be gone next session.

## What the next Claude needs to know

- **THE open decision (blocks #9):** does completing the **9-step form-wizard onboarding** mark `onboarding_completed = true`? Observed: after finishing the full wizard, the user stayed `onboarding_completed=false`, `onboarding_fyn_step="path_choice"`. That's the ROOT of #9 (web-vs-/m Fyn divergence): same user, same flags, **/m Fyn showed onboarding bubbles** (correct per the dispatch predicate) but **web "Chat with Fyn" gave advice + did an inline write** (ignored the onboarding state) — which the canonical "surface-agnostic dispatch" contract says can't happen. If the wizard SHOULD complete onboarding → flip the flag + null `onboarding_fyn_step` on wizard finish, and that fixes both #9 and #10. If it should NOT → #9 is a genuine web-dispatch bug (web shouldn't advise+write a mid-onboarding user). **Do not touch dispatch/onboarding logic until CSJ answers** — guessing risks the canonical Fyn contract.
- **Deploy stance:** CSJ said "still in development" → **no csjones deploy**. Both PRs await CSJ merge. Prod untouched. Dashboard fix (#1/#2) is unit-proven but NOT live-browser-verified (needs a deploy CSJ declined) — be honest about that.
- **Deferred (documented in the report):** Estate money-column decimal cast (#11) was **reverted** — it breaks strict-typed `float` consumers across ~68 estate/IHT sites; needs a dedicated consumer-wide migration, not a batch flip. `estateService.analyzeEstate` has a live caller (not dead — audit was wrong). `CurrencyDisplayService` left as-is.
- **Release window** (when CSJ ships dev→prod) now covers #581–#614 (one migration `users.active_campaign` from earlier; the audit + life-events PRs add no migrations).

## Pick up from here

1. If CSJ answered the #10 question: implement the matching fix on a new branch off `dev`, then verify the SAME user gets the SAME Fyn state on web AND /m.
2. Else: await the decision; meanwhile the two PRs are review-ready.
3. If CSJ merges #613/#614: `git worktree remove` the two fix worktrees, and (only on CSJ's word) help scope the dev→prod release.
