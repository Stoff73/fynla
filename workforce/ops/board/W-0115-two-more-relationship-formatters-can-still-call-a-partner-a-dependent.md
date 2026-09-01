---
id: W-0115
title: Two more relationship formatters survive outside the family surfaces, and one of them can still tell a user their partner is a dependent
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0009-batch-i-onboarding-spouse.md
owner: build-lead
claimed_by: fix-batch-I
status: done
severity: medium
surfaces: [web]
created: 2026-08-21T19:50:00Z
claimed: 2026-08-21T20:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [familyMemberRelationshipLabel in resources/js/utils/familyMember.js, FamilyMember::getDisplayRelationshipAttribute]
prior_art_outcome: route
constitution_refs: [07-quality-bar, 04-voice]
---

## Intent

Raised by `fix-batch-I` while fixing W-0114. Reported rather than fixed, because
the two survivors live in modules other batches own and I could not verify their
payload shapes end to end.

### The rule W-0114 established

`family_members.relationship` is an enum of four values and the product offers
six, so a partner is stored as `other_dependent` and a step child as `child`.
**Storing an alias is fine; displaying the alias as though it were the truth is
not** — it makes the application state, in its own voice, that someone's partner
is a dependent.

W-0114 added `family_members.stated_relationship` (additive, nullable, display
only) and ONE display home on each side:

- `FamilyMember::getDisplayRelationshipAttribute()`, appended as
  `display_relationship` on every serialisation;
- `familyMemberRelationshipLabel()` in `resources/js/utils/familyMember.js`.

`FamilyMembers.vue` and `FamilyInfoStep.vue` were converged onto it, and their
two divergent formatters deleted.

### The two that remain

| File | Formatter | Exposure |
|---|---|---|
| `resources/js/views/Risk/RiskFactorDetailPage.vue:559` | `rel.replace(/_/g, ' ')` + title case | **Real.** It renders `factorData.components.dependants`, which can include `other_dependent` rows — and an aliased partner flagged as a dependant lands there. It would print "Other Dependent" for the user's partner. |
| `resources/js/components/Savings/SaveAccountModal.vue:1071` | label map, including `other_dependent: 'Dependant'` and a `step_child` branch | **Latent.** It filters to children, so a partner cannot appear, and `step_child` is stored as `child`, so that branch is already dead code. Wrong-in-principle rather than wrong-on-screen. |

Four vocabularies existed for one thing. Two are gone; these are the other two.

## Acceptance

- [ ] Both read `familyMemberRelationshipLabel()` — one home, not four (Rule 20).
- [ ] Verify what `factorData.components.dependants` actually carries before
      converging the Risk page. If it is not a `FamilyMember` serialisation it
      has no `display_relationship`, and the fix is to make the risk payload
      carry it rather than to guess on the client.
- [ ] Check the casing on both surfaces after the change — the helper returns
      lowercase words deliberately and each surface applies its own
      capitalisation, so a surface without `capitalize` will regress visually.
- [ ] `SaveAccountModal`'s dead `step_child` branch goes with it.
- [ ] Note the spelling divergence while you are there: `SaveAccountModal` says
      "Dependant" (British) and the enum value is `other_dependent` (American).
      User-facing text is British per CLAUDE.md; the column name is code and
      stays as it is.

## Working notes

Raised by `fix-batch-I` from the W-0111–W-0120 block. Deliberately not fixed
inside W-0114: Risk and Savings belong to other batches, the Risk payload shape
is unverified, and converging a formatter I cannot test end to end risks a casing
regression on a page I would not see. The harm is bounded — a partner has to be
flagged as a dependant to reach the Risk list at all — but it is the same false
statement W-0114 exists to remove, so it should not sit unrecorded.


---

## Working notes — fix-batch-I, 2026-08-21 (append-only)

### Half one — `SaveAccountModal.vue` — DONE

Converged onto the shared helper. Its private map was the fourth vocabulary for
one thing, and it carried a `step_child` branch that **could never fire**: step
children are stored as `child`, so no row ever reaches it with that value.

Collision check before starting, as instructed. The file is uncommitted-modified
in the shared tree, but its **mtime is 10:52:41 and it is now 20:07** — nine
hours stale, so nobody is writing to it. `fix-batch-F`'s hunks are at lines
~562, ~571 and ~807-814; my change is the import plus lines 257 and ~1071.
**No textual overlap.**

