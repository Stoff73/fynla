---
type: handover
mode: end-of-day
date: 2026-06-09
session: 1
branch: dev
previous_session: 2026-06-08 session-3 (context-clear)
---

# Handover — 2026-06-09, Session 1

## Where we left off
The freemium tier-pricing work (PR #501) and the `/m` admin router cold-boot fix (PR #500) are **merged to `dev` and live on csjones**, browser-verified. The day ended on a clean deploy — `dev` @ `87f2e4d`, working tree clean. Nothing is mid-edit. The one open decision is the **prod release** (`dev → main`), which is large and is CSJ's call.

## What shipped today (13 PRs, #489–#501)
Earlier sessions (1–3) — already handed over in `June/June8Updates/handover-2026-06-08-session-3-clear.md`:
- **#489** auth throttle — per-endpoint rate-limit buckets (fixes prod MFA password-reset 429 collision)
- **#490** net-worth joint asset co-owner name display
- **#491** tax-config admin save fix + FSCS £85k→£120k via TaxConfigService
- **#492–495** savetax suite — dynamic tax-saving math service, accurate £125,140 bands + tapered PA, Marriage Allowance gating, 60% trap as a distinct allowance
- **#496–497** mobile `/m` — full-bleed dashboard, grouped drawer nav, cross-SPA admin auth bridge
- **#498** savetax onboarding — section-led configurable flow + per-section tax-engine advice
- **#499** removed the onboarding dashboard-blur mechanic

This session (4) — the freemium + deploy work:
- **#500** `fix(router): hydrate user before role gating on cold boot` — `/m` Admin link no longer bounces to landing on first hop
- **#501** `feat(freemium): tier-driven upgrade modal + remove trial timer`:
  - `PaymentController::plans()` now builds purchasable plans from `TierConfigurationStore` (SSOT) — feature bullets derived by diffing each tier's `capability_matrix` vs the tier below (`tierFeatureBullets()`)
  - `PlanSelectionModal.vue` — tier-keyed ordering + "Most Popular" on tier2, penny prices (`formatCurrencyWithPence`), launch banner only when a real launch price exists
  - `CheckoutPage.vue` — fixed tier-resolution (`response.data.data` from `/pricing-config`)
  - `AppNavbar.vue` — removed the legacy free-trial timer
  - Public `/pricing` (`public/pages/pricing.php` + `pricing.js`) + `PricingPage.vue` + `faqData.js` rewritten to Free + Tier 1/2/3, all trial copy stripped
  - **Mojibake fix** — corrected pre-existing double-encoded em-dashes in the pricing page `<head>` (title/og/twitter/JSON-LD)

## What's in flight (NOT done)
- **Prod release** — not started; CSJ's call. See `June/June9Updates/deploy-2026-06-09.md`.
- Real tier prices — `tier_configurations` currently holds placeholders (£4.99/£14.99/£29.99 monthly). Set real values in the admin Tier Configuration screen; modal + checkout + public page read them live.

## Deploy status
**Deployed to dev (csjones.co/fynla)** — #500 + #501 built, uploaded (`public/build/`, manifest md5 match `bb5a542`, preserve-old-chunks), cache-cleared, **live-verified** on `https://csjones.co/fynla/pricing` (Free+Tier1/2/3, penny prices, billing toggle, title em-dash fixed). **Prod NOT released** — full runbook + freemium adds in `June/June9Updates/deploy-2026-06-09.md` (base: `June/June7Updates/deploy-2026-06-07.md`).

## Tech debt found this session
- Orphaned build chunks on csjones `public/build/assets` now ~1009 (preserve-old-chunks accumulation across deploys) — harmless; optional sweep.
- `TestUsersSeeder` recreates `trialing` subscriptions for john/jane/sarah on every `db:seed` — a freemium-era artifact (pure freemium has no trials). Cosmetic for tests, but worth aligning the seeder to `tier='free'` eventually.
- CLAUDE.md metric drift (minor): PHP Services counted 351 vs documented 350. +1, not worth a standalone edit.

## Known issues / blockers
- **Prod MFA password reset is broken** until #489 ships (per-IP throttle collision). The auth-throttle fix is in `dev`, prod-relevant — strongest single reason to schedule the prod release.
- Revolut checkout cannot complete on **localhost** (`"host must not be equal to localhost"`) — environmental; works on csjones/prod. Not a bug.

## Rules reinforced this session
- Scope vs. visible defects: fixed a pre-existing mojibake `<title>` on the page #501 rewrites rather than only reporting it — flagged it explicitly in the PR (not silent). See CLAUDE.md Working Style.
- Deploy isolation: the `mcp__ssh-fynla` MCP targets **production** — must NOT be used for csjones; csjones is reached via the passphrase-keyed `~/.ssh/fynlaDev` over plain `ssh`/`rsync`. (Worth a memory if it recurs.)

## Next session should
1. If releasing prod: open the `dev → main` release PR, build with `./deploy/fynla-org/build.sh` (root base path — never csjones settings), follow `June/June9Updates/deploy-2026-06-09.md` + the prod-drift reconciliation, monitor `storage/logs/laravel.log` 10–15 min. The auth-throttle #489 is the priority reason.
2. Set real tier prices in the admin Tier Configuration screen (placeholders currently live).
3. Optional: align `TestUsersSeeder` to `tier='free'` (drop the trialing subs).

## Context hints
- Active branch: `dev` (mainline integration branch)
- dev vs origin/main: **+158 / −7**
- Uncommitted: none (working tree clean; one unrelated untracked file `docs/mobile/designer-brief.pdf` left as-is)
- Last commit: `87f2e4d Merge pull request #501 from Stoff73/freemium-tier-pricing-modal`
- csjones ssh-agent holds `fynlaDev` for this session only — next session must `ssh-add ~/.ssh/fynlaDev` again before any dev deploy
