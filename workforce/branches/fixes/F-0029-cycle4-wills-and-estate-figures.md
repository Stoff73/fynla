---
id: F-0029
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m]
consistency_checked: 2026-08-23T02:20:00Z
status: active
---


**Rule 22 handover for this agent:** [`HANDOVER-fix-cycle4-wills-2026-08-23.md`](HANDOVER-fix-cycle4-wills-2026-08-23.md) — what is not on the board, the branches this persona cannot reach, and the dead ends.
# F-0029 — Cycle 4: wills, and the figures a will states

**Agent:** build-lead (`fix-cycle4-wills`) · **Branch:** `dev` (shared working tree)
**Defects:** D-13, D-14, D-15 · **ID block:** W-0391 – W-0400 (used: W-0391 – W-0397)
**Number and ID block issued by team-lead.** F-0028 was taken between the dispatch
and the first write; this is F-0029.

**Predecessors, read before touching anything here:**
`F-0003-batch-b-estate-wills.md` (W-0024, the mirror generator fix) ·
`F-0019-cycle2-ownership-applied-one-side-only.md` (the reach / fraction
vocabulary) · board items `W-0024`, `W-0154`, `W-0188`.

---

## 1. The principle

**A will is a document about ONE person. Every figure on a will screen must be
that person's.**

All three defects are one disease in two organs. Two of them put a *household*
answer where a *person* was asked about; the third put no answer at all where a
person was named.

| Defect | What was shown | What was asked |
|---|---|---|
| D-15 | the combined second-death household estate | this testator's estate |
| D-13 | the primary's executors | this testator's executors |
| D-14 | nothing | this legacy's legatee |

**None of the three needed a new mechanism.** D-15 routed a screen onto a figure
the same response already carried. D-13 was a data repair behind a fix that had
already landed. D-14 was a key name. The count of mechanisms answering "what is
this user's net estate" went **down by nothing and up by nothing** — the will
screen simply stopped reading a different question's answer.

---

## 2. Prior art

Checked 2026-08-23 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| D-15 per-user estate | `IHTCalculationService::calculate()` already returns `user_net_estate` (`:307`), published at `calculation.user_net_estate` on the very response the will page calls | **route** |
| D-13 backfill | `app/Console/Commands/BackfillWillBequests.php` (`estate:backfill-bequests`) — the house pattern for a dry-run-first estate repair | **extend** — same pattern, new command, one repair method on `WillDocumentService` reusing `isSameParty()` |
| D-14 resolution | none needed — `resources/mobile/views/modules/EstateBequests.vue:26` already reads the correct key | **route** (web onto the key `/m` was always using) |
| D-14 storage | `Bequest::isCharitable()` — the one home for "is this charitable", with the name list inline and no other caller | **extend** — list extracted to `nameLooksCharitable()`, one home, two new callers |
| the executor-name string | built inline in `markComplete()`, no other producer | **extend** — extracted to `WillDocumentService::executorNameFor()` so the repair cannot drift from completion |

---

## 3. What was measured, and what it settled

Every figure below was read on 2026-08-23 through the real code paths, with
`estate_analysis_16` and `estate_analysis_17` cleared before each read (W-0381).

### There are FOUR answers to "net estate", not two

| Mechanism | David (16) | Sarah (17) | Consumer |
|---|---|---|---|
| `IHTCalculationService` → `total_net_estate` | 1,728,780 | 1,728,780 | **`WillPlanning.vue` — the defect** |
| `IHTCalculationService` → `user_net_estate` | **989,500** | **739,280** | published, nobody read it |
| `NetWorthAnalyzer::generateSummary` → `net_worth` | **989,500** | **739,280** | `/m` estate screen, via `/api/estate/net-worth` |
| `EstateAgent` → `data.summary.net_estate` | 1,489,500 | 739,280 | `/api/v1/mobile/dashboard` estate card |

**They are not four implementations of one question — they are two questions.**
Rows 2 and 3 agree to the pound and both exclude assets flagged
`is_iht_exempt`. Row 4 does not filter that flag, so it carries David's
**£500,000** of defined contribution pensions. Row 1 is the combined second-death
estate, which is a correct and necessary figure in its own place.

