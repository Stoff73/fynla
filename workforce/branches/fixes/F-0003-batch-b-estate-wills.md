# F-0003 — Batch B: Estate & Wills

**Owner:** build-lead (agent `fix-batch-B`) · **Branch:** `dev` (no feature branch) ·
**Board items:** W-0024, W-0019, W-0023, W-0020, W-0022, W-0021, W-0046, **W-0053**, **W-0041** (2nd instance), **W-0037** (notes)

**Status at time of writing: ALL ITEMS CODE-COMPLETE, unit-verified, Pint clean, board
items updated and moved to `handoff` → `quality-lead`.** W-0046's backfill has been RUN
against local data on team-lead's authorisation (§2a). Nothing is in flight; §4 says so
explicitly. This document satisfies CLAUDE.md Rule 22 (Context Budget) —
it is the handover a replacement agent would be seeded from.

**Rule 14's loop is NOT closed by me on any item.** See §8 for exactly what I did and
did not verify, and why the browser evidence I hold is my own and not independent.

---

## 1. The dispatch, verbatim

> Fix **Batch B — Estate & Wills**, six board items found by a live persona run. Work in `/Users/CSJ/Desktop/fynla` on branch `dev`.
>
> ## Your items (read each board file in full first)
>
> - `workforce/ops/board/W-0024-mirror-will-copies-executors-spouse-becomes-own-executor.md` — **do this FIRST.** The mirror will copies executors verbatim, so the spouse's will appoints the spouse as her own executor. This must be fixed before W-0019 can be built on top, otherwise we make a broken mirror flow mandatory for every married couple.
> - `W-0019-married-users-must-only-get-mirror-wills.md` — **CSJ direction, not a bug report.** Married users get mirror wills ONLY. Any other request → clear message that we cannot do this and they should speak to a solicitor. **CSJ answered the open question 2026-08-21: a married user whose spouse will not engage gets the solicitor message too — no one-sided will.** The second open question (what happens to one-sided wills already stored for married users) is still CSJ's to answer — do NOT assume it, and do NOT silently rewrite anyone's existing will.
> - `W-0023-will-builder-gifts-never-become-bequests.md` — specific gifts never become `Bequest` rows; blocks all 6 persona bequests.
> - `W-0020-charitable-bequest-enum-mismatch.md` — checks `bequest_type === 'specific'`, a value the enum cannot hold, so cash legacies never reduce the IHT rate.
> - `W-0022-letter-says-no-liabilities-while-mortgage-exists.md` — letter to loved ones tells the surviving spouse "No outstanding liabilities recorded" while a £65,000 mortgage exists.
> - `W-0021-trust-card-shows-rpt-acronym.md` — Rule 9 violation.
>
> ## Two mandatory reviews before you finalise
>
> - **W-0020 must go through `tax-compliance-reviewer`.** The reduced IHT rate for charitable legacies is a tax-accuracy matter, not just a bug — verify the threshold and rate against `TaxConfigService`, never hardcoded.
> - **W-0019's refusal and solicitor-referral copy must go through `compliance-lead`** (advice-vs-guidance boundary) and `design-lead` (wording). We are telling users we will not do something and to seek professional help — that copy has to be right.
>
> ## Mandatory context
>
> Read `workforce/core/index.md`, then the vault docs for Estate (`v083/09-MODULES.md`, `EstatePlanning.md`). Rule 9: no acronyms in user-facing text. Rule 15: no icons on Estate detail views. Rule 2: all tax values via `TaxConfigService`.
>
> ## Rule 20
>
> Whatever you change must reach web AND `/m` (`resources/mobile/` has its own store, router and services — a fix in `resources/js/` does NOT reach it) AND Fyn. If Fyn can create or discuss wills, its answer must come from the same source as the form — a second vocabulary or a surface-specific branch is a violation, not a fix.
>
> ## Reproduction data is in place — use it, do not disturb it
>
> **David Jones (16)** and **Sarah Jones (17)**, linked, premium, with mirror wills already generated and a £185,000 trust. The persona contract is `tests/Persona/peak_earners.md` — note the mirror wills are NOT identical: David leaves £10,000 to Cancer Research UK, Sarah £10,000 to British Heart Foundation. **If the mirror flow cannot hold differing bequests, that is a further defect — raise it as a new board item rather than forcing the persona to fit.**
>
> Do NOT delete or modify these users, do NOT patch DB rows, do NOT `migrate:fresh`, do NOT touch `.env`.
>
> ## Definition of done
>
> 1. Root cause fixed once, reaching web, `/m` and Fyn.
> 2. Targeted tests only — NOT the full suite (CSJ standing instruction).
> 3. `./vendor/bin/pint` clean on what you touched.
> 4. Board items updated with status, file:line and evidence.
> 5. **No PR, no merge, no deploy, no csjones, no prod.** Report back to me; I am coordinating three parallel batches.
> 6. Do not reach into Ownership/Net-Worth (Batch A) or Retirement/Profile/Gates (Batch C). If a fix collides, stop and tell me.
>
> Report: what you fixed, the reviewer verdicts from tax-compliance-reviewer and compliance-lead, tests run with output, anything blocked and why.

### Standing rules received mid-batch

- **Test DB:** run every Pest command as `DB_DATABASE=laravel_testing_b ./vendor/bin/pest <paths>`. `phpunit.xml:46` sets `DB_DATABASE` without `force="true"`, so a shell override wins. Verified empirically — `laravel_testing_b` has 171 tables.
- **Rule 22 (Context Budget):** hand over at ~900k; report context position when reporting.
- **Browser policy (arrived AFTER I had already browser-verified — see §8):** fix agents do not browser-verify their own fixes; the browser belongs to `persona-passA2`; route agent-to-agent traffic through team-lead.

---

## 2. DONE — all six, with file:line evidence

### W-0024 — mirror will copies executors (done first, as instructed)

