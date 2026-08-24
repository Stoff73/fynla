---
id: F-0001
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/06-commercials.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-21T11:20:00Z
status: active
---

# Batch C — Retirement, Profile & Gates

Branch document for the five-item fix batch dispatched to `build-lead` from the
`persona-run-peak_earners-2026-08-20` mission. Doubles as the **Rule 22 context
handover**: everything a replacement agent needs to continue without re-deriving
anything.

**Board items:** W-0010, W-0017, W-0006, W-0011, W-0018.
**Handoff notes:** `workforce/ops/handoffs/W-{0006,0010,0011,0017}/build-lead-to-quality-lead-2026-08-21.md`.

**Context position at time of writing: roughly 400k of the 1M window — about 45%
of the way to the 900k buffer.** No harness pressure signal received. I am NOT at
the threshold; this document exists because the standing rule asks for the
reasoning to be recorded regardless of position, and because W-0018's argument is
expensive to rebuild.

---

## 1. The dispatch, verbatim

> Fix **Batch C — Retirement, Profile & Gates**, five board items found by a live
> persona run. Work in `/Users/CSJ/Desktop/fynla` on branch `dev`.
>
> ## Your items (read each board file in full first)
>
> - `workforce/ops/board/W-0010-no-way-to-add-pension-when-only-db-pension-exists.md` — **highest value, do FIRST.** Hard dead-end: after saving a Defined Benefit pension, `/net-worth/retirement` renders no "Add Pension" control anywhere in the DOM, while the completeness banner still asks for money-purchase pensions and the State Pension forecast. You need a DC pension to reveal the button that adds a DC pension. The persona's State Pension could not be entered at all because of this.
> - `W-0017-db-pension-form-cannot-hold-four-persona-fields.md` — Defined Benefit form has no Normal Retirement Age, no Spouse Pension %, only a numeric "Revaluation Rate" where the model wants a CPI/RPI/fixed/none enum, and no career-average/public-sector scheme type (the NHS 2015 scheme had to be stored as `final_salary`).
> - `W-0006-health-lifestyle-fields-never-persist.md` — `UpdatePersonalInfoRequest` whitelists `good_health`/`smoker`, columns that do not exist; the real `health_status`/`smoking_status` are stripped by `validated()`. Separately `UserResource` exposes none of `health_status`/`smoking_status`/`education_level`, so even the value that DOES persist renders "Not specified".
> - `W-0011-free-tier-cannot-save-any-expenditure.md` — free-tier Simple View always sends the detailed payload and trips the Premium gate, so free users cannot save expenditure at all. Confirmed tier-gated: it saves correctly at premium.
> - `W-0018-tierresolver-docblock-contradicts-code.md` — `TierResolver`'s docblock states "Explicit `users.tier` wins", but `resolve()` delegates wholly to `PremiumEntitlementResolver`, which reads only live Revolut candidates and Apple entitlements and never consults `users.tier`. Proven: setting `users.tier='premium'` alone left the resolver returning `free`; only a Subscription row moved it. `isGrandfatheredLegacyPaid()` still reads `$user->tier`. **Decide which side is correct and flag it for CSJ rather than assuming** — if the column is genuinely dead for gating, the fix may be to correct the docblock and remove the dead read; if it is meant to win, the resolver lost it in a refactor. State your reasoning; do not quietly pick one.
>
> ## Mandatory context
>
> Read `workforce/core/index.md`, then the vault docs for Retirement (`v083/09-MODULES.md`, `Retirement.md`) and Auth/Security (`v083/03-AUTH-SECURITY.md`) for the tier work. Rule 9: no acronyms in user-facing text — "Defined Benefit", "Annual Allowance", not "DB"/"AA". Rule 2: tax values via `TaxConfigService` only.
>
> ## Rule 20
>
> Every fix reaches web AND `/m` (`resources/mobile/` has its own store, router and services and does NOT inherit fixes from `resources/js/`). W-0010's dead-end almost certainly has a `/m` equivalent — check it rather than assuming.
>
> ## Reproduction data is in place — use it, do not disturb it
>
> **David Jones (16)** and **Sarah Jones (17)**, linked, both premium. Sarah has an NHS Defined Benefit pension (£35,000 p.a., £105,000 lump sum) stored with `normal_retirement_age` NULL, `spouse_pension_percent` NULL and `inflation_protection` 'none' against a persona that specifies 60 / 50% / CPI — that is W-0017's evidence, reproduce against it. The contract is `tests/Persona/peak_earners.md`.
>
> Do NOT delete or modify these users, do NOT patch DB rows to make anything pass, do NOT `migrate:fresh`, do NOT touch `.env`. Note W-0011 needs a free-tier user to reproduce — create a throwaway one rather than downgrading David or Sarah, and tell me its email so it gets torn down.
>
> ## Definition of done
>
> 1. Root cause fixed once, reaching web and `/m`.
> 2. Targeted tests only — NOT the full suite (CSJ standing instruction). I run the full suite at consolidation.
> 3. `./vendor/bin/pint` clean on what you touched.
> 4. Board items updated with status, file:line and evidence.
> 5. **No PR, no merge, no deploy, no csjones, no prod.** Report back to me; I am coordinating three parallel batches.
> 6. Do not reach into Ownership/Net-Worth (Batch A) or Estate/Wills (Batch B). If a fix collides, stop and tell me.
>
> Report: what you fixed, your reasoning and recommendation on W-0018, tests run with output, any throwaway users created, anything blocked and why.

### Amendments received since

**Amendment 1 — Rule 22, Context Budget (CLAUDE.md:298-323).** Hand over at ~900k
or the first harness pressure signal. Agent writes the handover and returns it;
the coordinator spawns a fresh agent seeded from it. Must carry the dispatch
verbatim, DONE with file:line evidence, IN FLIGHT state, NOT STARTED in priority
order, decisions and reasoning, dead ends ruled out, environment state including
any throwaway free-tier user's email. W-0018's reasoning called out as especially
expensive to rebuild. Report rough context position when reporting.

