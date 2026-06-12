---
type: handover
mode: context-clear
date: 2026-06-10
session: 2
branch: m-gamification-recommendations
---

# Context Clear Handover — 2026-06-10, Session 2

## Immediate state
The `/m` gamification + recommendations rework is built, committed (23 commits), pushed to `m-gamification-recommendations`, and **deployed + browser-verified on csjones** (`csjones.co/fynla/m`). NOT merged to dev/main, NOT on prod. Last commit `b20d619`. Working tree clean.

## The thread
- Built the whole `/m` dashboard rework per `docs/superpowers/specs/2026-06-10-m-gamification-recommendations-design.md` + plan `docs/superpowers/plans/2026-06-10-m-gamification-recommendations.md`: KYC-gated recommendations across all 6 modules, unified `focus_areas` carousel, planning-progress percentile, achievements panel, Fyn unlock bubble, varied persona seeding.
- Backend (A1–F1) went through the two-stage subagent review (spec + code-quality). Frontend (G1–H1) + a long series of fix commits were verified **live on csjones** (not via the review gates).
- CSJ pushed hard on quality. Three things became LAW this session (see below). The final state satisfies them.

## What the next Claude needs to know (NON-NEGOTIABLE rules CSJ enforced)
1. **`/m` is verified on csjones, NOT locally.** `/m/app` serves the pre-built `public/m-build/` bundle (gitignored, no Vite HMR). There is NO local-mobile build path the guardrail hook allows (`build-ios.sh` is iOS-only / points API at prod). Workflow: build with `./deploy/csjones-fynla/build.sh` → `rsync public/m-build/` + `public/build/` to csjones over `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co:www/csjones.co/fynla-app/public/...` → `git pull` the PHP on csjones → `cache:clear`. The `ssh-fynla` MCP tool is **PROD** — do NOT use it for csjones.
2. **EVERY module shows real recommendations OR the KYC info needed — NEVER "On track"/empty.** A module with insufficient data shows only the KYC prompt for what's required (`NextActionsService::dataNeededItem`). One with enough data shows real engine recs. This is now enforced in `focusAreas`.
3. **Every recommendation deep-links to its module screen** (`navigate /retirement` etc.), never a templated Fyn message. The recs ARE real engine output (`RecommendationsAggregatorService` → module agents) — fixed several engine bugs that made recs fake/empty.

## Engine bugs fixed this session (all real, all in the shared recommendation engine)
- **Estate**: was emitting an unconditional "discretionary trust, save £130,000" (= NRB×40%) for everyone. Now gated on `currentIHTLiability > 0`, saving capped to liability (`ComprehensiveEstatePlanService` ~L955).
- **Retirement**: produced ZERO recs (aggregator read a non-existent `analyze()['recommendations']` key). Now calls `generateRecommendations(analyze()['data'])`.
- **Protection**: (a) recs use a `title` key the aggregator dropped → now mapped; (b) `analyze()` requires a `protectionProfile` the gate didn't → added `protection_profile` blocking check to `ProtectionDataReadinessService` so profile-less users get a real "set up protection details" prompt.
- **Investment**: lazy-load `LazyLoadingViolationException` on `expenditureProfile` (strict mode on csjones, off locally) → `InvestmentDataReadinessService:373` now queries instead of lazy relation.
- Blank-title recs filtered; "Isa" → "ISA" casing; carousel rebuilt as a TRUE one-card-at-a-time carousel (swipe + dots).

## What's NOT done / still to verify
- **G2 (Fyn unlock bubble)** — code shipped, NOT browser-verified. Now MANY personas show KYC-prompt/locked cards, so it's testable (tap a locked card or the bubble → opens pre-seeded Fyn chat).
- **G3 (milestone banner lowered)** — code shipped, NOT browser-verified (needs a *new* milestone to fire).
- **Estate sub-NRB**: currently shows the KYC prompt "complete your estate planning details." CSJ may want estate to ALSO surface will/LPA recs (non-IHT). Deferred — CSJ's call.
- **KYC-prompt specificity**: gate-open-but-empty modules show a GENERIC "complete your <module> details". Could be made field-specific using the module readiness warnings.
- **Two untracked files** (pre-existing, ignore): `docs/mobile/designer-brief.pdf`, `docs/security/security-review-2026-06-09.md`.
- NOT merged to dev. NOT reviewed via final code-review gate. NOT on prod.
- `tech-debt-session` audit NOT run this session; full `vault-sync` deferred (context-clear).

## Pick up from here
1. Browser-verify on csjones (`/m`): mint a persona token (`User::find(<id>)->createToken('t')->plainTextToken` via ssh tinker; persona James = UID **62** on csjones, **732** local), set `localStorage m_scaffold_token`, load `/m/app/dashboard`. Verify: (a) the **Fyn unlock bubble** appears + tapping a locked/KYC card opens pre-seeded Fyn; (b) the **milestone banner** sits below the hero when a milestone fires.
2. Get CSJ's decision on **estate will/LPA recs** for sub-NRB users.
3. When CSJ approves: run the final code-review gate, then the dev → (eventually) prod path. The branch is `m-gamification-recommendations`, currently the checked-out branch on csjones.

## Browser-test recipe (csjones)
- Mint token: `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co 'cd ~/www/csjones.co/fynla-app && php artisan tinker --execute="echo \App\Models\User::where(\"email\",\"preview_young_family@fynla.local\")->first()->createToken(\"t\")->plainTextToken;"'`
- In Playwright: navigate `https://csjones.co/fynla/m/app/dashboard` → it redirects to `/fynla/login` → `localStorage.setItem('m_scaffold_token', '<token>')` → navigate to dashboard again. Clear SW/caches between bundle changes.
