---
id: W-0144
title: The generated will revokes every earlier will and imposes a 28-day survivorship period, and the user is never asked about either
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead, product-lead]
status: closed_invalid
severity: high
surfaces: [web]
created: 2026-08-21T20:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0101 (the same test, applied to the same renderer), W-0100 (the when_attorneys_can_act silent election — the first instance of this shape), W-0024, F-0003-batch-b-estate-wills]
prior_art_outcome: none
constitution_refs: [05-perimeter, 07-quality-bar]
source: found by fix-batch-G applying the two-limbed act-not-object test across the will renderer, 2026-08-21
---

## Intent

**Found by limb 2, which is why limb 2 exists.**

Compliance sharpened the act-not-object test into two limbs after W-0101: **assertive**
strings, where Fynla says something is so, get the act-not-object test; **performative**
clauses — the instrument's dispositive language — are out of scope for it, because
`I APPOINT X` cannot be true or false, it *effects*. But performative clauses get a
different test: **did the user actually choose this?** A performative clause generated
from a value the user never supplied is Fynla putting words in their mouth, **which is
worse than an assertion, not better.**

Applying limb 2 across `resources/js/utils/willDocumentRenderer.js` found two clauses
that fail it. Neither is a rendering defect — both are substantive dispositions.

### 1. Every generated will revokes all earlier wills — `:77`

> `HEREBY REVOKE all former wills and testamentary dispositions made by me and DECLARE
> this to be my last Will and Testament.`

**Emitted unconditionally. There is no field behind it, and the wizard never mentions
it** — `grep -rni 'revoke\|revocation'` across `resources/js/components/Estate/WillBuilder/`
and `WillDocumentService.php` returns **nothing**. A user with an existing professionally
drafted will, building a Fynla will to cover one new asset, is handed a document that
revokes the other one, and is told at no point that it does.

This is standard drafting and is very likely what most users want. **That is not the
point.** The point is that nobody asked them, and revocation is the single most
destructive clause in the instrument.

### 2. A 28-day survivorship period nobody chose — `:57`, `:138`

> `const survDays = data.survivorship_days || 28;`
>
> `If any person who would otherwise be entitled to benefit under this my Will dies
> within 28 days after the date of my death, such person shall be deemed to have
> predeceased me and shall not be entitled to any benefit under this my Will.`

`WillBuilderWizard.vue:277` sets `survivorship_days: 28` in the form data. **There is no
input for it anywhere in the builder** — no step, no field, no label. The number is a
Fynla constant the user never sees, and it changes who inherits.

**This is exactly the `when_attorneys_can_act` shape from W-0100**, which defaulted a
null to *"only when I have lost mental capacity"* and was removed for putting a legally
operative election into a document the donor never made. That one was in the testator's
voice too — which is precisely why "is it in the testator's voice" is the wrong test and
limb 2 is the right one.

## Acceptance

1. **Decide, per clause, whether Fynla asks or stops emitting.** These are product and
   compliance decisions, not build ones. The options are not symmetric: revocation is
   near-universal in drafted wills and removing it silently could be worse than including
   it silently; a survivorship period is a genuine election with no obviously right
   default.
2. **If Fynla asks:** a wizard step, and the clause emitted only from the answer.
3. **If Fynla keeps emitting:** the user is told plainly, at the point it happens, what
   the document will do — trunk §4, at the point of the result and not in a footer.
4. **Do not simply delete either clause.** Removing the revocation clause changes the
   legal effect of every will generated afterwards, and is its own decision with its own
   review.
5. A Vitest assertion per clause, in `resources/js/utils/__tests__/willDocumentRenderer.spec.js`.

## Working notes

- 2026-08-21 fix-batch-G: **found, deliberately not fixed.** Every available action —
  ask, stop emitting, or disclose — changes either the legal effect of the document or
  the flow of the builder, and none is a build decision. Raised with the reproduction in
  place of a patch.
- **Sequencing:** `fix-batch-B` owns the will builder (`F-0003`) and `persona-passA3` has
  been driving the estate surface. Check both before editing.
- **The method is the transferable part.** Both were invisible to a read-for-tone pass
  and to the act-not-object test on its own, because both clauses are performative and
  neither asserts anything. **Apply both limbs to every remaining renderer**, not just
  the one an item names.