**Amendment 2 — test database isolation.** `phpunit.xml:46` pins every Pest run to
a shared `laravel_testing`, and three concurrent batches deadlock at migration
time. Run every Pest command as
`DB_DATABASE=laravel_testing_c ./vendor/bin/pest <paths>`. The `<env>` element has
no `force="true"` so the shell variable wins. Batches A and B use `_a` and `_b`.
Do not edit `phpunit.xml` or `.env` — shared config belongs to the coordinator.
Any earlier run that failed with a deadlock or a mass of zero-assertion failures
is environmental; discard and re-run under `_c`.

---

## 2. Status summary

| Item | Status | Surfaces fixed | Live browser verified |
|---|---|---|---|
| W-0010 | DONE, handed to quality-lead | web (fixed); `/m` + iOS checked, no defect | **NO** |
| W-0017 | DONE, handed to quality-lead | web, `/m`, iOS | **NO** |
| W-0006 | DONE, handed to quality-lead | web, `/m` data source | **NO** |
| W-0011 | DONE, handed to quality-lead | web; `/m` + iOS checked, no defect | **NO** |
| W-0018 | GATED on CSJ, no code changed | n/a (backend gating) | n/a |

**Nothing is IN FLIGHT. No edit is half-applied. No item is NOT STARTED.** A
replacement agent inherits a clean tree with all five items resolved to the point
the dispatch allowed.

The only outstanding work inside the batch is **live browser verification**, which
the dispatch reserved for the persona-tester, so Rule 14's loop is deliberately
not closed by build-lead on any item.

---

## 3. DONE — evidence

### W-0010 — pension dead-end

Root cause confirmed as filed. The `pension-cta-row` holding **Add Pension** and
**Upload Statement** sat inside `.pension-cards-column`, which lives in the
`v-else` "Projections Content" arm reached only when
`projections.pension_pot_projection.dc_pension_count > 0`.

- `resources/js/components/NetWorth/PensionList.vue:355-388` — the CTA row moved
  OUT of every projections branch to the end of the `activeTab === 'current'`
  container. One control, one place; the Defined Contribution branch no longer
  carries a copy. The zero-pension empty state keeps its own
  "Add Your First Pension" button at `:45-50`.
- `PensionList.vue:88-96` and `:122-127` — the `guaranteed-income-summary`
  Defined Benefit rows and State Pension row are now clickable into the pension
  detail (`.guaranteed-item-clickable` at `:1413-1421`). **This was a second dead
  end**, not in the item as filed: `selectPension()` was only ever wired in the
  projections arm, so a Defined Benefit-only user could not open, edit or delete
  the pension either. W-0017 cannot be verified against Sarah's existing row
  without it.
- State Pension route confirmed reachable: "Add Pension" opens
  `UnifiedPensionForm` with `initialPensionType: null` → `DCPensionForm`, whose
  dropdown carries `state_pension` (`DCPensionForm.vue:46`) and `final_salary`
  (`:43-45`). No deep link into `activeTab='income'` needed.

Tests: `resources/js/components/__tests__/NetWorth/PensionListAddControl.spec.js`
— 5 specs (all four entry orders, empty state, clickable Defined Benefit row).

### W-0017 — Defined Benefit form

- **NEW** `resources/js/components/Retirement/dbPensionFields.js` — the one
  definition of `DB_SCHEME_TYPE_OPTIONS`, `DB_INFLATION_PROTECTION_OPTIONS` and
  `buildDbPensionPayload()`. Both forms compose from it.
- `DCPensionForm.vue:445-459` scheme type select; `:508-540` Normal Retirement Age
  + Spouse Pension %; `:558-591` Inflation Protection select and the conditional
  fixed rate; `:1281-1296` submits through the shared mapper; `:1275-1279`
  destructure extended so the new `db_*` keys cannot leak into a Defined
  Contribution payload; `:42-45` dropdown label now
  "Defined Benefit (Final Salary, Career Average or Public Sector)".
- `DBPensionForm.vue:74-81` scheme type from shared options; `:151-184` Normal
  Retirement Age + Spouse Pension %; `:198-233` Inflation Protection + conditional
  fixed rate; `:334-355` corrected edit population; `:404-460` submit through the
  shared mapper.
- `fyn-memory/procedural/tool_schema/savings/create_pension.md` and
  `create_pension.xai.md` — version 3 → 4, `effective_from` 2026-08-21. Added
  `spouse_pension_percent`, `inflation_protection`, `lump_sum_entitlement`; added
  `public_sector` to the `scheme_type` enum. xAI variant also adds the three to
  `required` (that schema is `strict: true` with everything required).
- `tests/fixtures/ToolSchema/getTools_anthropic_live.json`,
  `tests/fixtures/ToolSchema/getTools_xai_live.json`,
  `tests/fixtures/XaiToolSchema/getTools_xai_live.json` re-recorded. Diff touches
  `create_pension` only — verified by reading the diff, not assumed.
- `app/Constants/UpdateRecordAllowlist.php:73-82` — `db_pension` gains
  `scheme_type`, `spouse_pension_percent`, `inflation_protection`,
  `revaluation_method`, `lump_sum_entitlement`.
- `app/Agents/CoordinatingAgent.php:3349-3352` — validation rules for the three
  new `create_pension` params.
- `app/Services/Stores/PensionStore.php:617-619` — `inflation_protection`
  tightened from `string|max:64` to `in:cpi,rpi,fixed,none`.

Tests: `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php` (3 new),
`tests/Feature/AI/DirectWrite/CreatePensionTest.php` (4 new),
`resources/js/components/__tests__/Retirement/DbPensionFields.spec.js` (7).

### W-0006 — health / lifestyle

- `app/Http/Requests/UpdatePersonalInfoRequest.php:57-63` — real enums for
  `health_status` and `smoking_status`; dead `good_health` / `smoker` rules gone.
  `smoking_status` deliberately NOT `nullable` (the column is NOT NULL).
- `:32-43` — `prepareForValidation()` drops an unanswered select's key.
- `app/Http/Resources/UserResource.php:32-38` — exposes all three.
- `app/Services/UserProfile/UserProfileService.php:86-90` — the **third**
  publisher of the dead names, corrected. This is `GET /api/user/profile`, which
  is what `/m` reads (`resources/mobile/views/PersonalInformation.vue:162`).
- `resources/js/store/modules/userProfile.js:188-191` — same two dead keys in the
  Vuex `personalInfo` object, corrected.