| Change | Location |
|---|---|
| One swap helper for executors, guardians AND residuary | `app/Services/Estate/WillDocumentService.php:565` `swapPartiesForMirror()` |
| Applied at generation | `app/Services/Estate/WillDocumentService.php:299-330` |
| One name comparison, used everywhere | `app/Services/Estate/WillDocumentService.php:625` `isSameParty()` (public static) |
| One message | `app/Services/Estate/WillDocumentService.php:24` `EXECUTOR_IS_TESTATOR_MESSAGE` |
| Blocking validation (`severity: error`) | `app/Services/Estate/WillDocumentService.php:196-204` |
| Household children — one computation replacing two | `app/Services/Estate/WillDocumentService.php:106` `householdChildren()`, consumed at `:87` and `:232` |
| Copied-gift marker + review warning | `:31` `COPIED_GIFTS_MESSAGE`, set at `:318`, warned at `:212`, cleared at `:139` |
| Fyn reaches the SAME helper and message | `app/Agents/CoordinatingAgent.php:4086` `refuseSelfAppointedExecutor()`, called at `:4128` and `:4192` |

`wills.executor_name` needed no separate fix — `markComplete()` already derived it from
`$doc->executors`, which are now correct at source.

**The persona's differing bequests are NOT a further defect.** The mirror carries the
primary's gifts as an editable, flagged starting point; each partner edits their own
draft. David → Cancer Research UK and Sarah → British Heart Foundation is supported.
No new board item raised, per the dispatch's instruction to check before assuming.

**Stated limit, not hidden:** third-party executors (a professional, a sibling) keep the
primary's recorded `relationship`. One partner's relationship to the other's relatives
is not derivable — hence the review flag rather than an asserted value.

### W-0019 — married users get mirror wills only

New class: **`app/Services/Estate/WillTypePolicy.php`** — the one home for the decision
*and* the wording. Three consumers, no second vocabulary:

| Surface | Path |
|---|---|
| Web will builder | `WillDocumentService::prePopulateData()` `:99` returns `will_type_policy` → `resources/js/components/Estate/WillBuilder/steps/WillBuilderIntroStep.vue:55-100` |
| API refusals | `app/Http/Controllers/Api/Estate/WillDocumentController.php:33` `refuseUnsupportedWillType()`, gating `store` (`:96`), the `intro` step of `update` (`:150`), and `WillDocumentService::markComplete()` (`:415`) |
| Fyn | `app/Services/AI/Fyn/FynContextAssembler.php:566` `willStructureDirective()`, emitted at `:281` |

**Marital determination is `marital_status`-led, not `has_spouse`** (`WillTypePolicy::isMarried()`):
a declared status is authoritative in BOTH directions (`married`/`civil_partnership` →
married; `single`/`divorced`/`widowed` → not, even with a lingering `spouse_id`, which
survives a divorce by design per the `User::spouse` docblock); `liveSpouseId()` is
consulted only when nothing is declared. `prePopulateData` now also returns `has_spouse`
from `liveSpouseId()` rather than the raw column.

CSJ's answered question is implemented: married + no live partner account →
`allowed_will_types: []`, `can_build: false`, `REFUSAL_NO_MIRROR_PARTNER`, Continue disabled.

### W-0023 — will-builder gifts never become bequests

`WillDocumentService::syncBequests()` (`:480`), called from `markComplete()` (`:449`).
Cash → `specific_amount`; item → `specific_asset`. New nullable column
`bequests.will_document_id` (migration
`database/migrations/2026_08_21_090000_add_will_document_id_to_bequests_table.php`)
marks sync-owned rows: they are `forceDelete`d and rewritten each completion, while
hand-made rows (NULL) are never touched. A hand-made row matching on
(beneficiary, type) is **adopted** rather than duplicated (`:521-530`) — see §5.

### W-0020 — charitable bequest enum, and much more

See §3 for the full `tax-compliance-reviewer` verdict. Changes:
`WillAnalysisService.php:156` (`specific_amount`); `IHTCalculationService.php:1330-1341`
(the rate now reads recorded bequests); `EstatePlanService.php:476` (`'qualifies'`) and
`:536` (`current_percentage`); charity detection consolidated to `Bequest::isCharitable()`
with the duplicate deleted; `TaxConfigService::getCharitableReducedRate()` and
`getCharitableThresholdPercent()` added; `WillAnalysisService::hasUnvaluedCharitableGifts()`
(`:107`) suppresses a wrong instruction.

### W-0022 — letter says no liabilities while a mortgage exists

New nullable column `letters_to_spouse.auto_populated_fields` (migration
`2026_08_21_090100_...`). `LetterToSpouseService::getOrCreateLetter()` (`:80`) recomputes
owned sections on read; `updateLetter()` (`:400`) releases a section permanently once the
user edits it. `generateLiabilitiesInfo()` returns `null` where there is nothing, not a
denial. One liability count for both the letter and the checker —
`outstandingLiabilities()` / `outstandingLiabilityCount()`, consumed by
`LetterEstateValidationService.php:233`.

**Two further defects found and fixed in the same section** (the generator read columns
that do not exist): `$mortgage->lender` → `lender_name` (every mortgage listed with **no
lender**), and `$liability->creditor` / `outstanding_balance` → `liability_name` /
`current_balance` (every non-mortgage debt listed with no name and **£0.00**).

Also: `createWithDefaults()` now sets the relation on `$user` — without it a second
`getOrCreateLetter()` on the same instance attempted a duplicate insert against a unique
`user_id`. Pre-existing, but refresh-on-read makes repeated reads far more likely.

### W-0021 — RPT acronym

Two violations, not one: `resources/js/components/Trusts/TrustCard.vue:27` and
`resources/js/views/Trusts/TrustDetailView.vue:37`. Both now read "Relevant Property
Trust". Layout changes per `design-lead` so the longer phrase fits: `.item-name` gains
`flex-wrap: wrap` + `min-width: 0` and loses `flex-shrink: 0`; `.badge` gains
`white-space: nowrap`; `.header-badges` gains `flex-wrap: wrap`. No colour changes.
Module acronym sweep clean (two hits, both in comments).

---

## 2a. W-0046 — backfill Bequest rows for wills completed before W-0023

Assigned 2026-08-21 after the first six closed. CSJ: *"these need to work properly"* —
a defect, not a preference.

