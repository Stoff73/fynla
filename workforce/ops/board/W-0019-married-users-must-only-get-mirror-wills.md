---
id: W-0019
title: Married users are offered a one-sided Simple Will with no warning — only mirror wills should be enabled
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead, design-lead]
status: gated
severity: high
surfaces: [web, m, ios]
source: CSJ direction 2026-08-21, observed live during persona run 20-08-2026 Pass A
prior_art_checked: 2026-08-21
prior_art_outcome: extend
branch: branches/fixes/F-0003-batch-b-estate-wills.md
claimed: 2026-08-21T09:40:00Z
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
---

## Intent

**CSJ direction, issued 2026-08-21 while watching the persona run drive the will
process.** For a married couple, the correct instrument is a **mirror will**. The app
must therefore offer married users **mirror wills only**. Any other will structure a
married user asks for is out of the app's competence and must be met with a clear
message that we cannot do it and they should speak to a solicitor.

This is a product and legal-boundary decision, not a bug report. It is not open to
substitution or a cheaper approximation (Rule 16).

## What the app does today

`resources/js/components/Estate/WillBuilder/steps/WillBuilderIntroStep.vue:60-86`
presents a married user (`prePopulated?.has_spouse`) with **two equal, unsteered
choices** rendered side by side as identical buttons:

- **"Simple Will"** — *"A single will for you, distributing your estate as you wish."*
- **"Mirror Will"** — *"Two matching wills — one for you and one for your spouse."*

There is no default, no recommendation, and no warning attached to the simple path.
`willType` starts unset and either is equally reachable. The legal notice above
(`:9-26`) warns only about complex estates, witnessing, and jurisdiction — it says
nothing about the hazard of a one-sided will inside a marriage.

Observed live in the persona run: a married user can select Simple Will, name their
spouse as executor, and complete the flow, leaving the spouse with no matching will
and no prompt that anything is wrong.

## Why this is serious

Per CSJ: enabling a will for one spouse while making the other spouse the executor
should raise **massive red flags** — not only for Inheritance Tax but for various
probate issues. The app currently raises none. It presents the hazardous option as an
equal, unremarkable choice, which is worse than not offering it at all: the interface
itself implies the choice is safe.

## Acceptance

1. A user the app knows to be married is offered **mirror wills only**. The Simple
   Will option is not presented to them.
2. If a married user asks for anything other than a mirror will — through any surface,
   including Fyn — they receive a clear message that **we cannot do this** and that
   they should speak to a solicitor. No partial build, no "proceed anyway" escape.
3. Unmarried users are unaffected; the simple will remains theirs.
4. The wording of the refusal and the solicitor referral is reviewed by
   **compliance-lead** (advice-vs-guidance boundary) and **design-lead** (copy).
5. Applies on **web, /m and iOS** — one change, one place, per Rule 20. Fyn's will
   handling must reach the same answer as the form; a second vocabulary or a
   surface-specific branch is a violation, not a fix.
6. Existing wills already created under the old flow are not silently rewritten.
   Decide and record what happens to them — that is a CSJ call, flagged not assumed.

## Open questions for CSJ

- ~~**Married-but-separated, or a spouse who refuses to make a will.**~~ **Answered
  by CSJ 2026-08-21: they get the solicitor message too.** A married user whose
  spouse will not engage is not offered a one-sided will — the app says it cannot do
  this and refers them to a solicitor, exactly as for any other non-mirror request.
- **Existing one-sided wills** already stored for married users — leave, warn, or
  offer migration to a mirror pair?

## Working notes

Prior art: mirror wills are already implemented end to end — `will_type === 'mirror'`
drives spouse pre-fill (`WillBuilderPersonalStep.vue:52-66`), residuary auto-fill
(`WillBuilderResiduaryStep.vue:8-11,137-138`) and a generate-mirror action
(`WillBuilderReviewStep.vue:19-86`). This is an **extend**, not a build: the machinery
exists, the gating around it does not.