Tests: `tests/Feature/Api/UserProfileControllerTest.php` (4 new).

### W-0011 — free-tier expenditure

- `resources/js/components/UserProfile/ExpenditureForm.vue:2254-2262` — the
  unconditional category append now guarded by `if (!useSimpleEntry.value)`. Same
  guard on both spouse payloads (`:2280-2284`, `:2296-2300`).
- `app/Http/Controllers/Api/UserProfileController.php:497-533` —
  `guardDetailedExpenditure()`; called at `:157-159` (`updateExpenditure`) and
  `:387-389` (`updateSpouseExpenditure`).
- `:26-37` — `use_simple_entry` and `use_separate_expenditure` removed from
  `DETAILED_EXPENDITURE_FIELDS`.
- `resources/js/store/modules/auth.js:36-42` — one `hasCapability` getter
  mirroring `TeaserGate::allows` including the admin / preview bypass.
- `ExpenditureForm.vue:397-400` toggle hidden without the capability;
  `:1371-1377` default; `:2212-2217` stored-mode override; `:2383-2385` AI-fill
  no longer forces Detailed View.

Tests: `tests/Feature/Fyn/DetailedExpenditureGateTest.php` (3 new),
`resources/js/components/__tests__/UserProfile/ExpenditureSimpleEntry.spec.js` (5).

---

## 4. W-0018 — the full argument, recorded so nobody re-derives it

**No code was changed. The item remains `gated` on CSJ.**

### Recommendation: option (b) — the column is dead for gating, deliberately. The docblock is stale, not the code.

### What was independently verified

- `app/Services/Tiers/TierResolver.php:26-29` — `resolve()` delegates wholly to
  `PremiumEntitlementResolver::resolve()`.
- `grep -c '\->tier' app/Services/Billing/PremiumEntitlementResolver.php` → **0**.
  It branches on `is_preview_user`, then `resolveLiveProviders()` (`:69-102`):
  live Revolut candidates plus live Apple entitlements. `users.tier` plays no part.
- `TierResolver.php:38` is the ONLY decision-making read of `$user->tier` anywhere
  in `app/`. Confirmed by grepping `\->tier\b` across `app/` and discarding
  `tier_configurations` / `ResolvedEntitlement` hits.
- Writers of `users.tier`: `AuthController.php:638`,
  `SubscriptionRenewalService.php:237`, `SubscriptionExpiryService.php:76`,
  `PendingRegistration.php:109`, `E2EController.php:153`. All cache maintenance.

### The decisive evidence is not in the code — it is in the plan that produced it

`codex/plans/ios/2026-07-14-ios-04-storekit-entitlements.md:95-96`:

> - [ ] Make `TierResolver` use this resolver for paid access and otherwise return
>   Free. **A stale `users.tier='premium'` without a live provider grant must not
>   grant Premium.**
> - [ ] Provider event handlers may maintain `users.tier` as a query cache, but
>   capability checks use the resolver.

Corroborated by
`codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md:706`:
"This does not grant paid access because `TierResolver` still resolves
`users.tier='free'` and `DbTierGate` still applies Free limits."

So the current behaviour is the **intended** one. The docblock's "Explicit
`users.tier` wins" predates the StoreKit entitlements work and was never updated.

### Why the "internal inconsistency" the item raises is not one

`isGrandfatheredLegacyPaid()` reads `$user->tier` to ask *"has this user been
assigned a new tier yet?"* — `in_array($user->tier, TierConfigurationStore::TIERS)`
returning true means NOT grandfathered. That is the column used as a
**migration-cohort marker**, which is precisely what a cache is for. It never
grants entitlement; it decides whether a legacy paid subscriber's existing-data
creates should be spared the new cap. Reading a cache to answer "has this been
migrated" is fully consistent with (b). The asymmetry the item flagged is
therefore **not** evidence that the column was meant to win.

### The one thread that could not be pulled

The docblock cites "Spec §5.2". No §5.2 stating that the column wins could be
located anywhere in the repo. It is possible an older superseded spec did say so.
**CSJ should confirm this** — it is the only route by which (a) could still be right.

### If CSJ confirms (b), the work is small

1. Rewrite the `resolve()` docblock: entitlement is provider-truth only;
   `users.tier` is a maintained cache. Cite the iOS-04 decision.
2. Leave `isGrandfatheredLegacyPaid()`'s read in place, with a comment naming it a
   migration-cohort marker rather than an entitlement source.
3. Add a test: `users.tier = 'premium'` alone resolves `free`; adding a live
   entitlement resolves `premium`; setting `users.tier` does NOT flip
   `isGrandfatheredLegacyPaid()` for a user with no legacy plan.
4. Sweep other readers — already done, there are none.

### If CSJ says (a)

**Do NOT just add a check at the top of `resolve()`.** That directly reverses "a
stale `users.tier='premium'` must not grant Premium", and turns every one of the
five writers above into an entitlement grant — including the test-support
endpoint `E2EController.php:153`, which accepts `tier` from a request body. That
is a security-shaped change and needs its own board item with a security review.

---

## 5. Decisions taken, and why — do not re-litigate

1. **W-0017 consolidation.** There are TWO Defined Benefit forms —
   `DCPensionForm.vue` (ADD, what the persona used) and `DBPensionForm.vue` (EDIT
   + onboarding via `Onboarding/steps/AssetsStep.vue:447`). Editing one and not the
   other is the disease Rule 20 names, so a shared module was created rather than
   patching both templates in lockstep. A full component extraction was rejected:
   the two forms use different field-name prefixes (`db_*` vs unprefixed) which the
   `aiFormFill` highlight sequences key on, so extracting the fieldset risked
   breaking AI form-fill for no gain over sharing the enums and the mapper.
2. **W-0011 commercial question — answered from the enforcing layer, not escalated.**
   `database/seeders/TierConfigurationSeeder.php:36-37` gives Free
   `'expenditure' => 'full'` and `'expenditure_detailed' => 'none'`; `:79` gives
   Premium both. Simple expenditure IS free by design. `CoordinatingAgent`'s
   `handleUpdateProfile` (`section === 'expenditure'` with a monthly total) already
   wrote a simple total for any tier with no gate, while `handleSetExpenditure`
   (`:4952`) gated only the category write. The HTTP controller was the one
   mechanism that disagreed. **Do not re-raise this with CSJ.**
