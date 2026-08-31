---
id: W-0171
title: The estate calculation cannot be audited by the person whose money it is — £500,000 leaves the estate silently, £10,000 is deducted invisibly, and the rule that reverses it all on 2027-04-06 is never mentioned
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: product-lead
status: queued
severity: high
surfaces: [web, m]
created: 2026-08-21T21:20:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
reviewers: [product-lead, design-lead]
prior_art_checked: 2026-08-21
prior_art_found: [W-0134, W-0136, W-0139, W-0154]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

> **ID note.** Raised from **W-0171**, not from the tester's W-0131–W-0140 block, which is
> fully consumed. Highest allocated elsewhere on the board at the time of writing was
> W-0161; **W-0171–W-0175 is claimed by `persona-passA3`** and announced to the
> coordinator in the same breath as this item.

## Intent

Found by: persona run `peak_earners`, local `localhost:8000`, driven in Playwright as
**David Jones `users.id 16`** and **Sarah Jones `users.id 17`**. Full ledger in
`tests/Persona/20-08-2026_run/reports/R-17-persona-estate-figures.md` §3.

**Surface:** `/estate/inheritance-tax`, `/estate`, `/plans/estate`.

Raised as a defect rather than an editorial improvement on the coordinator's direction,
and the argument for that is in the Impact section: this is not a page missing an
explanation, it is **a page that cannot be checked by the person it is about**.

### Expected

An estate calculation shown to a user is a working, not a verdict. Every adjustment
between "here is what you own" and "here is what it will cost" should be visible and
attributable, because the user is the only person in the loop who knows whether the
inputs are right. Three adjustments in particular are large enough that omitting them
changes what the page means:

- **£500,000 of Defined Contribution pensions leaving the estate**, correctly, because
  pension funds enter the estate only from **2027-04-06** — a date inside this
  household's planning horizon.
- **£150,000 of nil rate band consumed** by a chargeable lifetime transfer made
  2020-09-01, which stops applying on **2027-09-01**.
- **£10,000 of charitable legacy deducted** from the taxable estate.

### Actual

Searched the rendered `/estate/inheritance-tax` page as David, fully expanded:

| Term | Occurrences on the page |
|---|---|
| `pension` | **0** |
| `2027` | **0** |
| `charitable` | **0** |
| `taper` | 1 — in a footnote asserting it does not apply |
| `gift` | 1 — in a footnote, with no corresponding row |

So:

- **The single largest adjustment to this household's estate is invisible and
  unmentioned.** £500,000 of pensions is excluded — correctly — and the page never says
  so, never says why, and never says that the exclusion reverses on a date 19 months
  away. A user reading their estate has no way to know that the number in front of them
  has a scheduled expiry.
- **£10,000 is deducted with no row and no word.** The taxable estate is £10,000 below
  net-estate-minus-allowances on both accounts and the page contains no reference to a
  charitable legacy at all (see W-0134, W-0139).
- **The £150,000 gift is named in prose and appears in no row**, so the column does not
  add up (W-0134).

**What the page does explain**, in full — two sentences:

> "Combined Nil Rate Band of £650,000 available (£325,000 each). Transfers between
> spouses are exempt from IHT on first death. Reduced by £150,000 due to gifts made
> within the last 7 years."
>
> "Full Residence Nil Rate Band of £350,000 available (£175,000 each). Your combined
> estate is below the £2,000,000 taper threshold."

The first states £650,000 as "available" while the arithmetic applies £500,000. The
second is true of the column on the left and false of the column on the right (W-0136).

**`/plans/estate` is worse, because it appears to explain and does not:**

> "Nil Rate Band: Individual Nil Rate Band. On second death, up to double may be
> available."

A definition that restates its own name, gives no figure, does not say which of "up to
double" applies here, and does not mention that £150,000 of it has been consumed.

**`/m` shows an estate with no tax figure at all** under a subtitle reading "Inheritance
tax exposure and planning" (W-0138), so the mobile surface explains nothing because it
shows nothing.

### Impact

