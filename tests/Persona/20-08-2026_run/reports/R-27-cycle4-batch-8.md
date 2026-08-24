# R-27 — Cycle 4, batch 8

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners`
**Surface:** web, local · **Account:** David (16)
**Batch closed:** 2026-08-22 ~19:25 · Continues R-19 … [R-26](R-26-cycle4-batch-7.md)

Expenditure. **This batch corrects a baseline error the whole run has been carrying**, and
finds a residual in W-0190's fix.

---

## The run's expected expenditure total has been wrong — the persona is not self-contradictory

`PASS-PLAYBOOK.md`, `RUN-STATE-2026-08-21.md` and several reports state that the persona
contradicts itself: *"expenditure headline £2,500 against categories summing £2,450"*. That
premise was used to justify treating **£2,450** as the expected figure, and it has been
carried into expected values across the run.

**It is an arithmetic error.** The fifteen categories sum to **£2,500**:

```
450 + 150 + 100 + 100 + 50 + 40 + 30 + 100 + 100 + 100 + 1000 + 50 + 80 + 100 + 50 = 2500
Food  Trans  Health Ins  Mob  Net  Subs Cloth Ent  Hols  Fees  Lunch Extra Activ Gifts
```

The persona's headline of £2,500 and its categories agree exactly. There is no
contradiction, and **£2,450 was never the right expected value.**

### Where the missing £50 actually was

Reading the stored values field by field, fourteen of fifteen matched the persona exactly.
One did not:

| Field | Stored | Persona |
|---|---|---|
| **Healthcare & Medical** | **£50** | **£100** |

Everything else — Food £450, Transport £150, Insurance £100, Mobile £50, Internet £40,
Subscriptions £30, Clothing £100, Entertainment £100, Holidays £100, School Fees £1,000,
School Lunches £50, School Extras £80, Children's Activities £100, Gifts £50 — was right.

**This was an entry error from an earlier cycle, not an application defect.** Worth noting
that £50 is also this field's own placeholder (`ExpenditureForm.vue:1419`,
`placeholder: '50'`), which is the likeliest way it happened.

**Corrected.** I entered £100 through the form and saved.

### What follows for the rest of the run

Every figure derived from monthly expenditure has been computed on £2,450 rather than
£2,500, and on a per-spouse half of £1,225 rather than £1,250. That includes the emergency
fund runway on the dashboard and in the risk profile, the "worked out for you" retirement
target, and the monthly surplus factor. None of those are wrong *mechanisms* — they were
fed a figure £50 light. **Expected values in the playbook should be re-derived from
£2,500.**

---

## D-26 (HIGH) — In "Joint (50/50)" mode, an edit updates only the editing spouse's half

Immediately after saving the £50 correction, and still there after a full page reload:

> **Entry Mode: Detailed Breakdown · Joint (50/50) expenditure**
>
> | Category | David | Sarah | Household |
> |---|---|---|---|
> | Essential Living | **£400** | **£375** | **£775** |
> | Manual Expenditure Total | **£1,250** | **£1,225** | **£2,475** |

Three things are wrong on one screen:

1. **A declared 50/50 split showing £1,250 against £1,225.** That is 50.5 / 49.5.
2. **Household Essential Living reads £775** when the categories give
   450 + 150 + 100 + 100 = **£800**.
3. **The household total reads £2,475** when it is **£2,500**. The household figure is being
   computed as *David's half + Sarah's half*, so when the two halves disagree the household
   total inherits the error rather than being the source of truth.

The database shows why. `expenditure_profiles` holds **one row only** — David's
(`user_id 16`), now `total_monthly_expenditure = 1250.00`, `updated_at 20:24:08`. **Sarah
has no row.** Her £1,225 is derived, and the derivation did not recompute when the owner
edited: it is still half of the old £2,450.

**This is a residual of W-0190**, which made the initial split 50/50 (`SharedExpend
£2450 → £1225`). That fix addressed the split at rest; it did not make the non-editing
spouse's derived half recompute on an edit. So the household drifts out of balance the
first time anyone changes a category — which is the ordinary case, not an edge case.

Screenshot: `146-web-david-expenditure-5050-split-1250-vs-1225.png`

---

## Verified GREEN on this page

- **Entry mode and split are declared plainly** — "Detailed Breakdown · Joint (50/50)
  expenditure" tells the user what it is doing, which is exactly the auditability F-0020
  was about.
- **The category grouping is sound** and reconciles to the persona: Communication &
  Technology £120 (50 + 40 + 30), Personal & Lifestyle £300 (100 + 100 + 100), Children &
  Dependents £1,230 (1,000 + 50 + 80 + 100), Other £50.
- **Financial commitments are separated and labelled "Auto-calculated"**, with the note
  *"Financial commitments (mortgages, loans, pensions, investments, protection) are
  automatically pulled from other modules"* — so the user can see which half of their
  outgoings they entered and which the app derived.
- Three budget views exist — Current Budget, Budget at Retirement, Budget if Widowed.

---

## Not done, and why

- **Sarah's own view of the expenditure page** — the £1,225 in her column is computed by the
  same code path that renders David's view, so I have not spent a login to see it from her
  side. If the coordinator wants it confirmed from her account, say so and I will.
- The **"Budget at Retirement"** and **"Budget if Widowed"** tabs are untested. The persona
  gives no figures for either, so there is nothing to check them against — noting them as
  unexercised rather than passed.
- Financial Commitments (David £1,916 / Sarah £1,235 / £3,151) not reconciled by hand. That
  is derived from the mortgage, protection and pension modules, several of which have open
  defects (D-01, D-02), so any arithmetic I did now would need redoing after those land.

## Assumptions

- I mapped the persona's "Gifts & Charity £50" to the app's **Gifts & Presents** field and
  left **Charitable Donations** at £0. The app splits one persona category into two; putting
  the whole £50 in either is defensible and this keeps the total right.
- I corrected Healthcare & Medical rather than reporting it as a defect, because the field
  accepts £100 without complaint — the fault was the earlier entry, not the application.

## Needs

- Board ID for **D-26**. It should go to whoever holds **W-0190**; it is the same mechanism
  and the branch document will already have the context.
- **A correction to `PASS-PLAYBOOK.md`** — its §2 expenditure figures and the
  "persona contradicts itself" note are both wrong and will mislead the next tester. I have
  not edited the playbook myself; it is a shared run artefact and the coordinator owns it.
  The persona file itself needs no change: it was right all along.
