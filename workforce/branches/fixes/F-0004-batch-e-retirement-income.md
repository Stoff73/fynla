---
id: F-0004
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/06-commercials.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-21T18:50:00Z
status: active
---

# Batch E — Retirement target income & scheme status

Branch document for the two-item fix batch dispatched to `build-lead` from the
`persona-run-peak_earners-2026-08-20` mission. Doubles as the **Rule 22 context
handover**: everything a replacement agent needs to continue without re-deriving
anything.

**Board items:** W-0035 (high), W-0032 (medium).
**Predecessor:** an earlier `fix-batch-E` worked this tree on 2026-08-21 between
roughly 14:14 and 14:21, never reported, and never wrote this document. §3 records
exactly what it left, established from the tree rather than from the board.

---

## 1. The dispatch, verbatim

> You are `fix-batch-E`, respawned. A previous fix-batch-E worked in this repo today,
> never reported, and never wrote its branch document. Your first job is to establish
> what it left behind, then finish it.
>
> ## Module and patterns (read these FIRST)
>
> Module: **Retirement**. Architecture: `Vue Component → API Service → Controller →
> Agent → Services → Models → DB`.
> - Vault: `/Users/CSJ/Desktop/fynlaBrain/v083/09-MODULES.md` and
>   `/Users/CSJ/Desktop/fynlaBrain/Retirement.md`
> - Conventions: `app/Services/CLAUDE.md`, `app/Http/CLAUDE.md`, `database/CLAUDE.md`,
>   `tests/CLAUDE.md`
> - Backend: `app/Http/Controllers/Api/RetirementController.php`,
>   `app/Services/Retirement/`, `app/Services/Stores/RetirementProfileStore.php`,
>   `app/Models/DBPension.php`
> - Frontend web: `resources/js/components/Retirement/`
> - Frontend `/m`: `resources/mobile/views/modules/` — **Rule 19: every fix reaches
>   web AND `/m`.**
>
> ## Recent work in YOUR module, by another agent, uncommitted in this same tree
>
> `fix-batch-C` closed W-0017, W-0030 and W-0036 today. **Read
> `workforce/branches/fixes/F-0001-batch-c-retirement-profile-gates.md` in full before
> touching anything** — it is 50k and it is the state of the module. In particular:
> - W-0036: a Defined Benefit pension was counted as income in payment from the day it
>   was entered. `DBPension::isInPayment()` now uses age-vs-Normal-Retirement-Age.
>   Batch C's note says the *proper* fix is to prefer `scheme_status === 'In Payment'`
>   when present and keep the age heuristic as the fallback — **that is your W-0032.**
> - W-0030 corrected a spouse-pension-percent unit convention with a migration.
> - Batch C deliberately landed W-0036 **before** W-0035, because fixing W-0035 first
>   would let an explicit target override the derived figure and hide the phantom
>   income while it carried on corrupting tax, Personal Allowance and Child Benefit.
>
> ## Your two items
>
> ### 1. W-0035 (high, `status: claimed` to you) —
> `workforce/ops/board/W-0035-target-retirement-income-has-no-entry-point.md`
> Target Retirement Income has no module-UI entry point, so every retirement
> projection runs on a 75%-of-income fallback the user never chose.
>
> **Your predecessor had already built a large part of this.** These are in the
> working tree, untracked or modified, last written 14:14–14:21 today:
> - `app/Services/Stores/RetirementProfileStore.php` (new)
> - `app/Http/Requests/Retirement/UpdateRetirementGoalsRequest.php` (new)
> - `app/Http/Controllers/Api/RetirementController.php` (modified)
> - `tests/Feature/Retirement/RetirementGoalsTest.php` (new)
> - `resources/js/components/Retirement/dbPensionFields.js` (new, 09:41 — may be batch
>   C's, check)
> - `resources/js/components/__tests__/Retirement/DbPensionFields.spec.js` (new, 10:04)
> - `resources/js/components/Retirement/DBPensionForm.vue`, `DCPensionForm.vue`
>   (modified)
> - `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php` (modified)
>
> **Reconstruct from the tree, not from the board — the board lags.** Establish what
> is complete, what is half-applied, and what was never started. Say so explicitly in
> your report; do not paper over a half-written file.
>
> ### 2. W-0032 (medium, `status: claimed` to you) —
> `workforce/ops/board/W-0032-scheme-status-collected-but-has-no-column.md`
> `scheme_status` is collected by both pension forms and silently discarded on every
> save — no such column exists. CSJ has already ruled: **`scheme_status` gets a
> column.** Do not re-raise that as a decision.
>
> This needs a migration. **Migration rules on this shared dev database,
> non-negotiable:**
> - `php artisan migrate --path=database/migrations/<your_file>.php` — **never bare
>   `php artisan migrate`**, and **never** `migrate:fresh` or `migrate:refresh` (they
>   drop every table).
> - Name it with today's date and a distinct time so it does not collide with the five
>   other untracked 2026_08_21_* migrations already in `database/migrations/`.
> - After any operation that could lose local data, `php artisan db:seed`.
>
> Once the column exists, close the loop batch C left open: `DBPension::isInPayment()`
> should prefer `scheme_status === 'In Payment'` when present, keeping
> age-vs-Normal-Retirement-Age as the fallback for records that predate the column.
>
> ## Rules that bind you
>
> - **Rule 2:** no hardcoded tax values — everything through `TaxConfigService`
>   (backend) / `taxConfig.js` (frontend).
> - **Rule 9:** no acronyms in user-facing text. Write "Defined Benefit", "Normal
>   Retirement Age", "Consumer Price Index". Only ISA may stay abbreviated.
> - **Rule 12:** no numerical scores in user-facing UI.
> - **Rule 15:** no decorative icons. Retirement module pages are a banned surface.
> - **Rule 19:** web AND `/m`. If a web change has a `/m` counterpart, build it. If
>   `/m` has no counterpart by design, say so explicitly.
> - **Rule 20:** one change, one place, all surfaces. If two mechanisms implement the
>   same behaviour, consolidating them to one is PART of the fix, not a follow-up.
> - **British spelling** in user-facing text, American in code identifiers.
> - **Never edit `.env` or DB rows to work around a bug.** Reading `.env` for a
>   credential is fine and expected.
>
> ## Environment
>
> - Your test database is **`laravel_testing_e`**. Run every test as
>   `DB_DATABASE=laravel_testing_e ./vendor/bin/pest <paths>`. `phpunit.xml:46` pins a
>   shared DB but has no `force="true"`, so the shell override wins. Running without it
>   causes deadlocks and 0-assertion failures that look like code failures and are not.
> - **`pgrep -f "vendor/bin/pest"` before starting a run.** I am running a full
>   consolidation suite on `laravel_testing_a` right now; other agents are on `_b`,
>   `_c`, `_d`. Contention between agents produces the identical 0-assertion signature.
>   If a vitest file times out at 5000ms under load, **re-run it in isolation before
>   believing it** — do not raise the timeout.
> - Laravel is on `:8000`, Vite on `:5173`, `public/hot` present. **Do not rebuild the
>   `/m` bundle** — build artefacts are mine; ask me.
> - **Never provision tiers, never create or delete test users, never touch test
>   data.** David (16), Sarah (17), Priya (20), her spouse (30) and Tomas (31) are live
>   reproduction data for other agents. `db_pensions.id 4` is deliberately unpatched as
>   an acceptance fixture — leave it.
> - The formatter deletes a `use` statement that is unreferenced at the moment it runs.
>   **Add an import and its first usage in the SAME edit**, or a dozen unrelated tests
>   will 500.
>
> ## What you do NOT do
>
> - **Do not commit, do not open a PR, do not deploy.** The tree is shared with five
>   other agents' uncommitted work. Nothing has been committed today.
> - **Do not browser-verify your own work.** A separate persona-tester closes Rule 14's
>   loop independently. Your job is code + green tests.
> - **No colour or palette changes of any kind** — CSJ has parked that entire
>   workstream.
>
> ## Deliverables
>
> 1. Both items fixed, with targeted test families green under
>    `DB_DATABASE=laravel_testing_e`, and `./vendor/bin/pint` clean on the files you
>    touched.
> 2. Both board items updated to `status: handoff`, `handoff_to: quality-lead`, with
>    append-only working notes carrying file:line evidence, decisions taken and
>    reasoning, and anything you noticed but deliberately did not fix.
> 3. A branch document at
>    **`workforce/branches/fixes/F-0004-batch-e-retirement-income.md`** following
>    `workforce/ops/FORMATS.md`. It must be a complete seed for a replacement: the
>    dispatch verbatim, what is DONE with evidence, what is IN FLIGHT and its exact
>    state, what is NOT STARTED, decisions and their reasoning, dead ends ruled out,
>    and environment state. The board item W-0035 already references this exact path.
> 4. A concise report back to me: what you found in the tree from your predecessor,
>    what you finished, test output, and anything needing my decision.
>
> **Report to me the moment you are blocked — do not sit idle.** I am the coordinator
> and I unblock tooling, environments, provisioning and test data. If you hit a
> question only CSJ can answer, state it plainly with a recommendation and carry on
> with everything that does not depend on it.
>
> **Rule 22:** if you reach ~900k context, stop taking new work, write the handover
> into your branch document, and return it to me.

### Amendments received since

None.

---

## 2. Status summary

| Item | Status | Where |
|---|---|---|
| W-0035 | **complete**, handed to `quality-lead` | §4 |
| W-0032 | **complete**, handed to `quality-lead` | §5 |

Nothing is in flight. Nothing is half-applied. Two things are deliberately **not
started** and named in §8 — the native (SwiftUI) derived-figure caption, and
`create_pension.md` (the dormant Anthropic provider copy of Fyn's tool schema).

---

## 3. What the predecessor actually left — established from the tree

The dispatch listed eight files. They are not one batch of work; they are two, by two
different agents. Established by reading each file, not by trusting the timestamps.

### Complete and green when inherited (predecessor's, 14:14–14:21)

The **entire backend** for W-0035, and it worked on the first run:

- `app/Services/Stores/RetirementProfileStore.php` — new store, `updateGoals()`.
- `app/Http/Requests/Retirement/UpdateRetirementGoalsRequest.php` — bounds from
  `ValidationLimits`, not retyped numbers.
- `app/Http/Controllers/Api/RetirementController.php:572-608` —
  `updateRetirementGoals()`.
- `routes/api.php:1048-1050` — `PUT /api/retirement/goals`. **The dispatch did not
  list `routes/api.php` as touched; it is.**
- `tests/Feature/Retirement/RetirementGoalsTest.php` — 11 tests.

Verified before touching anything: `DB_DATABASE=laravel_testing_e ./vendor/bin/pest
tests/Feature/Retirement/RetirementGoalsTest.php` → **11 passed (33 assertions)**.

### Not the predecessor's — batch C's, and already finished

- `resources/js/components/Retirement/dbPensionFields.js` (09:41)
- `resources/js/components/__tests__/Retirement/DbPensionFields.spec.js` (10:04)
- `DBPensionForm.vue`, `DCPensionForm.vue` (09:41 / 10:08)
- `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php` (10:00)

These are W-0017 (the Defined Benefit form's four missing fields). The dispatch
flagged `dbPensionFields.js` as "may be batch C's, check" — it is. Confirmed by
`buildDbPensionPayload()` carrying no `scheme_status` key at all, which is precisely
what W-0032 was left open to add.

### Never started

**Every surface of W-0035.** Not one line of frontend existed:

- `grep -rn "retirement/goals" resources/ app/ ios-native/` returned **nothing** —
  the endpoint the predecessor built had no caller anywhere.
- No service method, no Vuex action, no web component, no `/m` component.
- The hardcoded `35000` was still in place at `CapitalAdequacyTab.vue:323` and
  `PensionList.vue:593`.

**All of W-0032.** No migration, no column, no model change, no form wiring.

### Two defects found in the inherited backend

Neither was visible from the board; both came out of the Rule 20 enumeration.

1. **The endpoint did not mirror `users.target_retirement_age`.** Fyn's handler
   carried that mirror alone, with a docblock explaining why it is load-bearing:
   `RetirementProjectionService`, the "When you want to retire" data requirement and
   `ModuleAvailabilityProvider` all read the `users` column. A user setting 60 through
   the new endpoint would still have seen the default 67 on `/retirement`, with the
   checklist item outstanding. Fixed by moving the mirror into the store (§4).
2. **Two mechanisms wrote `retirement_profiles`.** The new store, and
   `CoordinatingAgent::handleCaptureRetirementGoals` doing its own
   `RetirementProfile::create`. Rule 20 makes consolidating them part of the fix, not
   a follow-up. Fixed (§4).

---

## 4. W-0035 — DONE, with evidence

### The defect

`retirement_profiles.target_retirement_income` is the figure every retirement
projection is built on. Nothing but Fyn could write it. Everyone else got
`RequiredCapitalCalculator`'s fallback — `(gross income − pension contributions) ×
75%` — presented as their own target. Sarah: told she needed £116,250 a year when she
had said £55,000.

### Backend (inherited, then corrected)

- `app/Services/Stores/RetirementProfileStore.php` — now also mirrors the retirement
  age onto `users.target_retirement_age` (`mirrorRetirementAgeOntoUser()`), and only
  for an age the user actually stated, so the create path's fallback is not written
  back onto itself as a no-op dressed up as a save.
- `app/Agents/CoordinatingAgent.php:5758-5789` — Fyn's capture now calls
  `RetirementProfileStore::updateGoals()` instead of creating the row itself. The
  branch decision (park the income vs write) stays in the agent because it is
  conversational protocol, not persistence.
- `app/Agents/CoordinatingAgent.php:5805-5807` — the Fyn-only mirror is gone; the
  store does it for every surface.

### Web

- `resources/js/components/Retirement/RetirementTargetCard.vue` — **new.** The entry
  point. Shows the target, states whether it was chosen or derived, and edits inline.
  Emits `save`; the parent owns the API call (Rule 3).
- `resources/js/components/NetWorth/PensionList.vue` — renders the card above the tabs
  and above the empty state, `handleRetirementTargetSave()`, `targetIncomeIsStated`,
  `targetIncomeLabel`, and `loadProjectionsAndStrategies()` now fetches required
  capital even with zero pensions (parity with `/m`, which fetches unconditionally).
- `resources/js/services/retirementService.js` — `updateRetirementGoals()`.
- `resources/js/store/modules/retirement.js` — `updateRetirementGoals` action.
- `resources/js/components/Retirement/CapitalAdequacyTab.vue` — `35000` gone,
  `targetIncomeCaption` added.

### `/m`

- `resources/mobile/views/modules/Retirement.vue` — the same card, the same endpoint
  (`apiPut('/api/retirement/goals', …)`), the same store behind it. Also now fetches
  `/api/retirement/required-capital`, which fixes a pre-existing `/m`-only gap: the
  analysis reads the profile with no fallback, so `/m` showed "—" where web showed a
  derived figure.

### The £35,000 that was never anyone's target

Both call sites removed. `PensionList.vue` and `CapitalAdequacyTab.vue` now read 0 and
say "Not set". The verdict colour on "Projected Gross Income" is suppressed when there
is no target — green there used to mean "beats the invented £35,000".

### Tests

- `tests/Feature/Retirement/RetirementGoalsTest.php` — **15 passed (42 assertions)**;
  4 added: the mirror on create, the mirror on update, form-and-Fyn writing an
  identical row, and the date-of-birth refusal.
- `resources/js/components/__tests__/Retirement/RetirementTargetCard.spec.js` — new,
  **10 passed**.
- `resources/mobile/views/modules/__tests__/RetirementTarget.spec.js` — new,
  **7 passed**.

### Acceptance, item by item

| Acceptance | State |
|---|---|
| Input exists on a module screen, persists the column | **Met** — web + `/m`, DB row asserted |
| `income_source: profile`, correct `required_income` | **Met** — `RetirementGoalsTest` |
| Downstream figures move with the target | **Structural, not re-tested** — see §7 |
| Fallback stays, and the UI says it is derived | **Met** — web + `/m` captions |
| `target_income_percent` still from `TaxConfigService` | **Met** — untouched, `RequiredCapitalCalculator.php:130` |
| Hardcoded `35000` removed | **Met** — both call sites |
| ONE entry point feeding all surfaces | **Met** — one endpoint, one store, Fyn consolidated onto it |
| `/m` and iOS can set and display it | **`/m` met. iOS partially — see §8.** |
| Re-verified live by the persona run | **Not mine** — tester's, per the dispatch |

---

## 5. W-0032 — DONE, with evidence

### Migration

`database/migrations/2026_08_21_180000_add_scheme_status_to_db_pensions_table.php`,
applied with `php artisan migrate --path=…` alone. Nothing else ran; no data was lost,
so no reseed was needed.

`db_pensions.scheme_status` — `string(20)`, nullable, no default, after `scheme_type`,
with a column comment recording the vocabulary and what null means.

### The vocabulary, and why it is snake_case

Stored `active` | `deferred` | `in_payment`. `app/Constants/PensionEnums.php` is the
one declaration; `DBPension`, `PensionNormaliser`, `PensionStore::validateDbCanonical()`
and `StoreDBPensionRequest` all read it rather than retyping a list.

**It began on the model, and the architecture suite was right to reject that.**
`tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` is a LOCKED allowlist:
`App\Models\DBPension` may only be referenced from the canonical pension write/read
set, and a form request is not on it and should not be. The failure read `Expecting
'App\Models\DBPension' not to be used on 'App\Http\Requests\Retirement\StoreDBPensionRequest'`
at `:7`.

**It was not an unused import** — the reference was live at `:46`, and "finishing the
usage" would have left the suite just as red. Adding the request to the allowlist would
have weakened a boundary whose own docblock says a red suite means the entry is
load-bearing, "not that the rule should be weakened". Moving the vocabulary into
`App\Constants` — the same shape as `ProfileEnums`, which a form request already
consumes — lets every layer read one list without any of them touching a pension
model. `PensionNormaliser` lost its model dependency entirely as a side effect.

The forms displayed "Active" / "Deferred" / "In Payment" and Fyn's tool schema
declares that same title-case enum. Storing title case would have matched them but
broken with every other enum in the app — `scheme_type` is `final_salary`,
`inflation_protection` is `cpi`, and `investment_accounts.scheme_status` (a different
column, employee share schemes, do not confuse them) is lowercase. Mapping costs three
lines in one place; a per-table convention costs forever.

### The mapping lives in one place

`PensionNormaliser::normaliseSchemeStatus()`, reached from all four inbound paths:
`fromFormDb()`, `fromFynPension()`, `fromUploadDb()`, and `normaliseDbFields()` — the
last added for `update_record`, which hands `PensionStore` a bare field list and so
passes through none of the `from*` methods. Without it, `update_record` would have
been the one writer with its own idea of what "In Payment" means.

An unrecognised value maps to null, not to a guess. Null is meaningful: it is what
`isInPayment()` reads as "fall back to age".

### `isInPayment()` — the loop batch C left open

`app/Models/DBPension.php:116-131`. A stated status wins, in **both** directions: "In
Payment" is income regardless of age, "Active" or "Deferred" is not income even past
the scheme age. The age heuristic remains for rows that predate the column. Both
directions are pinned by test, because both are wrong under the heuristic in cases
common in Fynla's audience — drawing early at 57 against a scheme age of 60, or
deferring at 62 past a scheme age of 60.

### Everywhere else

- `DBPension::$fillable` — `scheme_status` added.
- `UpdateRecordAllowlist` — `scheme_status` added to `db_pension`. Without it, `/m` and
  native users could state a status once through Fyn and never correct it — the exact
  gap W-0017 closed for the other four fields.
- `dbPensionFields.js` — `DB_SCHEME_STATUS_OPTIONS`, `formatSchemeStatus()`, and
  `scheme_status` in `buildDbPensionPayload()`.
- `DBPensionForm.vue` / `DCPensionForm.vue` — both read the shared options, both send
  the value, and `DBPensionForm` restores it on edit so a re-save cannot silently clear
  it.
- `PensionDetail.vue:195` — was `pension.scheme_status || 'Active'`, which displayed
  "Active" for every Defined Benefit pension ever saved because the value was
  discarded on write. Now `formatSchemeStatus()`, which reads unset as "Not recorded".
- `resources/mobile/views/modules/RetirementPensionDetail.vue` — a Scheme status row,
  importing the **same** `formatSchemeStatus` from `resources/js/`, not a copy. `/m`
  already imports `ownership.js` that way; the module is pure JavaScript with no Vue or
  store dependency.

### Tests

- `tests/Feature/Retirement/DbPensionSchemeStatusTest.php` — new, **10 passed (24
  assertions)**: persistence, edit-preserves-omitted, invalid rejected, Fyn's title
  case mapped, unrecognised dropped, both heuristic directions, the age fallback for
  legacy rows, the income actually moving, `update_record` correcting it, and the value
  reaching `GET /api/retirement` for `/m` and native.
- `DbPensionFields.spec.js` — **9 passed**, extended for the new field.

---

## 6. Decisions taken, and why — do not re-litigate

1. **`scheme_status` stores snake_case.** §5. Rejected: title case matching the labels
   and Fyn's schema.
2. **Fyn's xAI tool schema was NOT re-recorded.** `create_pension.xai.md` already
   declares `scheme_status` with the title-case enum and has done for versions. Editing
   it means re-recording a byte-identity golden master, for a mapping that costs three
   lines in the normaliser. The corpus is untouched.
3. **The entry point is the retirement module screen, not `/settings/personal`.** The
   board item notes `users` has no `target_retirement_income` column. Adding one, or
   adding a second form on the profile page, would be a second entry point — exactly
   what the acceptance forbids.
4. **`/m` gets a real inline edit, not a Fyn handoff.** `/m` was read-only for module
   data until batch C landed the health-and-lifestyle inline edit
   (`PersonalInformation.vue:95-140`), which writes to the same endpoint and validator
   as desktop. This follows that established pattern. Verified first that it is a
   pattern and not a one-off.
5. **Fyn now refuses to invent a `current_age` of 30.** The old handler defaulted to 30
   when the date of birth was unknown. `PensionProjector::getCurrentAge()` prefers
   `current_age` over the date of birth, so that fabrication silently shifted every
   projection the user ever saw. It now returns a validation error and Fyn asks. This
   is a behaviour change on a path no test covered; pinned by test now.
6. **A failed refresh is not a failed save.** The Vuex action's refetch of required
   capital and projections sits outside the try that reports failure, and its errors are
   logged and swallowed. Otherwise a Monte Carlo timeout would report a saved target as
   unsaved and have the user retype a figure the database already holds.
7. **The derived figure never pre-fills the edit box** — web or `/m`. Pre-filling would
   turn "we worked this out" into "you chose this" the moment the user pressed save
   without touching it, which is the confusion the whole item exists to remove.

---

## 7. Traced, not tested — stated honestly

The acceptance asks that required capital, the income projection, decumulation,
capital adequacy and Monte Carlo all move when the target changes.

They read one source, and that source is tested: `RequiredCapitalCalculator`'s
`required_income`, asserted to flip from `calculated`/90000 to `profile`/55000 in
`RetirementGoalsTest`. The consumers take it from there and do not re-derive it:

- `RetirementProjectionService.php:255` and `:380`
- `RetirementIncomeService.php:74` and `:162`
- `RetirementAgent.php:121` reads `$profile->target_retirement_income` directly

I did **not** add a test that drives a live Monte Carlo run to watch the numbers move —
it is slow and flaky, and the board already assigns live re-verification to the persona
tester. The propagation is structural, and the citations above are what makes it
checkable.

---

## 8. NOT STARTED — named, not hidden

### Native (SwiftUI) does not show the derived figure or say it is derived

`ios-native/Fynla/Features/Retirement/RetirementModels.swift:52-56`:

```swift
var targetIncome: Decimal? {
    if let target = analysis?.targetIncome, target > 0 { return target }
    if let target = index.profile?.targetRetirementIncome, target > 0 { return target }
    return nil
}
```

That is exactly what `/m` did before this batch. So:

- **Displaying a stated target: works today, unchanged, no native work needed.**
- **Setting one: works via Fyn, which now writes through the shared store.**
- **Showing the derived figure with a caption saying it was worked out: not built.**

Deliberately left. Rule 19 governs web and `/m`; native is a separate Swift codebase
with its own release cadence and TestFlight sign-off, and the change needs a new client
call, a new model, and a view change I cannot build or test from here. Guessing at it
unbuilt and unrun would be worse than naming it. **Recommend a separate board item.**

### `create_pension.md` — the Anthropic provider copy — has no `scheme_status`

`fyn-memory/procedural/tool_schema/savings/create_pension.md` lacks the field that
`create_pension.xai.md` has. The app runs xAI, so the live catalogue is correct and
this is dormant. Pre-existing drift, not introduced here. Left alone rather than edited
blind.

> **FOR THE CONSOLIDATION-POINT GOLDEN-MASTER CAPTURE — team-lead, do not delegate.**
> Standing rule issued 2026-08-21: **no agent re-records the tool-schema golden
> masters.** The capture is whole-catalogue and all-or-nothing, and it has already swept
> another agent's in-flight corpus edit into a fixture once. The team lead runs one
> capture after the corpus settles.
>
> **This batch has an edit that must be in that capture.** Nothing in
> `fyn-memory/procedural/` was changed here, so no re-record is owed *for a change I
> made* — but the drift above is real and is the kind of thing the single capture should
> resolve. Flagged, not fixed, and deliberately not folded into any casual sweep.

---

## 9. Dead ends and traps ruled out — do not re-walk these

1. **The formatter deletes an unreferenced `use` on write, exactly as the dispatch
   warned.** It bit twice — `PensionNormaliser` and `StoreDBPensionRequest`. The
   working order is: add the *usage* first, then the import in a second edit; or put
   both in one edit. Adding the import first always loses it.
2. **`investment_accounts.scheme_status` is a different column with a different enum**
   (`active`, `vesting`, `exercisable`, `exercised`, `expired`, `forfeited`,
   `cancelled`), for employee share schemes. Grep for `scheme_status` and you will hit
   it. It has nothing to do with pensions.
3. **`update_record` does not go through `PensionNormaliser`.** It calls
   `PensionStore::updateDb()` with a bare field list. Any future field with a stored
   vocabulary needs `normaliseDbFields()` or it will be the one path with different
   rules.
4. **`/m` cannot import from `resources/js/` freely — but it already does for pure
   JavaScript.** `ownership.js` is imported by four `/m` views via a relative path.
   Vue components and anything touching the web store are still off-limits: separate
   bundle, separate store, separate router.
5. **`vitest` cannot submit these forms with `trigger('click')` on the submit button.**
   It does not fire `@submit.prevent` in jsdom. Trigger `submit` on the form element.
6. **`store.token` is `null`, not `undefined`, in `/m` specs.** A
   `toHaveBeenCalledWith(…, undefined)` assertion fails on the third argument.
7. **`PensionList.loadProjectionsAndStrategies()` used to return early with zero
   pensions**, which would have left the new card blind to the derived figure for
   exactly the users who most need to set a target.

---

## 10. Environment state

- **Test database: `laravel_testing_e`.** Every run in this batch used
  `DB_DATABASE=laravel_testing_e`. Between 3 and 9 other `pest` processes were live
  throughout; no contention failures were seen.
- **The migration ran against the shared local dev database** (`php artisan migrate
  --path=…`, that file only). Nothing destructive ran, no data was lost, no reseed was
  needed. `db_pensions.id 4` is untouched and still NULL for `scheme_status`, so it
  still exercises the age fallback exactly as batch C's acceptance fixture requires.
- **Nothing committed. No PR. No deploy. No `/m` bundle rebuilt.**
- **No test users created, deleted, or modified.** No tier provisioning.
- `./vendor/bin/pint` clean on all touched PHP.

### Not mine, but failing in this tree

`resources/js/components/__tests__/UserProfile/FamilyMembers.spec.js` — 3 failures.
`FamilyMembers.vue`, `FamilyMemberFormModal.vue`, `FamilyMembersController.php`,
`StoreFamilyMemberRequest.php` and `FamilyMember.php` are all uncommitted
modifications by another batch. Untouched by me; reported to the coordinator.

---

## 11. Test results

| Suite | Result |
|---|---|
| `Feature/Retirement`, `Feature/Income`, `PensionStoreTest`, `PensionStoreEventsTest`, `Feature/AI/DirectWrite`, `UpdateRecordSecurityTest`, `CoordinatingAgentCaptureIntegrityTest` | **245 passed, 754 assertions** |
| `PensioncheckStatesTest`, `CampaignVerifyFlowTest`, `CampaignAuditFixesTest` | **101 passed, 235 assertions** |
| `resources/js/components/__tests__` + `resources/mobile` (vitest) | **all passed except the 3 `FamilyMembers` failures above, which are another batch's** |
| `--testsuite=Architecture` | **148 passed, 3 failed — none of them mine** (see below) |
| `./vendor/bin/pint` | **passed** |

### Architecture suite — the three remaining failures are not this batch's

Verified by `git status`, not by assumption:

- `app/Http/Requests/Concerns/ValidatesSharedOwnership.php` — 2 failures (must extend
  `FormRequest`, must have the `Request` suffix). The whole directory is **untracked**
  (`?? app/Http/Requests/Concerns/`), so it is another batch's new trait placed under
  `app/Http/Requests/`, which the `ApplicationArchitecture` rules reserve for form
  requests.
- `App\Models\SavingsAccount` used on `App\Http\Controllers\Api\SavingsController:16`
  — a genuine store-boundary breach introduced at `SavingsController.php:369`
  (`SavingsAccount::where('id', $id)->where('user_id', $user->id)->first()`). The file
  is **modified** in this tree by another batch.

Also seen while running the wider store family: 4 failures in
`tests/Unit/Services/Stores/LiabilityStoreOwnershipTest.php` (`ModelNotFoundException`).
That test file is **untracked** and `app/Services/Stores/LiabilityStore.php` is
**modified** — another batch's in-flight work. `PensionStoreTest` +
`PensionStoreEventsTest` are green (23 passed).

Status of the original two-item batch: **closed.** Nothing in flight, nothing half-applied. The W-0154 work that followed is recorded below.

---

# W-0154 — the retirement and profile inputs the estate consumes

Taken after the batch closed, on team-lead sequencing: `tax-compliance-reviewer` owned the
diagnosis of `IHTCalculationService` and `fix-batch-G` was editing it, so this covers only
the **upstream** half — whether the figures the estate calculation consumes from the
retirement and profile side are right.

**STOOD DOWN 2026-08-21 ~20:45.** CSJ changed the working model and all parallel fix
agents stopped. Nothing was mid-edit at that point: everything below under "Landed" was
complete, green and Pint-clean before the stand-down, and nothing is half-applied.

## Landed and standing

| Item | State |
|---|---|
| R2 — `expected_annual_pension` in `EstateAssetAggregatorService:192` | **Fixed** |
| R3 — `expected_annual_pension` in `HouseholdPlanningService:792` | **Fixed** |
| The two `$ihtConfig['rate'] ?? 0.40` sites (`:278`, `:968`) + the hardcoded `/2` (`:980`) | **Fixed** |
| Business Property Relief cap | **Raised as W-0091, not built** |
| R1, R5, R6, R7, and R2's two remaining instances | **Untouched** — recorded below |

Tests at stand-down: `tests/Unit/Services/Coordination` + `tests/Unit/Services/Estate` +
`tests/Feature/Estate` — **491 passed, 1,649 assertions, zero failures.** Pint clean.

> **Correction to the stand-down brief, which listed the rate sites as outstanding and
> `inheritance_tax.rate` as NULL.** They landed at 20:32, and the key is **not null — it
> has never existed.** See §"The rate sites" below. The brief also grouped "R2's third
> instance" with R5–R7 as untouched; the third instance (the aggregator) **is fixed**.
> What remains of R2 is the two phantom columns inside `IHTCalculationService`.

---

## UNTOUCHED — R1, the largest of them

**The estate's retirement income projection reads no pension at all.**

`IHTCalculationService::getRetirementIncome()` (`:816-847`) sums exactly two things:

1. `retirement_profiles.target_retirement_income`
2. a State Pension figure from `$statePension?->estimated_annual_amount` — **a column that
   does not exist** (R2)

**No Defined Contribution drawdown. No Defined Benefit pension income.** David Jones (16)
has £500,000 across two Defined Contribution pots; Sarah Jones (17) has a £35,000 NHS
Pension Scheme. Neither contributes anything to the income side of the projection that
produces the estate.

**This is the cause of the negative projected estate**, and the mechanism is established:
verified read-only, **neither David nor Sarah has a `retirement_profiles` row, and neither
has a `state_pensions` row**. So retirement income is **£0** — R1 means the pensions cannot
supply it, R2 means the State Pension cannot either, and there is no profile target.
Meanwhile `getRetirementExpenses()` (`:853-876`) falls back to
`getUserAnnualIncome() × RETIREMENT_EXPENDITURE_FALLBACK_RATIO`: **£72,500** a year for
David, **£60,000** for Sarah, inflating, from a retirement age of 60 (taken from
`users.target_retirement_age`) to a second death in the mid-eighties. Roughly twenty-six
years of five-figure expenses against zero income.

**Not reconciled to Sarah's exact −£185,535.84** — from that point the arithmetic is
`IHTCalculationService`'s own and belongs with the audit's F8, which covers
`projectCashWithInflation()`'s missing floor and the two irreconcilable liability figures.

---

## UNTOUCHED — R2's two remaining phantom columns

Both inside `IHTCalculationService`, both verified against the live schema **and** verified
to have no model accessor:

- **`$statePension?->estimated_annual_amount`** (`:827`, `:842`). `state_pensions` has no
  such column — the real one is `state_pension_forecast_annual`. **State Pension income is
  always £0 in the estate projection**, for every user, forever.
- **`$user->state_pension_age`** (`:824`, `:834`). `users` has no such column. Always falls
  to `DEFAULT_STATE_PENSION_AGE = 67`, and `state_pensions.state_pension_age`, which *does*
  exist and is populated, is never read.

---

## UNTOUCHED — R5, two answers to "what will you spend in retirement"

A Rule 2 breach and a Rule 20 breach at once.

- `RequiredCapitalCalculator:130` reads `retirement.target_income_percent` = **0.75** from
  `TaxConfigService` (seeded at `TaxConfigurationSeeder.php:990`).
- `IHTCalculationService::RETIREMENT_EXPENDITURE_FALLBACK_RATIO` (`:48`) = **0.50**,
  hardcoded.

Same household, same question, two figures, and the estate one cannot be moved by
configuration.

## UNTOUCHED — R6, a third retirement-age default

`IHTCalculationService::DEFAULT_RETIREMENT_AGE` (`:38`) = **68**.
`PensionProjector::DEFAULT_RETIREMENT_AGE` (`:25`) = **67** and
`DBPension::DEFAULT_NORMAL_RETIREMENT_AGE` = **67** — and those two are 67 *deliberately*:
`fix-batch-C` aligned them this morning for W-0036 so that "a pension cannot count as
income from one age while being projected forward from another". The estate service is the
third value and was not part of that alignment.

## UNTOUCHED — R7, the life expectancy override is structurally invisible to the estate

`FutureValueCalculator::getLifeExpectancy(User $user)` honours
`users.life_expectancy_override` (`:39-49`). `getLifeExpectancyYears(int $age, string
$gender)` (`:73`) **cannot** — it never receives the user — and that is the one
`IHTCalculationService::calculateLifeExpectancy()` calls (`:1399`).
`retirement_profiles.life_expectancy` is likewise read by `RetirementAgent.php:178` and
`DecumulationController.php:62`, and not by the estate.

**A user who has told the application when they expect to die is answered one way by
retirement and decumulation and another way by their inheritance tax projection, and
nothing anywhere reveals the disagreement.**

## UNTOUCHED — other hardcoded assumptions in the same input path

For the Rule 2 sweep, not fixed: `$currentAge ... : 50` (`:287`), `$yearsUntilDeath = 25`
(`:306`), `: 80` (`:309`), `return 25` (`:1393`), `DEFAULT_STATE_PENSION_AGE = 67` (`:40` —
a legislated figure, so `TaxConfigService` territory), `EXPENDITURE_FALLBACK_RATIO = 0.70`
(`:45`), `DEFAULT_PROPERTY_GROWTH_RATE = 3.0` (`:42`).

---

## The rate sites — FIXED, and the judgement that was asked for

**The judgement: the rate legitimately lives elsewhere, and `rate` was never the right
key. `inheritance_tax.rate` is not a configured-but-null value — it has never existed.**

Dumped live from `TaxConfigService::getInheritanceTax()` on 2026-08-21: the array holds
**`standard_rate` = 0.4**, and there is no `rate` member at all. `?? 0.40` swallowed the
miss. This is the same disease as R2's phantom columns — a name nothing answers to.

**Therefore the seeder needs no change.** The value was configured correctly the whole
time, and `standard_rate` is unambiguously canonical: `IHTCalculationService:1392,2003`,
`PersonalizedGiftingStrategyService:53` and `PersonalizedTrustStrategyService` (five sites)
all read it. `HouseholdPlanningService` was the only reader in the repository asking for
`rate`. The reported consequence stands exactly: every Inheritance Tax figure that path
produced came from a literal **no configuration change could move** — unreachable, not
merely unread.

**Fixed once, not in lockstep.** Both sites go through
`HouseholdPlanningService::inheritanceTaxRate()`; the hardcoded `/ 2` residence-nil-rate-band
taper goes through `rnrbTaperRate()` reading `rnrb_taper_rate`, which is configured and
already read by `IHTCalculationService:1266`.

**The fallback is loud**, as instructed. Both helpers `report()` a `\RuntimeException`
naming the missing key before falling back — `TaxDefaults::IHT_RATE` for the rate, matching
`PersonalizedTrustStrategyService`, and the statutory 0.5 for the taper. They still return
a sane figure rather than throwing at a user mid-calculation, but an unconfigured tax rate
no longer becomes 40% in silence.

---

## A failure mode worth naming, because it defeats the usual defence

**A test that shares the code's misconception cannot ever fail.**

Not a missing test. Not a wrong assertion. A test that mocks **the same wrong key** the
code under test asks for. `createHouseholdService()` mocked
`TaxConfigService::getInheritanceTax()` returning `'rate' => 0.40` — a key the real
configuration has never held. The service asked for `rate`; the mock supplied `rate`; they
agreed with each other, both disagreed with reality, and the suite stayed green through
every run for as long as both existed.

**How to recognise it elsewhere.** The shape is a test double that is built from the same
assumption as the code it exercises, rather than from the real contract. Any mock, stub,
fixture or factory hand-written to match what the code *expects* — instead of what the
dependency *actually returns* — can encode the same mistake twice and prove nothing. The
smell is a green test over a value nobody has verified against its real source: a config
array, an API payload shape, a column name, an enum member. Every phantom in this branch
document is that same class of defect one layer down —
`expected_annual_pension`, `estimated_annual_amount`, `users.state_pension_age`,
`inheritance_tax.rate`. The configuration layer version is simply the one that also had a
test agreeing with it.

**The countermeasure, as a shape rather than a fix.** Do not assert the value; assert that
the answer *moves* when the real input moves. `createHouseholdService()` now takes
overrides, and three tests **change the configured rate and require the output to follow** —
rate 0.40 → £400,000, rate 0.10 → £100,000; and the same for the residence-nil-rate-band
taper. A test written that way cannot pass against a hardcoded literal, whatever key the
mock happens to use, because a literal does not move. **Where a test's job is to prove a
value is read from configuration, vary the configuration — never assert the number.**

The cheaper companion check: verify the double against the live contract once. Dumping
`getInheritanceTax()` and reading the keys took one command and settled a question two
agents had framed as "is the key null?".

---

## Two judgements that must survive me

These are recommendations, not decisions. **Both are CSJ's to take.**

### 1. The 50% spouse-percentage default disagrees with the derived calculator

`HouseholdPlanningService::DEFAULT_SPOUSE_PENSION_PERCENT` = **50** when a scheme records
no spouse percentage. `PensionDerivedColumnCalculator::calculateDb()` (`:102-105`) returns
**null** in the same case, assuming nothing.

Same question, two answers — and **which one a household sees depends only on whether the
derived column happens to have been written yet.** Sarah is the live case: she will now be
told a precise-looking **£17,500** that rests entirely on an assumed 50%, because her NHS
scheme records no percentage. The field only reached the form in W-0017, today.

**Recommendation: the derived calculator's semantics should win — unstated means unknown,
and the real fix is recording the actual percentage.** Not taken here because it removes a
figure users can currently see, which is a product decision rather than a defect fix.

### 2. The absence could not be made loud for R3, and no field was invented to fake it

The instruction was to make a missing figure not read as a real zero. **For R2 that was
possible** and the fallback chain now does it. **For R3 it was not.**
`calculateDBPensionSpouseBenefit()` returns a `float` into `income_after` / `income_lost`;
a scheme with no recorded amount contributes the same `0.0` as a scheme that genuinely pays
a spouse nothing. The payload has no way to express "not known".

**An unread field was deliberately not added.** `nrb_deduction` (audit F10) and
`IHTPlanning.vue`'s seven never-emitted keys (F5) are what that produces, and this item is
about exactly that disease. It is commented at the call site and recorded on the board
instead.

---

## Environment state at stand-down

- Nothing committed. No PR. No deploy. No `/m` bundle rebuilt.
- No test users created, deleted or modified; no tier provisioning. All persona queries
  were **read-only** — other suites were live and the personas were being driven in a
  browser.
- Migration `2026_08_21_180000` (W-0032) is applied to local dev. `db_pensions.id 4`
  untouched, `scheme_status` still NULL, still exercising the age fallback as batch C's
  acceptance fixture requires.
- `laravel_testing_e` was this agent's database throughout.

## Not mine, seen in this tree

- `resources/js/components/__tests__/UserProfile/FamilyMembers.spec.js` ×3 — `fix-batch-I`'s,
  routed by team-lead.
- `app/Http/Requests/Concerns/ValidatesSharedOwnership.php` ×2 architecture failures
  (untracked directory) and `SavingsController.php:369` querying `SavingsAccount` directly
  — another batch's, and the second is the same store-boundary class of defect as the one
  corrected in W-0032.
- `tests/Unit/Services/Stores/LiabilityStoreOwnershipTest.php` ×4 (untracked).
- **Retracted:** `IHTRnrbAndCharitableExemptionTest` ×2, reported at 20:20 as failing, were
  **green** by 20:45 — `fix-batch-G`'s in-flight work had settled. Recorded so nobody
  chases a failure that no longer exists.

## Do not spend time on

The `BelongsTo.php:187` "null as an array offset" deprecation. `fix-batch-C` recorded it in
`F-0001` as reached through `getCompleteProfile()`; **that attribution is wrong** — the
auditor reproduced it as `->with('jointOwner')` where `joint_owner_id` is NULL, four store
sites, framework-level. The five `getCompleteProfile()` callers are not on the estate path
at all, so anyone chasing it as written finds no reproduction and concludes it does not
happen. Laravel-level, not an app change.

Status: **stood down.** R2, R3 and the rate sites landed and stand. R1, R5–R7 and R2's two
remaining phantom columns are untouched and fully recorded above. W-0091 raised, not built.
