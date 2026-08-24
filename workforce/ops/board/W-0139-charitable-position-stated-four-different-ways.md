---
id: W-0139
title: The charitable position is stated four different ways across two screens — Sarah is told she gives 0% on a page that deducts her £10,000, and the household's £20,000 is never recognised
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web]
created: 2026-08-21T20:35:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0020, W-0132, W-0154]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local, both persona accounts.

**Surfaces:** `/estate` (Charitable Bequest card), `/plans/estate` (Charitable Giving
panel), and the server calculation behind both.

Extends **W-0020**, which made the Inheritance Tax rate read recorded bequests, and
**W-0132**, which found the rate label reading a user toggle instead. This item is the
third face of the same disagreement: what the user is told they have given.

### Expected

The household holds **two** charitable legacies, both live:

```
bequests.51  user 16  Cancer Research UK        specific_amount  £10,000
bequests.50  user 17  British Heart Foundation  specific_amount  £10,000
```

On the household second-death estate the application models, the charitable total is
**£20,000**, the baseline is net estate − nil rate band, and both spouses should be told
the same thing about the same household.

### Actual

Six statements, from two screens and two accounts, read within minutes of each other:

| Where | Says | Should say |
|---|---|---|
| Server `charitable_deduction`, **both** accounts | £10,000 | £20,000 |
| `/estate` David — "Leave **£73,428**+ to charity to reduce your Inheritance Tax rate?" | threshold £73,428 | £73,428 ✓, but asked as though nothing had been given |
| `/estate` Sarah — "Leave **£58,428**+ to charity…" | threshold £58,428 | £73,428 — hers is computed off an un-reduced nil rate band (W-0154) |
| `/plans/estate` David — "Current Charitable Rate **1.6%**" | 1.6% | 1.62% of **his own** net estate; the server's own `charitable_giving_percent` is **0.81%** of the household's |
| `/plans/estate` Sarah — "Current Charitable Rate **0%**" | **0%** | She has a live £10,000 legacy, and the same page deducts £10,000 from her taxable estate |
| `/plans/estate` David — "Shortfall to Qualify £51,975 · Potential Saving £24,790" | | 10% and 4% of **David's individual net estate**, not of the statutory baseline |

**Sarah is told she gives nothing to charity by a page whose own tax figure includes her
gift.** `/plans/estate` shows her Current Charitable Rate as 0% and her taxable estate as
£224,280 — which is £1,234,280 − £1,000,000 − **£10,000**.

**Neither account ever sees £20,000.** Each counts only the logged-in spouse's legacy
against a household estate, so the 10% test — the test that decides between 40% and 36% —
runs against half the true charitable total on both accounts.

**No charitable row appears in the estate table on either account** (the word
"charitable" does not occur on `/estate/inheritance-tax` at all), so the deduction that
is applied is invisible where it is applied. That is W-0134's second arithmetic failure
and W-0132's toggle gate; recorded here for the cross-reference.

### Impact

W-0020 exists so that a recorded charitable legacy counts. It counts once, halved,
described differently on every screen, and denied outright to one of the two people who
made it. A user deciding whether to increase a legacy to reach the reduced rate is given
a threshold that changes with who is logged in (£73,428 or £58,428), a shortfall computed
from the wrong base (£51,975), and a starting position that is either 1.6% or 0%.

For this household the rate outcome is unaffected — £20,000 is still short of £73,428 —
so the harm today is advice quality rather than a wrong tax figure. On a household whose
legacies sit near the threshold, halving the total changes the rate.

### Repro

1. `david.jones@example.com`, premium, married, spouse accepted. `/estate` → read the
   Charitable Bequest card: "Leave £73,428+…". `/plans/estate` → Charitable Giving panel:
   "Current Charitable Rate 1.6%", "Shortfall to Qualify £51,975".
2. `sarah.jones@example.com` → `/estate`: "Leave £58,428+…". `/plans/estate`: "Current
   Charitable Rate **0%**", while the same page's taxable estate is £10,000 below net
   estate less allowances.
3. `php artisan tinker` → both `bequests` rows are live and £10,000 each.

### Acceptance

1. One charitable total for the household — £20,000 — used by the rate test, the
   deduction, the threshold and every screen (Rule 20: one source, not four).
2. The threshold is the statutory baseline (net estate − nil rate band), identical from
   either login, and it stops changing with who is signed in.
3. A user with a recorded legacy is never told their charitable rate is 0%, and the card
   states what they have already given before asking for more.
4. The percentage on `/plans/estate` and `charitable_giving_percent` from the server are
   the same number.
5. Verified in a browser on both persona accounts; then again on a household whose
   legacies cross the 10% threshold, to prove the rate flips once and identically on both
   logins.


---

## SUPERSEDED IN PART — 2026-08-23, by a statutory ruling

**Acceptance criterion 1 is partly wrong in law and must not be built as written.**

It asks that the **£20,000 household charitable total feed the rate test.** The
tax-compliance ruling of **2026-08-21** (recorded verbatim at
`IHTCalculationService::determineIHTRate():1240-1258`, and re-confirmed on the statute by
the **2026-08-23** verdict at `workforce/ops/handoffs/W-0399/`) holds the opposite:

- **s23(1) exemption — pooled** across both wills (£20,000). Correct as this item assumes.
- **Schedule 1A 10% rate test — the survivor's will ALONE** (£10,000), because the statute
  tests the estate of **one** deceased person and the first-to-die's legacy was already
  tested against a nil estate under spouse exemption. **"Summing both wills for the 10% test
  would over-qualify households for the 36% rate."**

**So "one number everywhere" is the wrong target for this pair.** Two figures answering two
statutory questions is correct; the defect was that only one of them reached the card, under
a label claiming it was the other. That half is fixed (W-0399, `handoff`).

**Criterion 4 stands and is being delivered** — the percentage on `/plans/estate` and
`charitable_giving_percent` becoming the same number is the subject of **W-0452**, in flight
on `F-0033`. Whoever picks this item up should **re-word criterion 1 before claiming it**,
and treat criterion 4 as advanced rather than open.

*Recorded by team-lead after the agent working F-0033 declined to edit an item it did not own.*
