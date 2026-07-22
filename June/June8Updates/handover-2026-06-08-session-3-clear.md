---
type: handover
mode: context-clear
date: 2026-06-08
session: 3
branch: dev
---

# Context Clear Handover — 2026-06-08, Session 3

## Immediate state
Just finished **removing the onboarding dashboard-blur mechanic** (#499) — merged to dev, SPA rebuilt + uploaded to csjones, and verified live on csjones (fresh onboarding user → dashboard fully un-blurred, `filter: none`). The entire savetax onboarding thread is complete and live on csjones. Working tree clean.

## The thread (what this session did)
A long, productive session — all merged to `dev` and deployed to csjones. In order:

1. **Auth throttle fix (#489)** — root cause: Laravel inline `throttle:N,1` shares ONE per-IP bucket across all unauthenticated `/auth/*` routes (key = `sha1(domain|ip)`, no path/limit), so an MFA password-reset throttled itself → 429 on first attempt. Fix: per-endpoint named limiters `auth-3/5/10` keyed by `path|ip`. Memory: `reference_inline_throttle_shares_per_ip_bucket.md`. **Prod-relevant** (MFA users on prod still affected until released).
2. **Joint co-owner name (#490)** — net-worth PropertyCard now shows "Joint with <name>"; `NetWorthService::getJointAssets()` co_owner resolution fixed (was hard-coded null). Verified live on chris's "19 Worth Court" ("Joint with wife").
3. **SaveTax dynamic math (#492)** — new `SaveTaxEstimateService` (all values from TaxConfigService, Rule #2): income bands assume the upper bound, pension relief via an exact income-tax engine, ISA/dividend/CGT/PSA, spouse-£0 transfer levers. Funnel reworked: new income bands, auto-advance, back-with-highlight, single Continue on the last screen; plan page computes server-side + "average, not personal" disclaimer.
4. **Accurate £125,140 boundary + tapered-PA display (#493)** — top bands split at the real HMRC additional-rate threshold (was a round £130k); plan shows "Personal Allowance (tapered)" with an explanatory note. (CSJ said "£125,500"; I used the accurate £125,140 and flagged it — he didn't object.)
5. **Marriage Allowance gating + exhaustive test (#494)** — MA only for basic-rate recipients (was wrongly shown to higher-rate earners). Added an exhaustive test over ALL 1,536 answer combinations (71k assertions) proving allowance on/off + math correctness.
6. **60% Tax Trap as a distinct item (#495)** — surfaced as its own "60% Tax Trap" row (was buried on the Pension AA row; vanished if user had a pension).
7. **Section-led configurable onboarding (#498)** — savetax campaign onboarding is now income-first, DOB deferred to the pensions section, unheld sections skipped, with **per-section advice turns** drawn from `TaxStrategyCalculator` (Fyn-phrased). Sequence driven by ONE array: `OnboardingStateMachine::CAMPAIGN_SECTION_ORDER` (reorder there = reorder the journey). New `turn_type: 'advice'` (auto-advancing). 348 onboarding tests green; fully walked live on local + csjones.
8. **Dashboard-blur removal (#499)** — the blur only un-blurred at profile-review pauses, which the new flow bypasses, so it persisted. Removed entirely per CSJ. Frontend → needed SPA rebuild + upload to csjones (done).

Also verified two **pre-merged** fixes live on csjones during the run: `fix-taxconfig-admin-edit` (admin Tax Settings save button works — Edit→Save→"updated") and `fix-mobile-admin-link` (the `/m` Admin link auth-bridge — desktop boots authenticated, no bounce to landing). Ran `TaxConfigurationSeeder` + `SavingsActionDefinitionSeeder` (FSCS) on csjones.

## Files touched (all committed via PRs, merged to dev)
Backend: `app/Providers/RouteServiceProvider.php`, `routes/api.php`, `app/Services/Marketing/SaveTaxEstimateService.php`, `app/Services/NetWorth/NetWorthService.php`, `app/Services/Onboarding/OnboardingStateMachine.php` + `OnboardingChatDirector.php`, `app/Http/Controllers/Api/AiChatController.php`. Frontend: `public/pages/savetax*.php` + `js/savetax*.js`, `resources/js/components/NetWorth/PropertyCard.vue`, `resources/js/layouts/AppLayout.vue`, `resources/js/store/modules/aiChat.js`. Tests: auth throttle, net-worth co-owner, savetax math (exhaustive), onboarding section-flow + advice content. Spec: `June/June8Updates/savetax-math-spec.md`.

## What the next Claude needs to know
- **csjones is fully deployed + verified; prod (fynla.org) is NOT.** `dev` is ~149 ahead / 7 behind `main`. A prod release (dev→main→fynla.org) is CSJ's call and is the big outstanding item. The throttle fix (#489) is genuinely prod-relevant (MFA reset broken on prod until then).
- **csjones SPA was rebuilt this session** (blur removal). Active bundle `AppLayout-CuOoMRf9.js` is clean. Frontend changes to csjones need `./deploy/csjones-fynla/build.sh` + `rsync public/build/` (NOT just git pull).
- **The savetax campaign sequence is reorderable from `CAMPAIGN_SECTION_ORDER`** — don't rewire `next` pointers; edit that array.
- Income band keys are now `upto_50270 / 50271_100000 / 100001_125140 / over_125140` (+ spouse `zero`). Old keys (`personal-allowance/basic/higher/additional`) are gone from funnel contexts (only tax-calc engines use those band names — not a regression).
- Don't re-propose the £130k boundary — it's £125,140 (accurate) by decision.

## Pick up from here
1. **Decide the production release** (`dev → main → fynla.org`) — CSJ's call. If yes, the throttle fix + co-owner + whole savetax suite + gamification ride along. Runbook for the gamification piece: `June/June7Updates/deploy-2026-06-07.md`; note the savetax SPA changes need a prod build too.
2. Optional follow-ups (all minor, flagged this session):
   - `/m` Admin link lands on `/dashboard` not `/admin` on the very first hop (admin-route-guard cold-boot race; authenticated + `/admin` reachable on next nav). Could file a small fix.
   - Stale unreferenced `AppLayout-*.js` chunks on csjones (4) from accumulated deploys — harmless, sweep when convenient.
   - chris@fynla.org csjones password still unknown (reset blocked by safety guard) — wasn't needed this session (browser held his session; admin checks done).

## Context hints
- Active branch: `dev` (mainline)
- Behind origin/main by: 7 ; ahead by: ~149
- Uncommitted: none — working tree clean (untracked `docs/mobile/designer-brief.pdf` is not mine, leave it)
- Last commit: `99c8e64` Merge PR #499 (remove dashboard-blur mechanic)
- csjones: fully deployed + verified live; fynla.org: NOT deployed