3. **W-0011 strips rather than writes the incidental zeros.** A free user's
   previously stored category values are not theirs to clear through a form that
   never showed them. Pinned by a test. If a downgrade should zero the categories,
   that is the opposite behaviour and needs stating.
4. **W-0011 hides the Detailed View toggle rather than disabling it with an
   upsell.** A design call taken by build-lead; small to reverse, in one place.
5. **W-0017 fixed revaluation rate is conditional on the "Fixed rate" option**,
   because that is the only branch `PensionProjector::getRevaluationRate()` reads
   `revaluation_method` in. Nothing is lost: with `inflation_protection` defaulting
   to `'none'`, a typed number was never applied.
6. **W-0017 `scheme_status` requirement relaxed on EDIT only.** The field has no
   `db_pensions` column and is discarded on every save, so requiring it on an edit
   blocked the user for no benefit. The dead field itself was left alone.
7. **W-0006 unanswered select means "leave it alone", never "clear it".** There is
   no clear-field affordance in the UI. Consequence: an API client cannot null
   `health_status` through this endpoint.
8. **`spouse_pension_percent` is percentage points (50), not a decimal (0.5).**
   Written into the tool-schema descriptions explicitly. Evidence: column
   `decimal(5,2)`, `StoreDBPensionRequest.php:47` and `PensionStore.php:615` both
   `max:100`, `HouseholdPlanningService.php:791` divides by 100,
   `DBPensionFactory.php:45` uses 50.0/66.67/100.0.
9. **No throwaway free-tier user was created.** See §7.

---

## 6. Dead ends ruled out — do not re-walk these

- **The vault Known Issue claiming Defined Benefit projections ignore
  `inflation_protection` / `revaluation_method` is FALSE.**
  `app/Services/Retirement/PensionProjector.php:113-119` branches on
  `inflation_protection` (cpi 2.5%, rpi 3%, fixed → parsed from
  `revaluation_method`, none 0%, default 2%); `:92` uses `normal_retirement_age`;
  both are consumed by `projectAllPensions()` at `:201`. The Known Issue in
  `Current State/Retirement.md` can be closed. Do not re-investigate.
- **W-0011's "silent 403" is not silent.** `userProfile.js:289-293` rethrows and
  `ExpenditureOverview.vue:123-125` renders `err.response.data.message` in a banner
  at the top of the card. What the persona saw was a banner above the fold. Moot
  now — the 403 no longer occurs — but do not go looking for a swallowed error.
- **`/m` has no W-0010 equivalent.** `resources/mobile/views/modules/Retirement.vue:190-197`
  builds the `action: 'add'` contextual request unconditionally (suppressed only at
  the tier cap); `RetirementPensionDetail.vue:190-205` does the same for edit. The
  add affordance does not depend on pension mix. Checked, not assumed.
- **`/m` has no expenditure form.** `resources/mobile/views/Expenditure.vue` is 104
  lines and read-only (`apiGet` only). Entry is via Fyn.
- **`/m` renders no health, smoking or education field.** Zero grep hits across
  `resources/mobile/`. Its Personal Information screen has About you / Household /
  Dependants / Domicile / Financial summary only.
- **`ComprehensiveProtectionPlanService.php:193,197` is dead code, not a live
  fault.** `isset($user->smoker)` and `isset($user->good_health)` are always false
  (no property, no accessor), so both branches fall through to
  `$profile->smoker_status` / `$profile->health_status` on the protection profile —
  a real, working source. Left alone deliberately.
- **`RetirementActionDefinitionService.php:1656-1665`** reads the protection
  profile, not the `users` columns. Not affected by W-0006.
- **The eslint errors in `DCPensionForm.vue` are pre-existing.** The
  destructure-to-omit pattern produced 14 `no-unused-vars` errors on HEAD and 18
  after the change — same class, extended list. Verified by linting the HEAD
  version of the file. Not a regression.

---

## 7. Environment state

- **Reproduction data intact and unmodified.** Verified after all edits:
  David Jones id 16 `david.jones@example.com` tier=premium; Sarah Jones id 17
  `sarah.jones@example.com` tier=premium; `db_pensions.id = 4` "NHS Pension Scheme"
  still `scheme_type='final_salary'`, `normal_retirement_age NULL`,
  `spouse_pension_percent NULL`, `inflation_protection='none'`. **Deliberately left
  unpatched** so it serves as the acceptance test for the repaired edit path.
- **NO THROWAWAY FREE-TIER USER WAS CREATED. There is nothing to tear down.**
  The dispatch anticipated one for W-0011; it proved unnecessary. Tier behaviour is
  reproduced in tests with `User::factory()->create()` (free) versus
  `User::factory()->withActivePremiumSubscription()->create()`, which is stronger
  evidence than a hand-made account and leaves no residue. If the persona re-run
  wants a live free account for the browser pass, it must create one — David and
  Sarah are premium and were not downgraded.
- **No migrations added. `.env` untouched. `phpunit.xml` untouched.**
- **Test database: `laravel_testing_c`** (171 tables). Every Pest command must be
  run as `DB_DATABASE=laravel_testing_c ./vendor/bin/pest <paths>`.
- **No collision with Batch A (Ownership/Net-Worth) or Batch B (Estate/Wills).**
  The diff of every touched file was scanned for foreign hunks; none found.
  `PensionList.vue` sits under `components/NetWorth/` but is pension-specific and
  was not touched by Batch A.
- No PR opened, no merge, no deploy, no csjones, no prod. Branch is `dev`.

---

## 8. Files changed