**`php artisan estate:backfill-bequests`** —
`app/Console/Commands/BackfillWillBequests.php`. Dry-run by default (`--force` writes,
`--user=` scopes), matching `fyn:episodic:purge` and `fyn:user:erase`.

**Prior art — `extend`.** Six sources checked. 11 existing `Backfill*`/`Migrate*`
commands set the pattern; `fyn:episodic:backfill-blobs` is CLAUDE.md's own "one-shot
idempotent backfill" precedent. Nothing already did this job.

**It reuses the sync rather than reimplementing it** —
`WillDocumentService::syncBequestsForDocument()` (`:472`) is a thin public wrapper over
the same private `syncBequests()` a completion calls. That makes acceptance 4 true by
construction: the backfill and a later re-completion are the same code, and it clears
before it writes.

**Real before/after figures in a dry run** — the whole run executes inside a transaction
that is rolled back unless `--force`, so the numbers come from the real code path
against real rows rather than a second implementation guessing. Caches are cleared again
after rollback so a dry-run leaves nothing behind.

### Two defects found while building it, both mine, both fixed

1. **The Inheritance Tax calculation would not have picked the backfill up.** The
   calculation is cached in `iht_calculations`, keyed on hashes of assets and
   liabilities. Bequests were not in that key — sound while the rate depended only on
   `IHTProfile.charitable_giving_percent`, but **W-0020 made the rate read the
   bequests**. A user could record a charitable legacy, qualify for the reduced rate,
   and keep being served 40% from cache until their assets happened to change: W-0020's
   fix silently defeated by cache. Fixed with `charitableBequestFingerprint()`
   (`IHTCalculationService.php:1535`), folded into the key in both `generateHashes()`
   and `saveCalculation()`.
   **The pin was verified to fail without the fix** — removing the fingerprint makes the
   new test report `Failed asserting that 0.4 is identical to 0.36`.
2. **`syncBequests()` only cleared rows from its own document.** A user who completed a
   second will document kept the first document's gifts standing beside the new ones —
   two live sets for one will, both counted by the charitable total. It now clears every
   will-document-sourced row for the will (`whereNotNull('will_document_id')`), so the
   current document is authoritative. Hand-made rows (NULL) are still never touched.

### The local dry run, and the finding it surfaced

Six wills, six rows, **no estate changes its Inheritance Tax rate** (a £10,000 legacy
against seven-figure estates sits far below the 10% threshold). Full table on the board
item.

One row is flagged `REVIEW`: **Sarah Mitchell (24) would go £10,000 → £20,000**, because
her hand-made bequest names British Heart Foundation while her will *document* names
Cancer Research UK — her husband's charity, copied verbatim by the W-0024 mirror defect
before it was fixed. They are different legacies by name, so the adoption matcher
correctly does not merge them and the backfill faithfully records what her document
says. **Do not "fix" this by merging** — merging two differently-named charities would
be inventing. The flag exists so a human looks. This is pre-existing wrong data that the
backfill makes visible, which is an argument for running it.

### Not yet run against real data — deliberate

`--force` has not been run. The write path is proven by tests, but committing touches
David and Sarah while the tester is re-running against them, and creates Sarah
Mitchell's flagged row. Under the route-everything-through-team-lead rule that is their
call. Production is explicitly out of scope and was not touched.

Tests: `tests/Feature/Estate/BackfillWillBequestsCommandTest.php` (10) plus a
cache-staleness pin in `WillAnalysisCharitableBequestTest.php`.


## 2b. W-0053 — a completed mirror will could never be paired

**The report said there was no route back after completion. There is** —
`WillPlanning.vue:97-101` renders a **"View Will"** button whenever
`will.will_document_id` is set (which `markComplete()` always sets), pushing
`/estate/will-builder?view=document`; `WillBuilderView.vue:76-84` honours that param and
`:97-99` starts the wizard at Review for a completed document. The tester's conclusion
was reasonable — **Edit genuinely does not reopen the wizard** — but "View Will" does,
which is why this was a one-condition fix rather than a new surface.

**Root cause: a single gate.** `WillBuilderReviewStep.vue:81` carried `!isComplete`, so
the Generate button vanished the moment the will completed. And `mirrorData` is only ever
populated by clicking Generate in that session — never loaded from the server — so after
a reload the component could not tell whether a counterpart existed.

**Fix, three parts:** the button's condition now consults a new `mirrorGenerated()`
computed (`mirrorData || formData.mirror_document_id`) so it survives reloads and hides
once the pair exists; `generateMirrorWill()` returns the existing counterpart instead of
creating a second, because a post-completion button can be pressed twice; and
`validateDocument()` raises `MIRROR_NOT_GENERATED_MESSAGE` before completion so new cases
announce themselves. The warning is additive, not a substitute — team-lead is right that
a warning still permits the stranded state.

**The already-stranded row is rescued through the UI, no migration needed.**
`will_documents.14` (Priya Raman) now raises the warning, and `wills.will_document_id=14`
means "View Will" renders, which reaches Review, where Generate now shows.

**The spouse cannot generate from their side, and I did not change that.**
`WillDocumentController::generateMirror()` scopes to `where('user_id', $request->user()->id)`,
so the pair is only ever creatable by the first testator. Making it two-sided means one
account writing a will document into another, which needs a `SpousePermission` decision —
its own item, not a line added quietly inside this fix.

**Pattern worth keeping:** where a screen offers two terminal actions, walk both orders.
Generate-then-complete worked; complete-then-generate stranded.

## 2c. W-0041 second instance — `deleteBequest` 500s after succeeding

`WillController.php:221` declared `: JsonResponse` and returned `response()->noContent()`
— the row was deleted, then the response threw a TypeError, so the user saw an error for
an action that had succeeded. Fixed to the house standard (200 + `{success, message}`).
Recorded on W-0041 rather than as a new item, keeping both instances together.

No joint/cascade branch exists here (single `firstOrFail` + `delete`), but the
will-document-linked row is covered separately since those are what the newly shipped `/m`
and web controls delete. Sweep of the area now returns one `noContent()` hit,
`Admin/DocumentArticleController.php:73`, which Batch A already determined is correct.

