---
id: W-0031
title: education_level validation accepts three values the column enum cannot hold — latent 500 for Fyn and any API client
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0001-batch-c-retirement-profile-gates.md
owner: build-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
status: gated
severity: medium
surfaces: [web, m, ios]
source: found by fix-batch-C while fixing W-0006, 2026-08-21
prior_art_checked: 2026-08-21
prior_art_outcome: extend
---

## Intent

`app/Http/Requests/UpdatePersonalInfoRequest.php:64` allows `doctorate`, `foundation`
and `hnd`. The `education_level` column enum holds none of them. Validation passes,
the write dies as a `QueryException` — a 500, not a 422.

Unreachable through the web UI because the select never offers those options, but Fyn
and any API client can send them. Same class as W-0006: a request rule and a column
that disagree, with the rule winning validation and the column winning the write.

## Acceptance

Widen the column or narrow the rule — one decision, applied once, so the rule and the
column can never disagree again. If widening, the select and Fyn's catalogue must
offer the new values too, or they remain unreachable by a different route.

## Working notes

> **SUPERSEDED — see the `2026-08-21 build-lead` note below ("the item's premise was
> wrong").** A surface **does** offer all three:
> `resources/js/components/UserProfile/PersonalInformation.vue:326-334` had live
> `doctorate`, `foundation` and `hnd` options, and all three returned HTTP 500 on save.
> They were reachable, not dead. The note below still narrows, for a different reason.
> The claim was honestly made and is left as the record of what was believed.

Prefer narrowing unless CSJ wants the extra levels: three values no surface offers are
dead options, and Rule 9 means any new label is spelled out in full ("Higher National
Diploma", not "HND").

## Working notes (append-only)

- 2026-08-21 build-lead: FIXED by narrowing — but the item's premise was wrong,
  and the reasoning for narrowing is different from the one in the note above.

  **This was NOT latent, and not medium.** The working note says "three values no
  surface offers are dead options". A surface offers all three:
  `resources/js/components/UserProfile/PersonalInformation.vue:326-334` had
  `<option value="doctorate">`, `<option value="foundation">` and
  `<option value="hnd">` on a live, reachable select. Proved empirically before
  changing anything — all three return **HTTP 500** on save. A user picking
  "Doctorate" on the Personal Information page got a server error.

  That select was broken in both directions at once: it offered three values the
  column rejects and omitted three it accepts (`secondary`, `a_level`,
  `professional`), so those could not be recorded there at all.

  **Decision: narrow the rule to the six the column holds.** Reasoning, since the
  note's reasoning no longer applies:
  - Weight of evidence. The column and two of the three selects
    (`HealthInformation.vue`, onboarding `PersonalInfoStep.vue`) say six; one
    select and the request rule say nine. That is the same test used to settle
    W-0030's convention, applied consistently.
  - Narrowing is free and reversible; widening is not. **Zero users can hold
    `doctorate`/`foundation`/`hnd` today** — the write 500s — so nothing migrates.
    Widening would create rows that constrain any later decision to narrow.
  - It is a net gain for users, not a loss: fixing that select to the canonical six
    means Secondary, A-Levels and Professional Qualification can be recorded on the
    Personal Information page for the first time.
  - Widening would take a product decision (are Doctorate / Foundation Year /
    Higher National Diploma worth capturing separately?) on CSJ's behalf. Narrowing
    leaves that open. If CSJ wants them, widening later is an additive enum
    migration plus three entries in one constant.

  **"One decision, applied once" — the chain that now enforces it**

      users columns ─▶ App\Constants\ProfileEnums ─▶ resources/js/constants/profileOptions.js ─▶ resources/mobile/constants/profileOptions.js

  - `app/Constants/ProfileEnums.php` — canonical values for all three Health &
    Lifestyle enums, plus `OPTIONAL_SELECT_FIELDS` (the W-0006 empty-select list).
  - `UpdatePersonalInfoRequest.php:38,73-79` composes from it — no hand-written
    copy of a column enum survives in the rules.
  - `tests/Unit/Database/ProfileEnumColumnsTest.php` reads
    `INFORMATION_SCHEMA` and fails the moment the constant and the column disagree,
    in either direction. It also pins `smoking_status` NOT NULL, which is the fact
    the W-0006 empty-select handling depends on.
  - `resources/js/constants/profileOptions.js` is the one list for all three web
    selects, which previously disagreed three ways. It also absorbs
    `HealthInformation.vue`'s three private label maps — a fourth copy that had to
    be edited in step or a stored value rendered "Not specified".
  - Parity specs close both remaining links (see W-0034).

  **Tests:** `tests/Unit/Database/ProfileEnumColumnsTest.php` (4),
  `tests/Feature/Api/UserProfileControllerTest.php` (+2: all three rejected with 422
  not 500; all six accepted end to end),
  `resources/js/components/__tests__/UserProfile/ProfileOptionsParity.spec.js` (6).

  **Noticed, not changed:** the label "Secondary (GCSE/O-Levels)" contains an
  acronym, which Rule 9 forbids in user-facing text. It is pre-existing on two
  surfaces and I have now carried it verbatim to a third (`/m`), because divergent
  wording for one field across surfaces is a worse failure than the acronym.
  Spelling it out changes copy on a persona-tested page and belongs to CSJ — raising
  it rather than deciding it. One decision would fix all three surfaces at once.
