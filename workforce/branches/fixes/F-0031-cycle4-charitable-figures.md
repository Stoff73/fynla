---
id: F-0031
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web]
consistency_checked: 2026-08-23T03:00:00Z
status: active
---


**Rule 22 handover for this agent:** [`HANDOVER-fix-cycle4-wills-2026-08-23.md`](HANDOVER-fix-cycle4-wills-2026-08-23.md) — what is not on the board, the branches this persona cannot reach, and the dead ends.
# F-0031 — Cycle 4: the two charitable figures, and the rates the messages asserted

**Agent:** build-lead (`fix-cycle4-wills`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0399 (the card), W-0431 (the rate literals) · **ID block:** W-0431 – W-0440
**Number issued after collision:** F-0029 and F-0030 were both taken by other
agents between dispatch and first write. This is F-0031.

**Predecessor, read first:** `F-0029-cycle4-wills-and-estate-figures.md` — the
same disease in three other places, and the batch that found this one.

---

## 1. The principle, and the correction I owed

**A figure and the sentence attached to it must answer the same question.**

Both defects here are that rule broken in opposite directions:

| | The figure | The sentence |
|---|---|---|
| **W-0399** | correct, and statutorily reasoned | says "Your will" about the household's |
| **W-0431** | correct, read from configuration | asserts a rate as a literal |

### I filed W-0399 on a wrong premise, and the premise was mine

I raised it as *"an individual numerator over a household denominator"*, with the
0.6% reading "implausibly low when it would be 1.4% of her own estate", and
severity **high** on the grounds that the card *"tells a user how much money to
give away and the number may not be theirs"*.

**Reading the code settled it the other way.** `determineIHTRate()` carries
**tax-compliance-reviewer's statutory ruling of 2026-08-21**, quoted in full at
`IHTCalculationService.php:1240-1258`:

- **`charitable_amount` — the section 23(1) exemption.** Pooled across the
  household, "because every pooled member's charitable legacies are paid and
  every one of them leaves the combined estate".
- **The 10% rate test — the survivor's will alone.** IHTA 1984 Schedule 1A tests
  the estate of ONE deceased person; the first-to-die's legacy was tested on the
  first death against a nil estate under spouse exemption. Verbatim: *"Summing
  both wills for the 10% test would over-qualify households for the 36% rate."*

And the percentage is not a scope mismatch either: this is a **second-death**
model, so the survivor holds the combined estate and £10,000 over £1,728,780 is
internally consistent.

**team-lead's instruction was to establish which question each figure answers
before changing any of them, and not to assume all four should be individual.
Following it is what turned a wrong bug report into a correct one.** Severity
lowered high → medium: the "increase by £112,878" instruction was already
computed from the correct pair, so nobody is told a wrong amount to give away.

---

## 2. Prior art

Checked 2026-08-23 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| W-0399 the two figures | `determineIHTRate()` already computes both and already documents why | **route** — publish the one that was discarded; change no arithmetic |
| W-0399 the card copy | `IHTPlanning.vue`'s own W-0132 scar: hardcoded rate labels beside differently-computed figures | **extend** — same lesson, applied to the sentence rather than the label |
| W-0431 rate literals | `TaxConfigService::getCharitableReducedRate()` / `getCharitableThresholdPercent()`, both existing and both already the one home | **route** — the message was the only consumer not using them |

---

## 3. W-0399 — the engine drew the distinction and threw it away

`charitable_rate_test_amount` was set at `:1333` and `:1349` and **read by
nothing**. Grepped across `app`, `resources` and `tests`: zero consumers. It never
entered the result array, so it never reached `IHTController`, so the card had
**one** charitable figure to render and **two** to explain.

What reached the screen was the pooled exemption alone, under **"Your will leaves
£20,000 to charity."** Each will leaves £10,000. The £10,000 then appeared beside
it inside `iht_rate_message` with nothing distinguishing them.

### Medium, and not tidiness

The actionable number — *"Increase by £112,878"* — is computed from the correct
pair, so **nobody is told a wrong amount to give away.** That is what separates
this from W-0391.

But *"Your will leaves £20,000 to charity"* is **a false statement about a legal
instrument**, on a legal-planning card, to a user deciding whether they have given
enough. A reader who believes their will already leaves £20,000 **has a wrong
picture of their own estate** even while the instruction beneath it is right — and
the natural response to that belief is to give less, or to stop. The wrong number
is not in the advice; it is in the picture the advice sits on. **The sentence is
therefore fixed properly rather than minimally.**

### The shape underneath: a distinction computed, then discarded before the user

**This is the read-boundary failure, and it is the second instance in one night.**

`app/Http/CLAUDE.md` axis 7 records the first: *"The Resource omits a field the
template gates on"* — `MortgageResource` serialises `fixed_interest_rate` but not
`fixed_rate_percentage`, so a `v-if` naming the second field is structurally
unreachable and no data can satisfy it. The rule, the column, the Store and the
write are all correct; **only the journey home is broken.**

This is the same shape one door over. Here the engine does something harder than
storing a value — it applies a statutory distinction that a tax reviewer had to
rule on — **and then drops the result before any consumer can see it.** The
service is right. The controller is right. The card is right about what it was
given. The distinction simply never crosses the boundary.

**Both instances are invisible to every sweep aimed at the write path**, and both
present as a *presentation* bug while the cause is a *publication* one. The
recognisable signature, worth having on sight:

> **a value computed and read by nothing.** `grep` for a key that a service sets
> and count its consumers. Zero means either dead code or a distinction that
> never reaches the user, and the two look identical from inside the service.

Two instances in one cycle suggests this belongs in `app/Http/CLAUDE.md` beside
axis 7 rather than only in a branch doc. **Not added here** — that file is shared
and two agents are editing `resources/js/` in this tree tonight. Raising it for
team-lead to place.

### The fix — no arithmetic changed

1. **`IHTCalculationService`** — the figure now leaves `determineIHTRate()`,
   through `assessTaxPosition()`, into the result array. **The third branch set
   it at all**, so the card could not tell "nothing given" from "figure
   unavailable"; it does now.
2. **`IHTController`** — published on `iht_summary.current`, with the distinction
   stated where the two sit together.
3. **`IHTPlanning.vue`** — the card names **what each figure is**, never whose,
   and draws the distinction only when the two differ.

**Why never "whose":** neither figure is "your will" on a married household. The
exemption is the household's; the rate-test amount is the **survivor's**, who is
not the logged-in user half the time. Labelling either "yours" swaps one false
sentence for another.

---

## 4. W-0431 — found by editing the sentences and reading them

Every rate in all three messages was a literal — `36%`, `40%`, `10%` — beside a
calculation reading `TaxConfigService`. **The sentence asserted a rate the figure
next to it was not necessarily computed at.**

This is `IHTPlanning.vue`'s own W-0132 defect one layer over. That component's
docblock records it: *"two hardcoded strings decided by a user toggle that never
loaded, so the label read 40% permanently while the figure next to it had been
computed at 36%."* **It was fixed in the component and survived in the server
message the component renders underneath it.**

Fixed with one formatter beside the rates it describes. **No user-visible change
today**, because configuration holds 0.40/0.36/0.10 — which is precisely why it
went unnoticed, and why the test moves the configuration instead of asserting the
current strings.

### The pattern, stated because it recurred

**When you fix a label that disagrees with its figure, check every layer that
contributes to that label.**

W-0132 fixed the disagreement in the component. The identical defect survived in
the **server message the component renders underneath it** — same card, same
sentence to the user, one layer down. A fix applied at the layer where the bug was
*noticed*, while the layer that *supplies* the text went unexamined.

The tell is that both layers produce prose about the same number, and only one of
them was in the diff. **A rate appearing in a string is a tax value regardless of
which layer assembled the string.**

---

## 5. Rule 9

All three messages now read "Inheritance Tax rate", never "IHT", and the card's
own new sentence says "before Inheritance Tax is worked out". Asserted by tests
that refuse the acronym anywhere in the sentence.

Reported in F-0029 as functional-before-cosmetic; fixed here because the
functional work put me in the file, which is what team-lead asked for.

---

## 6. Tests

| File | Cases | Guards |
|---|---|---|
| `tests/Unit/Services/Estate/CharitableExemptionVersusRateTestTest.php` | 8 | the two figures differ and each is the right one; identical for both partners; **the exemption moves and the rate test does not** when the first-to-die gives more; every rate comes from configuration; Rule 9 |
| `tests/Feature/Estate/CharitableFiguresPublishedTest.php` | 3 | the distinction survives to `iht_summary.current` — the journey home |
| `resources/js/components/__tests__/Estate/IHTPlanningCharitableCard.spec.js` | 9 | the pooled figure is never called "your will"; both named when they differ; neither explained when they agree; a missing rate-test figure degrades to single-figure wording rather than `£NaN`; **and the payload survives the hand-written mapping into `ihtData`** — the join, added after the browser found it broken |

### The probe that carries the weight

**The first-to-die's legacy goes from £5,000 to £80,000** — sixteen times larger,
and larger than the survivor's. A rate test that pooled would leap to £110,000.
The exemption moves by £75,000; **the rate test must not move at all**, and the
test asserts both halves. That is the countermeasure from `tests/CLAUDE.md` §4 —
assert the answer MOVES when the real input moves — applied to a figure whose
correctness is about what it *ignores*.

**The Rule 2 test does the same thing to configuration:** it sets a **31%**
reduced rate and a **12%** threshold, values nothing else in the codebase uses, so
neither can be produced by a fallback or a coincidence.

### Fixtures are asymmetric by construction

£30,000 and £5,000 legacies giving **£35,000 pooled against £30,000 tested**, on
estates of £900,000 and £400,000. **The persona that found this leaves £10,000
from each spouse, so the pooled figure is exactly double the tested one** — any
reading that halved or doubled either would land on the other, and every "is it
the survivor's?" question has the same answer both ways. Nothing here uses figures
where that is possible.

### Mutation-tested — five, each disjoint

| Mutation | Red | Green |
|---|---|---|
| rate test pools both wills (the over-qualifying bug) | 3 | the rest |
| `charitable_rate_test_amount` dropped from the result | 5 | 2 |
| the card's "Your will leaves …" restored | 4 frontend | 3 |
| the `IHT` acronym restored | 1 | 7 |
| `36%` re-hardcoded in the message | 1 (the Rule 2 case) | 7 |
| the field re-dropped from the `ihtData` mapping | 2 (the join cases) | the 7 template cases — **correctly, since they inject past the mapping** |

**One case is labelled in the file as unable to discriminate:** the single-person
figures-coincide test. With one will there is nothing to pool, so the two figures
are equal whether the distinction is implemented correctly, backwards, or not at
all. Kept as a regression check, counted as nothing.

**Decoy check:** every test named after a class or method resolves and calls it —
`IHTCalculationService`, `TaxConfigService`, `IHTPlanning`, and the two endpoints.

**Suites:** `tests/Unit/Services/Estate` + `tests/Feature/Estate` → **421
passed**. `Unit/Services/Plans`, `Feature/Plans`, `Feature/Dashboard`,
`Feature/Mobile`, `Unit/Services/Mobile` → **295 passed**. Vitest Estate → **21
passed**. Pint clean. `DB_DATABASE=laravel_testing_t` throughout.

---

## 6a. The browser caught what the suite could not — third instance, in my own fix

**Read on the live page, the card was still wrong.** The false label was gone and
Rule 9 was clean, but **"across your household" and the second-death sentence were
both absent while the two figures plainly differ** (£20,000 against £10,000).

`ihtData` held `charitable_deduction: 20000` and **no
`charitable_rate_test_amount` at all**. The tell was `net_estate_value` sitting in
`ihtData` — a key that does not exist in `iht_summary.current`. So
`loadIHTCalculation()` builds that object by **enumerating fields by hand rather
than spreading the payload**, and dropped the new field one layer before the card.

**Service right. Controller right. Template right. The allowlist between them was
not, and nobody thinks of a hand-written mapping as a boundary.** That is the
computed-but-unread shape for the third time in one batch — and this time inside
the fix for the first two. **A field absent from a hand-written mapping is
invisible in exactly the way a field absent from a Resource is.**

Fixed at `IHTPlanning.vue:1684`, with `?? null` rather than `|| 0`: the card
distinguishes "no distinction to draw" from "nothing given to charity", and a
zero-coalescing default collapses those into one.

### Why the suite was blind, stated rather than glossed

**The component spec injects `ihtData` directly via `setData`.** It supplies the
object the mapping was supposed to produce, so it exercises the template and
**skips the mapping entirely**. Seven green cases over a card that rendered wrong
on the live page.

**That is the Fixture variant** (`tests/CLAUDE.md` §4): the data the test sets up
means the broken branch is never entered. Nothing in the file said *"and no
mapping runs here"* — which is precisely what makes this variant harder to see
than a mock or a clamp.

The Feature test asserted the endpoint **publishes** the field, which it did.
**Neither test covered the join.** Two cases now do, driving the component's real
`mounted()` with an endpoint-shaped payload so the real mapping runs. Mutation F —
re-dropping the field from the mapping — turns exactly those two red and leaves
the seven template cases green.

**The generalisation, since this is now the third instance:** a value can be
correct at every layer and still never arrive. **Testing the ends does not test
the join**, and a suite that injects at the boundary it means to prove will pass
for the wrong reason every time.

## 6b. The tax-compliance gate — CLEARED WITH CONDITIONS, 2026-08-23

Verdict: `workforce/ops/handoffs/W-0399/tax-compliance-reviewer-verdict-2026-08-23.md`.
**No condition blocks the batch; no figure in it is wrong; the 2026-08-21 ruling
is intact and pinned.**

| | | |
|---|---|---|
| **C1** | false comment at `IHTPlanning.vue:225-232` (blocking, comment-only) | **DONE** |
| **C2** | the percentage uses the wrong denominator | **FILED — W-0433** |
| **C3** | a sixth mutation survived the Rule 2 test | **DONE** |
| **C4** | four more Rule 2 instances | **ADDED to W-0432**, raised to high |

### C1 — the correction I had already made in prose was still false in the code

The comment read *"On this persona both spouses left £10,000, so the two
coincide."* **They do not** — the exemption pools to £20,000 while the rate test
stays at £10,000. It was the prediction I corrected to team-lead an hour earlier,
still sitting in the file. The reviewer's line is the one worth keeping:

> **in a batch about a false sentence attached to a correct figure, a false
> comment inside the fix is the one thing that must not survive.**

**A correction made in conversation is not a correction made in the artefact.**

### C3 — my own guard had a hole the shape of the thing it guarded

The Rule 2 test moved `reduced_rate_charity` and `charity_threshold_percent` and
left `standard_rate` at 0.40 — **so a re-hardcoded "40%" passed it green**, and
"40%" is the rate quoted in every branch of the message. All three now move
(0.41/0.31/0.12), and Mutation G — re-hardcoding the standard rate alone — turns
it red where it previously did not.

**A guard that moves two of three inputs silently certifies the third.**

### C4 — two of the four are not literals-in-prose at all, and my sweep could not have found them

`WillAnalysisService:74` is `$potentialSaving = $baseline * 0.04;` — **the 40−36
differential baked into arithmetic**, understating the saving by more than half
at a 31% reduced rate. `EstatePlanService:517` states the Schedule 1A test
against the **net estate** rather than the baseline — **a wrong statement of law,
live, on `/plans/estate` and in printed plans.**

**My sweep grepped for percentage literals inside quoted strings.** `* 0.04`
contains no `%` and no string. **A rate expressed as a decimal in arithmetic is
structurally invisible to a sweep for rates expressed as percentages in prose** —
and it is the more damaging form, because it changes a figure rather than a
caption. Recorded in W-0432 so the next sweep runs both passes.

### The coupling the reviewer returned — the most fragile shape a correct calculation can have

> **If any future change makes the model actually settle the first death, the
> pooled exemption must be removed in the SAME change, or the first legacy is
> relieved twice. They are correct only together.**

Pooling has no household estate in law. It reproduces the right tax **only
because the model never settles the first death** — the combined estate still
holds the first-to-die's assets in full, so it over-includes by exactly what the
pooled exemption over-deducts. **Two mechanisms each wrong alone and right in
combination**, where the natural improvement to one silently breaks the tax and
every test on the current model stays green while it happens. On W-0399 in those
words.

### Scope boundary — the clearance does not travel

Of **348 changed lines** in `IHTCalculationService.php`, roughly **40 are mine**.
**The gate cleared only the charitable-figures and rate-literal hunks.** The
projection edits in that file are F-0026's and are **not** covered.

## 7. What is NOT done, and is owed

- ~~The `tax-compliance-reviewer` gate has not been run.~~ **Cleared with
  conditions, 2026-08-23. C1 and C3 done; C2 filed as W-0433; C4 added to
  W-0432.** I did not run it and did not self-certify it.
- ~~The rendered page is unverified.~~ **Read 2026-08-23 as David Jones (16)**,
  identity confirmed per surface first (`sessionStorage.auth_token` → id 16;
  `localStorage.m_scaffold_token` absent), estate caches cleared by hand.
  Verbatim:

  > **£20,000 is left to charity across your household**, and comes out of the
  > estate before Inheritance Tax is worked out.
  > **The 10% test that decides the reduced rate looks only at the will operating
  > on the second death, which leaves £10,000.**
  > Standard **Inheritance Tax** rate of 40% applies. …to qualify for the 36% rate.

  Screenshot `153-web-david-charitable-card-20000-household-10000-second-death-W-0399.png`.
  **Sarah was not logged in, deliberately** — both figures are properties of the
  household's second death rather than of the session, so both accounts render
  the card identically, and a test pins that. Burning a login and an MFA code to
  re-read the same sentence would have held an exclusive resource for nothing.
- **One modelling question deliberately not reopened:** whether both spouses'
  legacies should pool into the section 23(1) exemption at all, given each will
  expresses its charitable gift as a second-death provision. That ruling carries
  a product sign-off (W-0154). **Flagged for re-confirmation because I am in the
  file; not challenged.**
- **`effective_tax_rate` and `iht_liability` untouched**, per team-lead's standing
  ruling. Still W-0154/W-0188's.

## 8. Rule 19 — `/m`

**No counterpart exists, and this is checked rather than assumed.**
`resources/mobile/` contains no consumer of `calculate-iht`, `iht_summary` or any
charitable figure — the only match for "charitable" is an expenditure category
label in `Expenditure.vue`. The `/m` estate screen shows no charitable
information at all. **No `/m` change, and no parity claimed.**

## 9. Raised, not fixed

| Item | Why |
|---|---|
| a sweep for rate literals inside user-facing strings beyond this method | **Done — W-0432**, now high severity after the gate added four instances I missed, two of them rate literals in *arithmetic* rather than prose. |
| the charitable percentage's denominator | **W-0433** — 0.6% against a 10% threshold measured on a different base; the statutory figure is 0.81%, and `EstatePlanService::charitablePercentage()` already computes it correctly. Not folded in: it moves a published figure. |

## 10. In flight

**Nothing.** Every edit applied, linted, covered and mutation-tested. Scratch
files outside the repository. No commit, no PR, no git lock.
