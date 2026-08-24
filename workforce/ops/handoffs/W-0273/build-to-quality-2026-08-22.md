# W-0273 — build-lead → quality-lead

## Done

`calculateCapacityForLoss()` now takes both terms of its ratio from the single
`NetWorthService::calculateNetWorth()` response it was already calling for the
denominator. Two queries and two imports deleted.

Measured: David (16) **£172,500 / 45.1%** (was £220,000 / 48.3%), Sarah (17)
**£132,500 / 17.9%** (was £85,000 / 11.5%).

Defined benefit disclosure added to the factor description and exposed as
`components.has_defined_benefit_pension`; `RiskFactorDetailPage.vue` now renders that
description.

4 new feature tests, including one asserting `pensions_total` is 0 **and specifically
not £700,000** — the ×20 capitalisation must not reappear here.

## Not done, and why

**Nothing in this item.** Browser-verified on both accounts; Sarah's detail page
shows £132,500 + £0 pensions ÷ £739,280 = 17.9% with the disclosure rendered beneath
the £0.

**Whether a guaranteed £35,000/year should raise the capacity-for-loss level.** It
plainly bears on capacity for loss, but the factor has no lever for income and adding
one is a product decision (Rule 16). Flagged to team-lead; interacts with W-0244.

**No recompute or expiry migration for stored `factor_breakdown`** — see below.

## What you need that isn't obvious from the artefacts

1. **The item as dispatched had the wrong diagnosis, and the correction changes what
   "done" means.** It was described as stored rows holding superseded figures. In fact
   `AutoRiskCalculator:129` ran its own `InvestmentAccount::where('user_id', …)` sum
   that W-0238 never touched, so the figures were being **recomputed wrong on every
   page load**. `RiskPreferenceService::getRiskProfile():216-227` recalculates live for
   display, so the stored row never reaches the screen — it is an audit artefact.
   **Verifying the stored row tells you nothing about the fix; verify the page.**
2. **Do not "fix" `pensions_total = 0` for Sarah.** It is CSJ's settled ruling on
   W-0241 — exclude and disclose — and correct twice over here: no capital to place at
   risk, no market risk to lose. The test that asserts £0 and not £700,000 is there to
   stop a well-meaning later change.
3. **The disclosure is READ from `App\Constants\PensionDisclosure`** — I wrote my own
   sentence first and deleted it when the constant landed mid-batch. Never re-type it.
4. **It is its own `disclosure` field, and that is load-bearing, not tidiness.**
   Appended to `description` it rendered **clipped** on the summary card: `line-clamp-2`,
   `scrollHeight` 48 against `clientHeight` 32, measured on the live DOM. All nine
   factors now carry a `disclosure` key (null for eight) so a surface renders it without
   asking which factor it holds. **If you regression-test this, assert on the field, not
   on a substring of the description** — a test looking for the sentence inside
   `description` will now fail, correctly.
5. **Both re-reads are now DONE and measured.** Card disclosure: `clientHeight` 32 =
   `scrollHeight` 32, `webkitLineClamp: none`, `overflow: visible`, uncut at 390 × 844
   too; exactly one of nine cards carries the node, none renders an empty one. `/m`
   retaken on `main-DTjymbsC.js` — 25.3 months, £31,030, £132,500, with identity
   confirmed from `fynla-state.auth.user` rather than from recognising the figures.

## Assumptions I made

- **That `has_db_pensions` from the net worth response is the right disclosure
  trigger.** It answers "does this user hold a Defined Benefit scheme", which is the
  question the £0 raises. It is `calculatePensionBreakdown()['has_db']` arriving in a
  response already being read — not a second flag, and not inferred from a zero.
- **That taking both ratio terms from one response is preferable to calling the
  aggregator separately.** They resolve to the same figures today; one response makes
  them structurally unable to diverge.
- **That I could edit `resources/js/views/Risk/RiskFactorDetailPage.vue`.** It is
  outside my stated exclusive scope and nobody else was scoped to it. I asked
  team-lead and proceeded rather than stall; if the answer comes back no, the Vue
  change is four lines and reverts cleanly without touching the backend.

## Surfaces covered / not covered

- **Web** — code complete, service-level measured, **browser-verified on both accounts** (Sarah through the MFA gate, code taken from the database).
- **`/m`** — no risk screen (W-0279); no change made. `/m` driven end to end for
  W-0271 on the same login.
- **iOS** — same backend; no native risk screen. **Not verified** — say so.