**Deleting either definition would break correctness**: without row 2 the
Inheritance Tax engine would tax pensions. So the genuine single-mechanism defect
is narrower than "two net estates" — it is one missing filter in row 4, raised as
**W-0397**.

**team-lead withdrew the "delete the other" instruction on measurement, released
the fence mid-batch, and W-0397 is now FIXED here.** All three per-user
mechanisms read 989,500 / 739,280.

**And row 4 turned out to have no constituency at all.** Two of its five readers
take it as `net_worth` (`DashboardAggregator:407`, `CoordinatingAgent:839`),
which looked like a reason to keep the larger figure — until measurement:
`NetWorthAnalyzer`, the module that actually answers "what am I worth", **already
excluded exempt assets**. So £1,489,500 was not "everything you own" as any other
mechanism defines it. It was a fourth number nobody else produced. Enumerating
the readers before changing the field is what turned a plausible objection into a
settled answer.

### The £12,000 the acceptance criteria are out by

The dispatch gives David £1,477,500 and a household of £2,216,780. Today the same
mechanisms give £1,489,500 and £2,228,780. The household data moved by £12,000 on
David's side after the tester's reading — `savings_accounts` 53/54,
`investment_accounts` 26/27, `properties` 9/19/20 and `dc_pensions` 9 were all
written after 2026-08-21 15:00 in this shared tree.

**So the acceptance figures are stale, and one of them names the wrong
mechanism.** £1,477,500 is the `EstateAgent` figure — pensions included — at the
older data state. Under the consolidated answer **David's will page reads
£989,500**.

**Raised with team-lead before implementing; ruled on 2026-08-23 and the
acceptance corrected to £989,500 / £739,280.** The ruling's reasoning is the
financial-planning fact, not a preference: a pension pot passes by nomination
outside the will, so a page headed "100% to spouse (£X)" that counts it
overstates what the instrument actually gives her. A will disposes of the estate;
it does not dispose of a death-benefit nomination.

### Sarah alone cannot settle D-15 — stated, not hidden

Sarah owns nothing flagged `is_iht_exempt`, so **£739,280 is her figure under
both the correct and the broken reading of the per-user payload.** Every
assertion in this batch is therefore made on BOTH spouses or on the pair. This is
the Collision variant of `tests/CLAUDE.md` §4 occurring in live data rather than
in a fixture.

---

## 4. W-0024 — the verdict, with the evidence the dispatch asked for

**W-0024 STAYS FIXED. `wills.id = 12` was pre-fix residue.**

**The fresh generation** (`WillDocumentService::generateMirrorWill()`, live dev
database, throwaway married pair, rolled back — no rows kept, verified 0
remaining):

```
FRESH MIRROR for spouse (Corin Probe)
  executors : [{"name":"Wren Probe",...,"relationship":"Spouse"},
               {"name":"Barclays Wealth",...,"relationship":"Professional Executor"}]
  guardians : [{"name":"Wren Probe","relationship":"Spouse"}]
  gifts     : [{...,"beneficiary_name":"Cancer Research UK","copied_from_partner":true}]
  residuary : [{"beneficiary_name":"Wren Probe",...}]
```

Every party swapped. Nobody is their own executor.

**Three independent supports that document 6 predates the fix:**

1. It was generated **2026-08-21 08:59:21**; W-0024 was claimed at **09:40**.
2. Its `specific_gifts` carry **no `copied_from_partner` marker**. The post-fix
   generator always writes one (`markGiftsAsCopied()`), and the fresh generation
   above does.
3. The residuary was already correct on both sides, which is the pre-fix
   behaviour — `swapResiduaryForMirror` predates W-0024.