One thing the conversion needed that the card surfaces did not:
`familyMemberRelationshipTitle()`. The label helper returns lowercase words
because the family cards apply `capitalize` themselves, but this call site renders
into an `<option>` inside a `<select>`, which does not reliably take that
styling — returning lowercase there would have shipped "(child)" where the user
previously read "(Child)". That is the casing regression this item's own
acceptance warned about, met in the first file. One home for the words, one for
the casing rule, both in the util; surfaces choose the form they need.

`eligibleChildren` comes from `userProfile/juniorIsaEligibleChildren`, which
filters `state.familyMembers` — real `FamilyMember` serialisations, so
`display_relationship` is present and the shared helper works as intended.

No spec exists for `SaveAccountModal`; both spec locations were searched this
time (`tests/frontend/` **and** `resources/js/components/__tests__/`), which is
the lesson from F-0009 §23 applied rather than restated.

### Half two — `RiskFactorDetailPage.vue` — STOPPED, and here is why

**It needs a payload change, not a formatter change.** Reported to the team lead
rather than actioned, per instruction.

`app/Services/Risk/AutoRiskCalculator.php:271-305` builds the list by hand:

```php
$dependants = FamilyMember::where('user_id', $user->id)
    ->where('is_dependent', true)
    ->get(['first_name', 'relationship']);
...
'dependants' => $dependants->map(fn ($d) => [
    'name' => $d->first_name,
    'relationship' => $d->relationship,
])->toArray(),
```

Three consequences:

1. It is **not** a `FamilyMember` serialisation, so no appended attribute reaches
   the client and `display_relationship` is simply absent.
2. The partial `get(['first_name', 'relationship'])` does not even load
   `stated_relationship`, so the accessor could not compute the right answer if
   it were appended — it would read a missing attribute, fall back to
   `relationship`, and return the alias.
3. **Converging the Vue formatter alone would change nothing.** The client would
   fall back to `relationship` and still print "Other Dependent" for a partner.
   It would look like a fix and be one.

The actual fix is two lines in the Risk service — add `stated_relationship` to
the select, map `display_relationship` instead of `relationship`. Small, but it
is a change to a Risk service, which is the boundary the team lead asked me to
stop at while `iht-audit` works adjacent figures.

**Exposure, so it can be prioritised honestly:** the list filters
`is_dependent = true`, so a partner appears only if someone ticked "financially
dependent" for them. Narrow — but it is the same false statement, and it is the
one place it still renders.


### Coupling check — the team lead's stop condition, answered

Instruction: *"if it turns out the risk factors are built from anything the estate
or retirement services also feed, stop there and I will sequence it properly."*

**Yes at file level, no at method level.**

| Scope | Coupling |
|---|---|
| `AutoRiskCalculator` (the class) | `NetWorthService` injected (`:35`); `PensionStore` called at `:130` — **a retirement store feeding `calculateCapacityForLoss`**. `NetWorthService` itself pulls `PensionStore`, `PropertyStore`, `CrossModuleAssetAggregator`. |
| `calculateDependantsFactor()` (the method to change) | **`FamilyMember` only.** No net-worth, no pension, no estate, nothing cross-module. |

The literal condition is met — a risk factor *is* built from a retirement store —
even though it is not the factor being edited and the two-line change cannot reach
it. **Stopped and reported rather than deciding the technicality did not apply**,
because the condition exists so the team lead can sequence against `iht-audit`,
not so I can assess my own blast radius.

Held ready, not applied:

```php
// app/Services/Risk/AutoRiskCalculator.php, calculateDependantsFactor()
->get(['first_name', 'relationship', 'stated_relationship'])
...
'relationship' => $d->display_relationship,
```

Plus a test that a partner flagged as a dependant reaches the risk payload as
"partner", mirroring the assertion used elsewhere in this batch — the harm
pinned, not described.

### Spelling — flagged, not taken

`other_dependent` renders as "Other **Dependent**". CLAUDE.md requires British
user-facing text and the British noun is "**dependant**" — which the Savings map
just deleted had right, while the family cards have had wrong throughout. It is a
one-line change now there is one home.

Not taken: it is user-facing copy on surfaces a persona run is testing,
`design-lead` owns copy, and choosing where the override lives is a small design
decision. Recommendation if wanted: the **backend accessor**
(`FamilyMember::getDisplayRelationshipAttribute()`), so web, `/m` and native
inherit one spelling rather than three.