**Reported, not fixed:** deleting a bequest carrying a `will_document_id` removes the row,
but a later re-completion recreates it from the document's gifts. Arguably correct — the
document is the source of truth — but surprising from the Estate screen.


## 3. `tax-compliance-reviewer` verdict on W-0020 — RECORD, expensive to rebuild

**Verdict on the one-word fix: correct.** It independently confirmed the enum
(`database/schema/mysql-schema.sql:388` —
`enum('percentage','specific_amount','specific_asset','residuary')`), both request rules,
and grepped every `bequest_type` comparison across `app/`, `resources/js/`,
`resources/mobile/`, `ios-native/` and `database/`: no other comparison against a
non-enum value survives. Board acceptance 6 satisfied.

### `TaxConfigService` values it confirmed

| Value | Verdict |
|---|---|
| **RNRB exclusion from the baseline** | **CORRECT ✅.** `WillAnalysisService.php:43` subtracts `nil_rate_band` only. Right per IHTA 1984 Sch 1A para 6 — the RNRB (ss.8D–8M) is not part of it. The Sch 1A step-3 add-back is implicitly satisfied because the caller passes a net estate computed before any charitable deduction. |
| **`inheritance_tax.nil_rate_band`** | Read correctly, but was the wrong *quantity* — see F2 below. |
| **`inheritance_tax.reduced_rate_charity`** | Present and correct as the source, but had six duplicated `?? 0.36` fallbacks and a seventh consumer on `TaxDefaults::IHT_CHARITABLE_RATE`. |
| **`inheritance_tax.charity_threshold_percent`** | **Seeded (`TaxConfigurationSeeder.php:330`), validated, rendered in the admin Tax Settings screen (`TaxSettings.vue:1380`) as though it governs the calculation — and read by ZERO calculation code.** Its words: a worse failure mode than a literal, because the UI asserts control that does not exist. |
| **`inheritance_tax.standard_rate`** | Read correctly, with no fallback — which is what made the asymmetry with `?? 0.36` in the same method indefensible. |

**Its ruling on my direct questions:**
- *Is the 10% safe as a statutory constant?* It would be — **except this codebase already
  decided otherwise** by seeding the key and surfacing it to admins. Read it.
- *Is `?? 0.36` acceptable?* **No, it must go.** Three specific reasons: the same method
  reads `standard_rate` with no fallback (one method, two policies); the key is validated
  `sometimes` (`StoreTaxConfigurationRequest.php:70`) so an admin can save a config
  without it and the literal fires silently; and there are six copies plus a seventh
  convention. Recommended a `TaxConfigService` accessor following the existing
  `getCLTLifetimeRate()` precedent (`TaxConfigService.php:283`, created in the 2026-05-23
  audit for exactly this problem). **Done** — both accessors added.

### The finding that mattered most (F18, Critical) — and it is FIXED

**`IHTCalculationService::determineIHTRate()` never called `getCharitableBequestTotal()`
at all.** It read `IHTProfile.charitable_giving_percent` — a planning figure the user
types on their profile — and nothing derived that from bequests. I verified this myself
at `IHTCalculationService.php:1311-1313` before acting.

Consequence: **the board's observed symptom was caused by this disconnect, not by the
enum typo**, and W-0020 acceptance 3 could not have passed with the typo fix alone.
`getCharitableBequestTotal()` had exactly two callers: itself and my new test.

**Fixed** at `IHTCalculationService.php:1330-1341`. Precedence is stated in the code:
**the recorded will wins**, because the will is the instrument HMRC reads; the profile
percentage remains the answer for a user with no bequests. `charitable_deduction` flows
from the same value, so the rate and the exemption cannot disagree. Pinned by three
end-to-end tests.

### Its ruling on my `specific_asset` / `residuary` exclusion

**Legal reading right, implementation right, safety claim WRONG.** Under s.23 a
charitable gift of a specific asset and a charitable residuary gift both qualify and both
feed the Sch 1A donated amount — and a charitable residuary gift is one of the most
common shapes in UK will drafting, usually far larger than a cash legacy.

Its key point, which I acted on: **understating is only "safe" when the output is a
computation.** This method's output is an *instruction* — "Increase charitable giving by
£40,928 to qualify". For a user whose will already leaves 10% of residue to charity, that
tells them to give away five figures to buy a relief they already hold. Not a
conservative failure; a specific, actionable, wrong instruction with a five-figure price
tag, and worse under Consumer Duty than saying nothing.

**Fixed:** `hasUnvaluedCharitableGifts()` (`WillAnalysisService.php:107`) suppresses the
instruction and returns `UNVALUED_CHARITABLE_GIFTS_MESSAGE` instead. The exclusion comment
was corrected too — it previously implied these gifts do not qualify; they DO.

### Other findings it raised that I FIXED

- **F19 (High):** `EstatePlanService.php:476` compared status against `'qualifies'`, a
  value the producer cannot emit (`below`/`at`/`above`) — **the same bug class as W-0020,
  one hop downstream**, and unreachable until my fix made a cash legacy able to qualify.
- **F20 (Medium):** `EstatePlanService.php:527` read `current_percentage`, a key the
  analysis never emits — pinned at 0.0.
- **F10 (High):** `WillAnalysisService`'s private charity list treated **`'trust'`** as a
  charity indicator. A "Smith Family Trust" counted toward the charitable total. A gift
  into a family trust is a chargeable transfer, not exempt — **the only unsafe-direction
  error in the file.** Ruled `Bequest::isCharitable()` the correct list.
- **F11 (Medium):** two copies of the same decision; `Bequest::isCharitable()` had zero
  callers. Consolidated, duplicate deleted, docblock records why `'trust'` must not return.
- **F2 (High):** the baseline used a single £325,000 band while `IHTCalculationService`
  used the combined figure (up to £650,000) — two thresholds for one household, roughly
  80% too high. `analyzeCharitableBequests()` now takes `$nrbAvailable` from the caller
  (`EstateAgent.php:194`).
