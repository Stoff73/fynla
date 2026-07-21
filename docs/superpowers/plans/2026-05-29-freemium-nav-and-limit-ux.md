# Freemium nav + limit UX rebuild — plan

**Branch:** `freemium-nav-limit-ux` (off `dev`)
**Date:** 2026-05-29
**Owner decisions:** CSJ, this session (see §Decisions)

## Problem (diagnosed against live code + SP2 spec)

SP2 freemium shipped the **backend** tier model (`tier_configurations.capability_matrix` + `count_caps`, `DbTierGate`, `estate.full` TeaserGate) but the **frontend nav + route guard were never migrated off the legacy trial-era plan model**. The two contradict each other.

1. **Greyed-dead nav items.** `resources/js/constants/featureGating.js` uses the old plan model (`student/standard/family/pro`) and hard-codes Property/Liabilities/Personal-Valuables/Business as `standard`-locked and Estate/Trusts/Holistic as `pro`-locked. `SideMenuItem.vue` renders a locked item as a **non-clickable greyed `<div cursor-not-allowed>`** with a hover tooltip; the router guard (`router/index.js:~1513`) redirects gated routes to Dashboard. Result: items the agreed matrix says are **Free** are dead, and gated items can't be reached at all.
2. **Wrong tier composition.** Agreed matrix (`docs/superpowers/specs/2026-05-16-sub-project-2-freemium-tier-model-design.md` lines 224–245) + CSJ this session: Property, Liabilities, Personal Valuables (Chattels), Business are **Free** (Property/cash/investments/pensions still count-capped). Estate is **teaser** (tier2+). Letter to Spouse is tier1+. What If Scenarios + Holistic Plan are paid.
3. **Limit UX is "fill form then fail".** At a count cap the Add button still opens the full form; submit → backend 403 (fixed today, PR #424) → frontend just `logger.error` + a red store-error banner; modal stays open. No upgrade modal, no link.

Backend `count_caps` (free): `savings_account:3, investment:2, pension_account:5, property:3, mortgage:10`. Non-count-gated free modules (liabilities/chattels/business) are unlimited.

## Decisions (CSJ, this session)

- **Gated nav click →** reusable teaser/upgrade page per gated module (Estate also shows its IHT-exposure teaser, SP2 §10). Not bespoke per-module content; not modal-only.
- **Add-at-cap →** keep the Add button; clicking at cap opens a limit→upgrade modal (never the empty form).
- **Free tier includes:** Business, Property, Liabilities, Personal Valuables (+ everything already free). What If Scenarios + Holistic Plan stay paid. Letter to Spouse tier1+. Estate teaser (tier2+).

## Slices

### Slice 1 — Limit-reached modal (most acute; ship first)
- **BE:** extend `GET /api/payment/trial-status` (or add `/api/tier/limits`) to return resolved `tier` + `count_caps` for that tier. (Counts come from FE lists; no usage-counting BE needed.)
- **FE:** a small `tierLimits` store/composable holding caps; a reusable `LimitReachedModal.vue` (plain text, no icons — Rule #16) with module name, cap N, and an Upgrade link that opens the existing `PlanSelectionModal`.
- **Wire:** on each capped surface's "Add" click (savings/investments/pensions/property), if `count >= cap` → open `LimitReachedModal` instead of the add form. Keep the existing backend 403 as defence-in-depth (already correct).
- **Acceptance:** Free user at cap clicks Add → modal "You've reached your Free limit of N — upgrade to add more" + working upgrade link; never the empty form; DB unchanged. Browser-verified per surface.

### Slice 2 — Capability-matrix-driven nav + tier composition
- **FE:** replace `featureGating.js` plan model with a capability-matrix tier model (free/tier1/tier2/tier3) resolved from `trial-status.tier`; expose per-route access verb (`full`/`limited`/`teaser`/`none`).
- **SideMenu/SideMenuItem:** every item clickable. Free-full items navigate normally. Teaser/none items navigate to the teaser/upgrade page (carry a small text "upgrade" affordance, no icon-as-glyph). Remove greyed-dead `cursor-not-allowed` rendering.
- **Correct tiers:** Property/Liabilities/Personal Valuables/Business → free; Estate/Will/Trusts/PoA → teaser (tier2+); Letter to Spouse → tier1+; What If/Holistic → paid.
- **Router guard:** stop redirecting gated routes to Dashboard; allow landing on the teaser/upgrade page.
- **Acceptance:** Free user can click every sidebar item; free modules open normally; gated modules open the teaser/upgrade page. No greyed-dead items.

### Slice 3 — Reusable teaser/upgrade page + Estate teaser
- **FE:** generic `TierTeaserView` ("what this does + Upgrade") keyed by module; Estate variant surfaces the IHT-exposure teaser line (reads existing all-tier estate read/IHT endpoints).
- **Acceptance:** each gated module's page shows educational copy + upgrade CTA; Estate shows the IHT teaser.

### Slice 4 — Backend gating reconciliation (defence-in-depth)
- Align `CheckFeatureAccess`/`feature:<plan>` usages and the gated routes to the capability-matrix tier model so UI + backend agree (spec §4.3). Retire the legacy plan hierarchy where it contradicts the matrix.
- **Acceptance:** a Free user bypassing the UI to a gated write route is refused with the structured upgrade payload; free routes (net-worth liabilities/chattels/business/property) are NOT refused.

## Sequencing
Ship Slice 1 → dev → csjones, browser-verify, then Slices 2–4. Each slice = its own PR off `dev`, admin-merge, redeploy, browser-verify (CLAUDE.md Rule #15 loop). Production untouched until CSJ calls the `dev → main` release.

## Out of scope
Mobile nav (separate surface); pricing-page copy; Revolut sandbox upgrade flow (works); any change to `count_caps` numbers (use the seeded values).
