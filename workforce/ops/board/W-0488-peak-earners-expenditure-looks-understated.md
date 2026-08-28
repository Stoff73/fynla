---
id: W-0488
title: peak_earners resolves to £1,250 a month and so reports 59.8 months of emergency runway
mission: persona-run-peak_earners-2026-08-20
owner: build-lead
status: queued
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
