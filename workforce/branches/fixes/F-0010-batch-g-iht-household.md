---
id: F-0010
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/05-perimeter.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-21T20:35:00Z
status: active
---

# F-0010 — Batch G: the inheritance tax household, W-0154 F1/F2/F3

**Owner:** build-lead (agent `fix-batch-G`) · **Branch:** `dev` · **Board item:** W-0154
(`critical`), plus W-0146 raised.

**Status: F1, F2 and F3 code-complete, 12 new tests plus 308 estate regression tests
green, Pint and ESLint clean.** F4–F11 untouched, per dispatch. No commit, no PR, no
deploy, not browser-verified. Nothing in flight.

---

## 1. The single mechanism

F1, F2 and F3 were one defect wearing three faces: **the service decided twice, and
differently, whose records it was covering.**

Assets and liabilities pooled on `$isMarried && $dataSharingEnabled` (`:135`, `:149`).
The allowances doubled on `$isMarried` alone. Every per-person input — gifts, transferred
allowances, the charitable planning percentage, the will — read `$user` only.

So:
- **F1**: the same household got two different bills. One spouse's £150,000 chargeable
  transfer reduced the pooled band in his view and by nothing in hers. £60,000 apart.
- **F3**: married with sharing off gave £1,000,000 of allowances against one person's
  assets — a £0 bill where the answer is £80,000.
- **F2**: the doubling was unattributed, so the published components could not be
  reconciled to the published total.

**The fix is one decision, made once:**

```php
$pooledMembers = $this->pooledMembers($user, $spouse, $isMarried, $dataSharingEnabled);
$poolsSpouse   = count($pooledMembers) > 1;
```

Everything downstream reads `$pooledMembers`. **Adding a per-person input to this
service now means adding it to that loop, not to `$user`** — which is the property that
stops F1 recurring, rather than four separate corrections that each could have been
missed.

---

## 2. What changed, and the reasoning that is expensive to rebuild

### F1 — gifts, capped per person

`calculateNRBDeductionForGifts()` takes the member list and delegates to
`nrbDeductionForOneMember()`, **capped at that member's own band**. That single change
fixes two things: the asymmetry, and the s8A defect the audit found at a boundary the
personas do not reach — the deduction used to come off the **pooled** £650,000, so a
£400,000 transfer by one spouse ate £75,000 of the other's band. IHTA 1984 s8A transfers
an unused **percentage** and it cannot go below zero.

**The per-person cap already existed** for the chargeable-transfer subtotal; it was
applied to the wrong subtotal. Capping per member makes "the sum can never exceed
members × £325,000" an invariant of the shape rather than a thing to remember.

### F1 — the charitable split, which is the subtle half

**Do not conflate these two.** `tax-compliance-reviewer`'s statutory ruling:

- **s23(1) exemption — pool every member's legacies.** Both are paid and both leave the
  combined estate. Deducting only the logged-in user's understated it on both accounts:
  a household where each spouse left £10,000 to a different charity had £10,000
  deducted, never £20,000, whichever spouse logged in.
- **10% rate test — the SURVIVOR's will alone.** The second-death estate is the
  survivor's. The first-to-die's legacy was tested on the first death against an estate
  that, under full spouse exemption, is nil. **Summing both would over-qualify
  households for the 36% rate.**

