# R-09 — Re-run playbook built; four new defects found without touching the app

**When:** 2026-08-21 11:00–12:45 · **Surface:** none (preparation + static analysis)
**Agent:** persona-tester (replacement, seeded from R-08)
**Branch:** `dev`, local. Four fix batches in flight — no surface re-tested.

---

## Done

**Resumed from R-08 without re-walking it.** Read the handover, `RUN-LOG.md`, the
persona contract and all 24 board items' Acceptance and Repro sections. Did not
re-litigate the recorded decisions and did not re-walk the recorded dead ends.

**Wrote `tests/Persona/20-08-2026_run/PASS-PLAYBOOK.md`** — 1,250 lines, eight
sections:

1. **Entry map** — every data block in `peak_earners.md` mapped to the exact screen,
   the fields in order, and the values to type. Grounded in the router and the form
   components as they stand today, not from memory.
2. **Expected values, precomputed** — every ownership share, per-module total,
   household and individual net worth, Inheritance Tax position, income-tax and
   pension-allowance position, retirement inputs, holdings values, Capital Gains Tax
   context, protection, goals progress and ISA allowances. Arithmetic shown
   throughout. Tax values read live from `TaxConfigService`, not remembered.
3. **Per-account verification matrix** — for every screen on web and `/m`: what David
   must see, what Sarah must see, and what Sarah must **not** see.
4. **A regression check per defect** — 28 rows now (24 board items plus the four new
   ones): exact click path, and the observable that proves it fixed.
5. **Pass B script** — the Fyn conversation for `/m`, turn by turn, with the handler
   each turn should reach and the DB row to confirm. Grounded in the actual capture
   tool list in `CoordinatingAgent`.
6. **Pass C script** — native iOS, dev-only, plus the native-specific checks.
7. **Persona lines with no home in the UI**, split into already-on-the-board,
   newly-raised, by-design, and persona-file inconsistencies.
8. **Sequencing** for the whole re-run.

**Two arithmetic results worth recording.** The method was validated against a
known-good result before being trusted: the chattels totals computed by hand
(£132,250 David / £60,750 Sarah / £193,000 household) match the two GREEN `/m`
screenshots from R-07 exactly. And David's verified tax figures were re-derived from
first principles — £36,800 headroom, and £19,101 saving decomposing as £8,937 at 45%
+ £6,776 at 40% + £3,388 of reclaimed Personal Allowance. Exact to the pound.

**Found four new defects by static analysis**, raised on the board in FORMATS shape
with `file:line` evidence, expected-vs-actual, repro and acceptance:

| Item | Sev | Summary |
|---|---|---|
| **W-0035** | high | Target Retirement Income has no module-UI entry point. Written only by Fyn's `capture_retirement_goals` (`CoordinatingAgent.php:5628`); onboarding sets only the *age*. So every retirement projection runs on the 75%-of-income fallback — David £100,050 against a stated £75,000, Sarah £116,250 against £55,000. Pass A of **any** persona run can never produce a correct retirement projection. |
| **W-0036** | high | A Defined Benefit pension is counted as income **in payment** from the day it is entered. `UserProfileService.php:338-356` adds any non-zero `accrued_annual_pension` with no age or in-payment check, while the state-pension branch four lines below gates correctly on `already_receiving`. Sarah, 48, with a Normal Retirement Age of 60, is treated as receiving £35,000 a year — corrupting her total income, Personal Allowance, income tax and Child Benefit position, not just her retirement target. |
| **W-0037** | med | `BequestForm.vue` cannot record `priority_order`, `beneficiary_type` or `charity_registration_number`. The persona gives a priority for all six bequests. Charitable status is instead inferred by keyword-matching the beneficiary's **name** (`Bequest::isCharitable()`) — which works for "Cancer Research UK" and "British Heart Foundation" but silently fails for Shelter, Mind, Barnardo's or Scope, and silently changes the Inheritance Tax rate on the whole estate. |
| **W-0038** | med | `GoalFormModal.vue` has no `is_essential` control and no ownership control, though `goals` carries `is_essential`, `ownership_type`, `joint_owner_id` and `ownership_percentage`. Three of the persona's six goals are joint and cannot be represented — no goal ever splits between spouses. `GoalDetailInline.vue:180` displays `is_essential`, a field nothing can set, so it reads "No" for every goal. |

**How W-0036 was found, since the technique transfers.** R-08 recorded Sarah's
derived target of £116,250 as a "known trap" without a cause. £116,250 ÷ 0.75 =
£155,000; £155,000 − £120,000 = £35,000, which is exactly her NHS pension. Reversing
the observed figure to its inputs located a defect two layers below the screen it
appeared on. The same reversal confirmed David's £100,050 = (145,000 − 11,600) × 0.75,
which is what proved the fallback was the source rather than a coincidence.

---

## Not done, and why

- **No surface was tested.** Four fix batches are mid-flight and the working tree is
  actively changing beneath them — `git status` shows edits in `PropertyController`,
  `SavingsController`, `InvestmentController`, `MortgageService`, `WillAnalysisService`,
  `WillDocumentService`, `UserProfileService`, `PensionStore`, four Form Requests and
  four Store normalisers. Any result on those surfaces would be a phantom from a
  half-applied fix. Deliberate, per the dispatch.
