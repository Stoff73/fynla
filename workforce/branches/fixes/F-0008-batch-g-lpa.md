---
id: F-0008
type: fix
parent: core/constitution/05-perimeter.md
applies: [core/constitution/07-quality-bar.md, core/constitution/04-voice.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-21T18:05:00Z
status: active
---

# F-0008 — Batch G: the Lasting Power of Attorney generator, and the badge that said "Compliant"

**Owner:** build-lead (agent `fix-batch-G`) · **Branch:** `dev` (no feature branch) ·
**Board items:** W-0100 (closed to `handoff`), W-0101–W-0110 (raised, `queued`)

**Status: code-complete, targeted tests green, Pint and ESLint clean, W-0100 moved to
`handoff` → `quality-lead`. Nothing is in flight — §4 says so explicitly.** No commit,
no PR, no deploy, no browser verification: a persona-tester closes Rule 14's loop, per
the dispatch. This document is the complete seed for a replacement agent (Rule 22).

---

## 1. The dispatch, in one paragraph

W-0100 was an audit of an unexamined document generator, plus a live defect attached
to it that team-lead judged the more serious half: **the application told users their
Lasting Power of Attorney was "Compliant", in green, and had done on production since
`1a3d17e99` (2026-03-16)**. Acceptance 5 (the regulatory perimeter) was already
answered by compliance-lead in `workforce/ops/reports/2026-08-21-W-0100-lpa-perimeter-review.md`
and was explicitly not to be redone. Acceptance 1–4 — read the generator, read the
renderer, audit the checks, establish the surfaces — were mine, together with the fix.

Compliance's position was given to me as the specification, not as an opinion to
weigh: the outcome must stop asserting compliance, validity or sufficiency, and must
instead say what was checked and what was not.

---

## 2. DONE — the fix, with file:line evidence

### 2a. The one home — `app/Services/Estate/LpaCheckPolicy.php` (new)

Rule 20. Everything Fynla is now entitled to say about this instrument lives in one
final class, on the `WillTypePolicy` pattern: the outcome vocabulary, the "what we did
not check" disclosure, the solicitor signpost, and `payload(int $failed, int $warnings)`
which is what clients render. Its docblock carries the reasoning so a future reader
cannot re-add a verdict by accident.

Three outcome constants, none of which claims a property of the instrument:
`OUTCOME_NO_ISSUES` ("No issues found in these checks"), `OUTCOME_POINTS` ("Some checks
raised a point to look at"), `OUTCOME_NOT_PASSED` ("Some checks did not pass"), each
with a singular variant when exactly one check trips.

It lives in `App\Services\Estate`, the same namespace as its only caller, so **no `use`
import was needed** — which sidesteps the formatter trap the dispatch warned about
entirely rather than working around it.

### 2b. The service — `app/Services/Estate/LpaComplianceService.php`

- `:49` — `$overallStatus = … : 'compliant'` **deleted**.
- The return array now spreads `LpaCheckPolicy::payload($failed, $warnings)`.
- **`overall_status` was deleted, not renamed.** That was deliberate: a rename leaves
  consumers compiling against a key that still means "the overall status of the
  instrument". Deleting it forced every consumer to be found. There were three
  (one component, two tests); all are updated.
- The class docblock now states what it does not determine, and why, so the class name
  — which still asserts — cannot mislead the next reader.

### 2c. The web component — `resources/js/components/Estate/LpaComplianceChecklist.vue`

- The `bg-spring-100 text-spring-800` / "Compliant" pill is **gone**. The outcome is a
  plain `<p>`.
- **Every string now comes from the API payload.** The component hardcodes no label, no
  heading and no disclosure — so a future `/m` or native surface renders the same words
  without a second decision being written.
- The "What we did not check" block and the solicitor signpost render **with the
  result**, not in a footer — trunk §4, which compliance-lead noted is being applied
  outside a currency figure for the first time.
- A comment at the top of the file states the rule, because this is exactly the kind of
  component somebody re-adds a status badge to.

### 2d. The renderer — `resources/js/utils/lpaDocumentRenderer.js`

Four changes, in descending order of how much they mattered:

1. **The fabricated signatures are gone.** When `completed_at` was set, the file drew
   the donor's, every attorney's and the certificate provider's name onto the signature
   lines in `Brush Script MT` (`:191, :204, :218, :231`; style at `:291`). `completed_at`
   is set by the user pressing "Complete" in the wizard (`LpaWizard.vue:345` →
   `save('completed')` → `LpaService::createLpa/updateLpa`). **None of those people had
   done anything**, and the certificate provider's drawn signature sat immediately below
   a block in which they certify the donor's understanding and the absence of fraud or
   undue pressure. Signature lines are now always blank, `.signed-name` is deleted from
   both stylesheets, and the section says so in words.
2. **The validity assertion is gone.** `:248` printed *"This instrument is now a valid
   Lasting Power of Attorney under the Mental Capacity Act 2005"* on the strength of
   `is_registered_with_opg` — a self-declared flag. The registration block now reports
   what the user told us and says Fynla has not verified it.
3. **The silent election is gone.** `:119-123` fell through its `else` to *"only when I
   have lost mental capacity"* when `when_attorneys_can_act` was **null**, writing a
   legally operative choice into the document that the donor never made. Now "Not
   specified.", matching how the same file already handled life-sustaining treatment
   three sections later.
4. **The document qualifies itself at the top**, not in a footer: the heading is now
   "LASTING POWER OF ATTORNEY — RECORD OF DETAILS" and the line beneath it says it is
   not a Lasting Power of Attorney and cannot be used as one. Because this lives in
   `renderLpaDocument()`, the on-screen view and the print output get it from **one**
   place. Rule 9: the print footer's "your LPA" is spelled out.

### 2e. Tests

- `tests/Unit/Services/Estate/LpaComplianceServiceTest.php` — the old
  `it('returns compliant status when all checks pass')` is rewritten to assert the new
  outcome; added coverage for the disclosure, the singular/plural wording, and a Rule 9
  sweep over **every** string the service hands a client.
- `tests/Feature/Estate/LpaControllerTest.php` — added
  `it('never returns a verdict on the instrument')`, which asserts `overall_status` is
  absent and scans the whole response body for verdict words. **"valid" is deliberately
  excluded from that scan** and asserted separately against `outcome_label` only: the
  disclosure says the checks *"cannot tell you whether your Lasting Power of Attorney is
  valid"*, and the negation is the point.
- `resources/js/utils/__tests__/lpaDocumentRenderer.spec.js` (new, 9 tests) — locks in
  the absence of `signed-name`, the blank signature lines, the absence of the validity
  sentence, the top-of-document qualification, and all three states of the
  when-attorneys-can-act branch.

**Results:** 42 passed / 181 assertions (`DB_DATABASE=laravel_testing_c ./vendor/bin/pest
tests/Unit/Services/Estate/LpaComplianceServiceTest.php tests/Feature/Estate/LpaControllerTest.php
tests/Unit/Services/Estate/LpaServiceTest.php`); 9 passed (`npx vitest run
resources/js/utils/__tests__/lpaDocumentRenderer.spec.js`, run in isolation per
`tests/CLAUDE.md`); Pint `passed`; ESLint exit 0.

---

## 3. W-0101 — the same defect in the sibling renderer, and the Rule 20 half of this batch

Added 2026-08-21 after team-lead moved W-0101 to the top of the board. It lives in this
document rather than a new one because it **is** W-0100's other half: one behaviour,
implemented twice, fixed once.

### What was wrong

`willDocumentRenderer.js` drew the testator's name on the signature line once
`signed_date` was typed (`:177`), and **each witness's name onto the witness signature
line the moment a witness row existed** (`:192`) — `if (w)` alone, no date condition, so
the witnesses' marks appeared *more readily than the testator's own*. Under Wills Act
1837 s.9 the witnesses' signatures are the formality a will's validity turns on.

Compounding it, the footer told the user *"This will is only legally valid once properly
signed and witnessed in accordance with the Wills Act 1837"* — so the document supplied
the instruction and the apparent evidence at once. compliance-lead ruled on that sentence
in W-0101's acceptance 3: s.9(1) opens *"No will shall be valid unless"*, stating
**necessary** conditions, while Fynla stated a **sufficient** one; it named two of four
limbs and hid s.9(1)(b) behind the undefined word "properly"; and it called the draft
"This will". **Both halves had to land together**, and did.

### The one home

**`resources/js/utils/documentSignatures.js`** (new) holds the rule — the
`SIGNATURE_NOT_RECORDED` sentence, `BLANK_DATE_RULE`, `blankSignatureLine()`, and
`drawnSignatureLines()`, the rule made executable. `blankSignatureLine()` **takes no
arguments on purpose: there is no parameter for "the name to draw", because there is no
case in which a name is drawn.**

`lpaDocumentRenderer.js` was **converged onto it in the same change**, so W-0100's fix
stopped being a private copy. That is the part that makes this Rule 20 rather than two
tidy fixes.

**`__tests__/documentSignatures.spec.js` is the register.** It runs the same assertions
over every renderer via `describe.each`, each fed data where every party is named and
every date set — the state a renderer is most tempted to draw in. **A third generator is
added to one array and is covered.** The detector has its own unit tests, so the register
is demonstrably not vacuous.

### Decisions taken, and why

1. **Not a `signed_date` gate.** Gating the witnesses on a typed date would move the
   facsimile one field later. `signed_date` is now consulted for nothing.
2. **The attestation date is always blank**, even when the user recorded one. The clause
   is in the testator's voice — *"I have hereunto set my hand this …"* — so filling it
   asserts an execution event Fynla did not witness. A typed date is not a signature.
   Same reasoning for the **witness Date** field.
3. **Full Name, Address and Occupation stay filled.** They label a person; a signature
   stands in for their hand. W-0101 acceptance 1 permits exactly this.
4. **This document is NOT disclaimed as "not a will" — the trap compliance flagged.** A
   will has no statutorily prescribed form, so printed and properly executed it could
   take effect as one. The Lasting Power of Attorney treatment is right there and false
   here; a test asserts the absence of that wording so nobody copies it across.
5. **The qualification renders from `renderWillDocument()` at the top**, so preview and
   print take it from one place. The `disclaimer` div and its now-dead CSS are gone.

### Verified, and not

**31 tests passed** across `documentSignatures.spec.js` (13), `lpaDocumentRenderer.spec.js`
(9) and `willDocumentRenderer.spec.js` (9). ESLint clean on every file changed.

**Two pre-existing lint findings were left alone and reported, not fixed:**
`WillBuilderReviewStep.vue:69` `'index' is defined but never used`, and an unused
`eslint-disable` directive on the `document.write` line. **Both verified present on
`HEAD`** by linting the pre-change versions — neither is this batch's.

**Not browser-verified.** `persona-passA3` was driving the estate surface throughout, so
bequest sync, mirror generation and `WillPlanning.vue` were not touched at all.

### Raised, not fixed

**W-0143** — `WillBuilderSigningStep.vue:11,17`: *"Follow the steps below to make it
legally binding"* and the heading *"How to Make Your Will Legally Valid"*. The identical
inversion of s.9(1), one file away, as an imperative heading over a numbered checklist.
Found by applying the act-not-object test mechanically to the file next door rather than
by feel — which is what compliance asked for, and it worked.


---

## 4. W-0102 + W-0103 + W-0151 — the party-role check, folded into one mechanism

**Survivor: W-0103** (the general case covers the specific ones). W-0102 and W-0151 are
`done` with `folded_into: W-0103`; their compliance rulings stay readable in their own
files rather than being copied.

### Prior art was `route`, and it changed the design

`WillDocumentService::isSameParty()` (`:698`, public static) already existed, and its own
docblock calls it *"the one home for that question"* for the mirror swap, the
executor-is-testator block and Fyn's `create_will` handler. **The name comparison routes
to it.** Consequences worth stating: the case/whitespace-only behaviour is inherited
rather than re-decided, and `WillDocumentService` — `fix-batch-B`'s file — **was not
edited**, because calling a public static is not a change to it.

### One method, five conflicts, a list of results

`LpaComplianceService::checkPartyRoles()`. Returning a **list** rather than one result is
deliberate: a single result would show the first conflict and hide the rest until it was
fixed, which is a silent omission, and `LpaComplianceChecklist.vue` needs unique
`:key`s. A test asserts all conflicts surface at once with no duplicate key.

| Key | Conflict | Status |
|---|---|---|
| `party_roles_certificate_provider_attorney` | certificate provider is an attorney here | `fail` |
| `party_roles_certificate_provider_other_instrument` | certificate provider is an attorney on the donor's other instrument | `fail` |
| `party_roles_donor_attorney` | donor as their own attorney | `warning` |
| `party_roles_donor_certificate_provider` | donor as their own certificate provider | `warning` |
| `party_roles_attorney_and_replacement`, `party_roles_duplicate_attorney` | two roles, or entered twice | `warning` |

### The status split is mine, and it is the one thing to review

`fail` where an instrument prohibits it (MCA 2005 Sch 1 para 2(6); SI 2007/1253
reg 8(3)(b), (c)). `warning` where compliance searched for an express prohibition and
**did not find one**. Marking those as failures would assert a rule that may not exist —
**the same overclaim W-0100 removed, pointing the other way.** Compliance ruled on
wording; mapping the distinction onto status is a build decision and is flagged to them.

### Strings are compliance's, verbatim

No paraphrase. The pass is *"The names in each role are different"* — **never "no
conflict found"**, and a test asserts that phrase appears nowhere. The pass description
under-claims relative to what the check now covers; that is the safe direction and I did
not widen compliance's sentence myself.

### The limit is disclosed once and proved

Two entries added to `LpaCheckPolicy::NOT_CHECKED`: the name-matching limit (covering all
five conflicts from one place rather than five hedged messages) and W-0151's
disclosure-only disqualification line, verbatim including the family clause compliance
said not to trim. **A test asserts "Dave Jones" against "David Jones" genuinely passes**,
so the disclosure is demonstrated rather than asserted.

**No family-member check was built.** "family member" is undefined in reg 8(4), reg 2 and
MCA 2005 s.64 — a check would have Fynla drawing a boundary the instrument leaves
undrawn. This entry replaces the shorter line offered on W-0106, which was never built,
so there is one entry, not two.

### W-0024 matched both ways, without a gate

A test asserts the conflict fires when present **and clears when corrected**. Completion
is **not** blocked the way `WillDocumentService`'s `severity: error` blocks a will:
neither acceptance asks for it, and blocking the W-0103 conflicts would refuse a save on
an arrangement nobody has established is prohibited. **Open recommendation:** if gate
parity is wanted, gate only the two statutory limbs, as its own item, with its own
decision about instruments already saved in that state.

### Verified

`LpaComplianceServiceTest` 28 tests; whole estate suite **292 passed / 946 assertions**,
batch B's will tests included. Pint clean. Not browser-verified.


---

## 5. W-0143 + W-0157 — the signing step, and the two-limbed test applied to the renderers

Same component, same pass, kept as two items because the mechanisms differ: W-0143 is a
**sentence shape**, W-0157 is **facts stated to the user**.

### W-0143 — the third instance of one inversion

Compliance's wording verbatim. Heading `Before your will can take effect`; body
`Your will has been saved. It is a draft until it is signed and witnessed. Section 9 of
the Wills Act 1837 sets out what the law requires; the steps below cover the parts you
can prepare for, and Fynla does not check any of them.`

*until* restores s.9(1)'s **necessary** form against the old copy's **sufficient** one.
*the parts you can prepare for* stops the list reading as exhaustive without enumerating
what it omits — limb (b) is a state of mind and no checklist reaches it.

**The instruction not to swap "binding" for "valid" is recorded in a comment above the
copy**, because that is the obvious wrong fix and the next person will reach for it.

Compliance ruled the step-4 instruction (each witness signs in front of the other) is
**stricter than s.9(1)(d) requires** and that over-compliance in an instruction is not a
defect. **It was not relaxed.**

### W-0157 — two fixed, one deliberately left alone

| Finding | Action |
|---|---|
| Storage fee **£75 → £24** (row C3) | Fixed, and moved from an inline string to a constant whose docblock carries publisher, verbatim quote, date read, the page's own "Updated 13 July 2026", the row id, and the instruction to update `sources.md` in the same edit |
| *"automatically void"* (row A14) | **NOT touched.** W-0153's shape; both compliance and team-lead required it be handled under that item's answer, because patching one instance leaves the systemic gap true. Interim wording ready to apply |
| Witness age *"18 years or older"* | **Softened** to `Adults` plus a line saying the Wills Act sets no age and this is Fynla's guidance. It could not be sourced, so it says less |

**The provenance now travels with the value.** £75 survived because it was inline,
unsourced and undated — a register entry alone would not have caught it, because nobody
opens the register next to a string nobody suspects.

**Left alone and stated rather than lost:** `Of sound mind`, in the same bullet list, is
the same unsourced shape. It was not flagged, I did not touch it, and I am not asserting
it is wrong.

### The two-limbed test applied to the renderers — and it found W-0144

Compliance sharpened act-not-object into two limbs: **assertive** strings get the
act-not-object test; **performative** clauses are out of scope for it (they cannot be
true or false) but get a different one — **did the user actually choose this?**

Applied across `willDocumentRenderer.js`, limb 2 found two clauses that fail:

1. **Every generated will revokes all earlier wills** (`:77`), unconditionally, with **no
   field behind it and no mention anywhere in the builder.**
2. **A 28-day survivorship period** (`:57`, `:138`) from a constant hardcoded in
   `WillBuilderWizard.vue:277` with **no input anywhere** — it changes who inherits.

Both are in the testator's voice, which is why "testator's voice" is the wrong test.
Both are the `when_attorneys_can_act` shape from W-0100. **Raised as W-0144, not fixed:**
every available action — ask, stop emitting, or disclose — changes the legal effect of
the document or the flow of the builder.

### The qualification is now pinned to the TOP, not merely present

Compliance flagged the dependency: the witness's printed name is typed **in advance**, a
plan rather than a record, and is safe only because the document qualifies itself before
the reader reaches it. Both renderer specs now assert the qualification appears **above
the document's own title rule**. Move it to a footer and printed names beside blank
signature lines become assertive again — which is how it got into a footer the first time.

### Verified

**37 Vitest tests** across `documentSignatures.spec.js`, `lpaDocumentRenderer.spec.js`,
`willDocumentRenderer.spec.js` and the new
`components/__tests__/Estate/WillBuilderSigningStep.spec.js`. ESLint clean. Not
browser-verified.


---

## 6. DONE — the W-0100 audit (acceptance 1–4)

Written up in full in W-0100's working notes. The short form:

- **Acceptance 1** — the W-0024 shape is present and statutory: **nothing compares
  `certificate_provider_name` to the attorneys** (MCA 2005 Sch 1 para 2(6)). Raised as
  W-0102, not fixed. Three further role conflicts in W-0103.
- **Acceptance 2** — the type split is handled correctly; one conflation (fixed, §2d
  item 3) and one gap (W-0108).
- **Acceptance 3** — ten checks, two type-conditional. What they miss is W-0102 through
  W-0107.
- **Acceptance 4** — **web only.** `/m` and iOS native have no Lasting Power of Attorney
  surface at all, and `WebHandoffDestination` has no case for it — while Fyn's write
  tools reach every surface. W-0110.

### The reachability correction

The perimeter report listed as an unverified assumption that "Compliant" reaches the
user as rendered. **It does, but only down a narrow path, and it is worth knowing
exactly which** — the checklist renders only for `status === 'draft'`
(`LpaDetailView.vue:48`), and the registration check warns on any unregistered
instrument, so an ordinary draft always read "Review Needed". The green badge required:
register (`POST /lpa/{id}/register`), then re-open the wizard and press **Save Draft**
— `LpaService::updateLpa()` never clears `is_registered_with_opg`. Plus a replacement
attorney, one person to notify, and a certificate provider of two years or more.

**This does not soften the finding.** The endpoint returned `overall_status: 'compliant'`
in JSON for any qualifying registered instrument regardless of what the UI chose to
render, and the label was shipped and reachable.

---

## 7. IN FLIGHT

**Nothing.** No half-finished edit, no uncommitted experiment, no running process. Every
file listed in §8 is complete and passing. A replacement agent starts at §5 or §6.

---

## 8. Decisions taken, and why — do not re-litigate

1. **Audit findings became board items; only the overclaim was fixed.** The dispatch
   said so explicitly. The line I drew: anything that *asserts* something Fynla is not
   entitled to assert is the overclaim in another home and was fixed (the badge, the
   validity sentence, the drawn signatures, the silent election). Anything that requires
   a *new statutory check* or a *new legal statement* was raised. W-0102 is the closest
   call — it is the one the Act names and the one compliance-lead said to test first —
   and I have offered to take it in this batch on team-lead's word.
2. **The outcome is plain text, not a recoloured badge.** This resolves the constraint
   the dispatch flagged: no palette token changed, the parked palette workstream is
   untouched, and no colour asserts anything. ux-writing-expert's independent reading
   was the same — green carries "approved" whatever the label says.
3. **`LpaCheckPolicy` is a new home, not an addition to `WillTypePolicy`.** Compliance
   said to signpost a solicitor "composing from one home per Rule 20 (`WillTypePolicy`
   precedent)". I read that as *follow the pattern*, one home per instrument — not
   *put Lasting Power of Attorney wording inside the will class*. `WillTypePolicy`'s
   text was approved **verbatim** by compliance-lead and design-lead under W-0019, and
   refactoring it to share a sentence risks changing approved copy for no gain. The
   trigger for a shared home is a third instrument; it is recorded in
   `LpaCheckPolicy::REFERRAL`'s docblock so whoever hits it does not have to rediscover
   the constraint.
4. **The document's body clauses stay in the first person** ("I, [donor], appoint…").
   Rewriting every clause into reported speech would destroy the document's usefulness
   as a side-by-side reference against the official forms, which is its actual job. The
   top-of-document qualification carries the load instead.
5. **The `checkRegistrationStatus` descriptions were left alone** despite containing
   "£82" and "up to 8 weeks". Correcting a factual claim without a source replaces one
   unsourced number with another. Raised as W-0109 with the duplication.
6. **W-0101 was raised, not fixed**, though it is the most serious thing in this batch.
   `fix-batch-B` holds the will files today (F-0003) and a parallel edit would collide.
   It should be sequenced immediately after F-0003 lands.

---

## 9. Dead ends ruled out — do not re-walk

- **`is_registered_with_opg` cannot be set through `StoreLpaRequest` or
  `UpdateLpaRequest`** — it is not in either rule set. I checked, because if it were,
  the green badge would have been trivially reachable. It is set only by
  `LpaService::markAsRegistered()` and by Fyn's `handleUpdatePowerOfAttorney`.
- **Fyn cannot produce the draft-plus-registered state.** `CoordinatingAgent.php:4360`
  sets `is_registered_with_opg = ($status === 'registered')` in both directions, so the
  Fyn path self-corrects. Only the web wizard's "Save Draft" leaves the flag stranded.
- **`/m` and iOS were checked by absence-grep across all three client trees**, not
  inferred from W-0044. There is genuinely nothing there — do not go looking again.
- **There were no pre-existing tests for `lpaDocumentRenderer.js`.** The spec file in
  §2e is the first.

---

## 10. Environment state

- Test database `laravel_testing_c`, as dispatched. `pgrep` showed two other Pest
  processes running throughout; nothing of mine collided.
- No migrations. No seeders run. No users created, deleted or provisioned. No `.env`
  touched. No production query.
- No `/m` bundle rebuilt — and none was needed: `/m` has no Lasting Power of Attorney
  surface to rebuild.

---

## 11. Files changed

| File | Change |
|---|---|
| `app/Services/Estate/LpaCheckPolicy.php` | **new** — the one home for the wording |
| `app/Services/Estate/LpaComplianceService.php` | `'compliant'` deleted; composes the payload; docblock |
| `resources/js/components/Estate/LpaComplianceChecklist.vue` | badge → plain text; renders the disclosure; no hardcoded copy |
| `resources/js/components/Estate/LpaDetailView.vue` | duplicate heading and duplicate disclaimer sentence removed; `.signed-name` style deleted; `.doc-qualification` added |
| `resources/js/utils/lpaDocumentRenderer.js` | signatures, validity assertion, silent election, qualification, Rule 9 |
| `tests/Unit/Services/Estate/LpaComplianceServiceTest.php` | rewritten verdict test + 3 new |
| `tests/Feature/Estate/LpaControllerTest.php` | structure updated + verdict regression test |
| `resources/js/utils/__tests__/lpaDocumentRenderer.spec.js` | **new** — 9 tests |
| `workforce/ops/board/W-0100-*.md` | → `handoff`, working notes |
| `workforce/ops/board/W-0101-*.md` … `W-0110-*.md` | **new** — 10 items |
| `resources/js/utils/documentSignatures.js` | **new** — the one home for "never draws a signature" (W-0101) |
| `resources/js/utils/willDocumentRenderer.js` | signatures, the `:297` sentence, the top-of-document qualification (W-0101) |
| `resources/js/components/Estate/WillBuilder/steps/WillBuilderReviewStep.vue` | `.signed-name` removed, new classes styled (W-0101) |
| `resources/js/utils/__tests__/documentSignatures.spec.js` | **new** — the register over every renderer |
| `resources/js/utils/__tests__/willDocumentRenderer.spec.js` | **new** |
| `app/Services/Estate/LpaComplianceService.php` | `checkPartyRoles()` (W-0102 / W-0103 / W-0151) |
| `app/Services/Estate/LpaCheckPolicy.php` | two `NOT_CHECKED` disclosure entries |
| `resources/js/components/Estate/WillBuilder/steps/WillBuilderSigningStep.vue` | W-0143 wording, W-0157 fee constant and witness age |
| `resources/js/components/__tests__/Estate/WillBuilderSigningStep.spec.js` | **new** |
| `workforce/ops/board/W-0141` … `W-0144`, `W-0151`, `W-0157` | raised or closed |

**No file touched by `fix-batch-B` (F-0003) was touched here.** Verified against F-0003
§12 before starting.

---

## 12. Open — needs somebody who is not me

- **CSJ gate:** compliance-lead's recommendation to extend perimeter §7.3 so the rule
  binds the product and not only the agent. This batch fixed the instance; the rule that
  would have prevented it is still scoped to agents only.
- **CSJ answer:** how many real users hold a Lasting Power of Attorney on production.
  Both branches were pre-stated in the perimeter report and neither is resolved.
- **team-lead decision:** whether W-0102 (certificate provider as donee) is pulled into
  this batch. Recommended — it is statutory, it is the W-0024 shape, and the file is
  already open.
- **team-lead sequencing:** W-0101 immediately after F-0003 lands. It is live on
  production and it is the same defect this batch removed from the sibling renderer.
- **quality-lead:** the evidence pack. I have not written one and will not (§2.4).
- **persona-tester:** Rule 14's loop. Nothing here has been seen in a browser.
