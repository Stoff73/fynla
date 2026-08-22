---
id: W-0035
title: Target Retirement Income has no module-UI entry point — every retirement projection runs on a figure the user never chose
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: handoff
claimed_by: fix-batch-E
claimed_at: 2026-08-21
branch: fixes/F-0004-batch-e-retirement-income.md
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T12:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21
prior_art_found: [W-0010, W-0017]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, playbook preparation (static analysis, then
confirmed against the live figures the previous pass observed). Local
`localhost:8000`, premium. Accounts **David Jones (16)** and **Sarah Jones (17)**.

**Surface:** `/net-worth/retirement`, `/settings/personal`, `/plans/retirement`, and
every projection downstream of them.

**Not in Batch C.** Batch C covers the Defined Benefit form's missing fields (W-0017),
the Add Pension dead-end (W-0010), health persistence (W-0006), the free-tier
expenditure gate (W-0011) and the TierResolver docblock (W-0018). The target
retirement **income** field is untouched by all of them.

### Expected

The persona specifies a target retirement income for each spouse — David **£75,000**,
Sarah **£55,000** (`tests/Persona/peak_earners.md`, User Profile and Spouse tables).
Both should be enterable through a module form, persist to
`retirement_profiles.target_retirement_income`, and drive every retirement projection.

`GET /api/retirement/required-capital` should then return:

| Account | `income_source` | `required_income` |
|---|---|---|
| David | `profile` | 75000 |
| Sarah | `profile` | 55000 |

### Actual

There is no form field and no API route that writes
`retirement_profiles.target_retirement_income`. The column exists and is read widely,
but only two code paths ever write it:

- `app/Services/Onboarding/OnboardingService.php:482` and `:498` — both
  `RetirementProfile::updateOrCreate` calls set only `current_age` and
  `target_retirement_age`. Neither touches the income.
- `app/Agents/CoordinatingAgent.php:5628` — Fyn's `capture_retirement_goals` handler.
  This is the **only** writer.

`users` has no `target_retirement_income` column at all (verified against the live
schema), so `/settings/personal` cannot hold it either — `PersonalInformation.vue`
exposes `target_retirement_age` and nothing else retirement-related.

The consequence is that `RequiredCapitalCalculator::getRequiredIncome()`
(`app/Services/Retirement/RequiredCapitalCalculator.php:121-132`) always falls through
to its documented fallback, `(gross income − pension contributions) × 75%`:

| Account | Persona target | App's derived figure | Arithmetic |
|---|---|---|---|
| David | £75,000 | **£100,050** | (145,000 − 11,600) × 0.75 |
| Sarah | £55,000 | **£116,250** | 155,000 × 0.75 — her £120,000 salary plus the £35,000 Defined Benefit pension counted as current income |

Both figures were observed live by the previous pass (R-08 §4, "known trap"); the
arithmetic above reproduces them exactly, which confirms the fallback is the source.

`getIncomeSource()` (`:138-145`) returns `calculated` rather than `profile`, so the
API is honest about what it did — but nothing surfaces that distinction to the user,
who sees a target they never set presented as theirs.

### Impact

Every downstream figure for this household is built on the wrong target: required
capital, the retirement income projection, decumulation, capital adequacy, the income
gap, Monte Carlo, and any recommendation keyed on the gap. For David the target is
33% too high; for Sarah 111% too high. A household told it needs £116,250 a year when
it wants £55,000 will be told it is far further behind than it is.

It also means **Pass A of any persona run cannot enter this field at all**. It is
reachable only through Fyn (Passes B and C), so a web-forms pass can never produce a
correct retirement projection for any persona.

### Repro

1. Register a user, complete the profile with an income and a target retirement age.
2. Search `/settings/personal`, `/net-worth/retirement` and `/plans/retirement` for any
   target-retirement-**income** input. There is none.
3. `php artisan tinker` → `RetirementProfile::where('user_id', $id)->first()->target_retirement_income` is `NULL`.
4. `GET /api/retirement/required-capital` returns `income_source: "calculated"` and a
   `required_income` equal to (gross income − pension contributions) × 0.75.