```
app/Agents/CoordinatingAgent.php                      (+3 validation rules)
app/Constants/UpdateRecordAllowlist.php               (+5 allowed db_pension fields)
app/Http/Controllers/Api/UserProfileController.php    (guardDetailedExpenditure)
app/Http/Requests/UpdatePersonalInfoRequest.php       (real enums + empty-select handling)
app/Http/Resources/UserResource.php                   (+3 fields)
app/Services/Stores/PensionStore.php                  (inflation_protection enum rule)
app/Services/UserProfile/UserProfileService.php       (dead column names corrected)
fyn-memory/procedural/tool_schema/savings/create_pension.md      (v3 -> v4)
fyn-memory/procedural/tool_schema/savings/create_pension.xai.md  (v3 -> v4)
resources/js/components/NetWorth/PensionList.vue      (CTA hoist + clickable rows)
resources/js/components/Retirement/DBPensionForm.vue  (4 fields + edit mapping)
resources/js/components/Retirement/DCPensionForm.vue  (4 fields + shared mapper)
resources/js/components/Retirement/dbPensionFields.js (NEW - shared definition)
resources/js/components/UserProfile/ExpenditureForm.vue (simple-entry payload + gating)
resources/js/store/modules/auth.js                    (hasCapability getter)
resources/js/store/modules/userProfile.js             (dead column names corrected)
tests/fixtures/ToolSchema/getTools_anthropic_live.json      (re-recorded)
tests/fixtures/ToolSchema/getTools_xai_live.json            (re-recorded)
tests/fixtures/XaiToolSchema/getTools_xai_live.json         (re-recorded)

tests added/extended:
tests/Feature/Api/UserProfileControllerTest.php               (+4)
tests/Feature/Fyn/DetailedExpenditureGateTest.php             (+3)
tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php  (+3)
tests/Feature/AI/DirectWrite/CreatePensionTest.php            (+4)
resources/js/components/__tests__/NetWorth/PensionListAddControl.spec.js       (NEW, 5)
resources/js/components/__tests__/Retirement/DbPensionFields.spec.js           (NEW, 7)
resources/js/components/__tests__/UserProfile/ExpenditureSimpleEntry.spec.js   (NEW, 5)
```

---

## 9. Test results

All re-run under `DB_DATABASE=laravel_testing_c` after Amendment 2. Targeted
families only — no full suite, per the standing instruction.

| Command | Result |
|---|---|
| `tests/Feature/Fyn/DetailedExpenditureGateTest.php` + `tests/Feature/Api/UserProfileControllerTest.php` | 22 passed (98 assertions) |
| `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php` + `tests/Feature/AI/DirectWrite/CreatePensionTest.php` + both tool-schema golden masters | 33 passed, 2 skipped (89 assertions) |
| `tests/Unit/Services/Tiers` + `tests/Unit/Services/Stores/PensionStoreTest.php` + `tests/Unit/Services/Retirement/PensionProjectorTest.php` | 54 passed (113 assertions) |
| `npx vitest run` (full frontend) | 92 files, 949 tests passed |
| `./vendor/bin/pint --test` on all touched PHP | passed |

Earlier runs under the shared `laravel_testing` produced deadlock and
zero-assertion failures — including `MortgageStoreTest` (Batch A's file, never
touched here) failing 7/7 with 0 assertions. Those were environmental and are
discarded per Amendment 2. Every one passed on retry or under `_c`.

---

## 10. Noticed — outside this batch's remit, routed up

1. **HIGH — `spouse_pension_percent` unit contradiction.**
   `app/Services/Documents/FieldMappers/DBPensionMapper.php:96-114`
   (`parseSpousePercent`) converts 50% to **0.50** with the comment "DB stores as
   decimal (0.50 for 50%)". Everything else uses percentage points (see §5.8). A
   Defined Benefit pension imported from an uploaded document therefore stores 0.5,
   and `HouseholdPlanningService.php:791` then computes `annual × 0.005` —
   understating the spouse's pension by 100×. `AIExtractionService.php:496`
   documents the same wrong convention to the extraction model. **Wants its own
   board item at high severity.** Not fixed here: different entry path, and the
   convention should be a recorded decision.
2. **`education_level` rule accepts three values the column cannot hold.**
   `UpdatePersonalInfoRequest.php:64` allows `doctorate`, `foundation`, `hnd`; the
   column is `enum('secondary','a_level','undergraduate','postgraduate',
   'professional','other')`. Unreachable through the UI but a latent 500 for Fyn or
   any API client. Widen the column or narrow the rule.
3. **`scheme_status` is collected by both pension forms and has no column** —
   discarded on every save. Give it a column or take it off the forms.
4. **`/m` has no Health & Lifestyle section.** Its data source is now correct, but
   nothing renders health, smoking or education there. New feature work, flagged
   not skipped, per Rule 19.
5. **Vault correction needed:** `Current State/Retirement.md` Known Issue about
   projections ignoring `inflation_protection` is false (§6).

---

# Batch C2 — W-0030, W-0031, W-0034, W-0018 docblock, vault

Second dispatch to the same agent, same ground. Appended here rather than opened as
a new branch document so the whole batch stays in one place.

## C2 dispatch, verbatim

> **1. `W-0030` — `spouse_pension_percent` unit contradiction (HIGH, your find).** You have the evidence; now fix it. Two acceptance criteria I added beyond your report, both load-bearing:
>    - **Find and migrate existing rows written under the decimal convention.** Determine how many exist before deciding what to do — do not assume zero. Without this the fix is silent and live pensions stay wrong.
>    - **Correct the extraction prompt at `AIExtractionService.php:496`**, which actively teaches the model the wrong unit. Fixing `DBPensionMapper` alone leaves the model producing 0.50 forever.
>    Per Rule 20, converging all seven sites on one recorded convention is the fix; patching the mapper is not. Evidence favours percentage points 5 sites to 2 — but record the convention where a future contributor will find it.
>
> **2. `W-0031` — `education_level` rule accepts `doctorate`/`foundation`/`hnd`, which the column enum cannot hold.** Latent 500 for Fyn or any API client. Narrow the rule or widen the column — your call, with reasoning. **Do this before item 3**, since a new `/m` select must not offer options that 500 on save. If you widen, Rule 9 applies: "Higher National Diploma", never "HND".
>
> **3. `W-0034` — build the `/m` Health & Lifestyle section. CSJ direction, just now: "/m needs it."** New feature work, and you were right to flag rather than skip. Read the item — it carries an architectural correction worth internalising: **`/m` iframes the funnel, not the authenticated app.** `/m/app/*` is a separate Vue SPA under `resources/mobile/` with its own store, router and services, which is why it inherits nothing from `resources/js/`. This cannot be delivered by pointing an iframe at the web view. Read **and write**, same endpoint and same validation as web — including your `prepareForValidation` fix, so the empty-select-500 trap is not reintroduced on a second surface.
>
> **4. `W-0018` — correct the docblock, no behaviour change.** I put your argument to CSJ; they did not dispute it, and I said I would have the comment corrected. Make `TierResolver`'s docblock say what the code actually does — entitlement comes from live provider grants, `users.tier` is a query cache and a migration-cohort marker, never a grant. **Cite `codex/plans/ios/2026-07-14-ios-04-storekit-entitlements.md:95-96` in the comment** so the next person finds the reasoning instead of re-deriving it. **Leave the "Spec §5.2" question open in the board item** — you could not locate that spec, it is the one route by which (a) could still be right, and it stays flagged for CSJ. Comment only: change no logic.
>
> **5. Vault correction.** You proved the Retirement Known Issue false — `PensionProjector.php:113-119` does apply `inflation_protection` and `:92` uses `normal_retirement_age`. Correct `/Users/CSJ/Desktop/fynlaBrain/Retirement.md` (and `EstatePlanning.md`-style Current State docs if the claim is mirrored), citing the file:line proof. A wrong Known Issue costs every future agent that reads it.

