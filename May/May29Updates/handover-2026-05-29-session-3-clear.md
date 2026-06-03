---
type: handover
mode: context-clear
date: 2026-05-29
session: 3
branch: dev
trigger: context-handover skill (tripwire ~835k tokens)
---

# Context Clear Handover — 2026-05-29, Session 3

## Immediate state
Freemium nav + limit-UX rework (PRs #424/#425/#426) is **shipped to dev and deployed to csjones, browser-verified**. CSJ then **rejected** the "Upgrade" text tag I added to the sidebar nav items — that was MY invention, not in the spec or asked for. The **literal next action** is to remove it.

## The thread
1. Started this session resuming from session-2 handover: merged PR #423 (pure-freemium) → dev, deployed to csjones, ran `freemium:convert-trial-users` (9 users → Free), browser-verified the Free journey.
2. Smoke test surfaced a real bug: Free savings cap returned **500 not 403** — `SavingsController` had a `catch (TierLimitExceededException)` but never imported the class. Fixed + regression test → **PR #424** (merged, deployed).
3. CSJ then raised THREE issues: (a) limit hit = "fill form then fail" with no message, (b) greyed-dead nav items, (c) Property/Liabilities/Business/Personal Valuables wrongly gated when they're Free. Diagnosed root cause: **SP2 freemium frontend nav was never migrated off the legacy plan gating** (`featureGating.js` student/standard/family/pro + `SideMenuItem` greyed-dead div + router guard).
4. Got CSJ decisions: gated nav → reusable teaser/upgrade page; at-cap → limit modal on click; Free includes Business/Property/Liabilities/Personal Valuables; What-If + Holistic = **Tier 2+**; cadence = build all slices then deploy together.
5. Built + browser-verified Slices 1–4 → **PR #425** (merged, deployed). Then fixed a guard race (direct-load/refresh of gated URL skipped teaser) → **PR #426** (merged, deployed). csjones serving `app-CffbX23c.js`.
6. **CSJ's final message (UNADDRESSED): "why place 'upgrade' in the sidemenu? where did you get this design decision from? remove all 'upgrade' from the sidemenu!!!!"** — I agreed; the tag was unjustified. Tripwire fired before I could remove it.

## Files touched this session
All committed + merged to dev (PRs #424/#425/#426). Nothing uncommitted (only pre-existing untracked `docs/mobile/designer-brief.pdf` — NOT mine, leave it).
- Backend: `app/Http/Controllers/Api/SavingsController.php` (import fix), `app/Http/Controllers/Api/PaymentController.php` (trial-status → count_caps + capability_matrix), `routes/api.php` (ungate free modules), `database/seeders/TierConfigurationSeeder.php` (what_if/holistic = tier2+).
- Frontend: `resources/js/mixins/tierLimitMixin.js` (new), `resources/js/components/Shared/LimitReachedModal.vue` (new), `resources/js/constants/tierAccess.js` (new), `resources/js/views/TierTeaserView.vue` (new), `resources/js/components/SideMenu.vue`, `resources/js/components/SideMenuItem.vue`, `resources/js/router/index.js`, `resources/js/views/NetWorth/CashOverview.vue`, `resources/js/components/NetWorth/PropertyList.vue`, `resources/js/components/NetWorth/InvestmentList.vue`.
- Tests: `tests/Feature/Savings/SavingsApiTest.php`, `tests/Feature/Payment/SubscriptionStatusTest.php`.
- Plan: `docs/superpowers/plans/2026-05-29-freemium-nav-and-limit-ux.md`.

## WIP commit
- None — tree clean, all work merged to dev. No snapshot commit needed.

## Open decisions
- None blocking. The "remove Upgrade tag" instruction is unambiguous — just do it.

## Pick up from here (auto-continue contract)
1. **FIRST, do this — CSJ's explicit instruction:** Remove the "Upgrade" text tag from the sidebar. In `resources/js/components/SideMenuItem.vue`, delete the `<span v-if="!collapsed && gated" ... >Upgrade</span>` block in the router-link branch. The `gated` prop can stay defined (harmless) or be removed along with the `:gated="isGated(...)"` bindings in `SideMenu.vue` — simplest is to just delete the `<span>` so NO upgrade text shows in the nav. Gated nav items must remain **clickable** (they still route to `/teaser` via the router guard — that behaviour stays). Net effect CSJ wants: every nav item looks normal and clickable; the upgrade messaging lives ONLY on the teaser page they land on, never in the sidebar.
2. Branch off dev (`git checkout -b fix/remove-sidebar-upgrade-tag dev`), make the edit, `./vendor/bin/pint` is N/A (Vue), browser-verify on localhost as `john@example.com`/`password` (resolves to Free; local MFA code via `php artisan tinker --execute="\$u=\App\Models\User::where('email','john@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id',\$u->id)->latest()->first()->code;"`): confirm sidebar shows NO "Upgrade" text on Estate/Trusts/etc., items still clickable, Estate still routes to /teaser.
3. PR → dev, admin-merge (`gh pr merge <N> --merge --admin`), rebuild (`./deploy/csjones-fynla/build.sh`), upload `public/build/` to csjones, ssh `git pull origin dev && optimize`, re-verify on csjones.

## What the next Claude needs to know
- **The "Upgrade" tag was an unprompted design decision — CSJ rightly rejected it. Do NOT reintroduce upgrade affordances in the sidebar.** The agreed design (CSJ this session + SP2 spec): nav items clickable → land on teaser/upgrade page OR normal module. Upgrade messaging belongs on the teaser page only.
- The capability-matrix gating itself is correct and CSJ-approved — only the visual "Upgrade" tag in the nav is unwanted. Keep `isGated()`/router-guard→teaser; just remove the visible tag.
- Local `john@example.com` resolves to **Free** (tier=null) but his subscription row is `trialing/standard` — `TierResolver` still returns `free`. He has 7 savings (over cap 3) so the limit modal fires; investments page shows a stale "expired subscription" state (legacy artifact, separate issue).
- csjones smoke user `freemium-deploy-smoke-29may@example.com` / `Password1!` (id 71) is a real Free user with 3 cash accounts at cap — good for csjones re-verification.
- **Flagged follow-ups (CSJ aware, NOT started):** (a) pension limit modal not wired — `pension_account` cap counts DC only but add form is generic; (b) tier precision — Tier1 user can reach What-If/Holistic *write* routes via legacy `feature:` middleware defensive-allow (should be Tier2+); (c) Settings family-tab (`SettingsTabBar`/`FamilySettings`) still uses `featureGating.js` plan model and Family is Free → wrong gating.
- `featureGating.js` is now only used by the 2 Settings family components; `tierAccess.js` replaced it for nav + router.
- Vite :5173, Laravel :8000. Don't `pkill -f vite`.

## Branch / deploy state
- Branch: `dev` (in sync with origin, 0/0).
- Deploy status: **Deployed to dev + csjones** (`app-CffbX23c.js`). TierConfigurationSeeder reseeded on csjones (what_if/holistic keys live). **Production (fynla.org) UNTOUCHED** — CSJ's call for the `dev → main` release.
- The sidebar "Upgrade" tag is live on csjones and must be removed next session (above).
