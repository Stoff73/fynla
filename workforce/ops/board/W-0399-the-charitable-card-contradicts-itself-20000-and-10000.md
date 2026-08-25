---
id: W-0399
title: The Charitable Bequest card states £20,000 and £10,000 for the same legacy, two sentences apart, on both spouses' accounts
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0031-cycle4-charitable-figures.md
owner: build-lead
reviewers: [quality-lead, tax-compliance-reviewer]
status: gated
claimed_by: build-lead
severity: medium
surfaces: [web, m]
created: 2026-08-23T02:35:00Z
claimed: 2026-08-23T02:45:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0391, W-0154, W-0134]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

**Found during the browser verification of W-0391**, not by the tester — it is on
a card two rows above the one I was sent to check, and it is the same disease.

**Surface:** desktop web, `/estate`, Charitable Bequest card
(`resources/js/components/Estate/IHTPlanning.vue`).

### Actual — the card contradicts itself within two adjacent sentences

Read verbatim off Sarah Jones's screen (id 17), and **identical on David's**:

```
Your will leaves £20,000 to charity.
Standard IHT rate of 40% applies. Your charitable giving of 0.6% (£10,000)
is below the 10% threshold of £122,878. Increase by £112,878 to qualify
for 36% rate.
```

**£20,000 and £10,000, describing the same legacy, one line apart.**

### CORRECTED DIAGNOSIS — both figures are right, and a tax ruling says so

**Filed on the assumption that the figures disagreed. They do not. The premise
was mine and it was wrong.**

`IHTCalculationService::determineIHTRate()` carries **tax-compliance-reviewer's
statutory ruling of 2026-08-21**, written out in full at `:1240-1258`:

- **`charitable_deduction` £20,000 — the section 23(1) exemption.** Pooled,
  because "every pooled member's charitable legacies are paid and every one of
  them leaves the combined estate". Deducting only the logged-in user's
  understated it on both accounts.
- **The 10% rate test £10,000 — the survivor's will alone.** IHTA 1984 Schedule
  1A tests the estate of ONE deceased person. The first-to-die's legacy was
  tested on the first death against an estate that, under full spouse exemption,
  is nil. The comment is explicit: *"Summing both wills for the 10% test would
  over-qualify households for the 36% rate."*

**And the 0.6% is not a scope mismatch either.** I claimed it put an individual
numerator over a household denominator and "would be 1.4% of her own estate".
Wrong: this is a **second-death** model, so the survivor holds the combined
estate. £10,000 over £1,728,780 is internally consistent.

**The arithmetic was never touched by this fix.**

### The real defect — the engine draws the distinction and then discards it

`charitable_rate_test_amount` was computed at `:1333` and `:1349` and read by
**nothing**. Grepped across `app`, `resources` and `tests`: zero consumers. It
never entered the result array, so it never reached `IHTController`, so the card
had **one** charitable figure to render and **two** to explain.

What survived to the screen was the pooled exemption alone, rendered at
`IHTPlanning.vue:219` under **"Your will leaves £20,000 to charity."** That
sentence is false for both spouses: each will leaves £10,000. The £10,000 then
appeared beside it inside `iht_rate_message` with nothing saying they answer
different questions.

**The figure is right. The label attached to it is not.** Same family as W-0391
and W-0397 — a household figure under a first-person label — but the fix is
publishing a figure and rewording a card, not changing a calculation.

### Medium severity, and the sentence still gets fixed properly rather than minimally

The actionable number — *"Increase by £112,878"* — is computed from the correct
pair, so **nobody is being told a wrong amount to give away.** That is what
separates this from W-0391, and it is why this is medium and that was high.

**It is not tidiness.** *"Your will leaves £20,000 to charity"* is **a false
statement about a legal instrument**, on a legal-planning card, to a user who may
be deciding whether they have given enough. A reader who believes their will
already leaves £20,000 **has a wrong picture of their own estate** even while the
instruction beneath it is correct — and the natural response to that belief is to
give less, or to stop. The wrong number is not in the advice; it is in the
picture the advice sits on.

### Original filing, retained for the record — three scopes in one card

| Figure | Scope | Source |
|---|---|---|
| £20,000 | **household** — both spouses' legacies pooled | `calculation.charitable_deduction`, rendered at `IHTPlanning.vue:219` |
| £10,000 | **individual** — this testator's actual legacy | `WillAnalysisService:78` `charitable_total` |
| 0.6% and the £122,878 threshold | **household** — 10,000 ÷ 1,728,780, and 10% of (1,728,780 − 500,000) | the Inheritance Tax calculation's own baseline |

Each will holds exactly one £10,000 charitable legacy — verified in the database:
David → Cancer Research UK, Sarah → British Heart Foundation. So **£20,000 is
correct for the household and wrong for either person**, and the card presents it
under the heading "Your will".