## C2 status

| Item | Status | Browser verified |
|---|---|---|
| W-0030 | DONE — 4/4 acceptance criteria | n/a (backend + migration) |
| W-0031 | DONE — narrowed, with a chain that prevents recurrence | NO |
| W-0034 | BUILT — acceptance 1-6 done, **7 blocked** | **NO — blocked, see below** |
| W-0018 | DONE — docblock only, zero logic change | n/a |
| Vault | DONE — 3 false claims corrected + convention recorded | n/a |

Nothing in flight, nothing half-applied.

## The enforcement chain C2 built

The single most reusable outcome. Three of these five items were the same disease —
a hand-written copy of a truth that lives somewhere else — so the fix was a chain
where every link is a test:

    users columns
      ─▶ App\Constants\ProfileEnums              (tests/Unit/Database/ProfileEnumColumnsTest.php)
        ─▶ resources/js/constants/profileOptions.js    (ProfileOptionsParity.spec.js)
          ─▶ resources/mobile/constants/profileOptions.js  (profileOptionsParity.spec.js)

A select can no longer offer a value the column rejects, on any surface. That is
what W-0031 was, and it reached a user as an HTTP 500.

## Decisions taken in C2 — do not re-litigate

1. **W-0031: narrow, not widen.** The board's stated preference rested on "three
   values no surface offers" — disproved: `PersonalInformation.vue:326-334` offered
   all three from a live select, and all three returned HTTP 500 (proved
   empirically before changing anything). Narrowed anyway, for different reasons:
   the column plus two of three selects say six (same majority test used for
   W-0030); zero users can hold the three today so nothing migrates; narrowing is
   the reversible direction; and it is a net gain, since that select could not
   record Secondary, A-Levels or Professional Qualification at all.
2. **W-0030: `parsePercentage()` deliberately NOT changed.** Savings and mortgage
   `interest_rate` genuinely store fractions and
   `SavingsThreeIngestParityTest.php:202` pins that. A new
   `parsePercentagePoints()` sits alongside it; merging the two would trade this
   bug for another.
3. **W-0030: affected rows identified by value, in (0,1).** Documented in the
   migration docblock along with the one case it cannot separate (a fraction that
   truncated into a plausible points value).
4. **W-0034: a section on the existing `/m` profile screen, not a new route.** That
   screen already fetches the corrected payload, and `/m` already collapses web's
   separate Domicile section the same way.
5. **W-0034: `/m` option labels identical to desktop, acronym included.** Divergent
   wording for one field across surfaces is a worse failure than the acronym.
   "Secondary (GCSE/O-Levels)" is raised for CSJ on W-0031 as one decision covering
   all three surfaces.
6. **Vault: `Current State/Fixes/retireFix.md` left alone.** It is the historical
   statement of the problem as found in February, and
   `Feb/Feb21Updates/currentStateFixesVerify.md:251` already records it APPLIED.
   Only the *current state* doc misleads, so only it was corrected.

## Dead ends and traps ruled out in C2

- **The Pint / formatter hook strips a just-added `use` statement while it is still
  unused.** Adding `use App\Constants\ProfileEnums;` before adding a reference to it
  left the import silently removed, and every request through
  `UpdatePersonalInfoRequest` then 500'd — 9 tests red for a reason that looked
  nothing like the cause. This is the trap recorded in the
  `reference_worktree_symlinked_vendor_break` memory. **Add the reference and the
  import in the same edit, and check `grep '^use '` after any formatter run.**
- **MySQL will not take a bound parameter in DDL.** `ALTER TABLE ... COMMENT ?`
  fails with a syntax error; the comment must be inlined.
- **`tests/Unit/Constants` is not bound in `tests/Pest.php`** — a test placed there
  gets "A facade root has not been set". `Unit/Database` is bound and is where
  schema-conformance tests already live. `Pest.php` is shared config; it was not
  edited, to avoid colliding with a sibling batch.
- **The mapper's public method is `map()`, not `mapFields()`.**
- The vault claim that projections ignore `inflation_protection` is false, and was
  false since February — see the corrected `Current State/Retirement.md:915`.

## Blocked

**W-0034 acceptance 7 — browser verification on `/m`, both accounts.** Not done and
not claimable. It needs the built mobile bundle: csjones serves it and is out of
scope for this batch, and building `public/m-build/` locally is a raw vite build,
which CLAUDE.md forbids without asking. Needs either a sanctioned local mobile
build or a csjones deploy — the coordinator's call.

The four C1 items also remain unverified in a browser, unchanged from C1.

## C2 files changed

