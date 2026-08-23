---
id: W-0391
title: Both spouses' wills state the combined household estate as what that one person leaves — wrong for both, and by 2.3x for one
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: build-lead
reviewers: [quality-lead]
status: handoff
severity: high
surfaces: [web, m]
created: 2026-08-23T00:40:00Z
claimed: 2026-08-23T00:50:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0154, W-0188, W-0137]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, cycle 4, D-15. Local `localhost:8000`.
Accounts **David Jones (16)** and **Sarah Jones (17)**, married, premium.

**Surface:** desktop web, `/estate` → Will Planning tab.

### Actual

Both wills rendered `100% to spouse (£1,716,780)` — the same figure on two
different people's wills. It is the combined second-death household estate the
Inheritance Tax engine models for a married couple, so it counts David's assets
as passing from Sarah to David.

`WillPlanning.vue:512-514` computed it from `netEstateValue`, loaded at `:627`
from `iht_summary.current.net_estate`.

### Root cause

The response `POST /api/estate/calculate-iht` carries **both** figures. The page
read the household one.

- `iht_summary.current.net_estate` = `calculation.total_net_estate` — combined,
  identical for both spouses **by design** (`IHTController:110`).
- `calculation.user_net_estate` — per user, unconditionally set
  (`IHTCalculationService.php:307`), reaching the frontend on the same response.

### Measured, caches cleared before every read (W-0381)

| Mechanism | David (16) | Sarah (17) |
|---|---|---|
| `total_net_estate` (what was shown) | 1,728,780 | 1,728,780 |
| `user_net_estate` | **989,500** | **739,280** |
| `NetWorthAnalyzer::generateSummary` → `net_worth` (`/m` estate screen) | **989,500** | **739,280** |
| `EstateAgent` → `data.summary.net_estate` (mobile dashboard) | 1,489,500 | 739,280 |

Rows 2 and 3 agree exactly: both exclude assets flagged `is_iht_exempt`. Row 4
does not filter that flag and so carries David's **£500,000** of defined
contribution pensions — raised separately as **W-0397**.

**The dispatch's acceptance figure for David (£1,477,500) is the `EstateAgent`
figure at a data state £12,000 lower**, not the per-user estate. **team-lead
corrected the acceptance to £989,500 / £739,280 on 2026-08-23**, ruling that a
pension pot passes by nomination outside the will, so a page headed "100% to
spouse" that counts it overstates what the instrument gives her. The household
data moved after the tester's reading: `savings_accounts` 53/54,
`investment_accounts` 26/27, `properties` 9/19/20 and `dc_pensions` 9 were all
written after 2026-08-21 15:00 in this shared tree.

### What the browser cannot settle

**Sarah owns nothing flagged `is_iht_exempt`, so £739,280 is her figure under
both the correct and the broken reading.** Her agreement proves nothing about
this defect. Every assertion is therefore made on both spouses or on the pair.

## Fix

`WillPlanning.vue::loadNetEstateValue()` reads `calculation.user_net_estate`. No
backend change; no new mechanism; the fallback chain that could reach a household
aggregate is gone — an absent per-user figure now yields £0 rather than the
household's.

The spouse line names its base: `100% of your own estate to your spouse (£X)`.

## Acceptance

- [x] Sarah's will states her own estate; David's states his; the two differ.
- [x] Neither shows the combined household figure.
- [x] The will page agrees with `/api/estate/net-worth`, which is what the `/m`
      estate screen reads — asserted in a test, not by inspection.
- [x] Verified over live HTTP on both accounts with caches cleared:
      989,500 / 739,280.
- [x] **Rendered page read.** David `£989,500`, Sarah `£739,280`, neither page
      showing the household figure. Evidence below.
- [x] **Mobile dashboard agrees for David.** The fence came down mid-batch and
      team-lead instructed this be closed here; **W-0397** is fixed, so all three
      surfaces now read 989,500 / 739,280 for the same user.


### Browser verification — 2026-08-23, localhost:8000, Playwright

**Tab established as nobody** on arrival (both token stores empty) — checked
rather than assumed, and it was the state team-lead warned about. Logged in
through the real form on each account and confirmed identity with
`GET /api/auth/user` before reading anything: **id 16 David Jones**, then
**id 17 Sarah Jones**. `estate_analysis_16` / `_17` cleared by hand before each
read (W-0381).

Read verbatim off `/estate/will-builder`:

| | David (16) | Sarah (17) |
|---|---|---|
| Spouse line | `100% of your own estate to your spouse (£989,500)` | `100% of your own estate to your spouse (£739,280)` |
| Executors | Sarah Jones · Barclays Wealth | **David Jones** · Barclays Wealth |
| Specific Gifts | `£10,000 to Cancer Research UK` | `£10,000 to British Heart Foundation` |
| Residuary | Sarah Jones — 100% | David Jones — 100% |

The two estate figures **differ**, each is its owner's, and **neither £1,728,780
nor £1,716,780 appears anywhere on either page**. Nobody is their own executor.
Every gift names its recipient.

Screenshots:
`tests/Persona/20-08-2026_run/pass-a-web/150-web-david-will-own-estate-989500-executor-sarah-gift-named-W-0391.png`
`tests/Persona/20-08-2026_run/pass-a-web/151-web-sarah-will-own-estate-739280-executor-david-gift-named-W-0391-W-0393-W-0395.png`

## Working notes

- 2026-08-23 build-lead: fixed and mutation-tested. Restoring the household read
  turns exactly the 4 estate-figure cases red and leaves all 3 gift cases green.
  Tests: `resources/js/components/__tests__/Estate/WillPlanning.spec.js` (8),
  `tests/Feature/Estate/IHTPerUserNetEstateTest.php` (4, deliberately asymmetric
  £800,000 vs £350,000 so the per-user figure, the household figure and half the
  household figure are three distinct numbers).
- **Not self-certified.** Handed to quality-lead for the evidence pack.