Implemented as `$charitableAmount` (pooled, what the caller deducts) versus
`$rateTestAmount` (the survivor's, what the threshold compares). A test asserts two
£70,000 legacies do **not** clear a £73,428 threshold that their sum would clear.

**This is a modelling ruling — a statute mapped onto a construct the statute does not
have — and it carries a product sign-off requirement.** Flagged, not assumed.

### F2 — attribution, and what NOT to do

`nrb_spouse_modelled` and `nrb_gift_deduction` are new, and the five figures now
reconcile:

```
nrb_individual + nrb_spouse_modelled + nrb_transferred − nrb_gift_deduction = nrb_available
```

**`nrb_transferred` stays 0 for a living couple and that is correct.** There is no
transferable nil rate band while both spouses are alive — s8A creates the claim on the
survivor's death. The doubling is this service's second-death modelling assumption,
stated in a docblock and never surfaced. **Do not "fix" this by writing 325,000 into
`nrb_transferred`.** A test pins it.

The £175,000 the user could not account for was **two unlabelled effects netting out**:
+£325,000 modelled transfer, −£150,000 gift deduction.

**Rendered, not just returned.** `IHTController` publishes both new fields;
`IHTPlanning.vue` maps them; `IHTCalculationTable.vue` gains **one** row — "Less
allowance used by gifts in the last 7 years" — placed after every nil-rate-band branch,
because the deduction applies to all of them. It is signed `+` because it is an addition
to the taxable estate.

### F3 — the allowances now cover the estates being taxed

Doubling gates on `$poolsSpouse`. `calculateRNRB()` takes `$poolsSpouse` and is passed
`$spouse` as **null** when the spouse's estate is not pooled — otherwise
`hasMainResidence()`, `hasDirectDescendants()` and `getMainResidenceNetValue()` would
grant the residence band, and raise its cap, from a property excluded from the estate.

### The comment that concealed all of it

`:147` and the deduction docblock both said spouse nil rate band was *"handled separately
by SpouseNRBTrackerService"*. **That service has never had a caller** — verified by the
audit and independently by me. It was the stated reason the asymmetry was safe. Both
comments removed; the class left in place; **W-0146 raised** to decide wire-up or
deletion, because deleting a service is a decision.

---

## 3. Verified

**`IHTHouseholdConsistencyTest` — 12 tests, all fixtures, no persona users touched.**
The headline: **£145,712 identical from both logins**, the exact figure the audit
computed by hand, with net estate £1,234,280, allowances £850,000 and a £20,000 pooled
charitable exemption.

Regression: `tests/Unit/Services/Estate/` + `tests/Feature/Estate/` **308 passed / 1,002
assertions**, plus 73 across the downstream payload consumers (Coordination, Plans,
EstateAgent, PropertyReadConsumerParity). Pint clean, ESLint clean.

### Two pre-existing tests changed, and why that is not weakening them

`IHTRnrbAndCharitableExemptionTest` called `calculate($user, $spouse)` in three places
without the third argument. Under the old code the argument made no difference to the
allowance, so those tests **encoded F3**: married, sharing off, allowances doubled. Each
is explicitly about *"a married couple's"* combined £350,000.

They now pass `dataSharingEnabled: true`. **Every expected figure is unchanged** — only
the precondition is stated. A comment in the file records this so it does not read as an
assertion having been relaxed to fit.

---

## 4. IN FLIGHT

**Nothing.**

---

## 5. Deliberately NOT done

- **F4–F11**, per dispatch. The charitable percentage-of-the-wrong-thing (F4a), the
  reduced rate against a nil liability (F4b), the three-component Sch 1A question (F4c),
  the client-side allowance builders, Rule 2 hardcoded values, taper relief, the
  substring charity match.
- **`calculateProjectedValues()` untouched.** It holds an equivalent "who dies second"
  comparison inline. `survivingMember()` is the one home for that question going
  forward, and its docblock says converging the two belongs with **W-0137**, which owns
  that method and was under investigation by another batch. Behaviour is identical
  today, so nothing is broken by the wait.
- **`SpouseNRBTrackerService` not deleted** — W-0146.
- **No browser verification**, and **no writes to users 16/17**. `persona-passA3` was
  entering that household throughout; every fixture here is built from scratch.

---

## 6. Files changed

| File | Change |
|---|---|
| `app/Services/Estate/IHTCalculationService.php` | `pooledMembers()`, `survivingMember()`, `nrbDeductionForOneMember()`; F1/F2/F3 through `calculate()`, `calculateRNRB()`, `determineIHTRate()` |
| `app/Http/Controllers/Api/Estate/IHTController.php` | two new summary fields |
| `resources/js/components/Estate/IHTPlanning.vue` | maps the two new fields |
| `resources/js/components/Estate/IHTCalculationTable.vue` | the gift-deduction row |
| `tests/Unit/Services/Estate/IHTHouseholdConsistencyTest.php` | **new**, 12 tests |
| `tests/Unit/Services/Estate/IHTRnrbAndCharitableExemptionTest.php` | three calls state their precondition |
| `workforce/ops/board/W-0146-*.md` | **new** |

---

## 7. Environment

`laravel_testing_c` throughout; `pgrep` before every run. No migrations, no seeders, no
`.env`, no production query, no `/m` rebuild, **no database writes of any kind** — every
figure came from fixtures created and rolled back inside `RefreshDatabase`.
