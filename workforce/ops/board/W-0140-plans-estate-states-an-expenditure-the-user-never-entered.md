---
id: W-0140
title: /plans/estate states an Annual Expenditure neither user entered — £39,420 against a recorded £29,400, and £7,500 for a user with no expenditure recorded at all — and it drives Disposable Income
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0016-cycle1-m-chattels-and-plan-expenditure.md
owner: build-lead
status: done
severity: medium
surfaces: [web]
created: 2026-08-21T20:40:00Z
claimed: 2026-08-21T21:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0135, UserProfileService::expenditurePresentation, DisposableIncomeAccessor]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local, both persona accounts.

**Surface:** `/plans/estate` → Personal Information → Financial Overview.

### Expected

"Annual Expenditure" on a plan the user is asked to act on should be the expenditure the
user recorded. David's is recorded and matches the persona exactly:

```
users.id 16   monthly_expenditure 2450.00   annual_expenditure 29400.00
              expenditure_entry_mode 'category'
users.id 17   monthly_expenditure NULL      annual_expenditure NULL
```

£2,450/month is the sum of the persona's fifteen categories, so **£29,400** is the
correct annual figure for David. Sarah has entered none, so the honest outputs for her
are either her share of the household's or an explicit "not recorded" — not a number.

### Actual

| | Recorded | `/plans/estate` shows | Disposable Income it produces |
|---|---|---|---|
| David | £29,400 | **£39,420** | Net £96,370 − £39,420 = **£56,950** (£4,746/month) |
| Sarah | **nothing** | **£7,500** | Net £78,157 − £7,500 = **£70,657** (£5,888/month) |

**David's figure is £10,020 above his recorded expenditure**, and I could not derive
£39,420 from any combination of his recorded expenditure and his share of the property's
running costs and mortgage (£625/month → £7,500/year would give £36,900). **Sarah's
£7,500 is exactly £625 × 12** — her half of the joint property's monthly commitment —
presented under the label "Annual Expenditure", as though property costs were her total
household spending.

So the two accounts derive the same labelled figure by different rules, and neither
returns the value the user entered.

### Impact

Disposable income is the figure every affordability statement in the plan rests on — how
much can be gifted annually, whether life cover premiums are affordable, whether the
charitable shortfall can be funded. Overstating David's expenditure understates his
disposable income by £10,020 a year; understating Sarah's overstates hers by whatever her
real share is. Neither user can reconcile the number to anything they typed, which is the
same trust problem as W-0134 in a different place.

### Repro

1. `david.jones@example.com` → `/plans/estate`, wait ~15s → Personal Information →
   Financial Overview → "Annual Expenditure: £39,420".
2. `php artisan tinker --execute='$u=App\Models\User::find(16); echo $u->annual_expenditure;'`
   → `29400.00`.
3. `sarah.jones@example.com` → same panel → "£7,500". Her `annual_expenditure` is NULL.

### Acceptance

1. "Annual Expenditure" is the user's recorded annual expenditure, from the same source
   the expenditure module writes and the dashboard reads (Rule 20).
2. Where a user has recorded none, the plan says so rather than substituting a derived
   figure under the same label.
3. If property running costs are intended to be added, they are a separate labelled line
   and the composition is shown — the plan already has room for it.
4. Both accounts derive it the same way.
5. Verified in a browser on both persona accounts against the database values.

---

## Re-derived 2026-08-21 ~21:45 against the COMPLETE household

The coordinator asked for these figures to be re-derived rather than reused after the
missing two-thirds of the household was entered. **Both moved, both are still wrong, and
the re-derivation makes the mechanism clear.**

| | Recorded | Was (1/3 household) | **Now (full household)** | Property costs, that account's share |
|---|---|---|---|---|
| David | £29,400 | £39,420 | **£52,394** | £20,474/yr (£1,706.20/mo) |
| Sarah | **NULL** | £7,500 | **£14,820** | **£14,820/yr (£1,235/mo)** |

**Sarah's figure is now exactly her share of the three properties' monthly commitments** —
£625 (The Willows) + £610 (City Centre Flat) = £1,235/month × 12 = **£14,820**, to the
pound. She has no recorded expenditure at all, so **100% of what the plan labels "Annual
Expenditure" is property cost**.

David's £52,394 is *approximately* his recorded £29,400 plus his £20,474 property share
(£49,874) but does not reconcile — £2,520 unexplained, and roughly £1,414 of that is
attributable to the Manchester mortgage being charged at 50% instead of 40% (**W-0172**).

> **CORRECTED — the £2,520 is NOT unexplained.** It is David's protection premiums,
> £210/month, correctly included by the derivation rule. Verified against
> `getFinancialCommitments`: properties 1,706.20 + protection 210.00 = 1,916.20/month.
> **Do not hunt a defect here — there is none.** W-0172's ~£1,414 sits *inside* the
> £20,474 property component and remains `fix-batch-F`'s. See the banked trace below.