### Evidence

- `app/Services/Retirement/RequiredCapitalCalculator.php:121-132` (fallback), `:138-145`
  (`income_source`), `:156-168` (`calculateUserNetIncome`)
- `app/Services/Onboarding/OnboardingService.php:479-489, 491-505`
- `app/Agents/CoordinatingAgent.php:5615-5636`
- `resources/js/components/UserProfile/PersonalInformation.vue` — no income field
- Live schema: `users` has no `target_retirement_income`; `retirement_profiles` does
- Persona lines: `tests/Persona/peak_earners.md` User Profile "Target Retirement
  Income | £75,000"; Spouse "Target Retirement Income | £55,000"

## Acceptance

- [ ] A retirement target-income input exists on a module screen and persists
      `retirement_profiles.target_retirement_income` — verified by DB row.
- [ ] `GET /api/retirement/required-capital` returns `income_source: "profile"` and
      `required_income` 75000 for David, 55000 for Sarah.
- [ ] Required capital, the income projection, decumulation, capital adequacy and
      Monte Carlo all move when the target changes, in the right direction and
      magnitude.
- [ ] The fallback stays as a fallback: a user who has not set a target still gets a
      sensible derived figure, and the UI says it is derived rather than presenting it
      as the user's own target.
- [ ] `target_income_percent` (0.75) keeps coming from `TaxConfigService`
      (`retirement.target_income_percent`) — it already does; do not hardcode it while
      fixing this.
- [ ] The hardcoded `35000` at the end of the fallback chain in
      `CapitalAdequacyTab.vue:323` and `PensionList.vue:593` is removed or moved into
      configuration. (Secondary: those components read the correct key — `profile` is
      the `RetirementProfile` from `GET /api/retirement`, `RetirementController.php:95,125`
      — so the hardcode only fires when both the API value and the profile value are
      absent.)
- [ ] ONE entry point feeding all surfaces (Rule 20) — not a web form plus a separate
      `/m` field plus a separate native field.
- [ ] `/m` and iOS retirement screens can set and display it (Rule 19).
- [ ] Re-verified live in the browser by the persona run, both accounts.

## Working notes

Sarah's £116,250 is worth a second look on its own account: it implies her Defined
Benefit pension income of £35,000 is being counted as **current** income in
`calculateUserNetIncome()`. If that is what `total_annual_income` contains, it is
arguably wrong independently of this item — she is not receiving that pension yet.
Confirm what feeds `income_occupation.total_annual_income` before deciding whether
that is a separate defect.

---

### fix-batch-E — 2026-08-21, handed to quality-lead

Branch document: `workforce/branches/fixes/F-0004-batch-e-retirement-income.md`.

**What was already here.** A previous `fix-batch-E` had built the whole backend and
never reported it. Verified green before touching anything: `RetirementProfileStore`,
`UpdateRetirementGoalsRequest`, `RetirementController::updateRetirementGoals`
(`:572-608`), `PUT /api/retirement/goals` (`routes/api.php:1048-1050`) and 11 tests —
11 passed, 33 assertions. **Zero frontend existed**: `grep -rn "retirement/goals"
resources/ app/ ios-native/` returned nothing, so the endpoint had no caller anywhere.
`dbPensionFields.js` and the two form diffs are batch C's W-0017 work, not this item's.

**Two defects in the inherited backend, both found by the Rule 20 enumeration.**

1. The endpoint did not mirror `users.target_retirement_age`. Fyn's handler carried
   that mirror alone, and it is load-bearing — `RetirementProjectionService`, the "When
   you want to retire" data requirement and `ModuleAvailabilityProvider` all read the
   `users` column, so a user who set 60 through this endpoint would still have seen the
   default 67 on `/retirement` with the checklist item outstanding. Moved into
   `RetirementProfileStore::mirrorRetirementAgeOntoUser()`, so every surface gets it.
