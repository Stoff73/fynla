---
type: handover
mode: end-of-day
date: 2026-06-27
session: 1
branch: main (work on worktree feature branches off dev)
previous_session: 2026-06-25 session 1
---

# Handover — 2026-06-27, Session 1

> Work performed 2026-06-26. A long, productive run: **4 of 7 batches** of the `/m` fixes programme shipped to dev + verified on csjones.

## Where we left off
Mid-programme on CSJ's ~30-item `/m` fix list (planned in `June/June25Updates/m-fixes-plan.md`). **Batches 1–4 are DONE — merged to dev, deployed to csjones, browser/DOM/API-verified.** Stopped at the Batch 5 (Freemium) boundary for end-of-day; Batch 5 is fully mapped and the worktree branch is ready. Prod (fynla.org) is UNTOUCHED — all work is on dev only.

## The programme (single source of truth: `June/June25Updates/m-fixes-plan.md`)
CSJ pasted a `/m`-scoped list grouped into 7 build batches (1 PR per surface, verify each on csjones before next). **Read the plan doc first on resume.**

## What shipped today (all on dev, NOT prod)
- **Batch 1 — Funnel pages (#574):** collapsible "What does this mean?", second "Find out how" CTA at top of allowances, white registration error, shortened calc subtext. `savetax-plan.php` + `savetax-plan-v4.js/.css`. csjones-verified.
- **Batch 2 — Onboarding state machine (#575):** income-first **two bubbles** + **bold** question + "(this includes bonuses and commissions)"; time → single "about 5 minutes"; section-aware verify handoff copy; **removed Gift Aid**; recap→question **BUBBLE_BREAK split** mechanism + income ack; mobile **bold renderer** (`renderFynText` util); spouse advice cut to one line + saving figure; **terminal = "Take me to my tax strategy" button** (route-bubble, not auto-nav). Core live-verified on /m with real xAI (2.1/2.2/2.3/2.4/2.5/2.7); 2.6/2.8/2.9 unit+golden-master verified (CSJ accepted, didn't require live walk to terminal).
- **Batch 3 — Actions single-source (#576):** completed recs **excluded** from list → next-best fills (4.4); wheel "X of Y" = **running tally** (X=banked/tracked, Y=banked+open) — CSJ chose "0 of 4 → 1 of 5"; **per-item unlock labels** ("Unlock pension info"/"Enter your pension details"); removed per-card "X of Y done"; **Tax Strategy page reads `composed_plan.items`** = dashboard's canonical source. **API-verified on csjones: mark-done → 0/4→1/5, completed excluded, "Unlock pension info" filled the slot.**
- **Batch 4 — Tax Strategy page (#577):** "headroom"→"available" + hero figure **green** (spring-600); personalised intro gated on `onboarding_completed`; reduced copy; back-CTA "See all your actions to get more for your money". Live-DOM-verified on csjones.

Full suite **5121 passed / 0 failed** at each batch. Branches m-funnel/m-onboarding/m-actions/m-taxstrategy merged to dev (`a4593ae`).

## What's in flight (NOT done) — Batches 5, 6, 7
- **Batch 5 — Freemium (MAPPED, not started).** Branch `m-freemium` is created off dev (worktree `fynla-m-funnel`, CLEAN/empty). Touches the **LIVE tier system** (on prod), bigger surface:
  - **5.1** "X of Y accounts used" + upgrade link on 4 screens (Savings cap 3, Investment cap 2, Pension cap 5, Protection). Plan: surface `account_limit` via each module API (`DbTierGate::hardLimit($user, ENTITY_KEY)`); free caps in `TierConfigurationSeeder:45`. Iframe break-out to `/settings?tab=subscription` via `(window.top||window).location.href` (see `MobileChrome.vue:292`). Entity keys: `savings_account`/`investment`/`pension_account` (see each Store `::ENTITY_KEY`).
  - **5.2** Fyn says accounts-left on add + at-cap "upgrade" + link. `CoordinatingAgent` already throws+catches `TierLimitExceededException` (e.g. `CoordinatingAgent:2406`); add the count + link to the message.
  - **5.3** NEW tier gate so free users hit Upgrade on **Holistic Plan** (`HolisticPlanningController` has NO tier gate today). **Goals STAYS FREE** (CSJ's explicit call). Web upgrade pattern: `LimitReachedModal.vue:27` → `/settings?tab=subscription`.
- **Batch 6 — Bugs:** 6.1 SaveTax reg-fail → onboarding loses SaveTax entry point (`funnel_answers` dropped when `PendingRegistration` deleted at `AuthController:601`; dispatch matches on `users.funnel_answers` at `AiChatController:676`). 6.2 bank account saved as savings (`SavingsAccountNormaliser:60` defaults unknown type → `easy_access`; should map "bank"/"current" → `current_account`). 6.4 Goals add/edit buttons (mobile `Goals.vue` is read-only). (6.3 edit-details + 6.5 cash-ISA + 6.6 GA: see plan — 6.3 reframed as page-specific dynamic prompt; 6.5/6.6 dropped.)
- **Batch 7 — Cross-surface sweep:** "partner" → "spouse" EVERYWHERE incl. marketing (funnel ~35, web SPA ~85, /m 1) — **KEEP "civil partner"** (47, HMRC term). Careful regex: replace "partner" NOT preceded by "civil ". Confirm live funnel file (`savetax.php` vs `savetax-v2.php`). Plus **D/6.3 edit-details** = page-AND-data-specific Fyn opener (names the actual holdings on the current screen; deterministic client-side).

## Open decisions for CSJ (flagged, not blocking)
- **3.3 nuance:** the dashboard HIDES completed actions (4.4) but the Tax Strategy page shows the full composed plan — so a completed tax action disappears from the dashboard but stays on the Tax Strategy page. CSJ to decide: should the Tax Strategy page ALSO hide completed (true parity), or stay a full overview? (I lean: leave it as an overview.)
- **Desktop-web parity (Rule 19):** the desktop web Tax Strategy components (`resources/js/components/TaxStrategy/*`) carry the same OLD copy as the /m page (headroom, verbose intros). CSJ scoped this list to "the /m route", so I did /m only — flagged for parity, not changed.
- **dev → prod release** of Batches 1–4 is pending — CSJ decides when to ship (never recommend deploy).

## Deploy status
**Deployed to csjones (dev) only. Prod (fynla.org) UNTOUCHED.** csjones is at `a4593ae` (dev): `git pull origin dev` ran per batch + `public/build/` + `public/m-build/` rsynced + cache chain (no route:cache). The eventual prod release of Batches 1–4 is a future `dev → main` PR (CSJ's call).

## Tech debt found this session
Net clean — every batch went through the full suite (5121 green) + targeted unit tests for the new models (running-tally `MobileLevelServiceTest`, exclusion `NextActionsServiceTest`, golden-master corpus resync). No tech-debt-session report written (work shipped + tested + verified). The only debt is the deferred parity item above (desktop web Tax Strategy copy).

## Known issues / blockers
**Nothing broken.** All four batches verified live on csjones. Two recurring TEST-ENV gotchas (not bugs): (1) the worktree's symlinked `vendor` broke `App\` autoloading until I `cp -R`'d a real vendor + `composer dump-autoload` — the worktree now has a REAL vendor (keep it). (2) The Pint formatter drops a freshly-added `use` import if it runs while the import is momentarily unused — re-add the import AFTER the usage exists (hit this twice on `RecommendationTracking`). (3) Insights SEO tests 500 in a worktree lacking a Vite manifest — environmental, resolved once `public/build` exists.

## /m verification gotchas (for Batch 5+ live checks)
- The desktop→/m **token bridge does NOT fire on a cold Playwright navigation** to `/m`. Reliable path: log in on `/m` directly at `/m/app/login` (canonical /m login) → MFA → fetch the 6-digit code via SSH tinker (`EmailVerificationCode` for the user) → land on `/m/app/dashboard`.
- Test user on csjones: **`savetaxb2test@example.com` / `Password1!`** (Hawkeye Pierce — married, non-working spouse, £80k, ISA; MID-onboarding, so `onboarding_completed=false` → 3.2 intro gated off; HAS a tickable tax recommendation now).
- Backend behaviour is fastest to verify via the API with a tinker-minted Sanctum token (how Batch 3 was proven): `curl -H "Authorization: Bearer $TOKEN" https://csjones.co/fynla/api/v1/mobile/dashboard`.

## Next session should
1. **Read `June/June25Updates/m-fixes-plan.md`** (the programme) + this handover.
2. **Start Batch 5 (Freemium)** in the `fynla-m-funnel` worktree on `m-freemium` (off dev, clean, has REAL vendor + built `public/build`/`public/m-build`). Order: 5.1 (account counts + upgrade links across 4 screens) → 5.3 (Holistic gate, Goals stays free) → 5.2 (Fyn count/at-cap messaging). It touches the LIVE tier system — be careful.
3. Per-batch flow (established): build on feature branch → PR to dev → admin-merge (`gh pr merge <N> --merge --admin`) → `git pull origin dev` on csjones + `./deploy/csjones-fynla/build.sh` locally + rsync `public/build/`+`public/m-build/` → cache chain → live-verify (API or /m-login per above).
4. Then Batch 6 (bugs) + Batch 7 (partner→spouse sweep + edit-details).

## Context hints
- Active branch type: **mixed** (main dir on `main`; all feature work in the `fynla-m-funnel` worktree off `dev`).
- main is behind origin/dev by the 12 batch commits (the pending dev→prod release).
- Uncommitted (main dir): only `June/June25Updates/m-fixes-plan.md` (the plan — committed with this handover) + long-standing untracked carry-overs (June15/June19/docs/ — deliberately left, not this session's).
- Worktrees ALIVE: `fynla-m-funnel` (m-freemium, Batch 5 — keep, has real vendor + builds), `fynla-coala` (separate programme — keep).
- Last dev commit: `a4593ae` Merge PR #577 (Batch 4).