- **The four new items were not browser-verified.** They are static findings with
  `file:line` evidence and, for W-0035 and W-0036, arithmetic that reproduces figures
  the previous pass observed live. Each carries a repro to run once the batches land.
  **I COULD NOT TEST THESE IN THE BROWSER** — they are marked as such and their
  acceptance criteria all require live re-verification.
- **The registration / verification / onboarding / spouse-linking sweep was not
  started.** It was the coordinator's stated follow-on if I finished early. I stopped
  to report first, because four new defects — two of them high severity, one affecting
  income tax and Child Benefit — change the fix ordering and the coordinator should
  see them before I start something new.

---

## Assumptions

1. **The State Pension is entered on both accounts.** The persona gives one block with
   no owner, in a file where every other spouse-owned record is marked "Owner |
   Spouse". Recorded in the playbook §1.4 as an assumption to be overturned by CSJ if
   wrong.
2. **Joint records are owned primarily by David, with Sarah as `joint_owner_id`** —
   carried forward from R-08, not re-litigated.
3. **The app models the household second-death estate** for Inheritance Tax. The
   previously verified £1,059,280 / £1,000,000 allowances shape is consistent with
   that. §2.5 states the assumption and says to recompute if the run shows a per-user
   estate.
4. **Sarah's Defined Benefit lump sum entitlement of £105,000 is not a current asset**
   and should not appear in net worth. Flagged in §2.1 as a thing to check rather than
   asserted.
5. **The mortgage maturity dates** are the run date plus the persona's stated terms
   (2039-08-20 / 2041-08-20 / 2044-08-20 from a 2026-08-20 base). If the re-run happens
   on a different date, they must be recomputed.

---

## Needs

1. **Board ID allocation is racing.** I wrote four items as W-0030–W-0033 and found
   all four numbers taken by another agent between my `ls` and my write —
   W-0030 (spouse_pension_percent units), W-0031 (education_level enum),
   W-0032 (scheme_status), W-0033 (dead reads), W-0034 (`/m` health section). I
   renumbered mine to **W-0035–W-0038** and re-pointed the cross-references. Next free
   is **W-0039**, but the collision will recur. Please arbitrate: either allocate
   blocks per agent, or make the board's ID assignment atomic.
2. **A fix-ordering decision.** **W-0036 must land before W-0035.** Setting an explicit
   retirement target would override the derived £116,250 and hide the phantom pension
   income on the retirement screen, while it carried on corrupting income tax, Personal
   Allowance and Child Benefit. Fixing W-0035 first would make W-0036 harder to see,
   not easier.
3. **W-0020's acceptance criterion cannot be met by this persona alone.** It asks for
   "a charitable cash legacy of 10%+ of the baseline moves the rate to 36%, verified
   end to end against the persona". Against the **full** estate the baseline is
   £1,078,780 and the threshold £107,878, but the persona's legacies total £20,000 —
   nowhere near. Playbook §2.5 splits the verification in two: confirm the £20,000 is
   counted at all (which is the actual bug), then flip the rate with a temporary
   oversized legacy and remove it. Please confirm that is acceptable, or amend W-0020.
4. **Re-tasking.** I am not blocked and not finished. Ready to start the registration /
   verification / onboarding / spouse-linking sweep with throwaway accounts, or take
   whatever is more useful. Proposed throwaway emails, for your teardown list:
   `pt.throwaway.primary+0821@example.com` and
   `pt.throwaway.spouse+0821@example.com`.

---

## Noticed

- **A persona-file inconsistency worth a product-lead decision.** The expenditure
  headline says £2,500/month; the fifteen categories sum to **£2,450**. The previous
  pass entered £2,450 and got `monthly_expenditure` 2450, which is correct behaviour
  against a file that does not add up. Playbook §2.4 and §7.4 record it. **Not fixed
  in the persona file** — that is a deliberate decision to record, not a tester edit.
  Five further inconsistencies are listed in §7.4, including that the persona's stated
  net worth range of £1.5m–£2m only fits the data if pensions are excluded
  (£1,728,780 excluding, £2,228,780 including).
- **W-0017 and W-0026/W-0027 have already moved in the working tree.** `DBPensionForm`
  now carries Normal Retirement Age, Spouse Pension (%) and an Inflation Protection
  selector; `PolicyFormModal` now carries `start_date`, `end_date` and
  `additional_beneficiaries`; `LifeInsurancePolicy` now has `joint_life` in
  `$fillable`; `WillAnalysisService` now compares against `'specific_amount'` with a
  W-0020 comment. Batches C, D and B are landing. Not verified — flagged so the
  coordinator knows the playbook's regression checks are pointed at live targets.
- **A second-order question inside W-0036, routed with it.** `total_annual_income`
  also folds in rental income (`UserProfileService.php:597, 621`). For this persona
  that is correct, but it means the retirement-target derivation treats Buy-to-Let
  income as something that stops at retirement. Whether that is intended is a
  product question, not a defect; noted in W-0036's working notes rather than raised
  separately.

---

## Context position

Roughly **240k** of budget consumed. Well inside the Rule 22 buffer; no handover due.