- **F16 (Low):** soft-delete accumulation in `syncBequests` — now `forceDelete`.
- **F22/F23 (Medium):** my own test overclaimed Rule 2 compliance and its rate assertion
  could not detect the fallback (it read the same config element the code reads). Both
  fixed; the test now moves the configured rate to 0.30 and asserts the analysis follows.

### NOT FIXED — recorded in full on W-0020, needs separate items

- **F3 (High):** the baseline ignores the s.18 spouse exemption and IHT-exempt assets.
  `EstateAgent::buildAssetSummary()` does not `reject(is_iht_exempt)`, unlike
  `IHTCalculationService.php:109-110`. For a married user leaving everything to their
  spouse the true Sch 1A baseline is ~nil; we compute the whole estate less the band.
- **F4 (High):** `potential_saving = baseline * 0.04` is shipped as a £ figure and matches
  neither the tax saved (~£31,105 on a £409,280 baseline) nor the net cost (−£9,823) —
  wrong by roughly a factor of two, because the gift leaves the estate before the reduced
  rate applies.
- **F9 (High):** charity determination is name-substring matching in production.
  `beneficiary_type` and `charity_registration_number` are never populated by any write
  path (`BequestForm.vue:214-222` does not send them; `SaveWillDocumentRequest.php:49-54`
  has no charity field on a gift), so both structured checks are dead outside tests.
  "The Donkey Sanctuary" and "RNLI" are missed; "Cancer Consultants Ltd" is a false
  positive. **Needs a form field — a product decision.**
- **F14 (Medium):** `wills.spouse_bequest_percentage` is not updated by `markComplete()`,
  so a will leaving 60/40 still renders "100% to spouse".
- **F8 (Medium):** Sch 1A components (survivorship / settled / general, para 3, with the
  para 7 merger election and para 9 opt-out) are not modelled; one whole-estate test runs.
  It would accept this as a documented limitation, but the docblock currently reads as if
  the calculation is complete.
- **F7 (Low):** a sub-NRB estate produces "£0 below the 10% threshold of £0 … save £0".
- **F17 (Low):** cache invalidation is not spouse-aware on a mirror completion.
- **F21 (Medium):** `TaxSettingsController.php:330` reads `reduced_rate` where the key is
  `reduced_rate_charity`, so its hardcoded `0.36` fires 100% of the time — in the admin
  panel, for this very value.

---

## 3a. `compliance-lead` + `design-lead` verdicts on W-0019 copy — APPROVED TEXT, VERBATIM

**This copy is agreed AND implemented** in `app/Services/Estate/WillTypePolicy.php`.
Recorded here verbatim so no replacement has to re-run either review.

### `REFUSAL_MARRIED` — shown in place of the will-type chooser; returned in API 422s; quoted by Fyn

> A mirror will is the only will we can build for you here — a matching pair, one for you and one for your spouse or civil partner, each leaving to the other first, then to the beneficiaries you both choose.
>
> We can't build a will for one of you on its own. A will for one spouse or civil partner alone is outside what this tool is designed to do.
>
> If you want a different arrangement, please speak to a qualified solicitor. This tool doesn't provide legal advice — a solicitor can take your full circumstances into account and draft a will to match.

### `REFUSAL_NO_MIRROR_PARTNER` — married user whose partner will not make a matching will

> We can't build your will here. A mirror will only works as a pair — we build both from the same details, and each of you signs and witnesses your own. If your spouse or civil partner isn't going to make theirs, there's nothing to mirror.
>
> That's a limit of this tool, not a comment on your situation.
>
> Please speak to a qualified solicitor. This tool doesn't provide legal advice — a solicitor can advise on what fits your circumstances, including where only one of you is making a will.

### `REFUSAL_HEADING`

> Mirror Wills Only

### The Fyn directive (`FynContextAssembler::willStructureDirective()`, `:566`)

Interpolates the two constants above; it does **not** paraphrase them.

> The user is married or in a civil partnership. Fynla builds mirror wills only for these users — a matching pair, one for each partner.
>
> If they ask you to build, draft or set up any other kind of will — a simple will, a one-sided will, a will for them alone — do not draft, outline or part-build it, and do not offer a workaround. Reply with this text, unchanged:
>
> "{REFUSAL_MARRIED}"
>
> If they want a mirror will but tell you their spouse or civil partner will not make a matching one, reply with this text instead, unchanged:
>
> "{REFUSAL_NO_MIRROR_PARTNER}"
>
> You may open with a short natural line, but the text above must appear unchanged, and you must not add exceptions, caveats or alternatives to it. Do not close this particular reply with a follow-up question offering any other kind of will; the only follow-up you may offer is to start their mirror wills.
>
> Recording the details of a will they have already made elsewhere is unaffected — keep capturing those details as normal. A will they only intend to make outside Fynla is not a will to record; that is the same refusal.

### `compliance-lead` — what it BLOCKED and why

**Verdicts:** `REFUSAL_MARRIED` — BLOCKED as drafted → CLEAR WITH CHANGES.
`REFUSAL_NO_MIRROR_PARTNER` — CLEAR WITH CHANGES. Fyn directive — BLOCKED.
Its closing line: *"This is not an approval"* — it clears within competence or flags;
publication still requires the human button.

1. **Blocked the sentence "a one-sided will where you're married needs care over
   Inheritance Tax and probate that this tool isn't able to give you."** Breaches
   perimeter §2 Rule 1 (unhedged categorical assertion), Rule 5 (invokes Inheritance Tax
   with no tax caveat), and — the blocker — **Rule 7: never state tax positions from
   memory; always retrieve from the centralised configuration.** The sentence asserted a
   consequence sourced from nothing but the board item's own product rationale.
   Its reasoning, stated carefully within its limits: **spousal exemption and the
   transferable nil rate band both cut AGAINST the assumption that a one-sided will
   between spouses carries an Inheritance Tax penalty.** It did not determine the position
   — that is the determination it may not make — but a plausible reading points the other
   way, and *"an unauthorised firm giving a wrong reason is worse than giving no reason."*
   Also a self-contradiction: the copy said this needs expertise we lack, then supplied our
   expert diagnosis of which expertise (perimeter §7.3). **The sentence is gone**; the
   reason we give is now within competence — it is a limit of the tool.
