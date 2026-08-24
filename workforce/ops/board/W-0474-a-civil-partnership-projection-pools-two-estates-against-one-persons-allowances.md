---
id: W-0474
title: A civil partnership's projected estate pools both partners' assets against one person's allowances, because one predicate omits the status nine siblings include
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: main-inference
reviewers: [tax-compliance-reviewer]
status: gated
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-24T07:40:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0154, W-0465]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer round four, finding F1, 2026-08-24
---

## Intent

`IHTCalculationService.php:125`:

```php
$isMarried = in_array($user->marital_status, ['married']) && $spouse !== null;
```

`civil_partnership` is a live enum value, accepted by `UpdatePersonalInfoRequest:63`
and captured by Fyn onboarding. **The migration that added it states in its own
docblock that `IHTCalculationService` branches on `['married','civil_partnership']`.
It does not.** Nine siblings do — `EstatePlanService:687`, `IntestacyCalculator:27`,
`WillTypePolicy:37`, `ProfileCompletenessChecker:29`, `UserContextBuilder:443`,
`TaxStrategyCalculator:163`. This service is the outlier.

**The damage is asymmetric, because the two columns use different predicates:**

| | predicate |
|---|---|
| Current assets, liabilities, allowances | `$isMarried && $dataSharingEnabled` |
| Projected assets, relief, liabilities, properties, investments | `$dataSharingEnabled && $spouse` |

`$dataSharingEnabled` is `$hasLinkedSpouse && hasAcceptedSpousePermission()`, and
that method applies **no marital-status test**. So for a civil partnership
`$dataSharingEnabled` is true, `$spouse` is not null, and `$isMarried` is false.

The projected column therefore takes **both partners' assets, liabilities,
properties, investments, cash, business value and business relief** and assesses
them against **one person's £325,000 + £175,000**. The taper base is struck on the
same doubled estate, so it crosses £2,000,000 roughly twice as fast and strips the
residence band.

**Direction: OVERSTATES projected tax.** This is the W-0154 F3 shape the engine was
already fixed for once, still live on one marital status.

**Statute:** IHTA 1984 s18 (spouse exemption, extended to civil partners by Civil
Partnership Act 2004 s.246 and SI 2005/3229), s8A, s8G.

## Acceptance

1. `['married', 'civil_partnership']` at `:125`, and `$isMarried &&` added to the five
   projection predicates so both columns pool on the same rule.
2. Before/after for a civil-partnership household with data sharing on, showing the
   projected allowances doubling and the taper base halving.
3. A test that a civil partnership and a marriage with identical holdings produce
   identical figures — the cheapest guard against a tenth consumer drifting.
4. `tax-compliance-reviewer` on the change: it moves tax.

## Working notes

- 2026-08-24 — Pre-existing; the predicate predates W-0465. Raised now because
  `8f09eaddc` **rewrote those exact lines** and newly routed the business relief and
  the projected taper base through them, so it is load-bearing on two tax-moving
  figures that were not there before. Filed separately rather than folded in, because
  it moves tax on its own and needs its own before/after.
- 2026-08-24 — **Fixed, and the two predicates are now one.** Rather than widening
  `:141` and adding `$isMarried &&` to each projection branch — six copies of a rule
  that had already drifted once — the question *"do these figures cover two people's
  records or one?"* is asked by a single `poolsSpouse()`
  (`IHTCalculationService:2405-2440`), which every branch on both columns calls,
  `pooledMembers()` included. The status list is a constant,
  `POOLING_MARITAL_STATUSES` (Rule 20).
- 2026-08-24 — **Before/after, civil partnership with sharing on**, one member
  holding a £400,000 residence and the other £150,000 of investments:

  | | before | after |
  |---|---|---|
  | current gross assets | £400,000 | £550,000 |
  | nil rate band available | £325,000 | £650,000 |
  | current liability | £30,000 | **£0** |
  | projected gross assets | £1,510,883.51 | £1,510,883.51 |
  | projected nil rate band | £325,000 | £650,000 |
  | projected taxable estate | £1,185,883.51 | £860,883.51 |
  | **projected liability** | **£474,353.40** | **£344,353.40** |

  The projected estate was ALREADY pooling both partners — that half never consulted
  marital status — so the fix does not change what is in the estate, only what it is
  assessed against. **£130,000 of overstated projected tax, plus a £30,000 current
  bill that should never have existed.** (Residence band is £0 in both columns for an
  unrelated reason — this fixture has no direct descendants — so nothing here
  demonstrates the taper claim in Intent either way.)
- 2026-08-24 — **W-0340 closes with it.** The same predicate ran the other way for an
  unmarried couple with linked accounts and sharing accepted: measured at
  £1,510,883.51 of projected estate where the correct figure is £942,626.20, against
  a single nil rate band and a spouse exemption they are not entitled to. Both
  directions are pinned in the test.
- 2026-08-24 — Guard: `tests/Unit/Services/Estate/IHTCivilPartnershipPoolingTest.php`,
  3 tests / 12 assertions. **All three fail against the pre-fix service**
  (`git show HEAD:…` run directly). Estate 345, estate feature + agents 238, plans +
  tax 265 — all green. Pint clean.
- 2026-08-24 — **The `tax-compliance-reviewer` gate (acceptance 4) is NOT met.** CSJ's
  standing instruction for this session bans dispatching any agent. The change moves
  tax, so the item stays `gated` for that review rather than being called done.