- 2026-08-21 build-lead: **BUILT**, browser-verified, both mandated reviews done.
  Handing to quality-lead for the evidence pack.

  **One home, three consumers (Rule 20).** `App\Services\Estate\WillTypePolicy`
  (`app/Services/Estate/WillTypePolicy.php`) holds the decision AND the wording:
  - Web will builder — via `will_type_policy` on
    `GET /api/estate/will-builder/pre-populate`
    (`WillDocumentService::prePopulateData()` `:99`), rendered by
    `WillBuilderIntroStep.vue:55-100`.
  - API refusals — `WillDocumentController::refuseUnsupportedWillType()` (`:33`)
    gates `store`, the `intro` step of `update`, and
    `WillDocumentService::markComplete()` gates completion.
  - Fyn — `FynContextAssembler::willStructureDirective()`
    (`app/Services/AI/Fyn/FynContextAssembler.php:566`) quotes the SAME constants
    verbatim rather than letting the model compose its own refusal.

  **Marital determination — one rule, and it is not `has_spouse`.**
  `WillTypePolicy::isMarried()`: a declared `marital_status` is authoritative in
  BOTH directions (`married`/`civil_partnership` → married; `single`/`divorced`/
  `widowed` → not, even with a lingering `spouse_id`, which survives a divorce by
  design per `User::spouse`); `liveSpouseId()` is consulted only when nothing is
  declared. Both reviewers independently flagged that gating on
  `prePopulateData`'s `has_spouse` would have told a cohabiting couple "because
  you're married" and told a civil partner they were married. `prePopulateData`
  now also returns `has_spouse` from `liveSpouseId()`, not the raw column.

  **CSJ's answered question is implemented:** married + no live partner account →
  `allowed_will_types: []`, `can_build: false`, `REFUSAL_NO_MIRROR_PARTNER`,
  Continue disabled. No one-sided will, no "proceed anyway".

  **compliance-lead verdict.** Draft string (2) CLEAR WITH CHANGES; strings (1)
  and (3) **BLOCKED as drafted**:
  - It blocked "a one-sided will ... needs care over Inheritance Tax and probate"
    under perimeter §2 Rule 7 (never state a tax position without a dated
    source) and §7.3 (competence boundary). Its reasoning: spousal exemption and
    the transferable nil rate band both cut AGAINST the assumption that a
    one-sided will between spouses carries an Inheritance Tax penalty, so we
    would be publishing internal product rationale as user-facing technical
    justification, possibly backwards. **The sentence is gone.** The reason we
    give is now the one within our competence: it is a limit of the tool.
  - It blocked the Fyn directive because telling the model to "tell them plainly"
    lets it re-word the refusal on every turn — a second vocabulary, i.e. the
    exact Rule 20 breach acceptance 5 forbids. **The directive now interpolates
    the constants and instructs that they appear unchanged.**
  - **Consumer Duty concern, recorded as it asked:** refusing a married user
    whose partner will not engage is a foreseeable-harm concern under the
    consumer-support outcome, since the alternative is intestacy. Its own view is
    that CSJ's side of the trade is defensible — a firm cannot discharge a
    support obligation with a broken tool, and W-0022/23/24 are three open
    high-severity defects against this same generator. What decides whether
    residual harm is real is the QUALITY of the referral, which is why
    "including where only one of you is making a will" is in the copy as a
    compliance change, not a flourish.

  **design-lead verdict.** Rewrote both strings for voice and corrected a factual
  error in my draft: it said the partner "makes their own matching will
  alongside yours", but `WillBuilderPersonalStep.vue:66` and
  `WillBuilderReviewStep.vue:79-86` show **we** generate both — what the partner
  actually does is sign and witness theirs. Treatment (c): delete the chooser
  rather than leave a lone button (a solitary button still frames the step as a
  chooser, which acceptance 1 forbids); label changed from the question "What
  type of will would you like to create?" to the statement "Type of will";
  paragraph 1 as plain body text, paragraphs 2-3 in the step's EXISTING violet
  notice (`bg-violet-50 border-violet-200 text-violet-800/700`) — violet because
  this is a scope boundary, not an error, and raspberry is already this step's
  selection/CTA colour. No icons added (Rule 15 clean). Rule 9 clean in both
  strings; pinned by a test.

  **design-lead also corrected this board item.** Line 36 says "`willType` starts
  unset and either is equally reachable." It does not:
  `WillBuilderIntroStep.vue:114` read `this.formData.will_type || 'simple'` — a
  married user who never looked at that block proceeded with a **one-sided will
  having made no choice at all**. The live behaviour was worse than reported.
  Now initialised from `allowed_will_types`.

  **Browser evidence (localhost:8000, Playwright, real login + MFA).**
  - `/estate/will-builder` as a married user renders "Type of will", the lead
    paragraph, and the "Mirror Wills Only" notice. **No Simple Will button.**
    Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/20-web-mirror-wills-only-W-0019.png`
  - `POST /api/estate/will-builder` live, same session:
    `will_type: "simple"` → **422**, message begins "A mirror will is the only
    will we can build for you here"; `will_type: "mirror"` → **201**.
  - Fyn layer, gating verified per-path: married + "make me a simple will" /
    "I want to write a will" / "how do wills work" → directive emitted (2138
    chars, quoting `REFUSAL_MARRIED` and `REFUSAL_NO_MIRROR_PARTNER` verbatim);
    married + "I will retire at 60" → **no directive** (the modal verb does not
    misfire); unmarried + "make me a simple will" → no directive.

  **Acceptance 6 — existing one-sided wills — STILL CSJ'S CALL. Not assumed, not
  actioned.** Nothing has been rewritten. What I did implement is the narrow,
  reversible part: a married user cannot CREATE a simple will, cannot SWITCH an
  existing document to simple, and cannot COMPLETE a simple draft. Documents
  already `status: complete` are untouched and still viewable. Local count of
  non-mirror documents held by married users: **0**. The production count is
  unknown to me and is part of the same gate as W-0024.

  **`/m` and iOS.** Neither has a will-builder surface — `/m` routes are only
  `/estate` and `/estate/bequests` (`resources/mobile/router.js:69-70`) and hands
  off to the web builder via `WebHandoffDestination::ESTATE_WILL`; `ios-native/`
  has a read-only estate summary and no bequest/will/trust screens at all. So
  both reach this rule through the shared endpoint and the handoff, which is
  parity by architecture, not an exclusion. **Native gap found, not fixed, not
  mine:** `ios-native/Fynla/Core/Navigation/WebHandoffClient.swift:3-8` lacks the
  `estateWill` case the PHP enum and `/m` both have, so the native app has no
  route to the Will Builder at all. Raise as its own item.


- 2026-08-21 compliance-lead: **exposure 1 CLOSED — production count came back zero for real
  users.** Four mirror wills on fynla.org, all seeded preview personas; across 49 real accounts
  nobody has used the will builder. That is the "count is 0" branch I pre-stated in the delta
  report, and it resolves as written: **no notification question, nothing to remediate, no user
  holds a defective will.** The ">0" recommendation (direct notification naming the defect)
  does not arise. Report updated at §4a.

  **Two things it does NOT mean, recorded so they are not assumed away.** (1) **This is not a
  control working.** No gate existed on production; W-0024's defect is still in
  `origin/main:WillDocumentService.php:309` and production still defaults married users to a
  one-sided will (`origin/main:WillBuilderIntroStep.vue:114`). The count is zero because nobody
  used the feature, not because anything stopped them — the same defect with a hundred users
  would have produced a hundred defective wills and nothing would have known. (2) **The trunk
  findings are untouched.** The missing legal-services clause, the missing procedure for
  Consumer Duty trades, and the silence on withdrawing a paid capability were never contingent
  on the count.

  **Still open and unchanged:** production keeps producing the artefact this item exists to
  prevent until the release lands, and the Premium no-partner class still loses a paid
  capability on release with no notification path — that one needs deciding BEFORE the
  release, not after.

- 2026-08-21 compliance-lead: **delta report written —
  `workforce/ops/reports/2026-08-21-W-0019-perimeter-delta.md`.** CSJ direction the same
  day: *"if compliance raised issues these need to be checked, and a report shown of the
  delta and why."* Four perimeter questions checked against the trunk, the code and
  `origin/main`. **Recommendations only — the trunk is unamended; amendments are CSJ's.**

  **The four §6 questions I was said to have recorded here were never written down.**
  `branches/fixes/F-0003-batch-b-estate-wills.md:373` and `:546` both state they are
  "recorded on W-0019"; this file has no such section and no other file in the repo holds
  them (grep across `workforce/` and `tests/Persona/`). The report reconstructs them from
  what F-0003 §3a narrates. Routed to the Chief of Staff as a handoff-verification failure.

  **Three live exposures, separated from five documentation gaps:**
  1. W-0024's executor defect **is on production** — `origin/main:WillDocumentService.php:309`
     copies `executors` verbatim. Production count unknown; the report pre-states what
     follows whether it is 0 or >0, so the decision is immediate once CSJ has the number.
  2. **Production has no mirror-only gate AND silently defaults married users to a one-sided
     will** — `origin/main:WillBuilderIntroStep.vue:114`. Worse than this board item reported
     even after design-lead's correction: not just reachable, but the default. Every day dev
     is unreleased, production keeps producing the artefact this item exists to prevent.
  3. **The will builder is Premium-only** — verified, not assumed:
     `routes/api.php:950` → `estate.full` → `EnsureFullEstateAccess` 403
     `required_tier: premium`. So this item **withdraws a paid capability** from married
     users with no linked partner, with no notification path. Needs a decision BEFORE
     release. A second affected group is unnamed on this item: `refusalFor()` `:129` tests
     `canBuildMirror()` before the requested type, so a married user whose partner account is
     deleted cannot complete a mirror draft they already started.

  **Correcting my own earlier verdict on this item.** Above I wrote that CSJ's side of the
  Consumer Duty trade "wins". Under perimeter §7.3 that was a determination, not a flag —
  a sign-off with a different verb. The harm is stated and what would discharge it is stated;
  **whether the trade is acceptable is CSJ's, and it is open.**

  **Referral quality, carried through to four tests** (§6 of the report): says what *we*
  can't do rather than what the user can't have — **met**; leaves no impression a will is
  unavailable to them — **met**, and the clause "including where only one of you is making a
  will" is load-bearing, not wordy, so it must not be edited out; names a professional
  **specifically enough to act on** — **partially met** (it gives a direction, not a route);
  doesn't leave them worse off than when they asked — **not established** (they are inside a
  feature they pay for). Recommendation: a signposting line to a free public directory.
  Deliberately not drafted by me — naming an external service is a public-claim and
  design-lead decision.

  Trunk gaps found: no clause anywhere on the legal-services regime governing a tool that
  *generates a will* (§2's seven rules are all financial-advice controls — none would have
  caught W-0024); no procedure for trading against a Consumer Duty outcome; §4's positive
  disclosure stops at figures and does not reach a defective document already in a user's
  hands; nothing on withdrawing a paid capability. Dated sources for all four are in the
  report, which also seeds the source register §7.2 requires and `workforce/` lacks.

- 2026-08-21 build-lead: Rule 22 handover for this batch is
  `workforce/branches/fixes/F-0003-batch-b-estate-wills.md` — it carries the dispatch
  verbatim, the full `tax-compliance-reviewer` verdict on W-0020 (§3), the approved
  `compliance-lead` + `design-lead` refusal copy for W-0019 verbatim (§3a), decisions
  taken, dead ends ruled out, and environment state. **Rule 14's loop is NOT closed by
  me on this item** — see §8; the browser evidence recorded above is my own, gathered
  before the no-self-verification policy landed, and needs independent re-verification.

- 2026-08-21 team-lead: **CSJ DECISION — proceed. "There are no premium subscribers."**
  The concern was that shipping mirror-wills-only would withdraw a paid capability from
  premium subscribers in the no-partner class, which would be remediation rather than
  disclosure once live. **With no premium subscribers there is no cohort to remediate**,
  so the release carries no withdrawal exposure and needs no customer disclosure.
  This matches the earlier answered question on the same item (production mirror-will
  count was **zero real customers** — all four were preview personas).
  **Unblocked: implement mirror-wills-only for married users as specified.** The
  solicitor message for the mirror-wills-only + spouse-who-will-not-engage case was
  already agreed and stands.