**This is why the arithmetic defects survived.** W-0134 (rows summing to £1,000,000
beneath an £850,000 subtotal), W-0136 (the taper never applied to a £2.34m projected
estate) and W-0139 (one spouse told her charitable rate is 0% on a page deducting her
£10,000) all sat on a live premium surface. None is subtle — each is visible to anyone
who adds up a column. They survived because **nothing on the page invites anyone to add
up the column**: the components are not components, the adjustments are not rows, and
the prose asserts figures the arithmetic contradicts. The user, who is the one person
with both the motive and the standing to check, is given no way in.

The regulatory shape matters too and is `compliance-lead`'s to weigh rather than the
tester's: a projection that a user is expected to plan around, whose largest single
input is excluded silently and reverses on an unstated date, is difficult to reconcile
with Consumer Duty's understanding outcome. **Flagging, not concluding.**

And the 2027 point has a clock on it. On **2027-04-06** pension funds enter the estate.
For this household that is £500,000 arriving in a £1,234,280 estate, which pushes it
through the £2,000,000 residence-nil-rate-band taper threshold and changes the answer
materially. The application already models this — `pension_amendment` is computed and a
`PensionAmendmentBanner.vue` component exists — and none of it reached the page under
test.

### Repro

1. `david.jones@example.com` (premium, married, spouse accepted, £500,000 of Defined
   Contribution pensions, a £150,000 chargeable lifetime transfer, a £10,000 charitable
   bequest).
2. `/estate/inheritance-tax`, wait ~12s, **Expand All**.
3. Search the rendered text for "pension", "2027" and "charitable": zero hits each.
4. Add the visible column: net estate £1,234,280 less allowances £850,000 is £384,280;
   the page prints £374,280.
5. `/plans/estate` → "Current Situation" → read the Nil Rate Band explanation.

### Acceptance

1. Every adjustment between net estate and taxable estate is a labelled row with a
   figure — pension exclusion, gift deduction, charitable legacy — so the column can be
   added by hand and reaches the printed answer. (Shared acceptance with W-0134; this
   item is satisfied only if the rows also **say what they are**.)
2. The pension exclusion is stated on the estate surfaces, with the 2027-04-06 date and
   what changes then. The existing `pension_amendment` output and
   `PensionAmendmentBanner.vue` are the obvious source — check why they are not
   rendering here before building anything new (Rule 20: one mechanism).
3. Allowance prose states the figure actually applied and is scoped to the column it
   describes.
4. `product-lead` and `design-lead` agree the wording, since the fix is editorial even
   though the tracking is not. `compliance-lead` sees it before it closes, for the
   Consumer Duty question above.
5. Verified in a browser on both persona accounts and on `/m`, by adding the column up
   by hand and reaching the printed figure.

### Notes

- Recorded first as a product observation in R-17 §3 and promoted to a board item on the
  coordinator's direction, on the argument that a page which cannot be audited is a
  defect in the product rather than a gap in its copy.
- This item does **not** duplicate W-0134, W-0136, W-0138 or W-0139. Those are wrong or
  missing numbers; this is the absence of the provenance that would have exposed them.
  Fixing them without fixing this leaves the next set undiscoverable by the same route.

- 2026-08-31 build-lead: **VERIFIED PARTIAL against `dev` — one of the three adjustments is now
  disclosed, two are not, and no code anywhere cites this item.**
  **Disclosed:** the £150,000 nil-rate-band deduction now reaches the page, but as a side effect
  of **W-0134**'s mapping fix (`IHTPlanning.vue:1543`/`:1566`), not as work on this item.
  **Not disclosed:** the £500,000 of Defined Contribution pensions leaving the estate. `grep` for
  `2027`, "outside your estate" and an exclusion disclosure across `IHTPlanning.vue` finds only
  the trust sentence at :707 — nothing states that pensions are outside the estate until
  2027-04-06, which is a date inside this household's planning horizon.
  **`grep -rn 'W-0171' app/ resources/` returns nothing**, so the provenance work this item asks
  for has not been started. The item's own closing note is the reason to keep it: the absence of
  provenance is what makes the next wrong number undiscoverable.