## Gate review — `tax-compliance-reviewer`, 2026-08-24: **FLAGGED**, then closed

**The tax treatment was CLEARED within competence**, against statute the reviewer read
live: IHTA 1984 s18, s8A, s8D, s8G on legislation.gov.uk plus HMRC IHTM11031 and
IHTM43001. The "spouse or civil partner" wording in s18, s8A(1)(a) and s8G(2) is
inserted by **SI 2005/3229 reg 7**, in force 5 December 2005, under the power in CPA
2004 s.246 — so the docblock's citation of s.246 alone was imprecise and now names the
operative instrument. The one non-identity found (IHTM43001: for civil partners the
first death must fall on or after 5 December 2005) **cannot bite** — no civil
partnership existed before that date, and this service reads a stored transferred-band
figure rather than modelling first-death dates.

**Two findings blocked the gate. Both are now fixed.**

### F1 (HIGH) — there was a SEVENTH pooling branch, and I missed it

`$projectedCash` passed the raw `$dataSharingEnabled` into
`HouseholdCashFlowProjector`, which kept the pre-fix predicate verbatim. So a `single`,
`divorced` or `widowed` user with a linked, sharing partner still had **that partner's
savings, income and expenditure** in their projected estate while investments,
properties, liabilities, chattels and business relief had correctly left it.
**OVERSTATED projected tax — the W-0340 direction, unclosed on one of six components**,
against a commit message that claimed W-0340 closed with it. It claimed too much.

Fixed at the CALL SITE, as the reviewer advised, not inside the projector: the
projector's parameter is renamed `$poolsSpouse` and it is now told rather than
deciding. **`IHTFormattingService` had the same call** and is aligned with it — that
breakdown is the table whose whole purpose is to explain the headline, so pooling it
differently is how the two came apart before.

### F2 (MEDIUM) — the guard could not see F1, and never entered three branches

The **Fixture** variant from `tests/CLAUDE.md` §4. `partnershipHousehold()` created one
property and one investment account and nothing else: no savings, no income (the User
factory sets none of the seven `annual_*` fields), no `expenditureProfile` — the
fixture set `users.monthly_expenditure` while the projector reads
`expenditureProfile.total_monthly_expenditure`, a different field — and no liability.
**Every cash input was zero for both members**, so `projected_cash` was identical
whether the partner was pooled or not and the unmarried-couple assertion passed with F1
fully live. The spouse branch of `projectLiabilities()` and the projected
business-relief branch were never entered either, both named in the docblock as covered.

Fixed: savings, incomes, expenditure profiles and a liability, asymmetric between the
members. **Mutation-checked — restoring the raw flag at the cash call site now turns
the unmarried-couple test red**, which is precisely what it could not do before.

**A second defect surfaced from that fixture work, unprompted:** the first strengthened
version failed once and then passed six times. The cause was the factories randomising
`interest_rate`, `account_type`, `liability_type`, `monthly_payment` and
`maturity_date` — this test compares two households that must differ in **nothing** but
marital status, so an unpinned field gives them different projected figures. All pinned;
**six consecutive green runs** after.

### F3 (MEDIUM-LOW) — the one branch that deliberately differs, now documented

The projection HORIZON (`:687`) keys on `$isMarried` with no sharing term. Kept: how
long the household lasts is a fact about the household, whose records are in the estate
is a question about permission. Commented at the line, including the consequence the
reviewer named — **a civil partnership with sharing OFF now gets the longer horizon and
therefore MORE projected tax**, a figure that moved opposite to the headline and is not
in the before/after table. The class docblock's claim that both halves "read the one
predicate now" was an over-claim of exactly the kind it had just been rewritten to
remove; it now states the exception.

### F4–F7 recorded

- **F4** — a third definition of "two people" survives in the cache path (`:2087`,
  `:2193`, `:2200`) and `marital_status` is in neither hash. **Dormant** — nothing
  writes those rows (W-0131) — but fixing W-0131 without adding marital status to the
  hash would resurrect a pre-fix answer. Noted on this item deliberately.
- **F5** — the constant was `private`, so the "one home" claim was false. Now
  `App\Support\HouseholdPooling`, public. **Four sibling services still read
  `['married']` alone — filed as W-0480.**
- **F6** — a benefit nobody measured: `EstateIhtExposureDetector` reads `is_married`
  back off the result, so a linked, sharing civil partnership stops being told
  *"Linking your accounts gives a fuller picture"* when they had already linked. Fixed
  as a side effect, web and `/m`.
- **F7** — pre-existing and out of scope: `projectMemberLiabilities()` is called for the
  spouse using the USER's ages (moves tax, unrelated to marital status); no IHTA s18(2)
  restriction is modelled for anyone.

### What the reviewer did NOT verify, carried forward rather than dropped

It did not reproduce the before/after arithmetic, did not measure F1 or F3 in pounds,
did not re-run the wider suites, did not read s8G(3)(d) verbatim, did not check any
frontend rendering, and did not confirm `civil_partnership` reaches the database
through Fyn onboarding (only the validation rule and the migration enum).

### Verification after the fixes

Estate unit **345**, estate feature + agents + plans + tax + tiers **546** — green. The
guard is 3 tests / 12 assertions, mutation-checked on both the level and the cash
predicate, six consecutive runs. Pint clean.
