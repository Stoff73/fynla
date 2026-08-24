---
id: W-0188
title: The two logins still project household estates £103,206 apart — W-0135's two-screen half is fixed, the two-account half is untouched
mission: persona-run-peak_earners-2026-08-20
branch: F-0018
owner: build-lead
status: gated
severity: high
surfaces: [web]
created: 2026-08-22T00:35:00Z
claimed: 2026-08-22T06:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0135, W-0137]
prior_art_note: closed by W-0137 — same mechanism, no separate fix
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 2 re-verification of **W-0135**, local, both persona accounts, read-only.
**Surfaces:** `/estate/inheritance-tax` and `/plans/estate`.

### What IS fixed — verify and keep

**The two-screen half of W-0135 is fixed.** For one user the drill-down and the plan now
agree exactly:

| David | `/estate/inheritance-tax` | `/plans/estate` |
|---|---|---|
| Projected net estate | £4,368,401 | £4,368,401 |
| Projected taxable | £3,848,401 | £3,848,401 |
| Projected tax | £1,539,360 | £1,539,360 |

Same for Sarah across her two screens. **The current-year column is identical on all four
surfaces (£846,780 / £338,712) — no regression.**

### Actual — what is NOT fixed

The two **accounts** still project different households, on all surfaces:

| Figure | David | Sarah | Apart |
|---|---|---|---|
| Projected net estate | **£4,368,401** | **£4,471,607** | **£103,206** |
| Projected taxable estate | £3,848,401 | £3,951,607 | £103,206 |
| Projected Inheritance Tax | £1,539,360 | £1,580,643 | **£41,283** |

**£103,206 is unchanged from before the fix** — it was £103,206 when measured on
2026-08-21 and it is £103,206 now. The tax gap narrowed from £62,000-odd to £41,283 only
because both accounts now apply the same (correctly tapered) allowance to different
estates. **The underlying divergence was not touched.**

It remains proportional rather than fixed-size: it was £88,257 when the household was
one-third entered and £103,206 with it complete.

### Impact

One household, one second-death estate, and the answer depends on who signs in — by
£41,283 of tax. This is W-0154's disease in the projection, after W-0154 was fixed in the
current year. Everything projection-derived inherits it: the taper test, life-cover
sizing, and any gifting strategy quantified against the future estate.

### Notes for whoever takes it

**Likely upstream of W-0137, not independent of it.** The projected asset lines differ per
account because each account projects its own cash to a different place — David's cash
projected to −£1.8m and Sarah's to −£1.09m in the pre-fix measurements. If the projection
has no floor, the two households diverge exactly because their cash does. **Check W-0137
first; this may close with it.**

### Repro

1. `david.jones@example.com` → `/estate/inheritance-tax` → **Expand All** → age-84 Net
   Estate: **£4,368,401**.
2. `sarah.jones@example.com`, same screen: **£4,471,607**.
3. Neither account was written to between the two readings.

### Acceptance

1. Both logins project the same household estate and the same projected tax.
2. The current-year column stays at £846,780 / £338,712 — regression check.
3. Verified in a browser on both accounts and both screens.