The percentage is a third thing again: an **individual** numerator over a
**household** denominator, which is why 0.6% looks implausibly low for a £10,000
gift out of a £739,280 estate (it would be 1.4%).

### Why this is not cosmetic

The card tells the user how much more to give to reach the reduced 36% rate:
*"Increase by £112,878"*. That instruction is computed from the household
baseline. **A user acting on it cannot know whether the number applies to them or
to the couple**, and the two answers differ by the whole of the other spouse's
legacy. This is W-0154's third defect — a charitable test run against a baseline
that does not correspond to the computation it qualifies — surviving in the
presentation layer after being fixed in the engine.

## Fix

**No arithmetic changed. Three edits, all about the journey home.**

1. `IHTCalculationService` — `charitable_rate_test_amount` now leaves
   `determineIHTRate()`, through `assessTaxPosition()`, into the result array.
   The third branch (no legacy recorded) set it at all, so the card could not
   tell "nothing given" from "figure unavailable"; it does now.
2. `IHTController` — published on `iht_summary.current` beside
   `charitable_deduction`, with the distinction stated where the two sit
   together.
3. `IHTPlanning.vue` — the card names **what each figure is** rather than whose
   it is, and only draws the distinction when the two differ.

**Why not "whose":** neither figure is "your will" on a married household. The
exemption is the household's, and the rate-test amount is the **survivor's** —
who is not the logged-in user half the time. Labelling either one "yours" would
replace a false sentence with a different false sentence.

New copy, when the two differ:

> **£35,000** is left to charity across your household, and comes out of the
> estate before Inheritance Tax is worked out.
> The 10% test that decides the reduced rate looks only at the will operating on
> the second death, which leaves **£30,000**.

When they coincide — a single person, or a couple where only one partner left a
legacy — the second sentence is suppressed rather than explaining a distinction
that does not exist.

**Rule 9 applied while in the file:** the three rate messages now read
"Inheritance Tax rate", never "IHT".

## Acceptance

- [x] The card can no longer be read as self-contradicting: each figure is named
      for the question it answers.
- [x] The percentage is left alone — it was already consistent, and the claim
      that it was not has been retracted above.
- [x] The "increase by £X" instruction is unchanged, because it was already
      computed from the correct pair (rate-test amount against combined
      baseline).
- [x] Asserted on a fixture where the two figures **differ** — £30,000 and
      £5,000 legacies giving £35,000 pooled and £30,000 tested. On the persona
      that found this both spouses left £10,000, so the pooled figure is exactly
      double the tested one and any halving or doubling lands on a real number.
- [x] Mutation-tested five ways, each turning a disjoint set red.
- [ ] **`/m` — no counterpart exists.** `resources/mobile/` has no consumer of
      `calculate-iht` or `iht_summary` (grepped). The `/m` estate screen shows no
      charitable figure at all. **Stated rather than assumed, and not claimed as
      parity.**
- [x] **Rendered page read** — 2026-08-23, as David Jones (16), identity confirmed
      per surface before reading, estate caches cleared by hand. The card now
      names all three figures for what they are:

      > **£20,000 is left to charity across your household**, and comes out of the
      > estate before Inheritance Tax is worked out.
      > **The 10% test that decides the reduced rate looks only at the will
      > operating on the second death, which leaves £10,000.**
      > Standard **Inheritance Tax** rate of 40% applies. …to qualify for the 36% rate.

      Screenshot `153-web-david-charitable-card-20000-household-10000-second-death-W-0399.png`.
      **Sarah not logged in, deliberately** — both figures are properties of the
      household's second death, not of the session, so both accounts render this
      card identically and a test pins that.
- [x] **A defect the whole suite missed, found on that page and fixed.** See below.
- [x] **`tax-compliance-reviewer` gate: CLEARED WITH CONDITIONS.** C1 and C3
      done, C2 filed as W-0433, C4 added to W-0432. See the verdict section.

## The browser found a third instance of the same shape — inside this fix

**The first read of the live card was still wrong.** The false label was gone and
Rule 9 was clean, but **"across your household" and the second-death sentence were
both missing while the two figures plainly differ.**

`ihtData` held `charitable_deduction: 20000` and **no
`charitable_rate_test_amount`**. The tell was `net_estate_value` in `ihtData` — a
key that does not exist in `iht_summary.current`. `loadIHTCalculation()` builds
that object by **enumerating fields by hand rather than spreading the payload**,
and dropped the new field one layer before the card.

**Service right, controller right, template right — and the hand-written mapping
between them is a boundary nobody treats as one.** Fixed at
`IHTPlanning.vue:1684` with `?? null` rather than `|| 0`, because the card
distinguishes "no distinction to draw" from "nothing given to charity".