```
app/Constants/ProfileEnums.php                                    NEW
app/Http/Requests/UpdatePersonalInfoRequest.php                   composes from ProfileEnums
app/Models/DBPension.php                                          UNIT CONVENTION docblock
app/Services/Documents/AIExtractionService.php                    extraction prompt unit
app/Services/Documents/FieldMappers/AbstractFieldMapper.php       parsePercentagePoints()
app/Services/Documents/FieldMappers/DBPensionMapper.php           converged
app/Services/Documents/FieldMappers/DCPensionMapper.php           converged
app/Services/Tiers/TierResolver.php                               docblock only, zero logic
database/migrations/2026_08_21_120000_correct_spouse_pension_percent_convention.php  NEW
resources/js/constants/profileOptions.js                          NEW
resources/js/components/UserProfile/HealthInformation.vue         3 selects + 3 label maps -> shared
resources/js/components/UserProfile/PersonalInformation.vue       the 500-producing select
resources/js/components/Onboarding/steps/PersonalInfoStep.vue     3 selects -> shared
resources/mobile/constants/profileOptions.js                      NEW
resources/mobile/views/PersonalInformation.vue                    Health & lifestyle section (read + write)
/Users/CSJ/Desktop/fynlaBrain/Current State/Retirement.md         3 false claims + convention

tests:
tests/Unit/Database/ProfileEnumColumnsTest.php                              NEW (4)
tests/Unit/Services/Documents/FieldMappers/SpousePensionPercentConventionTest.php  NEW (12)
tests/Feature/Database/SpousePensionPercentBackfillTest.php                 NEW (5)
tests/Feature/Api/UserProfileControllerTest.php                             +2
resources/js/components/__tests__/UserProfile/ProfileOptionsParity.spec.js  NEW (6)
resources/mobile/__tests__/profileOptionsParity.spec.js                     NEW (8)
resources/mobile/views/__tests__/HealthLifestyleSection.spec.js             NEW (7)
```

---

# Close-out — 2026-08-21

Batch C accepted 4/5 + 1 gated; batch C2 accepted 5/5. Two closing tasks completed
on team-lead instruction, then this branch is finished.

## Knowledge recorded permanently

The three traps that cost time in C2 are now in the repo, not just in this
document — they are the kind of knowledge that leaves with the agent.

| Trap | Home |
|---|---|
| Formatter deletes a just-added `use` while momentarily unreferenced | `tests/CLAUDE.md` §"When a green suite goes inexplicably red" (1); memory `reference_formatter_strips_new_use_import` |
| MySQL rejects a bound parameter in DDL | `database/CLAUDE.md` §Migrations |
| `tests/Unit/Constants` unbound in `Pest.php` → "A facade root has not been set" | `tests/CLAUDE.md` §"When a green suite goes inexplicably red" (2) |

Framing chosen deliberately: two of the three present as *tests failing for a
reason unrelated to the test*, so they are grouped under that symptom in
`tests/CLAUDE.md` — which is where someone will be looking when it happens to
them, rather than under the tool that caused it.

Three further things were recorded alongside them, because they are the same class
of "invisible until it hits everyone at once":

- **Run a new migration once before it lands** (`database/CLAUDE.md`). A migration
  that has never executed is invisible until `RefreshDatabase` applies it in every
  batch's database simultaneously and every DB-touching test fails with 0
  assertions. Raised by Batch A; it cost them a 467-second run.
- **`php artisan migrate --path=<file>` on a shared dev database**
  (`database/CLAUDE.md`). Bare `migrate` applies everyone's pending migrations,
  including data migrations that belong to another owner. This is not theoretical:
  batch 48 on local dev contains this batch's `correct_spouse_pension_percent_convention`
  alongside another batch's `business_interests` migration, applied by whoever ran
  bare `migrate` — the team lead had deliberately held off running it for exactly
  that reason and the shared database removed the choice.
- **A data migration must fix what was DERIVED from the bad data**
  (`database/CLAUDE.md`). Correcting a source column while leaving a cached column
  computed from its old value makes the fix look complete while the wrong number
  still renders.

The contention fingerprints are recorded too (`tests/CLAUDE.md` §3), with the one
test that separates contention from a real failure: **re-run the file on its own.**

## `Pest.php` deliberately not edited

Binding `Unit/Constants` would have been the obvious fix for trap 3. `Pest.php` is
shared config and a sibling batch could be in it, so the test moved to the already
bound `Unit/Database` instead — where the sibling schema-conformance test
`UsersExpenditureColumnTypesTest` already lives, which makes it the better home
regardless. Confirmed as the right call by the team lead.

## Rule 9 acronym — routed, not decided

"Secondary (GCSE/O-Levels)" now appears on three surfaces. It was carried to `/m`
verbatim rather than reworded, because divergent wording for one field across
surfaces is a worse failure than the acronym. Routed to **design-lead**, whose
remit copy is — not CSJ's, as originally assumed. Left exactly as-is so
design-lead can replace it on all three surfaces at once.

## Final state of the migration on local dev

Ran at `[48]`, `2026-08-21 12:30:13`, **zero rows corrected** — logged by the
migration itself:

```
[2026-08-21 12:30:13] local.INFO: W-0030: no db_pensions rows used the decimal spouse-pension convention.
```

`db_pensions.id 4` verified untouched after the run, not assumed:
`final_salary` / `spouse_pension_percent` NULL / `normal_retirement_age` NULL /
`inflation_protection` 'none'. Its NULL percentage puts it outside the correction
predicate. Users 16 and 17 present and premium.

The column comment is present, which proves `up()` completed rather than merely
being recorded. Every row holding a percentage is internally consistent
(35000x50% = 17500, 18500x50% = 9250, 22000x50% = 11000), and the correction
branch has executed end to end in test against a seeded decimal row.

Unrelated: `db_pensions` ids shifted between surveys (1,2,3 → 5,6,7) because the
three affected rows belong to **preview personas** and `PreviewUserSeeder`
recreated them on a reseed. Not data loss, and nothing to do with this migration.

## Outstanding when this branch closed

- **`/m` browser verification (W-0034 acceptance 7)** — unblocked by the team lead
  rebuilding the bundle with the sanctioned `npm run build:mobile`; `persona-passA2`
  will close it. Note the corrected sequencing from CSJ: **web and `/m` go green
  LOCALLY, then dev** — `verify-m`'s "verify on csjones" is a general convention
  that does not outrank the plan for this run, so `/m` was never actually blocked
  on a csjones deploy. The refusal to build the bundle unilaterally was still
  right: build artefacts are the coordinator's.
- **Browser verification of all C1 + C2 items** — always scoped to persona-tester,
  never closed here. Rule 14's loop is not closed by build-lead on any item in
  this branch.