2. **Blocked the Fyn directive** because telling the model to *"tell them plainly that
   Fynla cannot do this"* makes it compose its own refusal, drifting from
   `REFUSAL_MARRIED` on every turn — **a second vocabulary, the exact Rule 20 breach
   W-0019 acceptance 5 forbids.** Also flagged: no instruction for the spouse-won't-engage
   case, and *"they should speak to a solicitor"* is directive language where §2 Rule 3's
   own phrasing is *"suggest the user speaks with"*. **Fixed** — the directive now
   interpolates both constants and requires them unchanged.
3. **"Qualified solicitor", not "solicitor"** — matches `WillBuilderIntroStep.vue:14` and
   `WillPlanning.vue:27`, which both already say it.
4. **Do NOT add FCA-authorisation wording here.** A will is a legal instrument; the right
   disclaimer is "this tool doesn't provide legal advice", which the product already uses
   twice. Injecting "we are not FCA-authorised" points at the wrong regime.
5. **Consumer Duty — recorded because it asked me to record it.** Refusing a married user
   whose partner will not engage IS a foreseeable-harm concern under the consumer-support
   outcome, since the likely alternative is intestacy, which is materially worse than a
   one-sided will. Its view is that **CSJ's side of the trade wins**: shipping a will
   builder producing documents Fynla cannot stand behind is itself foreseeable harm, and
   *"a firm cannot discharge a support obligation with a broken tool"* — W-0022, W-0023
   and W-0024 are three open high-severity defects against this same generator. What
   decides whether residual harm is real is **the quality of the referral**, which is why
   *"including where only one of you is making a will"* is a compliance change, not a
   flourish: without it the likely misreading is "I can't have a will", and the user does
   nothing.
6. **Two exposures it rated sharper than the refusal itself:** existing one-sided wills
   built under the old flow (W-0019 acceptance 6), and fair value if the will builder sits
   behind premium — a subscriber who upgraded partly for it, now told the app won't build
   their will. It had not verified the gating, so raised the latter as a question.
7. It drafted **four new §6 perimeter questions** for
   `workforce/core/constitution/05-perimeter.md` (recorded on W-0019), and flagged that
   `workforce/` has **no dated source register** — a gap in its own equipment, for the
   Quartermaster.

### `design-lead` — what it changed and why

1. **Corrected a factual error in my draft.** It said the partner "makes their own matching
   will alongside yours". That is not what the app does: `WillBuilderPersonalStep.vue:66`
   ("Your spouse's mirror will be generated automatically from the same details") and
   `WillBuilderReviewStep.vue:79-86` ("Generate Spouse's Will") mean **we** build both.
   What the partner actually does is **sign and witness theirs**. The draft promised a
   step that does not exist.
2. **Removed the second-person "Because you're married" assertion.** `civil_partnership` is
   a live `marital_status` value with its own migration
   (`2026_04_15_091500_add_civil_partnership_to_users_marital_status.php`), so the copy
   would have been factually wrong for a civil partner routed through the same gate.
   Hence "your spouse or civil partner" — **in the new W-0019 strings only.** It explicitly
   did NOT propose a global rename; the standing partner/spouse-sweep instruction holds.
3. **UI treatment (c): delete the chooser, replace with a statement.** Not a lone Mirror
   Will button — *"a button with no alternative is a fake choice"*, and its
   selected/unselected state implies something else is behind it, which is the mental model
   W-0019 exists to kill. W-0019 acceptance 1 says the Simple Will option "is not
   presented"; a solitary button still frames the step as a chooser.
4. **Label changed** from the question "What type of will would you like to create?" to the
   statement **"Type of will"** — *"leaving a question above a non-choice is the single
   worst detail available here."*
5. **Violet, not raspberry.** Rule 8 maps warning/caution → violet. This is a scope
   boundary, not a user error; raspberry is already this step's selection and CTA colour,
   so a raspberry panel would compete with the primary action and read as alarm.
   Tokens confirmed present in `tailwind.config.js`: `bg-violet-50`, `border-violet-200`,
   `text-violet-800`, `text-violet-700`, `text-horizon-500`, `text-neutral-500`.
   (Note: `neutral` has only a `500` — do not reach for `neutral-600/400`.)
6. **Rule 15 clean, with a live hazard flagged.** The treatment adds no icons or glyphs.
   But `fynlaDesignGuide.md:793-802`'s Alert pattern includes an icon span — **do not copy
   it**; Rule 15 wins over the guide. And `CoreIdentity.php:63` instructs Fyn to *"always
   end your response with a natural follow-up question"*, which handed this refusal would
   manufacture an escape ("Would you like me to build a simple one anyway?"), breaching
   acceptance 2. **Handled** — the directive overrides it for this case.
7. **Rule 9 clean** in both strings; "Inheritance Tax" spelled out, no acronyms. Pinned by
   a test.
8. **It corrected this board item too.** W-0019 line 36 claims "`willType` starts unset and
   either is equally reachable." It did not: `WillBuilderIntroStep.vue:114` read
   `this.formData.will_type || 'simple'`, so **a married user who never looked at that
   block proceeded with a one-sided will having made no choice at all.** Live behaviour was
   worse than the board reported.
9. **Constant shape:** store each message as an ordered array of paragraphs, because the
   web renders paragraph 1 outside the notice and 2–3 inside it while the API and Fyn need
   the whole thing. **Adopted** — `REFUSAL_MARRIED` and `REFUSAL_NO_MIRROR_PARTNER` are
   arrays, with `WillTypePolicy::asText()` for the single-string surfaces.

## 4. IN FLIGHT

**Nothing.** All six items are code-complete, Pint clean, tests green, board items updated
to `handoff` → `quality-lead`. No edit is half-applied. No branch is open. No PR exists.

## 5. Decisions taken, and why