**Correcting the dispatch's own supporting evidence.** The dispatch reads Sarah's
correct charity (British Heart Foundation rather than David's Cancer Research UK)
as proof that part of W-0024 landed. **It is not.** The post-fix generator would
have produced *Cancer Research UK* carrying `copied_from_partner: true`.
Document 6 holds British Heart Foundation and **no** marker. That is the tester's
own manual edit — recorded in W-0024's working notes ("I changed it to British
Heart Foundation and the document regenerated correctly") and corroborated by the
document's `updated_at` 09:03:33 against its `created_at` 08:59:21. The charity
being right is evidence the tester edited it, not evidence of a partial fix.

### But W-0024's fix had a hole, and it is fixed here (W-0396)

Found by writing the repair's tests, not by the persona. The generator matched
each partner on **one** spelling, and built the two sides from different sources:
the primary's from the will's `testator_full_name`, the partner's from
first + middle + surname off the profile. **A partner with a middle name recorded
but named in the will without it matched neither**, so nothing was swapped and
W-0024's exact symptom returned for that household.

**W-0024's own tests could not see it**, and neither could this persona: both
give the partner no middle name, so the right answer and the wrong answer are the
same string. Collision again, in a second place, in the same batch.

---

## 5. Status — ALL THREE DONE

| Item | Outcome | One home |
|---|---|---|
| **W-0391** D-15 · the will page states a household aggregate as one testator's estate | **DONE** · `handoff` → quality-lead | `IHTCalculationService::calculate()` → `calculation.user_net_estate`, already published |
| **W-0393** D-14 · a specific gift renders with no recipient | **DONE** · `handoff` → quality-lead | `beneficiary_name` — the key every write path already produces and `/m` already read |
| **W-0394** D-14 storage · `beneficiary_type` never written, both charities stored as individuals | **DONE** · `handoff` → quality-lead | `Bequest::nameLooksCharitable()` / `inferBeneficiaryType()` |
| **W-0395** D-13 · pre-W-0024 mirror wills name their own testator | **DONE** · `handoff` → quality-lead | `WillDocumentService::repairSelfNamedParties()` + `estate:backfill-mirror-parties` |
| **W-0396** the mirror generator matched one spelling of a name | **DONE** · `handoff` → quality-lead | `WillDocumentService::nameVariants()` |
| **W-0397** `EstateAgent`'s estate summary does not exclude Inheritance-Tax-exempt assets | **DONE** · `handoff` → quality-lead | `EstateAgent::buildAssetSummary()` |
| **W-0392** the per-user estate excludes Business Property Relief assets | **RAISED, not fixed** — a product call, see below |

### Files changed

| File | Change |
|---|---|
| `resources/js/components/Estate/WillPlanning.vue` | reads `calculation.user_net_estate`; gift recipient key; the spouse line names its base |
| `app/Http/Controllers/Api/Estate/WillController.php` | `classifyBeneficiary()` on create and update |
| `app/Http/Requests/Estate/StoreBequestRequest.php` · `UpdateBequestRequest.php` | `beneficiary_type`, `charity_registration_number` |
| `app/Models/Estate/Bequest.php` | name list extracted to `nameLooksCharitable()`; `inferBeneficiaryType()` |
| `app/Services/Estate/WillDocumentService.php` | `nameVariants()`, `executorNameFor()`, `namesItsOwnTestator()`, `repairSelfNamedParties()`, `partnerNameAsThisWillWritesIt()`; `swapPartiesForMirror()` takes candidate lists; `syncBequests()` classifies |
| `app/Console/Commands/BackfillMirrorWillParties.php` | **NEW** — `estate:backfill-mirror-parties` |
| `app/Agents/EstateAgent.php` | `buildAssetSummary()` rejects `is_iht_exempt` assets (W-0397, after team-lead released the fence) |

**Three files outside the dispatch's exclusive scope were edited, deliberately
and declared:** `WillDocumentService.php`, `Bequest.php` and — after team-lead
released the fence mid-batch — `EstateAgent.php`. None is on the fenced list as
of the edit. Both were unavoidable under Rule 20 — the repair shares its name comparison
with the generator, and the storage fix has two write paths (`WillController` and
the will builder's `syncBequests`), so putting the classification in only one of
them would have been the copies-in-lockstep failure Rule 20 exists to stop.

### Data repaired on the local dev database

- `estate:backfill-mirror-parties --force` — dry-run first over **every** will
  document in the database: **one** match, `will_documents.id = 6`, no false
  positives on the seven correct documents. Applied. `wills.id = 12`
  `executor_name` is now `David Jones, Barclays Wealth`; David's document 5 and
  `wills.id = 11` verified **unchanged**.
- `estate:backfill-bequests --user=16 --force` and `--user=17 --force` — re-synced
  through the existing command so the two charitable legacies carry
  `beneficiary_type = charity`. Charitable total £10,000 → £10,000 and the
  Inheritance Tax rate 40% → 40% on both: **no user-visible figure moved**, which
  is the correct outcome for a classification that `isCharitable()` was already
  deriving from the name.

### Live verification (HTTP, both accounts, caches cleared before each read)

```
user 16  calculation.user_net_estate  = 989500     will_info.executor_name = Sarah Jones, Barclays Wealth
         calculation.total_net_estate = 1728780    bequest: Cancer Research UK / charity
user 17  calculation.user_net_estate  = 739280     will_info.executor_name = David Jones, Barclays Wealth
         calculation.total_net_estate = 1728780    bequest: British Heart Foundation / charity
```

**All three per-user mechanisms, after W-0397, caches cleared:**

```
user 16   mobile dashboard 989,500 | will page 989,500 | /m estate screen 989,500
user 17   mobile dashboard 739,280 | will page 739,280 | /m estate screen 739,280
```

David read 1,489,500 on the dashboard before W-0397.

Will documents 5 and 6 both return `specific_gifts` keyed on `beneficiary_name`,
and executors `["Sarah Jones","Barclays Wealth"]` / `["David Jones","Barclays
Wealth"]` respectively.

### The rendered page — done 2026-08-23

**The tab was established as authenticated as NOBODY on arrival** (both token
stores empty), which is exactly the state team-lead warned about. Identity
confirmed with `GET /api/auth/user` on the token in use before reading anything,
per account. Caches cleared by hand before each read.

Read verbatim off `/estate/will-builder`:

| | David (16) | Sarah (17) |
|---|---|---|
| Spouse line | `100% of your own estate to your spouse (£989,500)` | `100% of your own estate to your spouse (£739,280)` |
| Executors | Sarah Jones · Barclays Wealth | **David Jones** · Barclays Wealth |
| Specific Gifts | `£10,000 to Cancer Research UK` | `£10,000 to British Heart Foundation` |
| Residuary | Sarah Jones — 100% | David Jones — 100% |

The two figures **differ**, each is its owner's, and **neither £1,728,780 nor
£1,716,780 appears anywhere on either page**. Nobody is their own executor. Every
gift names its recipient. Screenshots `150-` and `151-` in
`tests/Persona/20-08-2026_run/pass-a-web/`.

**Rule 14's loop is closed on all three defects.**

### An environment condition worth recording, because it cost twenty minutes

**Playwright's synthetic typing did not reach Vue in this session**, and the
first read of that was going to be "the login form is broken". It is not.
`fill()` and `pressSequentially()` both set the DOM `value` correctly — the
inputs showed the right text — but `LoginView`'s reactive `form.email` and
`form.password` stayed **empty**, so `handleSubmit` fired no request at all.

Diagnosed rather than worked around: dispatching a manual `input` event updated
Vue's state instantly, **so `v-model` is fine and the page is not defective.**
The cause is that the page was **remounting mid-interaction** — static request
count went 125 → 251 → 252 with no navigation. **Vite HMR, reacting to other
agents editing `resources/js/` in this shared working tree**, was resetting the
component between the keystrokes and the click.

The fix is to make fill-and-click atomic in one evaluation so no reload can
intervene. **Recorded because the next agent to drive a form in this tree will
hit it, and the natural conclusion — "login is broken" — is wrong.** It is also
why `public/hot`'s age is not a sufficient check: Vite WAS running and serving.

---

## 6. The children's bequests — missing data, not a defect

**Stated plainly, as instructed, rather than manufactured into a defect.**

The persona gives each will two percentage bequests (William 50%, Charlotte 50%)
plus the charitable legacy. Only the charity exists in `bequests` for either
account. Two findings settle it:

1. **The save path does not drop percentage bequests.** Proven, not assumed:
   `tests/Feature/Estate/BequestBeneficiaryTypeTest.php` posts a 60% and a 40%
   bequest through `POST /api/estate/bequests`, reads both back through
   `GET /api/estate/bequests`, and asserts each percentage, each name, and each
   priority order. Deliberately 60/40 — at 50/50 a path that wrote one row twice
   or dropped one would be indistinguishable from a correct one.
2. **The children were never entered as bequests.** Both will documents record
   them in the residuary as a *substitution* beneficiary — free text, "William
   Jones and Charlotte Jones in equal shares, held in trust until age 25". That
   text is deliberately document-only: `syncBequests()`'s docblock records why,
   and the reason is sound — the `bequests` table cannot express "a share of what
   is left after the gifts", and storing it as `percentage` would corrupt
   `Will::getNonSpouseAllocationPercentage()`, making a mirror will that leaves
   everything to a partner report a 100% NON-partner allocation.

**So the tester saw a real absence with two causes and could not separate them.**
The data entry is unfinished, AND the residuary substitution the persona relies
on is invisible to every consumer of `bequests` — including `/m`'s bequests
screen. The second is a deliberate design limit, not a bug, but it is why the
household reads as though the children are not provided for.

---

## 7. Rule 19 — `/m`

- **D-14: `/m` was never affected.** `resources/mobile/views/modules/
  EstateBequests.vue:26` already reads `beneficiary_name`. The web screen was the
  outlier. **No `/m` file changed, so no rebuild of `public/m-build/` is
  needed** — the storage half reaches `/m` through the shared backend.
- **D-15: `/m` has no counterpart card.** The "100% to spouse (£X)" line is
  web-only. `/m`'s estate screen shows `net_worth` from
  `/api/estate/net-worth`, which is **989,500 / 739,280** — the same figure the
  will page now reads. Locked by a test.
- **D-13: reaches `/m` for free** — the repaired `wills.executor_name` and
  document are shared backend state.

---

## 8. Tests

| File | Cases | Guards |
|---|---|---|
| `resources/js/components/__tests__/Estate/WillPlanning.spec.js` | 8 | both spouses' figures differ and each is its own; the household figure appears on neither; the percentage applies to the right base; no fallback can reach a household aggregate; gifts name their beneficiary |
| `tests/Feature/Estate/IHTPerUserNetEstateTest.php` | 7 | the engine keeps per-user and household distinct; each spouse's own estate is the other's spouse estate; agrees with the endpoint `/m` reads; **all three mechanisms agree for a user holding a non-zero exempt asset**; the breakdown reconciles to its own total |
| `tests/Feature/Estate/BequestBeneficiaryTypeTest.php` | 7 | charity and person stored differently in one household; an explicit type beats the name; a charity the name list cannot know; rename reclassifies, an amount edit does not; percentage bequests round-trip |
| `tests/Unit/Services/Estate/MirrorWillPartyRepairTest.php` | 13 | the repair fixes the broken side, leaves the correct side alone, rewrites the stored executor name, is idempotent, refuses an unmarried testator; the generator recognises every recorded spelling |

**Mutation-tested in both directions.** Each defect was restored and the suite
re-run:

| Mutation | Went red | Stayed green |
|---|---|---|
| will page reads `iht_summary.current.net_estate` again | 4 W-0391 cases | all 3 W-0393 cases |
| gift template reads `gift.recipient` again | 3 W-0393 cases | all 5 W-0391 cases |
| `beneficiary_type` removed from the request rules | the 2 explicit-type cases | the name-inference case |
| `classifyBeneficiary()` removed from create | the name-inference case | the 2 explicit-type cases |
| the repair's direction reversed | 8, including the direction guard | the selector's own case |
| the name-variant candidates reduced to one spelling | 3, including both W-0396 cases | **the whole of `WillDocumentServiceTest` — 38 cases** |
| the exempt-asset filter removed from `buildAssetSummary()` | the 2 exempt-asset cases | the no-exempt-asset regression guard |

**That last row is the finding, not a formality.** W-0024's entire suite is green
against a generator that still produces a will appointing its own testator. It
proves the Collision is structural here, not incidental.

**Decoy check:** every test named after a class or method was grepped for that
name in its own body. `repairSelfNamedParties` ×11, `nameVariants`,
`generateMirrorWill`, `executorNameFor` ×2, `nameLooksCharitable`,
`isCharitable` ×2, `user_net_estate` ×9 — all resolved and called, none
title-only.

**That last row matters too, and is documented in the test itself.** The
no-exempt-asset guard **cannot** discriminate — it is Sarah's shape, and it
passes whether the filter exists or not. It is kept as a regression check and
labelled as unable to prove anything about the defect, rather than counted as
coverage.

**Suite state:** `DB_DATABASE=laravel_testing_p` — `tests/Unit/Services/Estate`,
`tests/Feature/Estate`, `tests/Unit/Agents/EstateAgentGoalsTest.php` → **414
passed**; with `CreateWillToolTest` and `UserProfileCharitableBequestsTest` →
**412 passed** on the earlier run. `tests/Unit/Services/Plans`,
`tests/Feature/Plans`, `tests/Feature/Dashboard`, `tests/Feature/Mobile`,
`tests/Unit/Services/Mobile` → **291 passed**, run specifically because W-0397
changes a figure those surfaces read. Vitest `WillPlanning.spec.js` → 8 passed.

---

## 9. Raised while working — not fixed, and why

| Item | Why it is not folded in |
|---|---|
| **W-0392** the per-user estate excludes Business Property Relief assets | `is_iht_exempt` is set for pensions **and** for a trading business qualifying for the relief. A pension with a nomination genuinely passes outside the will; a business does not — the relief removes it from the **tax**, not from the **estate**. So the will page understates the estate of any business owner. Invisible on this household (David has no business interest), and it is a product call about what a will screen should count, not a bug with a right answer. |
| `summary.effective_tax_rate` and `summary.iht_liability` are household figures beside a per-user estate | Both read 19.87% / £343,512 for **both** spouses, before and after W-0397 — **observed, not introduced.** That is W-0154 / W-0188's household-versus-individual split, already at `handoff`, and the arithmetic lives in `IHTCalculationService`, which stays fenced. Widening into a tax-reviewed area mid-batch would be scope creep. |
| the residuary substitution beneficiary is invisible to every `bequests` consumer | See §6. Deliberate, documented, and the reason this household reads as though its children are unprovided for. Fixing it means giving the `bequests` table a way to say "a share of the residue", which is a schema decision. |
| `validateDocument()`'s executor-is-testator block still compares one spelling | **Deliberate, and stated in the code.** That check BLOCKS completion: a false positive stops someone finishing their will, and a father and son can share a name where only one has a middle name recorded. The generator and the repair CHOOSE a replacement, and their alternative is a legally incoherent document. Different cost of error, different test. |
| `will_documents.survivorship_days` is NOT NULL with default 28, and `generateMirrorWill()` copies it straight from the primary | Reached only by an in-memory primary document that never round-tripped the database, so it is a test-fixture artefact rather than a live path — `WillBuilderController` always passes a loaded model. Recorded because it is axis 1 of `app/Http/CLAUDE.md`'s rule-vs-schema list and the next person to build a fixture will hit it. |

---

## 10. Raised from the browser pass itself

Two items that the screen showed and the API did not, both filed rather than
fixed:

| Item | Finding |
|---|---|
| **W-0399** the Charitable Bequest card contradicts itself | `/estate` renders *"Your will leaves **£20,000** to charity"* directly above *"Your charitable giving of 0.6% (**£10,000**)"* — **two sentences apart, same legacy, identical on both accounts.** £20,000 is the household total, £10,000 the individual's, and the 0.6% is an individual numerator over a household denominator. The card then tells the user to *"Increase by £112,878"* off the household baseline. Same disease as D-15, a different card, and the figure originates in the **fenced** `IHTCalculationService`. Also carries a Rule 9 acronym violation ("IHT"), reported not fixed — functional before cosmetic. |
| **W-0398** the children's shares reach no consumer of `bequests` | The non-defect half is stated plainly: **the save path is sound**, proven by a 60/40 round-trip, so the missing rows are unfinished data entry. The real finding is that the residuary substitution the persona DOES record is invisible to the Estate module, `WillAnalysisService` and `/m`'s bequests screen — which shows "1 bequest" for a will providing for three beneficiaries. **That is why this household reads as though its children are unprovided for.** |

## 11. In flight

**Nothing.** Every edit is applied, linted (`pint` clean), covered and
browser-verified. Probe tokens revoked, throwaway probe users confirmed absent,
scratch files outside the repository, no git lock held.

**Board:** W-0391, W-0393, W-0394, W-0395, W-0396, W-0397 at `handoff` →
quality-lead. W-0392 `queued`, `blocked_by: [csj-decision]`. W-0398 and W-0399
`queued`.