### Why the tests were blind, stated rather than glossed

**The component spec injects `ihtData` via `setData`** — it supplies the object
the mapping was supposed to build, exercising the template and **skipping the
mapping entirely.** Seven green cases over a card that rendered wrong live. **That
is the Fixture variant** (`tests/CLAUDE.md` §4): nothing in the file said *"and no
mapping runs here"*.

The Feature test proved the endpoint **publishes** the field. **Neither covered
the join.** Two cases now do, driving the real `mounted()` with an
endpoint-shaped payload. Re-dropping the field turns exactly those two red and
leaves the seven template cases green.

**A value can be correct at every layer and still never arrive. Testing the ends
does not test the join.**

## TAX-COMPLIANCE GATE: CLEARED WITH CONDITIONS — 2026-08-23

Full verdict: `workforce/ops/handoffs/W-0399/tax-compliance-reviewer-verdict-2026-08-23.md`.
**No condition blocks the batch. No figure in it is wrong. The 2026-08-21 ruling
is intact and genuinely pinned.**

| Condition | Disposition |
|---|---|
| **C1** false comment at `IHTPlanning.vue:225-232` (blocking, comment-only) | **DONE** — the comment claimed both figures coincide on this persona; they do not |
| **C2** the percentage uses the wrong denominator | **FILED as W-0433**, not folded in — it moves a published figure |
| **C3** a sixth mutation survived the Rule 2 test | **DONE** — `standard_rate` now moves to 0.41 too |
| **C4** four more Rule 2 instances | **ADDED to W-0432**, severity raised to high |

### THE COUPLING — record it, because it is invisible until someone improves the model

Pooling is a construct with no household estate in law, and it reproduces the
right tax **only because the model never settles the first death**: the combined
estate still holds the first-to-die's assets in full, so it over-includes by
exactly what the pooled exemption over-deducts.

> **If any future change makes the model actually settle the first death, the
> pooled exemption must be removed in the SAME change, or the first legacy is
> relieved twice. They are correct only together.**

**Two mechanisms that are each wrong alone and right in combination is the most
fragile shape a correct calculation can have** — because the natural improvement
to one of them silently breaks the tax, and every test on the current model stays
green while it happens.

### Q3 advisory — no change now, recorded for whoever revisits the wording

The card says *"looks only at the will operating on the second death"*. That is a
statement about the **model** presented as one about the **test**: Schedule 1A
tests each component of an estate separately, and Fynla models neither divergent
case. Accurate as law for what Fynla models; **the safer verb if this is ever
reworded is "the estate passing on the second death."**

### Scope boundary of the clearance — do not let it travel

Of **348 changed lines** in `IHTCalculationService.php`, roughly **40 are mine**.
**The gate cleared only the charitable-figures and rate-literal hunks.** The
projection edits in that file belong to F-0026 and are **not** covered by this
verdict.

## The gate, before it reported

team-lead made tax-compliance review a condition. **I did not run it and did not
self-certify it** — the same principle that keeps Build from writing its own
evidence pack applies harder to a statutory gate. Retained below because what was
prepared for the reviewer is what made the review bounded.

What the reviewer needs to know, to make that review cheap:

- **No arithmetic changed.** The diff adds a key to two arrays, publishes it, and
  rewords three sentences plus one card. The statutory ruling of 2026-08-21 is
  untouched and is now pinned by tests that fail if the rate test is pooled.
- **Rule 2 was violated in the messages and is now fixed** — see **W-0431**.
- **One modelling question I deliberately did not reopen:** whether both spouses'
  legacies should pool into the section 23(1) exemption at all, given each will
  expresses its charitable gift as a second-death provision. That is a ruling
  with a product sign-off already attached (W-0154). **Flagged for
  re-confirmation because I was in the file, not challenged.** **The reviewer
  re-confirmed it on the statute and returned the coupling recorded above** —
  which is a better answer than the sign-off alone, because it names the
  condition under which the ruling stops holding.

## Working notes

- 2026-08-23 build-lead: **corrected my own diagnosis on reading the code.** The
  figures were never in conflict; the distinction between them simply never
  reached a screen. Severity lowered high → medium: nobody is being told a wrong
  amount to give away, because the instruction was already computed from the
  correct pair. The harm is a false sentence and two unexplained numbers on a
  legal-planning card.
- 2026-08-23 build-lead: found while browser-verifying W-0391. Evidence:
  `tests/Persona/20-08-2026_run/pass-a-web/152-web-sarah-charitable-card-says-20000-and-10000-W-0399.png`.
- **Rule 9 now fixed**, since the functional work put me in the file: all three
  rate messages spell out "Inheritance Tax", asserted by a test that also
  refuses the acronym anywhere in the sentence.
