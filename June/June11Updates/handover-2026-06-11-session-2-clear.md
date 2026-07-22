---
type: handover
mode: context-clear
date: 2026-06-11
session: 2
branch: dev
---

# Context Clear Handover — 2026-06-11, Session 2

## Immediate state
Session ended cleanly at the wrap — nothing in flight. dev @ `857ea2a` (11 commits this session), pushed; **csjones deployed + verified @ `857ea2a`** (both bundles). NOT on prod. Suite was verified fully green this session (4,541 passed / 0 failed).

## The thread
Four work blocks, each completed and live-verified on csjones:
1. **Both session-1 chips delivered.** The 24 pre-existing suite failures cleared (`daa45dd` + `367d207`) — root causes: missing `tier_configurations` seeds (13 tests — `forTier()` `firstOrFail`s on create AND update via SnapshotPolicies), a REAL prod-class bug (`NetWorthService::getJointAssets` lazy-loaded `jointOwner` 5× → live 500 on `/api/net-worth/joint-assets`, fixed with eager loading), insights-featured test realigned to the newer 1dba112 fallback contract, completeness test now seeds the (deliberately, PR #526) required ProtectionProfile, arch rules scoped/allowlisted per their own documented escape hatches. GiftFactory rewritten to live schema enums (`095ccb6`).
2. **CSJ's new standing law codified: CLAUDE.md Rule #19** — every instruction applies to the `/m` pathway (mobile-web iframe build, NOT the iOS build) unless excluded; "done" = web AND /m verified. Memory: `feedback_m_pathway_parity_default.md`. PR #528 audited for /m parity → two real gaps fixed (`909627d`): `moduleRoute('tax')` deep-link + tax `openFynForCapture` prompt; verified live on /m (Playwright + server tinker).
3. **"/m landing regression" root-caused: `php artisan optimize`/`route:cache` makes the SPA catch-all shadow the server-rendered `/` homepage** (despite the `.+` guard) — guests AND the /m iframe (loads `/`) got the SPA shell with the pre-#521 LandingPage design. Files were never wrong; routing was. Fixed live (`route:clear`), banned durably (`13121a2`): DEPLOY.md + both build.sh + CLAUDE.md Troubleshooting + memory `reference_route_cache_shadows_homepage.md`. Deploy chains now end `config:cache` (config MUST stay cached — forge-creds rule). Prod checked: NOT affected. Savetax funnel + campaign logic verified intact in-frame (step 1 → 2 clicked).
4. **PR #527 merged** (Phailanx gamified web dashboard, `7c1d75c`) with two in-merge repairs: (a) it consumed a non-existent bucketed `recommendations` payload key → rewired to the canonical `focus_areas` aggregation, unlocks→Fyn / recs→web-route deep-links, design untouched (`e21862a`); (b) Dashboard.vue was UTF-8-as-CP1252 double-encoded (18 chars + BOM) → iconv round-trip repair (`d9385ce`). Verified live as john: Level 3 header, 79% percentile, Save tax "2 actions" / Locked / Locked tabs, two tax strategy unlocks listed, unlock click opens Fyn dock, /m + homepage unaffected.

## Files touched (all committed + pushed)
dev @ `857ea2a`, working tree clean. Untracked `docs/mobile/designer-brief.pdf` + `docs/security/security-review-2026-06-09.md` pre-date the session, deliberately left (carried from session 1).

## What the next Claude needs to know
- **NEVER `php artisan optimize` / `route:cache` on Fynla servers** — see Rule in CLAUDE.md Troubleshooting + `reference_route_cache_shadows_homepage.md`. Deploy chains end with `config:cache`. Smoke tests grep CONTENT (`grep -c "Get started for free"`), not just 200.
- **The /m SPA ships in a separate `public/m-build/` bundle** — uploading `public/build/` alone leaves /m stale (bit us once this session; deploy note records it).
- **Legacy deploy docs (`deploy/DEPLOYMENT_v0.6.2.md`, `deploy/DEPLOYMENT_FYNLA_ORG.md`) still contain route:cache/optimize instructions** — superseded by DEPLOY.md but a trap if followed. Tech-debt item in CSJTODO.
- Web gamified dashboard design notes (flagged to CSJ, not changed): only 3 areas (Save tax/Retirement/Savings) have tabs — protection/investment/estate/goals recs surface on /m + module pages only; unlock rows open the Fyn dock without a preset capture message (web dock has no programmatic send, unlike /m); unlock rows still render a no-op checkbox + Skip (cosmetic).
- Insights-featured judgement call (flagged in session, CSJ hasn't objected): test realigned to the May fallback contract (`1dba112`, CSJ-merged) over the April no-fallback decision (`5d3ac7f`). Flip back controller+test together if CSJ prefers the April behaviour.
- gh CLI merge endpoint still 401s on this machine — local merge commit + push remains the workaround (used for #527).
- Vault June11Updates mirror was missing at session start (session-1 handover was repo-only) — fixed this wrap; check it stuck.

## Pick up from here
1. **CSJ re-tests the Azlan journey on https://csjones.co/fynla** (carried from session 1 — still the acceptance moment; csjones now also has the gamified web dashboard + all today's fixes).
2. **CSJ eyeballs the gamified web dashboard** on csjones (`/dashboard` as a real user) — accept or redirect the two design notes above.
3. Then: Track 2 (coala) planning from spec §7 (brainstorm with CSJ — design mostly settled in the spec), or the dev → main release call (CSJ's; auth throttle #489 still the standing prod reason).
