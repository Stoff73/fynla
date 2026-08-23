---
id: W-0024
title: Mirror will copies executors verbatim — the spouse's will appoints the spouse as her own executor
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0003-batch-b-estate-wills.md
owner: build-lead
reviewers: [compliance-lead]
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T09:15:00Z
claimed: 2026-08-21T09:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21
prior_art_found: [W-0019]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **mirror-will test** requested by team-lead
following CSJ's W-0019 direction. Local `localhost:8000`, premium.
Accounts: **David Jones (16)** primary, **Sarah Jones (17)** mirror.

**Surface:** desktop web, `/estate/will-builder`, mirror will generation.

Extends **W-0019** (married users should get mirror wills only). W-0019 says mirror is
the right instrument; this item says the mirror generator itself produces a legally
incoherent document. Fixing W-0019 without this makes the problem *universal* for
married users rather than optional.

### Expected

Persona `tests/Persona/peak_earners.md:527-561` — mirror wills:

| | David's will | Sarah's will |
|---|---|---|
| Executor | Sarah Jones & Barclays Wealth | **David Jones** & Barclays Wealth |
| Residuary | 100% to Sarah | 100% to David |
| Charity | Cancer Research UK £10,000 | **British Heart Foundation** £10,000 |

In a mirror pair each spouse appoints **the other** as executor. That is the whole
point of the instrument.

### Actual

Sarah's generated will (document 6) reads, verbatim from the live document view:

```
LAST WILL AND TESTAMENT
of Sarah Jones

1. I, Sarah Jones, of The Willows, 15 Chestnut Lane, Guildford, Surrey, GU1 4RH,
   GP Partner, born on 22 April 1978, HEREBY REVOKE all former wills ...

APPOINTMENT OF EXECUTORS
2. I APPOINT Sarah Jones of The Willows, 15 Chestnut Lane, Guildford, Surrey,
   GU1 4RH (my Spouse) to be the Executor and Trustee of this my Will.
```

**Sarah appoints Sarah as executor of Sarah's own will, and the document describes her
as her own spouse.** No warning, no validation, no prompt to review.

Stored data, both documents:

```
doc 5 (David, user 16): executors = [{"name":"Sarah Jones","relationship":"Spouse"}, {"name":"Barclays Wealth",...}]
doc 6 (Sarah, user 17): executors = [{"name":"Sarah Jones","relationship":"Spouse"}, {"name":"Barclays Wealth",...}]
                                     ^^^^^^^^^^^^^^ identical — never swapped
wills.id 12 (Sarah): executor_name = 'Sarah Jones, Barclays Wealth'
```

### Root cause

`app/Services/Estate/WillDocumentService.php:267-324`, `generateMirrorWill()`. It
swaps **only** the residuary estate:

```php
$mirrorResiduary = $this->swapResiduaryForMirror(
    $primary->residuary_estate ?? [], $primary->testator_full_name, $spouseFullName
);
...
'executors'      => $primary->executors,       // :309  copied verbatim
'guardians'      => $primary->guardians,       // :310  copied verbatim
'specific_gifts' => $primary->specific_gifts,  // :311  copied verbatim
'residuary_estate' => $mirrorResiduary,        // :312  correctly swapped
```

`swapResiduaryForMirror` proves the swap logic exists and works — the residuary IS
correct on both sides (David's names Sarah, Sarah's names David). Executors simply
never got the same treatment.

### Second, related finding — the charity legacy is seeded wrong

`:311` copies `specific_gifts` verbatim, so Sarah's mirror was pre-filled with
**David's** charity: "Cancer Research UK £10,000", where the persona says British
Heart Foundation.

To be precise, because it changes the severity: the field **is editable** on the
spouse's draft. I changed it to British Heart Foundation and the document regenerated
correctly. So this is **a silently wrong default in a legal document**, not an
impossibility — the spouse must notice that her charitable legacy names the other
spouse's charity and correct it. Nothing flags the value as copied.

### Third — the spouse gets no Guardians step at all

David's will builder shows a Guardians step and warns "You have children under 18 but
have not appointed a guardian." Sarah's mirror builder has **no Guardians step in the
stepper at all** (Intro, Personal, Executors, Gifts, Residuary, Funeral, Digital,
Review, Signing) and no warning, because the children are `FamilyMember` rows on
David's account and appear on hers only as "shared from spouse".

Sarah is Charlotte's mother. In a mirror pair, the guardian appointment matters most
in *her* will — a guardian only takes effect when both parents are gone, so both wills
need it. She is not offered it.

