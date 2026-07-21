---
type: handover
mode: context-clear
date: 2026-06-16
session: 2
branch: savetax-verify-capture
---

# Context Clear Handover — 2026-06-16, Session 2

## ⭐ Immediate state — THE MANDATE FOR NEXT SESSION
**Land Option B + the E2E for the SaveTax verify-after-capture feature.** Its backend (plan Tasks 1–6) and the two `/m` screens + nav (Tasks 7–9) are **done, committed, pushed, and green** on `savetax-verify-capture` (off `dev`, 10 commits, on origin). Work paused at **Task 10**, which turned out to be a real frontend refactor (not the planned tweak); CSJ chose **Option B** to resolve it. Next session: implement Option B, then the browser E2E, then the dev PR.

## The thread (this session's arc — huge session, 4 workstreams)
1. **Cross-module composer Phase 5** — 5.1 aggregator wire-through (composed module plans behind `COMPOSED_MODULE_PLANS`, default ON), 5.2 web `/holistic-plan` composite view + `GET /api/holistic/composite-plan`, 5.3 net-new `/m` holistic screen. All built, browser-verified (peak_earners), PR #561.
2. **Merged #558→#559→#560→#561 to `dev`**, then opened + merged **#562 dev→main** and did a **FULL PROD DEPLOY to fynla.org** — first prod landing of **CoALA Phases 1–6 + composer Phases 1–5** (280 commits, 11 migrations on the live DB, corpus reindex, strategy seeders). Browser-verified `/m` holistic on prod. `FYN_LEARNING_ENABLED` stays OFF on prod; `COMPOSED_MODULE_PLANS` ON (rollback = `false`). Also deployed `dev→csjones`.
3. **New feature: SaveTax verify-after-capture** — brainstorm → spec → plan → backend (D1) + screens (D3). Paused at the Task-10 architecture fork.

## Files touched (committed on `savetax-verify-capture`)
- Spec: `docs/superpowers/specs/2026-06-16-savetax-verify-after-capture-design.md`
- Plan (READ THIS FIRST next session — has the revised Task 10/Option B steps): `docs/superpowers/plans/2026-06-16-savetax-verify-after-capture.md`
- Backend: `app/Services/Onboarding/OnboardingStateMachine.php` (verify config + helpers + 3 generic verify states + re-routed edges), `app/Services/Onboarding/OnboardingChatDirector.php` (navigation-on-bubbles), `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` (corpus parity), tests `tests/Unit/Services/Onboarding/CampaignVerifyFlowTest.php` + fixes to `OnboardingWorkflowTableGoldenMasterTest.php`/`CampaignSectionFlowTest.php`/`OnboardingStateMachineTest.php`.
- Screens: `resources/mobile/views/Income.vue`, `resources/mobile/views/Expenditure.vue`, `resources/mobile/router.js`, `resources/mobile/components/MobileChrome.vue` + `resources/mobile/views/Dashboard.vue` (Cash Management nav), `app/Services/UserProfile/UserProfileService.php` (additive `income_summary`), `tests/Feature/Api/UserProfileIncomeSummaryTest.php`.

## What the next Claude needs to know (non-obvious)
- **The architecture finding that forced Option B:** the `/m` onboarding *campaign* chat lives **inside `Dashboard.vue`**; the app uses a plain `<router-view/>` (NO `<keep-alive>`), so navigating to the verify screen (`/income` etc.) **unmounts Dashboard and destroys the chat + the waiting Gate-2 bubble**. Module screens render `MobileChrome`'s dock, which starts a **fresh advice** chat, not the onboarding conversation. So the spec's "navigate → reopen chat → Gate-2 waiting" can't work as-is.
- **Option B (CSJ's choice):** make `MobileChrome`'s Fyn dock **resume the persisted onboarding conversation** (already in `ai_messages`) when opened mid-onboarding (`store.user.onboarding_completed === false` + active onboarding conversation) — so on `/income` the dock shows the waiting Gate-2 turn. Plan §"Task 10 (REVISED)" has the exact steps (extract a shared onboarding-chat composable used by both `Dashboard.vue` + the dock; resume-on-open; widen `handleOnboardingNavigation`'s `/tax-strategy`-only guard to the verify destinations).
- **One thing already verified:** the Gate-2 bubble DOES survive a navigation turn in `Dashboard.vue::send()` (it has text+bubbles, not dropped at ~lines 1090-1095); the ONLY blocker on the existing path is `handleOnboardingNavigation` (line ~1116) hardcoded `if (routePath !== '/tax-strategy') return;`. Widen it: `['/tax-strategy','/income','/expenditure','/savings','/investment','/retirement']`.
- **No new write tools needed** — the edit path (Gate-2 "no") uses the already-whitelisted `update_record`/`update_profile` (in `OnboardingPromptBuilder::toolsForFocus('savetax')`).
- **Mobile build:** after `resources/mobile/*` edits, rebuild before browser-testing (no HMR). Local: `bash scripts/build-mobile.sh` (local base). csjones is the Rule #14 E2E gate.

## Pick up from here (do these, in order)
1. Read `docs/superpowers/plans/2026-06-16-savetax-verify-after-capture.md` — the "STATUS / Task 10 (REVISED) — Option B" section.
2. **Implement Option B** (resume onboarding in the dock + the `handleOnboardingNavigation` whitelist) on `savetax-verify-capture`. TDD where practical; keep the onboarding suite green.
3. `bash scripts/build-mobile.sh`; **browser E2E on `/m`** (csjones, peak_earners) — walk the SaveTax campaign and confirm the full per-section verify loop: Gate 1 "anything else?" → navigate to the section's screen + nudge → reopen dock → Gate-2 resumes → edit path → Yes advances; new Income/Expenditure screens + Cash Management nav render; giving verifies inline.
4. Full `./vendor/bin/pest`; `tech-debt-session`; open the **dev PR**.

## Tech debt found this session (deferred)
- `Income.vue`/`Expenditure.vue` each define a local `formatCurrency` (accepted mobile-bundle convention; candidate for a shared `resources/mobile/format.js` — see the standing note in `tech-debt-report.md` #2).
- Plan gap fixed in-session: the original Task 10 was a "tweak"; it's actually the Option-B refactor (now documented in the plan).

## Known issues / blockers
- None broken. The feature is half-built but **green** (onboarding suite 318 pass + income_summary feature test). The verify flow is **not yet user-reachable** (Option B not built), so onboarding behaviour on dev/prod is unchanged (the branch isn't merged).

## Context hints
- Active branch: `savetax-verify-capture` (off `dev`), pushed, 10 commits ahead of `dev`.
- Prod (fynla.org): now CoALA + composer (shipped this session) — NOT the savetax-verify feature (feature branch only).
- Uncommitted: none, working tree clean.
- Last commit: `816a20d docs(plan): status + Task 10 reframed to Option B`.
- Vault-sync: SKIPPED this clear (marathon session) — run a full `vault-sync` next session or at EOD.