---

## DONE — 2026-08-21, both halves

Team lead authorised half two after confirming `iht-audit` is in the estate
projection and the inheritance tax service, not the risk module.

### Half two — the Risk payload

Fixed where the problem was, in `AutoRiskCalculator::calculateDependantsFactor()`:

- `stated_relationship` added to the select, **because `display_relationship` is
  computed from it** — a partial select without it would have silently fallen
  back to the stored enum and printed "Other Dependent" for someone's partner
  anyway;
- `display_relationship` added to the mapped row **alongside** `relationship`
  rather than replacing it, so anything that needs to branch still has the raw
  value while the client renders what the user chose.

`RiskFactorDetailPage.vue` now reads `familyMemberRelationshipTitle(dep)`.

**All four formatters are gone.** `grep -rn "formatRelationship" resources/js
resources/mobile` returns nothing.

### The spelling — taken, in the backend accessor

`FamilyMember::RELATIONSHIP_WORDS` maps `other_dependent` → `other dependant`,
applied in `getDisplayRelationshipAttribute()`. "Dependent" is the adjective; the
noun is "dependant"; CLAUDE.md requires British user-facing text. The column keeps
the American form because it is code.

**One home, so `/m` and native inherit it without a second edit** — which is the
entire argument for computing display strings on the server.

`FamilyMemberFormModal.vue:36` — the dropdown option label — went with it. Leaving
the input saying "Other Dependent" while the card said "Other Dependant" would
have created a fresh inconsistency in the act of removing one.

**The observation worth keeping, and the reason it is not a footnote:** of the
four formatters, the ONE in the Savings modal had the spelling **right** and the
family cards had it **wrong**. **Consolidating on what most call sites did would
have propagated the error into the only place that was correct.** Majority is not
a source of truth; it is a headcount. Recorded on the model constant so the next
person to consolidate something reads it there.

### The front end deliberately does NOT know the words

`familyMemberRelationshipLabel()` passes `display_relationship` through and falls
back to the column's own words. **No wording map on the client** — that would be
the copy-in-lockstep failure this item exists to remove. Documented in the util
and pinned by a test that asserts the fallback returns "other dependent",
precisely because the fallback is not supposed to know better.

### Tests

- `tests/Feature/Api/FamilyMemberRelationshipAliasTest.php` — **18 passed**, adding
  one that drives the real `AutoRiskCalculator` and asserts the partner reaches
  the risk payload as "partner" and **not** containing "dependent", plus one
  pinning the British noun.
- Risk regression: `tests/Feature/Risk/`, `tests/Unit/Services/Risk/`,
  `AutoRiskCalculatorEnhancementTest` and the family suites — **110 passed, 0
  failures.**
- Frontend: **129 passed** across 11 files. No spec exists for
  `RiskFactorDetailPage` or `SaveAccountModal`; both spec locations searched.
- `pint` clean.

### Not amended, on instruction

Screenshot-bearing reports already filed use the old spelling. They were accurate
when taken and were left alone.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  Two formatters survived outside the family surfaces, both reading the **raw enum** instead of the appended `display_relationship`:

  - `AdvicePromptBuilder:441` — `ucfirst($member->relationship ?? 'family member')`
  - `LetterToSpouseService:760` — `ucfirst($member->relationship ?? 'dependent')`

  **The accessor exists precisely because the enum is the wrong thing to print.** `FamilyMember::getDisplayRelationshipAttribute()` prefers `stated_relationship` — what the user actually chose where the label was translated — over the stored value, and its own docblock says why: *"Never the raw enum on an aliased row: that is how the application ends up telling somebody their partner is a dependent (W-0114)."* These two did exactly that.

  **The second one was worse than a formatting slip.** Its fallback was the literal string `'dependent'`, so a member with no recorded relationship was **asserted to be a dependant in the Letter to Spouse** — a document the reader's family is meant to act on after a death. Now `'family member'`, which claims nothing.

  The first fed **Fyn's prompt**, so the model was told a partner was a dependent and would then say so in its own words — a wrong fact laundered through generated prose, where it is much harder to trace back.

  Sweep confirms no raw-enum formatter remains: `grep` for `ucfirst($member->relationship` and the `?? 'dependent'` fallback returns nothing.

  **Tested:** 60 letter/prompt/relationship tests pass (219 assertions) and 63 family-member tests (168 assertions). Pint clean.