- 2026-08-21 compliance-lead: **RULING — provisional. All three options are available without an
  impermissible assertion, but each only in one specific form, and the constraint differs for
  each.** **Not an approval** (`05-perimeter.md` §7.3). **Provisional** — legal services is
  Unmapped (§1.1, §1.3).

  **What I have NOT done, because it was correctly not asked:** no view on whether a general
  revocation clause is lawful, standard drafting, or what it does to an earlier will. Those are
  determinations. **Nothing below depends on any of them**, which is what makes it rulable.

  ### First: these are two different defects, and the difference decides the remedy

  Read rather than assumed:

  | | Revocation (`:77`) | Survivorship (`:57`, `:138`) |
  |---|---|---|
  | Data behind it | **None.** The clause is hardcoded into the template string | **A field exists** — `survivorship_days` |
  | Default | n/a — there is nothing to default | `28`, set at `WillBuilderWizard.vue:277` |
  | Persisted | no | **yes** |
  | Mirror generator | copies the clause | **copies the value** — `WillDocumentService.php:422` |
  | Input control | none | none |

  **So the revocation clause is one Fynla writes with no representation of the user's intent at
  all. The survivorship period is a default the user was never shown.** They are not the same
  problem and the second is the smaller one.

  **Both fail limb 2 of the scope test** — performative language is out of scope for
  act-not-object, but in scope for *did the user actually choose this?* Neither did.

  ### Why the revocation clause is the more serious of the two — and of several others

  The drawn signatures asserted something about **this** document. **The revocation clause
  operates on a document Fynla has never seen, does not know exists, and cannot inspect.**
  That is a different class: the generator's output reaching outside its own instrument.

  Stated in competence terms and no further: **Fynla emits an operative clause directed at an
  object it has no knowledge of.** I am not saying what that clause achieves.

  ### The three options — each is available, each in one form only

  **1. Stop emitting. Permissible as an act; NOT neutral, and it must not be presented as a
  fix.**

  Removing a clause is not a claim, so the act-not-object test does not forbid it. **But
  team-lead's instinct is right and here is the in-competence reason: a will silent on
  revocation and a will that revokes are both legally operative states, and Fynla can evaluate
  neither.** Choosing silence without telling the user is the same act as choosing the clause
  without telling them — **the direction changes, the omission does not.**

  **If this option is taken, it must ship with no explanation of why**, because the explanation
  is where the assertion enters.

  **2. Disclose. Permissible ONLY in the descriptive form.**

  - **Permitted:** quote the clause verbatim and state that Fynla included it and the user was
    not asked. That describes **the act Fynla performed**.
  - **Forbidden:** any sentence saying what the clause does to earlier wills — *"this cancels
    your previous will"*, *"your earlier will will no longer apply"*. **That is the
    determination, and it is the form a disclosure naturally wants to take**, which is exactly
    team-lead's worry and it is correct.

  It converts an invisible clause into a visible one the user can take to a solicitor. **The
  referral already exists and has one home** — do not write a second (Rule 20).

  **3. Ask. Permissible ONLY if the question is one of FACT about the user, never one of legal
  effect.**

  This is the trap team-lead identified, and it is escapable:

  - **Forbidden:** *"Do you want to revoke your previous wills?"* — requires the user to
    understand a term Fynla would then have to explain, **and the explanation is the
    assertion.**
  - **Permitted:** **"Have you made a will before?"** A question about the user's own history.
    Fynla is entitled to ask it and entitled to record the answer.

  **That is the move that makes "ask" viable**, and it is the one I recommend, because the
  answer routes the decision without Fynla ever characterising the clause.

  ⚠️ **W-0100's pattern binds whichever is chosen: an unanswered question must not become an
  answer.** If the user does not answer, **the clause must not be emitted on a default** — that
  is the `when_attorneys_can_act` defect, which this generator's sibling has already had removed
  once.

  ### Recommendation

  **Ask the factual question; disclose descriptively where the clause is emitted.** The two
  compose, and neither requires Fynla to say what the clause does. **Where the user says they
  already hold a will, that is the point at which the existing solicitor referral is warranted**
  — Fynla being on notice that its output will interact with an instrument it cannot see is a
  fact about Fynla's own position, not a claim about the law.

  **Whether Fynla should instead decline to generate for such a user is a product decision and
  is not mine.** I note only that `WillTypePolicy` is already the one home for refusal-and-
  referral wording if that route is taken.

  ### Survivorship — simpler, and the reason is the field

  **The remedy is to surface a value that already exists, not to invent a control.** Same
  descriptive rule: Fynla may state **that** the document contains a 28-day survivorship period
  and that Fynla set it rather than the user. **It may not explain what a survivorship period
  does** — that is the same determination in smaller form, and team-lead is right that surfacing
  invites it.

  ⚠️ **The mirror generator copies `survivorship_days` (`WillDocumentService.php:422`), so the
  unshown default propagates to the spouse's will**, and the revocation clause reaches both
  wills of a mirror pair. **For a couple who each hold earlier wills, both documents carry it.**
  Worth stating because W-0024's fix made the mirror faithful, which means it now faithfully
  copies this too.

  ### Not ruled

  - Whether the clause is lawful, standard, or effective. **Determinations.**
  - Whether Fynla should generate a will at all for a user who already holds one. **Product.**
  - The wording of any solicitor referral — **`WillTypePolicy` holds it and it was approved
    verbatim under W-0019.** Do not write a second copy.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev`.**
  `resources/js/utils/willDocumentRenderer.js:73-77` still emits
  `HEREBY REVOKE all former wills and testamentary dispositions made by me` **unconditionally**,
  and `grep -rni 'revoke|revocation'` across `resources/js/components/Estate/WillBuilder/` and
  `app/Services/Estate/WillDocumentService.php` still returns nothing — there is no field behind
  the clause and the wizard still never mentions it. Limb 2 fails exactly as filed: a performative
  disposition generated from a value the user never supplied. Unchanged since 2026-08-21.

- 2026-08-31 build-lead: **CLOSED BY CSJ'S RULING 2026-08-31 — not a defect.**

  > *"it is revoked that is the law, there is a 28 day period"*

  Both clauses are correct and both stay as they are. A new will revoking all former wills is standard practice and the effect the testator intends; a 28-day survivorship period is a standard clause. The item treated settled drafting as a missing question. It is not one, and the defaults are not to be changed.

  `survivorship_days` remains on the document and is carried to a mirror will (`WillDocumentService:439`), so the pair cannot drift apart.

  **Do not re-raise this.** The absence of a prompt asking the user to confirm revocation or to set a survivorship period is deliberate, not an omission.
