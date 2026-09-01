---
id: W-0488
title: peak_earners resolves to £1,250 a month and so reports 59.8 months of emergency runway
mission: persona-run-peak_earners-2026-08-20
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
source: found while quantifying W-0276, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_found: [W-0276]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 01-mission]
---

## Intent

Measured 2026-08-25 while quantifying W-0276:

| Persona | Cash | Monthly expenditure | Runway shown |
|---|---|---|---|
| peak_earners | £74,750 | **£1,250** | **59.8 months** |

`01-mission.md` §3 describes `peak_earners` as *"David & Sarah Mitchell — multiple
properties, SIPP + NHS pension"*, and `PreviewController::PERSONA_METADATA` as *"a
couple in their late 40s at peak earning capacity, with substantial assets and
complex planning needs"*. The landing page states **~£220k household income**.

£1,250 a month — £15,000 a year — is not a plausible outgoing for that household,
and it is well below the mortgage payments implied by "multiple properties". The
runway that falls out, **just under five years**, is not a credible headline either.

## Which of two things this is has not been established

1. **Seed data gap** — the persona's expenditure records are thin or absent, and
   `ResolvesExpenditure` is faithfully reporting what little is there. Then the fix
   is the seeder, and no production code is wrong.
2. **Resolution defect** — expenditure exists but the resolver is missing
   categories, or is reading one spouse of a linked household rather than the
   pair. Then real users are affected and this is considerably more serious.

**Establish which before changing anything.** The runway figure is only the symptom
that made it visible; the same monthly figure feeds emergency-fund targets, the
`HolisticPlanner`, and every recommendation that reasons about affordability.

The `SavingsAgent` payload carries `expenditure_source` and `expenditure_label`
beside the amount — start there, since they record where the number came from.

## Acceptance

1. The source of the £1,250 identified — which records, which resolver path,
   whether both spouses of the linked household are counted.
2. A statement of which of the two causes it is, with evidence.
3. Fixed in whichever layer is actually wrong. If it is the seeder, the persona's
   expenditure is made consistent with the income and property the persona claims.
4. Re-measured afterwards: cash, monthly expenditure and runway for all six
   personas, so the figures are known to be plausible rather than merely unblocked.

---

## Closed 2026-09-01 — both causes are real, and cause 2 is the dominant one

**Acceptance 1 — the £1,250 traced to source.** `resources/js/data/personas/peak_earners.json`
declared `expenditure.total_monthly: 2500`, which `PreviewUserSeeder:362` halves for a
married persona. The resolver was faithful: `ResolvesExpenditure:34` returns
`users.monthly_expenditure` and nothing else.

**Acceptance 2 — which cause, with evidence: BOTH.**

**Cause 1, seed gap — confirmed.** The persona declared £2,500 a month with
`utilities: 0` and `rent: 0` — **no household bills at all** — against a £265,000
household income (£145k + £120k) and three properties. Its category sum was £2,450
against a stated £2,500, so the total did not even match its own parts.

**Cause 2, resolution defect — confirmed, and it is the serious one the item feared.**
`users.monthly_expenditure` **excludes mortgage payments by schema**: there is no
mortgage expenditure column, and payments live only on `mortgages.monthly_payment`.
The household's stated outgoings were **£700 LESS than its mortgage payments alone**
(£1,250 against £1,950).

**The application already computes the right figure and the runway does not use it.**
`UserProfileService::getExpenditureBreakdown():314-323` sums manual expenditure **plus**
`getFinancialCommitments()`, which at `:994` builds "Property Expenses (mortgage +
council tax + utilities + maintenance)" and is documented as matching the Expenditure
tab. The runway path uses `ResolvesExpenditure` instead. **Two household-outgoings
figures, one of them wrong wherever there is a mortgage** — a Rule 20 shape.

**Acceptance 3 — the seeder fixed.** `utilities` set to 320, the total re-derived from
the categories rather than asserted independently, and the user and spouse figures
brought into line: household £2,770, £1,385 each.

**Acceptance 4 — re-measured, all six, after re-seeding.**

| persona | cash | manual | commitments | runway shipped | runway with commitments |
|---|---:|---:|---:|---:|---:|
| young_family | 15,950 | 1,951 | 2,415 | 8.18 | **3.65** |
| peak_earners | 102,000 | 1,225 | 4,549 | **83.27** | **17.67** |
| entrepreneur | 169,180 | 4,500 | 10,213 | 37.6 | **11.5** |
| young_saver | 10,700 | 1,033 | 578 | 10.36 | 6.64 |
| retired_couple | 103,500 | 1,065 | 1,648 | **97.18** | **38.15** |
| student | 1,200 | 340 | 55 | 3.53 | 3.04 |

**Every persona is overstated, by up to 4.7×**, and commitments exceed manual
expenditure for five of the six. So the figures are now **known** rather than merely
unblocked — and what they show is that the seeder was the smaller half.

### NOT FIXED, and deliberately — needs its own item

**Routing the runway through the commitments-inclusive total changes the headline
emergency runway, the risk score and the life-event allocation for every mortgaged
user**, through `ResolvesExpenditure`'s three consumers (`SavingsAgent:104`,
`AutoRiskCalculator:470`, `LifeEventAllocationService:587`). That is not a persona-data
change and it does not belong inside one.

The measurement above is the evidence for that item. The fix is small — the two
figures already exist — but its blast radius is every user with a mortgage, and it
should be decided and verified deliberately rather than absorbed here.
