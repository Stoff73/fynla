---
id: W-0451
title: The estate decision trace publishes a saving of £19,580 beside two figures that subtract to £34,351 — a 43% error on a surface whose purpose is auditability
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0033-cycle4-the-charitable-saving-and-the-percentage-denominator.md
owner: build-lead
reviewers: [tax-compliance-reviewer, quality-lead]
status: done
claimed_by: fix-cycle4-figures
severity: high
surfaces: [web]
created: 2026-08-23T04:30:00Z
claimed: 2026-08-23T04:08:43Z
blocked_by: []
gate: tax-compliance-reviewer-cleared-2026-08-23
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0432, W-0433, W-0431]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer verdict 2026-08-23 on W-0432/W-0433, condition C1 (answering Q2)
---

## Intent

**Raised by the tax-compliance gate answering a question I flagged rather than
fixed.** I left `WillAnalysisService`'s `potential_saving` computing on the
baseline and asked whether that base was right. The answer is that it is not, and
that **the application already contradicts itself in a single sentence, live.**

### The sentence, rendered for user 16 from `EstateAgent::generateRecommendations()`

> *"On the taxable estate of £858,780: at 40% = £343,512, at 36% = £309,161 —
> saving £19,580."*

**£343,512 − £309,161 = £34,351.** The sentence publishes **£19,580** beside its
own two figures.

**A £14,771 error — 43% — checkable on a calculator, on a decision trace whose
entire stated purpose is auditability.** And under the sentence's own promise the
actual reduction is £50,902, so **there are three candidate answers and the
published one is none of them.**

### Cause — two mechanisms, one quantity

| Mechanism | Base |
|---|---|
| `EstateAgent` (the two figures) | the **taxable estate** |
| `WillAnalysisService::analyzeCharitableBequests()` (`potential_saving`) | the **baseline** |

The baseline over-includes by exactly the charitable gift plus the residence
band. Confirmed against this household: **£814,500 − £629,500 = £185,000 =
£10,000 + £175,000.**

### Why it was not fixed in F-0032

F-0032 made the RATE configuration-driven — `$baseline * ($standard − $reduced)`
instead of `$baseline * 0.04`. **It deliberately did not re-derive the formula**,
because changing the base moves a published figure and that is not a Rule 2 fix.
The reviewer confirmed the boundary was right and answered the question behind it.

**F-0032 amplifies this under a configuration move but does not cause it:** the
gap is `(taxable − baseline) × differential`, so at 41/31 it grows 2.5×. **At the
seeded 40/36 the differential is exactly 0.04 and the published figure is
byte-identical before and after.** Not a reason to hold F-0032.

## Acceptance

- [ ] One definition of "what the reduced rate saves this estate", in one place,
      read by both `EstateAgent` and `WillAnalysisService`.
- [ ] The two figures a sentence prints and the saving it claims **reconcile by
      subtraction** — asserted as arithmetic, not as literals, because that is
      the property a reader checks.
- [ ] **The error direction varies by household**, so it cannot be defended as
      conservative and must not be left with a caveat.
- [ ] Fixtures where the taxable estate and the baseline differ by a value that
      is not a round multiple — on this household the gap is exactly the
      residence band plus the legacy, which is guessable.
- [ ] `tax-compliance-reviewer` on the resulting definition: the reduced rate
      applies to the chargeable estate, so which of the three candidates is the
      lawful answer needs stating, not assuming.

## Working notes

**2026-08-23 — build-lead (`fix-cycle4-figures`), F-0033. Claimed.**

**The lawful answer chosen, for the gate to judge.** Of the three candidates the
reviewer set out, the item is built on the third — **the actual reduction in the
Inheritance Tax bill** — because that is the question the sentence asks
(*"increase giving by £X and save £Y"*). Reaching the threshold does two things
and the old sentence modelled only one: the rate falls **and** the gift itself
leaves the estate under the section 23(1) exemption. One formula covers both
branches:

```
saving = standard × E  −  reduced × (E − shortfall)
```

For an estate already qualifying the shortfall is zero, both bills sit on the same
chargeable estate, and it collapses to the rate differential on it — the
reviewer's own reading of what the quantity "purports to describe". In both
branches one of the two bills is the actual `iht_liability`.

**The threshold does not move with the gift** — Sch 1A para 5 adds the donated
amount back — which is what makes one subtraction the whole answer. Pinned by its
own case.

**W-0451 and W-0452 were one disease.** The trace printed the HOUSEHOLD chargeable
estate beside a saving derived from a baseline struck on the INDIVIDUAL's net
estate. Fixing the base alone would have left a sentence whose two sides came from
different households; fixing the estate alone would have left the 43% error. Both
moved.

**A FOURTH mechanism was found and routed:** `IHTPlanning.vue:962` computed the
saving in the browser as the differential on the chargeable estate, under the words
*"your estate would pay about £Y less"* — a fourth answer, and the wrong answer to
its own sentence. Rule 20 makes routing it part of this fix. It also needed a line
in the component's hand-written mapping — the fifth instance of that allowlist
shape this cycle.

**NOT fixed, named:** `GiftingStrategy:227` is a fifth mechanism computing the same
quantity. `recommendOptimalGiftingStrategy` has **zero production callers** (grep
across `app`, `routes`, `resources`), so no user sees its figure. Left alone
deliberately; recorded so nobody concludes the consolidation was incomplete.

**Eight mutations, all killed.** Two survived the first pass, both faults in my own
cases — see F-0033 §6. The more useful: the unvalued-gifts case read only from the
survivor's session, so a mutation reading the logged-in user's will passed it. That
is the blindness the reviewer named on W-0433, reproduced one screen below a
docblock describing it. **Documenting a blind spot does not inoculate you against
it.**

Branch doc: `workforce/branches/fixes/F-0033-cycle4-the-charitable-saving-and-the-percentage-denominator.md`

- 2026-08-31 build-lead: **CLOSED — verified against `dev`, and the subtraction now comes out.**
  `EstateAgent:797-817` records the defect and the fix in place: both bills, both bases and the
  saving all come from the one definition in `IHTCalculationService::assessTaxPosition()`, so the
  subtraction a reader performs on the sentence IS the subtraction the application performed.
  The second bill names its own base — increasing the gift lowers the rate AND removes the gift
  from the estate, so 36% is charged on a smaller estate than 40% was, which is why a sentence
  printing one base for two bills could never be made to add up.
  `WillAnalysisService:144-149` publishes `taxable_estate`, `taxable_estate_if_qualifying`,
  `tax_at_standard_rate` and `tax_at_reduced_rate` so any sentence quoting the saving can print
  its working.
  **C1 is closed too** (`EstateAgent:758`): the sentences now name the SURVIVOR whose position the
  figures describe, not whoever is logged in.