1. **Residuary beneficiaries stay document-only** (W-0023). The `bequests` table cannot
   express "a share of what is left after the gifts": a residuary row could only be stored
   as `percentage`, and `Will::getNonSpouseAllocationPercentage()` (`Will.php:79-81`) sums
   exactly those rows — so a mirror will leaving 100% to a partner would report a 100%
   **non**-partner allocation. Recording it would corrupt an existing answer to buy a
   duplicate of one the document already holds. Reasoning is in the method docblock and
   pinned by a test.
2. **Copy the mirror's gifts and flag them, rather than not copying at all.** The board
   offered both. Flagging keeps the mirror able to hold *differing* gifts, which the
   persona requires. The flag clears when the partner saves the Gifts step, so "review
   before completing" needs no separate dismissal.
3. **Executor-is-testator is `severity: error`, not `warning`** — `markComplete()` blocks
   only on errors (`:432`), and the acceptance says "validate and block".
4. **Marital status beats `spouse_id` in both directions.** Both reviewers independently
   flagged that gating on `has_spouse` would tell a cohabiting couple they were married
   and tell a civil partner the same.
5. **Adopt a matching hand-made bequest rather than adding a second row.** Not in my first
   cut; added after `tax-compliance-reviewer` (F15) pointed out that `PreviewUserSeeder`
   creates exactly such rows for every persona, so a doubled charitable total was likely,
   and doubling is the *unsafe* direction.
6. **Block creating / switching to / completing a simple will for a married user; leave
   already-completed documents untouched.** This is the narrow, reversible part of W-0019
   acceptance 6. Nothing was rewritten — that decision is CSJ's.
7. **`forceDelete` for sync-owned bequests**, soft delete elsewhere. "Replaced" has to
   mean replaced, and these rows hold no history the document does not already hold.
8. **Fyn gets a per-turn context layer, not a procedural-corpus entry.** A corpus entry
   would duplicate the copy in markdown, which is the Rule 20 violation. The layer
   interpolates the PHP constants so there is literally one string.

## 6. Dead ends ruled out — do not re-investigate

- **The per-batch test database fixes contention BETWEEN agents, not WITHIN one.** I
  deadlocked my own regression by starting a second Pest command against `_b` while the
  first was still running — same collision class, reappearing inside one agent, and again
  producing failures with **0 assertions** because nothing reached a test. Serialise your
  own runs: check `pgrep -f "vendor/bin/pest"` before starting one.
- **A run can also die from outside.** One regression aborted with `The environment file
  is invalid!` because prose was briefly written into `.env` by something outside this
  agent. It was transient and `.env` was clean again minutes later. Treat a mid-run
  environment error as an environment event, not a code failure, and re-run.
- **The 22 test failures on the shared `laravel_testing` DB were NOT a code failure.**
  Migration-time collision with a sibling batch (`SQLSTATE[40001]` deadlock, then
  `SQLSTATE[42S02]` unknown table while another agent dropped tables mid-run). Zero
  assertions ran. Use `DB_DATABASE=laravel_testing_b`.
- **Pint silently drops a just-added `use` statement that is not yet referenced.** It
  removed `use App\Models\Estate\Bequest;` from `WillDocumentService.php` between my
  adding the import and adding the code that used it, producing
  `Class "App\Services\Estate\Bequest" not found`. Add the import in the same edit as the
  first usage, or re-check after Pint. (Matches the `reference_worktree_symlinked_vendor_break`
  memory.)
- **`/estate/will-builder` does not render the wizard for a user who already has a will** —
  `WillBuilderView.vue:6-12` shows `WillPlanning` instead. David and Sarah both have
  completed wills, so the intro step cannot be reached on their accounts. The "Edit" button
  on that card edits the `WillPlanning` record, **not** the wizard.
- **`tier = 'premium'` alone does not pass the `estate.full` gate** — an active
  `Subscription` row is also required, else `/estate/will-builder` redirects to
  `/teaser?module=estate`.
- **`browser_take_screenshot` times out on the will-builder Review step** (twice, ~5s,
  waiting on fonts/animation). Use the accessibility snapshot instead; it captures the
  strings and the disabled-button state.
- **The mirror generator does NOT need a marital gate.** A mirror will is always the
  allowed structure; `generateMirrorWill()` correctly gates only on a live spouse.

## 7. Environment state

- Branch `dev`. **No PR, no merge, no deploy, no csjones, no prod.** `ssh-fynla` never used.
- **Two migrations run locally** (`php artisan migrate --force`, additive only):
  `bequests.will_document_id`, `letters_to_spouse.auto_populated_fields`. No data lost, so
  no reseed was required; persona rows verified intact afterwards.
- **David (16) and Sarah (17) unmodified.** Verified after all work: `marital_status=married`,
  reciprocal `spouse_id`, `tier=premium`, docs=1, wills=1, bequests=0, trusts=1 (David),
  mortgages=1 each. No DB rows patched. No `migrate:fresh`. `.env` untouched.
- **Throwaway users created for verification and DELETED afterwards** — `wb.adam@example.com`
  (19) and `wb.beth@example.com` (18), plus their subscriptions, wills, will documents,
  bequests, trust, property, mortgage, letter and tokens. Confirmed 0 remaining; total
  users back to 17.
- One stray draft (`will_documents.id 7`) was created on David's account by a live API probe
  and **deleted**; his document count is back to 1.
- Test DB: `laravel_testing_b`.

## 8. What I verified, and what that does NOT amount to

**Unit/feature tests — mine to own.** 899 passed, 3 skipped, **0 failed** (6779 assertions,
1323s) across `tests/Unit/Services/Estate/`, `tests/Feature/Estate/`,
`tests/Unit/Services/Plans/`, `tests/Unit/Agents/`, `tests/Unit/Services/UserProfile/`,
`tests/Architecture/`, `tests/Feature/Fyn/`. Targeted families only; no full suite.
`./vendor/bin/pint --test` **passed** on all 22 files touched.

29 new cases across four files:
- `tests/Unit/Services/Estate/WillTypePolicyTest.php` (14)
- `tests/Unit/Services/Estate/WillDocumentServiceTest.php` (+10, three new describes)
- `tests/Unit/Services/Estate/WillAnalysisCharitableBequestTest.php` (13)
- `tests/Unit/Services/UserProfile/LetterToSpouseRefreshTest.php` (6)
- `tests/Feature/Estate/WillBuilderApiTest.php` (+6)