- **W-0018** — code and docblock now agree; the **Spec §5.2 question stays open**
  for CSJ. No §5.2 stating that `users.tier` wins could be found anywhere in the
  repo, and it is the only route by which the opposite reading could still be right.

Status: **closed** — then reopened for one further item, W-0036, below.

---

# W-0036 — Defined Benefit pension counted as income in payment

Taken after the C2 close-out, as the highest-value unassigned board item. High
severity, and a tax defect wearing a retirement bug's clothes.

## What it actually was

The item found one copy. **There were three**, byte-identical, each gating the
State Pension correctly on `already_receiving` four lines below a completely
ungated Defined Benefit loop, each with a docblock asserting the check its code did
not perform:

- `UserProfileService::calculateAnnualPensionIncome()`
- `Tax\IncomeDefinitionsService::calculatePensionIncome()`
- `PersonalAccountsService::calculateAnnualPensionIncome()`

The second is why the blast radius is income tax, the Personal Allowance taper and
Child Benefit rather than one retirement screen.

## The fix — convergence, as predicted

- `app/Models/DBPension.php:82-115` — `isInPayment(?int $userAge)`, the per-record
  predicate, plus `DEFAULT_NORMAL_RETIREMENT_AGE = 67`, deliberately equal to
  `PensionProjector::DEFAULT_RETIREMENT_AGE` so a pension cannot count as income
  from one age while being projected forward from another.
- `app/Traits/ResolvesIncome.php:26-58` — `resolvePensionIncomeInPayment()`, the
  household sum plus the State Pension gate. `ResolvesIncome` was already the
  documented home for income resolution, so this is joining an existing single
  source rather than inventing a fourth.
- All three services now `use ResolvesIncome`; each private copy is three lines.

## Verified on the live row, not only in test

```
Sarah (17): age 48, employment £120,000
db_pensions.id 4: accrued 35000.00, nra NULL  ->  isInPayment = FALSE
annual_pension_income  0.00        (was 35,000.00)
total_annual_income    120,000.00  (was 155,000.00)
required_income        90,000.00   (was 116,250.00)
/m income sources      {"employment":120000}   — pension line gone
```

£90,000 is exactly what the acceptance predicted.

## Decisions taken

1. **The form keeps writing `accrued_annual_pension`.** Acceptance asked for a
   deliberate choice. `projected_annual_pension_at_nra_gbp` is *derived*, not
   user-input — `PensionDerivedColumnCalculator::calculateDb()` sets it to a rounded
   copy of the accrued column — so moving the form's write there would make a
   derived column user-authored and collide with W-0017's landed work. The defect
   was never which column held the number; it was that nothing asked whether the
   number was payable yet.
2. **Null Normal Retirement Age falls back to 67 rather than excluding.** Not
   cosmetic: Sarah's real row has NULL there, and excluding-on-null would have
   stripped income from genuinely retired users with the same NULL. Both directions
   are pinned by test.
3. **Null date of birth counts nothing.** Inventing income is the failure being
   fixed, so the conservative direction is the correct one.

## Audit of the other readers (acceptance item 8)

Only those three treated the value as income now. The rest are correct as they
stand and were not touched: `PensionProjector`, `RetirementProjectionService:630`,
`RetirementIncomeService:295` and `RetirementProjectionContractService:90` all want
the future figure by design; `NetWorthService:318` capitalises it as an asset
(x20 plus lump sum); `ModuleDataRequirementsService:722` only asks whether a pension
exists.

## `/m` and iOS — one fix, no second edit

`/m` Income reads `/api/user/profile` → `income_summary` → `incomeSources()` →
`IncomeDefinitionsService`. `/m` Personal Information reads
`income_occupation.total_annual_income` from the same payload. Both corrected by the
same change and verified for Sarah. That is Rule 19 satisfied by construction rather
than by a parallel `/m` fix — the outcome the enforcement-chain work was aiming at.

## Stated honestly rather than claimed

**Child Benefit did not change for Sarah.** The mechanism is corrected, but her
account records zero eligible children, so her figure is £0 either way. The persona
specifies two; they have not been entered. Re-check when they are.

## Answer for W-0032 — does the gate need `scheme_status`?

**Age against Normal Retirement Age is sufficient for the reported defect, and it is
not sufficient in general.** It is wrong in both directions in cases that are common
in exactly Fynla's audience:

- **Early retirement** — drawing a Defined Benefit pension at 57 with a scheme age
  of 60. Real income, counted as zero.
- **Deferral past the scheme age** — 62 with a scheme age of 60 and not yet claimed.
  No income, counted in full.

`scheme_status` ("Active" / "Deferred" / "In Payment") answers exactly this, is
already collected by both Defined Benefit forms, and is already discarded because
there is no column.

**Recommendation for W-0032: give it a column.** It is the cheaper half of the
decision — the forms and their labels already exist, only persistence is missing —
and it converts a heuristic into a stated fact. `DBPension::isInPayment()` is the
single place that would change: prefer `scheme_status === 'In Payment'` when
present, keep age-vs-Normal-Retirement-Age as the fallback for records that predate
the column. The alternative — taking it off the forms — keeps the heuristic and
throws away the one field that would make it exact.

## Sequencing

Landed **before W-0035**, as the tester established. Fixing W-0035 first would have
let an explicit target override the derived figure and hide the phantom income on
the retirement screen while it carried on corrupting tax, Personal Allowance and
Child Benefit.

## Tests

- `tests/Feature/Income/DbPensionNotInPaymentTest.php` — 7 passed
- Profile + income family (`Api/UserProfileControllerTest`,
  `Api/UserProfileIncomeSummaryTest`, `Unit/Services/UserProfileServiceTest`,
  `Unit/Services/UserProfile`, `Feature/Income`) — **76 passed, 368 assertions**;
  nothing had baked in the old behaviour
- Tax family (`Unit/Services/Tax`, `Feature/Tax`) — run separately
- Pint clean

## Noticed, not fixed

`getCompleteProfile()` emits a PHP deprecation from `vendor/.../BelongsTo.php:187`
("Using null as an array offset") on every call. Pre-existing and unrelated; worth
its own item.

Status: **closed.** Nothing in flight, nothing half-applied.