### Evidence

Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/14-web-sarah-mirror-will-names-herself-executor-W-0024.jpg`
— Sarah's own login, her own will, showing "I APPOINT Sarah Jones ... (my Spouse)".

Report: `reports/R-06-mirror-will-test.md`.

### Repro

1. Two linked spouse accounts, premium.
2. As the primary: `/estate/will-builder` → Mirror Will → name the **spouse** as
   executor → complete.
3. Generate the spouse's will (`POST /api/estate/will-builder/{id}/mirror`, or the
   "Generate Spouse's Will" button on the Review step).
4. Log in as the **spouse** and view her will. It appoints **her** as executor of her
   own will, and her specific gift is the primary's charity.

## Acceptance

- [ ] `generateMirrorWill()` swaps executors the way it already swaps the residuary:
      where the primary's executor is the spouse, the mirror's executor is the primary.
- [ ] A will can never appoint its own testator as executor — validate and block, on
      any path, not just the mirror generator.
- [ ] `relationship` is recomputed for the mirror rather than copied ("my Spouse" must
      describe the mirror's testator's relationship, not the primary's).
- [ ] Guardians are offered on **both** wills of a mirror pair where the household has
      a minor child, regardless of which account holds the `FamilyMember` rows.
- [ ] Copied `specific_gifts` are surfaced to the spouse as "copied from your partner's
      will — review before completing", or not copied at all. A charitable legacy must
      not silently default to the other spouse's charity.
- [ ] `wills.executor_name` is regenerated for the mirror, not inherited.
- [ ] Verified end to end against this persona: David → Sarah + Cancer Research UK;
      Sarah → David + British Heart Foundation.
- [ ] `compliance-lead` reviews whether a generated will naming its own testator as
      executor has any regulatory exposure beyond being wrong.
- [ ] `/m` and iOS (Rule 19).

## Working notes

(append-only)

- 2026-08-21 persona-tester: found on the mirror path at team-lead's instruction after
  CSJ raised W-0019. Not fixed by me — routed to build-lead, compliance-lead to review.
- **Bearing on W-0019's open questions:** the mirror generator creates the spouse's
  document with `status: 'draft'` and, at generation time, `will_id: NULL` (the spouse
  had no `Will` row; one was created on completion, id 12). So the spouse must log in
  and complete it herself — the primary cannot finish it for her. That is relevant to
  CSJ's open question about *"a spouse who refuses to make a will"*: today the pair
  simply sits half-finished, one complete will and one indefinite draft, with no
  surfacing of that state to either party. I did not test what the app does with a
  permanently-draft mirror.
- **What the mirror gets RIGHT:** residuary correctly swapped both ways; testator
  identity, address, DOB and occupation correctly replaced from the spouse's profile;
  the spouse can see and edit her own draft from her own login; completing it creates
  her `Will` row.
- W-0023 also reproduces on the mirror path: after both wills completed, `bequests` is
  **0** for both — neither charitable legacy reaches the `bequests` table.

- 2026-08-21 build-lead: **FIXED**, browser-verified. Not self-certified — handing to
  quality-lead for the evidence pack.

  **Root cause and fix.** `generateMirrorWill()` swapped only the residuary
  (`WillDocumentService.php:291-295` as was). Every party list is now swapped
  through one helper, `WillDocumentService::swapPartiesForMirror()`
  (`app/Services/Estate/WillDocumentService.php:565`), applied to `executors`,
  `guardians` and `residuary_estate` alike. Where a swap happens the
  `relationship` is recomputed to "Spouse", because after the swap the named
  party IS the mirror testator's partner.

  **Executor-is-testator is now blocked on every path**, not just the mirror
  generator, and from one home:
  - `WillDocumentService::EXECUTOR_IS_TESTATOR_MESSAGE` — the one message.
  - `WillDocumentService::isSameParty()` (`:625`) — the one name comparison.
    Deliberately conservative: case and whitespace only. Two people can share a
    name and guessing at nicknames in a legal document would be worse.
  - `validateDocument()` (`:196-204`) raises it at `severity: error`, so
    `markComplete()` refuses.
  - Fyn's `create_will` / `update_will` reach the SAME helper and message via
    `CoordinatingAgent::refuseSelfAppointedExecutor()`
    (`app/Agents/CoordinatingAgent.php:4086`).

  **Guardians on both wills** — `WillDocumentService::householdChildren()`
  (`:106`) reads FamilyMember rows from the user AND their live spouse, deduped.
  It replaced two separate minor-child computations (`prePopulateData` and
  `validateDocument` each ran their own), so the wizard gate and the validator
  now give one answer.

  **Copied gifts are surfaced, not silent.** The mirror's gifts carry
  `copied_from_partner: true`; `validateDocument()` raises
  `COPIED_GIFTS_MESSAGE` until the partner saves the Gifts step, which clears
  the marker (`updateStep()` `:139`). Chosen over "don't copy at all" so the
  mirror can still hold DIFFERING gifts — which it does; see below.

  **`wills.executor_name` is fixed at source** — `markComplete()` already
  derived it from `$doc->executors`, which are now correct.

  **Browser evidence (localhost:8000, Playwright, real login + MFA).** Driven on
  a throwaway married pair (Adam & Beth Hall) because David and Sarah already
  hold completed wills, so the wizard does not render for them. The pair was
  deleted afterwards; **David (16) and Sarah (17) were not modified** — verified
  after cleanup: docs=1, wills=1, trusts=1, mortgages=1 each, unchanged.

  - Named himself executor → Review step rendered "A will cannot appoint its own
    testator as executor. Name the person who will carry out your wishes." and
    **"Complete & Finalise" was disabled**.
  - Corrected to the spouse → error cleared, completion allowed.
  - "Generate Spouse's Will" produced, verified in the database:
    ```
    Adam's will  : executors [{"name":"Beth Hall","relationship":"Spouse"}]  residuary Beth Hall
    Beth's mirror: executors [{"name":"Adam Hall","relationship":"Spouse"}]  residuary Adam Hall
    gifts        : [{"beneficiary_name":"Cancer Research UK", ... ,"copied_from_partner":true}]
    ```
    Before this fix Beth's mirror named **Beth** as her own executor.

  **The persona's differing bequests are NOT a further defect.** The mirror
  carries the primary's gifts as an editable, flagged starting point and each
  partner edits their own draft, so David → Cancer Research UK and Sarah →
  British Heart Foundation is supported. No new board item raised.

  **Limit, stated rather than hidden:** third-party executors (a professional, a
  sibling) keep the primary's recorded `relationship`. One partner's
  relationship to the other's relatives is not derivable — hence the
  review flag rather than an asserted value.

  **compliance-lead verdict (2026-08-21).** Exposure is not the FCA perimeter —
  will drafting is a legal service. It is that Fynla generated and served a
  legal instrument incoherent on its face, while `WillBuilderIntroStep.vue:13`
  told the user the document was built "based on the information you provide" —
  which misdescribes where the error came from, and the user provided nothing
  wrong. The wrong values were also PERSISTED to `wills.executor_name`, so
  Fynla's own record of the household's intentions was wrong and fed estate
  modelling downstream.

  **GATE FOR CSJ — this defect is on production.** `generateMirrorWill()` landed
  in `9cfeadb46` (2026-03-16), which `git branch -r --contains` puts on
  `origin/main`. compliance-lead's determining question is whether any real user
  has generated a mirror will on fynla.org — a `will_documents WHERE will_type =
  'mirror'` count on prod. **I did not run it**: the `ssh-fynla` MCP is
  production and Batch B is scoped local-only. Raising it, not answering it.

  Tests: `tests/Unit/Services/Estate/WillDocumentServiceTest.php` — 6 new cases
  under "mirror wills swap every party, not just the residuary (W-0024)".

- 2026-08-21 build-lead: Rule 22 handover for this batch is
  `workforce/branches/fixes/F-0003-batch-b-estate-wills.md` — it carries the dispatch
  verbatim, the full `tax-compliance-reviewer` verdict on W-0020 (§3), the approved
  `compliance-lead` + `design-lead` refusal copy for W-0019 verbatim (§3a), decisions
  taken, dead ends ruled out, and environment state. **Rule 14's loop is NOT closed by
  me on this item** — see §8; the browser evidence recorded above is my own, gathered
  before the no-self-verification policy landed, and needs independent re-verification.

- 2026-08-23 build-lead (`fix-cycle4-wills`, F-0029): **re-verified at team-lead's
  instruction after cycle 4 saw the symptom again on `wills.id = 12`. VERDICT:
  this fix STAYS FIXED; that row is pre-fix residue.** Decided by generating a
  fresh mirror through the live service on a throwaway married pair (transaction
  rolled back, 0 rows kept) — every party swapped, nobody their own executor.
  Three supports that document 6 predates the fix: generated 2026-08-21 08:59:21
  against a 09:40 claim; no `copied_from_partner` marker, which the post-fix
  generator always writes; residuary already correct, which is the pre-fix
  behaviour. **The correct charity on Sarah's mirror is NOT evidence of a partial
  fix** — the post-fix generator would have produced Cancer Research UK with the
  marker; British Heart Foundation with no marker is the tester's own edit,
  recorded in these notes above and corroborated by `updated_at` 09:03:33 against
  `created_at` 08:59:21. Residue cleared by **W-0395**
  (`estate:backfill-mirror-parties`).

  **But this fix had a hole, now closed as W-0396.** `generateMirrorWill()`
  matched each partner on ONE spelling, built from different sources on the two
  sides — the primary's from `testator_full_name`, the partner's from
  first + middle + surname. A partner with a middle name recorded but named in
  the will without it matched neither, so nothing swapped and this exact symptom
  returned. **The 6 tests added here could not see it** (their fixtures give the
  partner no middle name, so the right and wrong answers are the same string) and
  neither could the persona. Reducing the candidates back to one spelling leaves
  all 38 cases in `WillDocumentServiceTest` green against a generator producing a
  self-appointing will. **The GATE above is therefore LARGER, not resolved:** any
  pre-fix mirror on production is broken, and so is any post-fix mirror where the
  partner has a middle name recorded. `estate:backfill-mirror-parties` is the
  remedy for both. Still not run against production — the `ssh-fynla` MCP is
  production and cycle 4 is local-only.