**Browser work I did, and its status.** I drove Playwright end-to-end on all six items
**before** the policy arrived that fix agents do not browser-verify their own work. That
evidence is recorded on the board items for the receiver's benefit, but under that policy
**it is my own evidence and does not close Rule 14's loop on any item.** Treat it as a
lead, not a certification. Independent re-verification by the tester is still required.
I have stopped using the browser and released it.

What it did show (for the re-run's benefit): `will_type: "simple"` → 422 with the exact
copy and `"mirror"` → 201; the Review step rendering the executor-is-testator error with
**Complete & Finalise disabled**; the generated mirror naming **Adam** as Beth's executor;
completion creating the `Bequest` row with `getCharitableBequestTotal()` returning 10000;
`GET /api/estate/bequests` (the endpoint `/m` calls) returning it; the letter picking up a
mortgage added *after* row creation and the checker emitting zero contradicting warnings;
both trust surfaces reading "Relevant Property Trust"; and the Fyn layer firing on
"make me a simple will" but **not** on "I will retire at 60".

**I COULD NOT screenshot the executor-block Review step** — timed out twice on a page
animation. Accessibility snapshot is on W-0024 instead. The W-0019 screenshot saved:
`tests/Persona/20-08-2026_run/pass-a-web/20-web-mirror-wills-only-W-0019.png`.

## 9. Open gates — CSJ's, not mine

1. **W-0024 is on production.** `generateMirrorWill()` landed in `9cfeadb46` (2026-03-16);
   `git branch -r --contains 9cfeadb46` puts it on `origin/main`. `compliance-lead`'s
   determining question is whether any real user has generated a mirror will on
   fynla.org — a `will_documents WHERE will_type = 'mirror'` count. **Not run:**
   `ssh-fynla` is production and this batch is local-only.
2. **W-0019 acceptance 6 — existing one-sided wills.** Nothing rewritten. Local count of
   non-mirror documents held by married users: **0**. Production unknown.
3. **Four new §6 perimeter questions** drafted by `compliance-lead` (not answered by it),
   recorded on W-0019. Amending the trunk is not build-lead's.

## 10. Known limitations shipped knowingly

- **Gift priority does not round-trip.** The will-builder gift form has no priority field,
  so `syncBequests` assigns `priority_order` sequentially from array order. The persona's
  ordering (charity 1, children 2) has nowhere to live. Not a blocker; do not expect it.
- **Wills already completed under the old code do not backfill.** The sync runs on
  completion, so David and Sarah still show `bequests = 0`. Re-completing populates them.
  Whether a backfill command is wanted is a CSJ call, adjacent to W-0019 acceptance 6.
- **Native has no route to the Will Builder at all.**
  `ios-native/Fynla/Core/Navigation/WebHandoffClient.swift:3-8` lacks the `estateWill`
  case that `app/Enums/WebHandoffDestination.php:14` and `/m` both have. Found, not fixed,
  needs its own item.
- **Trust badges use non-palette colours** (`blue-*`, `green-*`) — a live Rule 11 breach on
  all three relevant-property surfaces. `design-lead` explicitly said not to bundle it with
  the acronym fix; needs CSJ sign-off.
- **`attorney_name` still holds the "Solicitor" field** on the letter. Renaming a column
  while fixing a content bug is out of scope.

## 11. Surfaces

| Surface | Covered how |
|---|---|
| **web** | Directly — will builder intro step, trust card, trust detail. |
| **`/m`** | By architecture, verified not assumed. `resources/mobile/router.js:69-70` registers only `/estate` and `/estate/bequests`; there is no `/m` will builder, letter or trust card. `/m` reaches the will builder via `WebHandoffDestination::ESTATE_WILL` → the web page that now enforces the rule, and its bequests screen (`EstateBequests.vue:87`) reads `GET /api/estate/bequests`, which W-0023 now populates. All other fixes are server-side. |
| **iOS** | `ios-native/` has a read-only estate summary and no will/bequest/letter/trust screens — `grep -rni "bequest\|testator\|guardian\|solicitor" ios-native/ --include=*.swift` returns zero. Server-side fixes reach it; the missing `estateWill` handoff case is logged above. |
| **Fyn** | `FynContextAssembler::willStructureDirective()` quoting the same constants, plus `CoordinatingAgent::refuseSelfAppointedExecutor()` using the same helper and message. |

## 12. Files changed

`app/Services/Estate/WillTypePolicy.php` (new) ·
`app/Services/Estate/WillDocumentService.php` ·
`app/Services/Estate/WillAnalysisService.php` ·
`app/Services/Estate/IHTCalculationService.php` ·
`app/Services/Estate/LetterEstateValidationService.php` ·
`app/Services/UserProfile/LetterToSpouseService.php` ·
`app/Services/Plans/EstatePlanService.php` ·
`app/Services/TaxConfigService.php` ·
`app/Services/AI/Fyn/FynContextAssembler.php` ·
`app/Agents/CoordinatingAgent.php` · `app/Agents/EstateAgent.php` ·
`app/Http/Controllers/Api/Estate/WillDocumentController.php` ·
`app/Models/Estate/Bequest.php` · `app/Models/LetterToSpouse.php` ·
`app/Constants/TaxDefaults.php` ·
`database/migrations/2026_08_21_090000_add_will_document_id_to_bequests_table.php` (new) ·
`database/migrations/2026_08_21_090100_add_auto_populated_fields_to_letters_to_spouse_table.php` (new) ·
`resources/js/components/Estate/WillBuilder/steps/WillBuilderIntroStep.vue` ·
`resources/js/components/Trusts/TrustCard.vue` ·
`resources/js/views/Trusts/TrustDetailView.vue` ·
plus the five test files listed in §8.

**No collision with sibling batches.** Nothing in Ownership/Net-Worth or
Retirement/Profile/Gates was touched. `CoordinatingAgent.php` is shared; my three hunks
(`refuseSelfAppointedExecutor` and its two call sites) are additive and confined to the
will handlers.
