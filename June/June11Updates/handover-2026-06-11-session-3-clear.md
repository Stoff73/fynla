---
type: handover
mode: context-clear
date: 2026-06-11
session: 3
branch: dev
---

# Context Clear Handover — 2026-06-11, Session 3

## Immediate state
SaveTax `/savetax/plan` allowance-card corrections are **complete, committed to dev, deployed to csjones, and verified live on `/m`**. Working tree clean (dev @ `b7dfa96`, up to date with origin; only the two long-standing untracked docs remain). Nothing in flight.

## The thread
The session iterated on the public **`/savetax/plan` result page** allowance cards (server-rendered `public/pages/savetax-plan.php` + `public/pages/js/savetax-plan-v4.js`, math in `app/Services/Marketing/SaveTaxEstimateService.php`; `/m` iframes this exact page, so web + `/m` are one surface). Five corrections, in order:
1. `fb98583` — merged the standalone "60% Tax Trap" card into the tapered Personal Allowance card; first cut of the gating + non-earner spouse allowances + £3,600 config key.
2. `2a7220d` — full spouse per-person parity: added Spouse PSA / Dividend / CGT (the spouse was missing three per-person allowances the primary had).
3. `dc46864` — **corrected the PA-vs-Pension-AA gating** (I'd put it on the wrong card — CSJ corrected me, twice). See canonical logic below.
4. `b7dfa96` — ISA cards now show a plain "A tax-free account — your savings and investments grow free of tax." description, no figures.
Also early in the session: the Track 2 coala-integration spec was written across 4 revisions (`43ba6cf`→`9248d81`) — see Outstanding.

## Files touched (committed this session)
`app/Services/Marketing/SaveTaxEstimateService.php`, `public/pages/js/savetax-plan-v4.js` (now `?v=9`), `public/pages/savetax-plan.php`, `tests/Unit/Services/Marketing/SaveTaxEstimateServiceTest.php` (16 tests / 92,853 assertions incl. the 1,536-combo sweep), `database/seeders/TaxConfigurationSeeder.php` (new `pension.relevant_earnings_minimum = 3600`), `docs/superpowers/specs/2026-06-11-track2-coala-integration-design.md`.

## What the next Claude needs to know
- **CANONICAL savetax allowance-card logic (CSJ, do not get backwards):**
  - **Personal Allowance** — a working earner's PA is auto-used by salary → **greyed**. Shown ON **only** for £0 income (non-earner, still unused) **or** the £100k–£125,140 band (tapering, reclaimable via pension). In the trap band it's labelled "(tapered)" and carries the 60% trap "could save" callout.
  - **Pension Annual Allowance** — for pensions, NOT income. **Always shown**: £60,000 for a worker, £3,600 (£2,880 net) for a non-earner. No gating.
  - The funnel's **primary income question has no £0 band** — only the spouse can answer "No income", so every "£0" rule is spouse-only by construction.
  - Spouse mirrors every primary per-person allowance (PA, Starting Rate, PSA, ISA, Pension AA, Dividend, CGT) with the same gating. All values via `TaxConfigService` (Rule #2).
- **The savetax work was committed DIRECTLY to dev (my mistake) — a clean retroactive PR is impossible.** GitHub branch protection **hard-rejects force-push** on `dev` (`protected branch hook declined`), so the commits can't be rewound onto a feature branch. CSJ asked for a "PR to dev" 3×; it cannot be delivered cleanly. **Fix going forward: branch BEFORE committing savetax-type work.** A local `savetax-allowance-cards` branch (= `b7dfa96`) exists, unpushed, as a record.
- **Deploy pattern reminder:** csjones serves `public/pages/*` from its own `git pull` — pushing to the dev branch does NOT update the dev *server*. Deploy = `ssh csjones … git pull origin dev` + reseed if a config key changed + `cache:clear`/`view:clear`. **Never `optimize`/`route:cache`** (see `reference_route_cache_shadows_homepage.md`). The `/m` iframe verification needs cookies/localStorage cleared (stale auth bounces public pages to /login).
- **Intermittent DNS blips on this machine** mid-session — `git push` failed twice with "Could not resolve host: github.com" then succeeded on retry. Just retry.

## Pick up from here
1. Nothing pending on savetax — it's done and live. If CSJ raises another allowance tweak, the logic + tests are in `SaveTaxEstimateService::allowances()` / `SaveTaxEstimateServiceTest.php`.
2. **Track 2 (coala) is the open thread**: spec at `docs/superpowers/specs/2026-06-11-track2-coala-integration-design.md` (v4, rebuilt on CSJ's canonical agent flow — see `feedback_coala_agent_flow_canonical.md`). It's **awaiting CSJ review**; once approved, the next step is `superpowers:writing-plans` (task #13). Do NOT start coala build work before CSJ approves the spec.
