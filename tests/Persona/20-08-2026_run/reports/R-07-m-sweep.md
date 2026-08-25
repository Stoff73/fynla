# R-07 — `/m` discovery sweep on surfaces outside the fix batches

**Run:** `peak_earners` · **Environment:** local `http://localhost:8000`, premium
**Accounts:** David Jones (16) · Sarah Jones (17) — both swept
**Ran:** 2026-08-21 09:25 – 10:35
**Scope (team-lead re-task):** `/m` dashboard, Goals, Protection, Chattels, Tax
Strategy, Life Events, net-worth read-only. Batch A/B/C surfaces avoided; joint-share
discrepancies on investments deliberately **not** chased.

---

## Done

### Entry first — the surfaces had no data to sweep

Premium lifted the free-tier caps, so the persona records these screens need were
entered on desktop and DB-verified:

| Entered | Count | Result |
|---|---|---|
| Chattels | 6 of 6 | all correct, incl. BMW X5 at £42,000 against £65,000 purchase **as the persona states** |
| Protection policies | 2 of 2 | sums and premiums correct; dates and beneficiaries not — W-0026, W-0027 |
| Goals | 3 of 5 remaining | 2 blocked by past dates — W-0029 |
| Life events | 8 of 10 | 2 blocked by past dates — W-0029 |

### `/m` sweep — GREEN checks, both accounts

Every figure below was recomputed by hand against the persona and the database.

**David**

| Surface | Figure | Hand-check |
|---|---|---|
| Dashboard milestone | "£2,000,000 … £880,250 away" → net worth **£1,119,750** | £987,500 + £132,250 new chattels ✓ |
| Goals | £308,000 of £740,000, **42%**, 3 of 3 on track | 95+185+28k / 200+500+40k = 41.6% → 42% ✓ |
| Goals — months left | 77 / 104 / 13 | recomputed from target dates: 77 / 104 / 13 ✓ |
| Protection | Total cover **£700,000** across 2 | £500,000 + £200,000 ✓ |
| Protection gap | Income protection £87,000 p.a. short | 60% of £145,000 = £87,000 ✓ |
| Chattels | **£132,250** across 5 | 85,000 + 4,500 + 17,500 + 4,250 + 21,000 ✓ |
| Chattels — joint | Art £17,500 · Desk £4,250 · BMW £21,000, all "Jointly owned" | exactly 50% of each ✓ |
| Tax Strategy | £36,800 → **£19,101**; child pension £720; dividend £197 | all three reproduced by hand ✓ |

**Sarah**

| Surface | Figure | Hand-check |
|---|---|---|
| Dashboard milestone | "£750,000 … £135,470 away" → net worth **£614,530** | £571,780 + £42,750 joint chattel shares ✓ |
| Goals | 1 of 1, £120,000 of £400,000, **30%**, 104 months | 120/400 = 30% ✓ |
| Protection | **£0** across 0 policies | correct — both policies are David's; protection has no joint concept ✓ |
| Protection gap | Final expenses £7,500 · Income protection £72,000 p.a. | 60% of £120,000 = £72,000 ✓ |
| Chattels | **£60,750** across 4 | 18,000 + 17,500 + 4,250 + 21,000 ✓ |

**Isolation — correct on every surface tested.** Sarah sees her ring and the three
joint chattels; David's Jaguar E-Type and First Edition Books are absent from her view,
and her ISA goal is absent from his. Neither sees the other's individual records.

**Worth highlighting: chattels get joint ownership right.** Each joint item renders the
viewer's 50% share with a "Jointly owned" badge, on both accounts, on `/m`, and the
household figures reconcile. That is the behaviour W-0014/W-0015 describe as broken for
investments — so the correct pattern already exists in this codebase and is visible on
the same surface.

**Gamification layer** (Level wheel, "X of Y actions", "ahead of X% of people", the
"Level 8 — Guardian" celebration) is present and working on both accounts. **CSJ-approved
by design (Rule 12 carve-out) — recorded as working, not flagged.**

---

## Not done, and why — five defects found

| W | Sev | Expected vs actual | Evidence |
|---|---|---|---|
| **W-0025** | med | Joint chattel saves with `joint_owner_id = NULL` and no error; 50% belongs to nobody and the spouse cannot see it | DB rows; **the NULLs were my omission** — the picker exists and works, nothing stopped me |
| **W-0026** | high | `policy_end_date` sent, 201'd, persists NULL on 4 of 5 policy models (not in `$fillable`); Life Insurance form has no date fields at all | captured request + DB + `$fillable` audit |
| **W-0027** | med | Life policy takes ONE beneficiary from a list excluding the children; no joint-life flag | DOM option list; persona needs 3 beneficiaries + joint life |
| **W-0028** | high | `/m` page titled "Goals and life events" renders **no life events**; `Goals.vue:174-175` never fetches them | full page text + zero-hit grep |
| **W-0029** | med | Goals and events cannot be dated today or earlier; 4 persona records unenterable | `min="2026-08-22"`, `checkValidity() false` |

### Persona records still not entered

| Record | Why |
|---|---|
| Goal: Max Pension Contributions (2026-04-05) | W-0029 past date |
| Goal: Charlotte's Gap Year Fund (2026-08-01) | W-0029 past date |
| Event: Previous Inheritance £45,000 (2020-03-15, "Completed") | W-0029 past date |
| Event: Annual Bonus £35,000 (2026-04-01) | W-0029 past date |
| Goal contribution streaks (36 and 60 months) | no field; streaks are earned via contributions, by design |
| Life policy: joint-life flag, 3 beneficiaries, start/end dates | W-0026, W-0027 |

**I did not edit the persona file** to make any of these fit.

---

## Assumptions

- That the persona's four past-dated records are stale rather than deliberately
  historical — except the £45,000 inheritance, which the file explicitly marks
  "Confirmed (Completed)" and therefore looks intentional. W-0029 is flagged to
  `product-lead` on that basis rather than assumed either way.
- That "William's House Deposit Help" auto-assigning to module **property** (persona
  says Savings) reflects the app's deliberate `home_deposit → property` mapping and a
  stale persona line. Not raised.

---

## Needs

Nothing blocking. Ready for re-task — see the coordinator note in my report.

---

## Noticed

- **A throwaway `TESTCO` critical-illness policy** was created to isolate W-0026 and
  **deleted** afterwards (`DELETE /api/protection/policies/critical-illness/3`, 200).
  The persona's two real policies remain.
- Sarah showing "Final expenses HIGH £7,500 short" while David does not is **correct** —
  his £500,000 life cover absorbs it and hers does not exist. Good logic, worth noting
  because it looks asymmetric at a glance.
- Sarah's £0 protection cover is a direct downstream consequence of W-0027: the persona's
  policy is Joint Life, which would cover her too, but joint life cannot be recorded.
- I did **not** chase investment joint-share figures on `/m` — Batch A territory, per
  instruction.