**This confirms the original diagnosis and sharpens it:** the two accounts derive one
labelled figure by two different rules — Sarah's is property costs alone, David's is
recorded expenditure plus property costs plus something unaccounted — and **neither
returns the number the user entered**. It still drives Disposable Income (David £52,615,
Sarah £68,744), on which every affordability statement in the plan rests.

**Acceptance 3 is now the likely shape of the fix:** property running costs and mortgage
payments evidently *are* meant to be in this figure. If so they must be a separate,
labelled line with the composition shown, and the row that says "Annual Expenditure" must
say the expenditure the user recorded.


---

## Diagnosis banked by fix-batch-I before stand-down, 2026-08-21

**Claimed, traced, NOT started. Claim released; no code was written and `F-0014`
was never issued in practice.** Recorded because the trace corrects two things
this item currently says, and whoever picks it up should not re-derive them or
start from the wrong shape.

### It is ONE derivation rule, not two

`UserProfileService::getExpenditureBreakdown()` is the single mechanism:

```
monthly_manual      = category sum when entry_mode='category', else monthly_expenditure
monthly_commitments = getFinancialCommitments()['totals']['total']
annual              = (manual + commitments) × 12
```

Applied identically to both accounts. Verified by invoking the real method
against `users` 16 and 17 — reconciles **to the penny**:

| | manual | commitments | annual | displayed |
|---|---|---|---|---|
| David 16 | 2,450.00 | 1,916.20 | **52,394.40** | 52,394 |
| Sarah 17 | 0.00 | 1,235.00 | **14,820.00** | 14,820 |