2. Two mechanisms wrote `retirement_profiles`: the new store, and
   `CoordinatingAgent::handleCaptureRetirementGoals` doing its own
   `RetirementProfile::create`. Fyn now calls the store
   (`CoordinatingAgent.php:5758-5789`); the park-vs-write branch stays in the agent
   because it is conversational protocol, not persistence. Pinned by a test asserting
   the module form and Fyn write a byte-identical row.

**Built.**
- `resources/js/components/Retirement/RetirementTargetCard.vue` (new) — the entry
  point. States the target, says whether it was chosen or derived, edits inline, emits
  `save` (Rule 3).
- `resources/js/components/NetWorth/PensionList.vue` — renders the card above the tabs
  and the empty state; `handleRetirementTargetSave()`; `targetIncomeIsStated` /
  `targetIncomeLabel`; `loadProjectionsAndStrategies()` now fetches required capital
  even with zero pensions, matching `/m`.
- `resources/js/services/retirementService.js`, `resources/js/store/modules/retirement.js`
  — service method and Vuex action.
- `resources/mobile/views/modules/Retirement.vue` — the same card, the same endpoint
  via `apiPut`, following the inline-edit pattern batch C established at
  `PersonalInformation.vue:95-140`. Also now fetches `/api/retirement/required-capital`,
  which closes a pre-existing `/m`-only gap: the analysis reads the profile with no
  fallback, so `/m` showed "—" where web showed a derived figure.
- The hardcoded `35000` is gone from `CapitalAdequacyTab.vue:323` and
  `PensionList.vue:593`; both now read 0 and say "Not set", and the verdict colour on
  "Projected Gross Income" is suppressed with no target (green there used to mean
  "beats the invented £35,000").

**Decisions.**
- The entry point is the retirement module screen, **not** `/settings/personal`. Adding
  a `users.target_retirement_income` column or a second profile-page form would be a
  second entry point, which the acceptance forbids.
- The derived figure never pre-fills the edit box on either surface — that would turn
  "we worked this out" into "you chose this" on a save the user never thought about.
- Fyn now refuses to invent a `current_age` of 30 when the date of birth is unknown; it
  returns a validation error and asks. `PensionProjector::getCurrentAge()` prefers
  `current_age` over the date of birth, so the old fabrication silently shifted every
  projection. Behaviour change on a previously untested path; pinned by test.
- The Vuex action's post-save refetch sits outside the failure-reporting try and
  swallows its errors: the target is saved by then, and a Monte Carlo timeout reported
  as a failed save would have the user retype a figure the database already holds.

**Traced, not tested.** Required capital, the income projection, decumulation, capital
adequacy and Monte Carlo all read one source, and that source is tested (`income_source`
flips `calculated`→`profile`, 90000→55000). Consumers: `RetirementProjectionService.php:255,380`,
`RetirementIncomeService.php:74,162`, `RetirementAgent.php:121`. No live Monte Carlo
test was added — slow and flaky, and live re-verification is the persona tester's.

**Noticed, deliberately not fixed.** Native (SwiftUI) displays a stated target already
(`ios-native/Fynla/Features/Retirement/RetirementModels.swift:52-56`) and sets one via
Fyn, which now writes through the shared store — but it does not show the derived
figure or say it is derived. That is exactly what `/m` did before this batch. Left
because native is a separate codebase with its own release cadence and the change
needs a new client call, model and view I cannot build or run from here. Recommend a
separate item.

**Tests.** `RetirementGoalsTest` 15 passed / 42 assertions (4 added);
`RetirementTargetCard.spec.js` 10 passed (new); `RetirementTarget.spec.js` 7 passed
(new, `/m`). Wider families: 245 passed / 754 assertions across `Feature/Retirement`,
`Feature/Income`, the pension stores, `Feature/AI/DirectWrite`,
`UpdateRecordSecurityTest` and `CoordinatingAgentCaptureIntegrityTest`; 101 passed /
235 assertions across the onboarding and campaign families. Pint clean.