**It only looks like two rules because Sarah's manual component is zero.** There
is no inconsistency between the accounts, so acceptance 4 ("both accounts derive
it the same way") is already satisfied — a fix aimed at it would be aimed at
nothing.

### The "£2,520 unaccounted" on David is accounted for

`getFinancialCommitments` totals, David: **properties 1,706.20 + protection
210.00** = 1,706.20 + 210.00 = 1,916.20/month.

£210 × 12 = **£2,520** — his life and critical illness premiums, correctly
included by the rule. Not a defect, and **not** attributable to W-0172 as the
re-derivation supposed. (W-0172's ~£1,414 error sits *inside* the £20,474
property component and remains `fix-batch-F`'s.)

Also checked and cleared: David's twenty category columns sum to **exactly**
2,450, matching `monthly_expenditure`. No third disagreement hiding there.

### The product question is already answered one method away

`UserProfileService::expenditurePresentation()` already returns, for the profile
surface:

```php
'manual_monthly_total'      => $breakdown['monthly_manual'],
'commitments_monthly_total' => $breakdown['monthly_commitments'],
'total_basis'               => 'Category entries plus financial commitments',
```

**The application already states in words that this figure is entries plus
commitments, and already shows the two components separately.** The profile
discloses; the plan reads past it. `DisposableIncomeAccessor` takes only the
composed total from `income_occupation.annual_expenditure`, and the panel prints
it under a bare "Annual Expenditure".

So the defect is not derivation and not labelling alone — **it is that one
surface discloses the composition and another discards it.** The honest version
already exists, computed, one method away.

### Scope is five copies, not one

The panel is **byte-identical** in `EstatePersonalInformation.vue`,
`InvestmentPersonalInformation.vue`, `RetirementPersonalInformation.vue`,
`ProtectionPersonalInformation.vue`, and again in `planPrintMixin.js:2031` for
the adviser pack. Fixing `/plans/estate` alone leaves the same statement in four
other places.

### It is NOT blocked on W-0172 / W-0173

The fix is what the figure is *called* and whether its composition is *shown* —
no ownership arithmetic. David's property component stays wrong until W-0172
lands, but **showing the composition makes that error visible rather than buried
inside one unreconcilable number**, and it corrects itself when `fix-batch-F`
lands. Whoever takes this should not wait.

**One consequence to sequence, not to fix here:** once the composition is shown,
the plan displays a property-costs line carrying W-0172's error openly. More
honest, but a tester will see it.


---

## DECISION TAKEN — team lead, 2026-08-21. This item is buildable, not merely diagnosed.

**The figure keeps its meaning — recorded entries PLUS financial commitments — and the
plan carries the disclosure the profile already has.**

Reasoning, recorded so it is not re-litigated: **Disposable Income must subtract
commitments to be true.** Changing the meaning to "only what the user typed" would leave
Disposable Income subtracting less than the household is actually committed to — and
Disposable Income is the figure every affordability statement in the plan rests on. So
the composed figure is the right number; what is wrong is that its composition is hidden
behind a label naming one component.

**And where a user has recorded nothing, the plan states that** rather than printing a
number that is 100% property cost under a label about spending.

**No arithmetic value changes.** This is a disclosure fix, end to end.

### What that makes the fix

1. Show the composition the profile already computes — the manual total and the
   commitments total — not only the composed figure.
2. Where `monthly_manual` is zero, say so explicitly instead of presenting commitments as
   though they were the user's expenditure.
3. **All five surfaces, not one.** Four plan panels plus the adviser print pack.
   **Whoever builds this does all five or none** — fixing the estate panel alone leaves
   the same false statement in four other places, which is exactly the partial-close
   W-0115 was raised to prevent.
4. **Compose from `expenditurePresentation()`** — it already returns
   `manual_monthly_total`, `commitments_monthly_total` and `total_basis`. **Do not write
   a second breakdown** (Rule 20). The honest version exists; the job is to stop
   discarding it.

### Acceptance, restated against the decision

- ~~1. "Annual Expenditure" is the user's recorded annual expenditure~~ — **superseded by
  the decision above.** The figure keeps its composed meaning; the disclosure changes.
- 2. Where a user has recorded none, the plan says so. **Stands.**
- 3. Property running costs are a separate labelled line with the composition shown.
  **Stands, and is now the shape of the whole fix.**
- 4. Both accounts derive it the same way. **Already satisfied** — one rule, verified to
  the penny. Nothing to build.
- 5. Verified in a browser on both persona accounts against the database values.
  **Stands.**
- 6. **New:** all five surfaces carry the change.

### Not blocked on W-0172 / W-0173

Confirmed by the team lead. The change touches **no ownership arithmetic**. Once the
composition is shown, the property-costs line openly carries W-0172's error until
`fix-batch-F` lands — **more honest, not less**, and self-correcting when it does. **Do
not read that line as a new defect**; tell the tester the same.

---

## Built 2026-08-21 — `cycle1-surfaces`, branch `F-0016`. All five surfaces.

**Handoff:** `handoffs/W-0140/build-to-quality-2026-08-21.md`.
Built to the team lead's decision above, not to the original acceptance list.
**No arithmetic value changes. Both figures are identical to what they were.**

### Prior-art check — outcome `extend`

`UserProfileService::expenditurePresentation()` already computed the honest version
(`manual_monthly_total`, `commitments_monthly_total`, `total_basis`) for the profile
surface, and `DisposableIncomeAccessor::getForUser()` is already the one method all four
plan services call. Nothing new was invented and **no second breakdown was written**: the
existing presentation gained annual totals and a `has_recorded_expenditure` flag, and the
accessor now carries them through.

### What changed

- `UserProfileService::expenditurePresentation()` (`:512`) — adds `manual_annual_total`,
  `commitments_annual_total`, `has_recorded_expenditure`; **`total_basis` stops naming a
  component the user does not have** (nothing recorded now reads *"Financial commitments
  only — no expenditure recorded"* instead of *"Category entries plus financial
  commitments"*). `summary_only_reason` corrected in the same way.
- `DisposableIncomeAccessor::getForUser()` (`:30`) — returns `expenditure_composition`.
- The four plan services pass it into `personal_information` (`EstatePlanService:615`,
  `ProtectionPlanService:239`, `RetirementPlanService:295`, `InvestmentPlanService:508`).
- **One home for the display decision:** `resources/js/utils/expenditureComposition.js` +
  `components/Plans/Shared/PlanExpenditureComposition.vue`. The four panels each gained one
  line (`:69` in all four); `planPrintMixin.js:2029` composes its rows from the same util.

Sarah's panel now reads "Annual Expenditure £14,820" followed by "Recorded Expenditure:
**None recorded**", "Financial Commitments: £14,820", and the basis line. David's reads
£29,400 recorded plus £22,994 of commitments — reconciling to his £52,394.

### Acceptance, as built

- 1 — **superseded** by the decision. 2 — **done**. 3 — **done**. 4 — **already satisfied**,
  nothing built. 5 — **outstanding**, browser verification is the tester's. 6 — **done**,
  all five surfaces.

### Two things the tester must be told before they look

1. **The property-costs component openly carries W-0172's error** (a mortgage share at 50%
   where 40% is due) until `fix-batch-F` lands. That is more honest than burying it inside
   one unreconcilable number, it self-corrects when that fix lands, and **it is not a new
   defect.**
2. **`/m` and native iOS have no plan Personal Information panel** — verified, not assumed.
   Their `/personal-information` is the profile screen, a different surface. `/m`'s
   **Expenditure** screen does pick up the corrected basis wording, from the same one home.

**Reported, not fixed:** `IncomeOccupation.vue:193` on the profile shows the same composed
figure with no composition beside it. Different surface, not named in the decision.

- 2026-08-31 build-lead: **VERIFIED ALREADY FIXED AND TESTED — closed.**

  `resources/js/utils/expenditureComposition.js` exists for this item and names it in its first line. The figure keeps its meaning — recorded entries PLUS financial commitments, which Disposable Income must subtract to be true — but the plans no longer print that composed total under a bare label naming only one of its components, and **a user who has recorded no expenditure is told so** rather than shown a number that is entirely commitments.

  One home, both mechanisms: `Plans/Shared/PlanExpenditureComposition.vue` and `Plans/Shared/planPrintMixin.js` both import `expenditureCompositionRows()` and `expenditureCompositionNote()`, so the four plan panels and the adviser print pack say the same thing (Rule 20). The server composes the numbers via `UserProfileService::expenditurePresentation`; the module owns only the labels and the presentation decision.

  **Tested:** 821 frontend tests and 123 expenditure tests pass (331 assertions).
