# Deploy Notes — 30 April 2026 Audit Fixes

**Branch:** `feature/fyn-persona-split` (not yet ready for deploy — accumulating Sprint 0 work)
**Commit:** `0434fa1` — `fix(fyn-ai): apply 15 audit findings (F-1..F-15)`

This commit contains backend-only AI engineering fixes. **No frontend rebuild required** for the Vue store change (it'll ship with the next Sprint 0 frontend bundle).

---

## What changed today (committed in 0434fa1)

### PHP backend (12 files modified, 3 new)

```
NEW   app/Services/AI/AdvicePromptCacheInvalidator.php   F-9
NEW   app/Services/Eval/EvalBypassGate.php               F-12
M     app/Agents/CoordinatingAgent.php                   F-9, F-12
M     app/Http/Middleware/PreviewWriteInterceptor.php    F-12
M     app/Services/AI/AdviceFyn.php                      F-1, F-10
M     app/Services/AI/AdvicePromptBuilder.php            F-14
M     app/Services/AI/Prompts/FcaProcessInstructions.php F-7
M     app/Services/AI/Prompts/UserContentSanitiser.php   F-2
M     app/Services/AI/WriteIntentClassifier.php          F-6
M     app/Services/Eval/EvalDeltaBuilder.php             pint
M     app/Services/Eval/EvalHttpDriver.php               F-12
M     app/Services/Onboarding/AssetCaptureEntityExtractor.php pint
M     app/Services/Onboarding/JourneyFieldResolver.php   pint
M     app/Services/Onboarding/OnboardingChatDirector.php F-11
M     app/Services/Onboarding/OnboardingPromptBuilder.php F-5
M     app/Services/Onboarding/OnboardingStateMachine.php pint
M     app/Traits/HasAiChat.php                           F-3, F-4, F-8, F-12, F-13, F-15
```

### Vue frontend (1 file)

```
M     resources/js/store/modules/aiChat.js   F-1 (new handoff_error SSE handler)
```

### Tests (3 modified/new — no deploy impact)

```
NEW   tests/Feature/Fyn/AdviceFynHandoffErrorTest.php
NEW   tests/Unit/Services/AI/WriteIntentClassifierTest.php
M     tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php
```

---

## When ready to deploy this branch

This branch is part of the Sprint 0 + Sprint 0 recovery work. The full deploy will go through `feature/fyn-persona-split → dev → main` once Sprint 0 is verification-complete (per S0.17 rollup).

When that deploy happens, this commit needs:

| Action | Why |
|---|---|
| Upload all `app/` PHP changes | Direct file replacement |
| Rebuild + upload `public/build/` | Vue store change in `aiChat.js` |
| `php artisan cache:clear` | Drop the F-9 cache keys (`ai_existing_records_*`, `ai_financial_context_*`) so the new invalidator pattern takes effect cleanly |
| `php artisan config:clear` | No config files changed in THIS commit, but routine on any deploy |
| `php artisan optimize` | Standard |

**No migration needed** for this commit. **No composer changes.** **No env-var changes.**

---

## Rollback impact (if reverted)

If `0434fa1` is reverted:
- F-1: malformed handoffs go back to silent-drop (INV-2.4.5 broken again)
- F-2: Unicode names get stripped (inclusivity regression)
- F-3: tool-result token bloat returns
- F-9: stale prompt caches return for up to 120s post-write
- F-12: leaked Sanctum tokens with `bypass-preview-mode` ability bypass preview filtering with no header pairing

Revert is technically safe (no data loss, no schema change) but each finding the audit closed re-opens.

---

## Verification before deploy

Run the test slice that covers everything F-1..F-15 touches:

```bash
./vendor/bin/pest \
  tests/Unit/Services/AI \
  tests/Feature/Fyn \
  tests/Feature/AI \
  tests/Unit/Services/Onboarding \
  tests/Feature/Onboarding \
  --testsuite=Architecture
```

Expected: 815+ passed, 0 new failures (only pre-existing time-flake in `AdviceReviewServiceTest::annual review due` — unrelated calendar arithmetic).

---

# Session 117 addendum — SaveTax Phase 1 calculator refactor

**Commit:** `7b31508` — `feat(tax): unify SaveTax recommendations into flat DTO (Phase 1)`

Backend-only refactor: introduces a `recommendations[]` field on the SaveTax dashboard payload as the canonical list of suggested actions. Legacy `assetShiftingSuggestions` and `crossSpouseSuggestions` arrays remain populated (frontend untouched in this commit). No behaviour change in rendered output.

## What changed in 7b31508

### PHP backend (3 files, 1 new)

```
NEW   app/DataTransferObjects/StrategyRecommendation.php   typed value object for one recommendation
M     app/DataTransferObjects/TaxStrategyOutputDTO.php     adds recommendations field; legacy fields PHPDoc-deprecated
M     app/Services/Tax/TaxStrategyCalculator.php           calculate() builds the unified array
```

### Tests (3 new/modified — no deploy impact)

```
NEW   tests/Unit/DataTransferObjects/StrategyRecommendationTest.php
M     tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php   added Phase 1 contract describe block
M     tests/Feature/Api/TaxStrategy/ShowEndpointTest.php      asserts new recommendations key
```

### Frontend

**No frontend changes.** `HouseholdView.vue`, `StrategyRecommendationList.vue`, `taxStrategy.js` Vuex module unchanged. Phase 2 will migrate them to consume `recommendations[]` directly when the new strategy categories (`income_band`, `allowance`, `lifecycle`, `warning`) need to render.

## Deploy actions for this commit

| Action | Why |
|---|---|
| Upload `app/DataTransferObjects/StrategyRecommendation.php` (new) | Direct file upload |
| Upload `app/DataTransferObjects/TaxStrategyOutputDTO.php` | Constructor signature change |
| Upload `app/Services/Tax/TaxStrategyCalculator.php` | calculate() body change |
| `php artisan cache:clear` | Routine — no specific cache keys to drop |
| `php artisan optimize` | Standard |

**No frontend rebuild needed** for this commit (zero Vue/JS changes).
**No migration. No composer. No env-var changes.**

## Rollback impact (if reverted)

The `recommendations[]` JSON key disappears from `GET /api/tax-strategy` responses. Frontend doesn't read it yet, so no UI regression. The legacy `assetShiftingSuggestions` / `crossSpouseSuggestions` are restored to their pre-refactor production shape (no `category`, no `requires_advice` fields — but those are additive so absence is non-breaking).

Phase 2 work depends on Phase 1 — reverting Phase 1 after Phase 2 lands would break the new strategies. Once Phase 2 ships, Phase 1 should be considered non-revertible without a coordinated revert of both.

## Verification before deploy (this commit)

```bash
./vendor/bin/pest \
  tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php \
  tests/Unit/DataTransferObjects/StrategyRecommendationTest.php \
  tests/Feature/Api/TaxStrategy/
```

Expected: 22 passed (156 assertions), 4 deprecated (PHP 8.5 + Pest internals — pre-existing project-wide pattern, see `tests/Unit/ValueObjects/` for an identical example).

---

# Session 118 addendum — SaveTax Phase 2 (11 strategies + frontend migration)

**Commit:** `ab3df47` — `feat(tax): SaveTax Phase 2 — 11 strategies + frontend migration + drop legacy fields`

Combined backend + frontend change. Adds 11 deterministic UK tax-strategy generators, migrates the Vuex store + components to `recommendations[]`, and drops the legacy `assetShiftingSuggestions` / `crossSpouseSuggestions` DTO fields. Verified live on the peak_earners persona — strategy #2 (additional-rate avoidance) surfaces with £30,021/yr saving, #7 dividend allowance with £197/yr, #17/#18 surface for households with under-18 children.

## What changed in ab3df47

### PHP backend (3 modified, 2 new)

```
NEW   app/Enums/StrategyCategory.php                    backed enum: income_band, allowance, household, lifecycle, warning + sortWeight()
NEW   app/Enums/StrategyPriority.php                    backed enum: high, medium, low + sortWeight()
M     app/DataTransferObjects/StrategyRecommendation.php  ctor accepts enum-or-string for category/priority; categoryEnum() / priorityEnum() helpers
M     app/DataTransferObjects/TaxStrategyOutputDTO.php    drops assetShiftingSuggestions + crossSpouseSuggestions properties; toArray() drops the JSON keys
M     app/Services/Tax/TaxStrategyCalculator.php          adds buildIncomeBandRecommendations / buildAllowanceRecommendations / buildLifecycleRecommendations / buildJointSavingsRecommendations; refines #9/#11; sorts recommendations[] by category sortWeight then priority sortWeight
```

### Vue frontend (3 files)

```
M     resources/js/store/modules/taxStrategy.js                   new getters: recommendations, recommendationsByCategory, individualRecommendations, householdRecommendations; legacy getters dropped
M     resources/js/components/TaxStrategy/StrategyRecommendationList.vue  renders by category with section headers ("Reduce your tax band", "Use your allowances", "Long-term opportunities")
M     resources/js/components/TaxStrategy/HouseholdView.vue        consumes householdRecommendations getter
```

### Tests (2 modified — no deploy impact)

```
M     tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php   adds 14 Phase 2 tests; removes legacy field assertions
M     tests/Feature/Api/TaxStrategy/ShowEndpointTest.php      asserts legacy JSON keys are dropped, recommendations[] carries household-category items
```

## Deploy actions for this commit

| Action | Why |
|---|---|
| Upload all 5 PHP files (2 new enums, 3 modified) | DTO + calculator changes |
| Rebuild + upload `public/build/` | Vue store + 2 component files changed |
| `php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize` | Standard |

**No migration needed.** **No composer changes.** **No env-var changes.**

## Cumulative deploy file set (sessions 112+113+114+115+117+118)

When the cumulative deploy lands, this commit's files merge into the existing set:

- ~30 PHP backend (incl. 3 new from session 115 + 1 new from session 117 + **2 new from session 118**)
- ~12 frontend (incl. **3 new touches from session 118** — taxStrategy.js, StrategyRecommendationList.vue, HouseholdView.vue)
- 4 migrations (none new in session 118)

## Rollback impact (if reverted)

If `ab3df47` is reverted standalone:
- The 11 new strategies disappear from the SaveTax dashboard (UI shows only the 6 Phase 1 household strategies in coupled modes, nothing in single mode).
- Frontend tries to read `recommendations[]` but a Phase-1-era backend doesn't populate the new categories — only the legacy household entries flow through, which is the pre-Phase 2 state.
- **WARNING:** Phase 2 dropped the legacy `asset_shifting_suggestions` / `cross_spouse_suggestions` JSON keys. If we revert Phase 2 *and* roll back the frontend bundle to Phase 1, the legacy keys would re-appear and the legacy frontend would consume them. If we revert Phase 2 backend WITHOUT reverting the frontend, the dashboard would show empty recommendations (frontend reads `recommendations[]` only).
- Mitigation: revert Phase 2 backend + frontend together, or roll forward to a Phase 2 fix.

Phase 3-5 (capture extensions, new tools, tapered-AA warning) build on Phase 2 — once they ship, Phase 2 should be considered non-revertible without a coordinated revert of all subsequent phases.

## Verification before deploy (this commit)

```bash
./vendor/bin/pest \
  tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php \
  tests/Unit/DataTransferObjects/StrategyRecommendationTest.php \
  tests/Feature/Api/TaxStrategy/
```

Expected: 40 passed (202 assertions). Architecture suite (`./vendor/bin/pest --testsuite=Architecture`) — 95 passed. Pint clean (`./vendor/bin/pint`).

## Live browser verification (already complete)

Navigated to `/tax-strategy` as the `peak_earners` preview persona and confirmed the dashboard renders three category groups:

- **Reduce your tax band** — "Shift income out of the 45% additional-rate band" — £30,021/yr (#2)
- **Use your allowances** — "You have £500 of unused Dividend Allowance" — £197/yr (#7)
- **Long-term opportunities** — Junior ISA "1 child under 18 — up to £9,000 of Junior ISA capacity a year" (#17), Junior Pension "Open a pension for each child — instant £720 a year of free money" — £720/yr (#18)

Category sort order matches `StrategyCategory::sortWeight()` (warning > income_band > allowance > household > lifecycle).


---

# Session 119 (30 April 2026, evening) — SaveTax Phase 3

**Commit:** `2fbb4c5` on `feature/fyn-persona-split` (pushed to origin).

Phase 3 of the SaveTax strategy expansion — capture-tool extensions for #4 salary sacrifice, #6 bed & ISA, #12 non-earner spouse pension. **Backend-only deploy** — no frontend rebuild needed for this commit (the frontend already consumes `recommendations[]` after Phase 2 and renders the new types under the existing `Allowance` / `Household` section headers without changes).

## Files changed (9 — 2 new migrations, 6 modified PHP, 1 modified test)

### Schema migrations (2 new — must run before backend goes live)

```
A     database/migrations/2026_05_04_000001_add_employer_ni_rebate_pct_to_dc_pensions.php             decimal(5,4) nullable
A     database/migrations/2026_05_04_000002_add_spouse_existing_pension_balance_to_tax_strategy_household_inputs.php   decimal(12,2) nullable
```

### Backend PHP (6 modified — no new files)

```
M     app/Models/DCPension.php                                  +employer_ni_rebate_pct in fillable + cast
M     app/Models/TaxStrategyHouseholdInput.php                  +spouse_existing_pension_balance in fillable + cast + docblock
M     app/Services/AI/AiToolDefinitions.php                     extends capture_salary_sacrifice + capture_spouse_non_working_assets schemas
M     app/Services/AI/XaiToolDefinitions.php                    extends same two tools (xAI parity)
M     app/Agents/CoordinatingAgent.php                          handler updates to persist new fields (rebate clamped to [0,1])
M     app/Services/Tax/TaxStrategyCalculator.php                +3 generators (~313 LOC) + resolveSpouseAge() helper, calculator now 1252 lines
```

### Tests (1 modified — no deploy impact)

```
M     tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php     +13 Phase 3 cases in 3 describe blocks
```

### Frontend (none changed)

Phase 2 already migrated the frontend to `recommendations[]`. The new strategies fall under existing categories (`Allowance` for #4 + #6, `Household` for #12) and render through the existing section headers without any Vue/CSS changes.

## Deploy actions for this commit

| Action | Why |
|---|---|
| Upload 6 modified PHP files | Model + tool + calculator changes |
| Upload 2 new migration files | Schema additions |
| **`php artisan migrate --force`** | New columns required for the calculator to read user data correctly |
| `php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize` | Standard post-deploy clearing |
| **No `npm`/`vite` rebuild** | No frontend changes |
| **No `composer install`** | No new dependencies |
| **No env-var changes** | Tax config fields read via existing TaxConfigService |

## Cumulative deploy file set (sessions 112+113+114+115+117+118+119)

Adding session 119 to the existing cumulative queue:
- ~30 PHP backend (Sessions 112-118 set) **+ 6 modified from session 119** (1 new agent handler edit, 1 calculator extension, 2 model fillable extensions, 2 tool-def extensions)
- ~12 frontend (no change in session 119)
- **6 migrations** (4 from earlier + **2 new from session 119**)

When the dev deploy goes out, run the artisan dance once at the end — all migrations are forward-only and idempotent (safety guards via `Schema::hasColumn` early-return).

## Rollback impact (if reverted standalone)

If `2fbb4c5` is reverted by itself:
- The 3 new Phase 3 recommendations disappear from the dashboard (Phase 2 strategies remain).
- The two new columns become orphaned — no immediate breakage; calculator falls back to defaults via the `?? 0` patterns in the cast layer.
- Capture tools still accept the new fields but the values would be persisted to columns that no longer exist after a `down()`. **Don't run `down()` on a live system without coordinating a tool-schema revert in the same window.**
- Tests would fail because the DCPension factory may attempt to set the new columns. The `salary_sacrifice` column from session 117/118 is unaffected.

Recommended: don't revert standalone. If a revert is needed, revert the commit AND keep migrations forward-only. The orphaned columns are harmless if left in place.

## Verification before deploy (this commit)

```bash
./vendor/bin/pest \
  tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php \
  tests/Feature/Api/TaxStrategy/ \
  --testsuite=Architecture
./vendor/bin/pint app/ tests/Unit/Services/Tax/ database/migrations/2026_05_04_*
```

Expected: 91 tax-module + 95 architecture passed. Pint clean. (Three pre-existing failures in `AdviceReviewServiceTest`, `EvalAuthControllerTest`, `PreviewBypassAbilityTest` are unrelated — confirmed by stash-and-rerun on the parent commit `2e36222`.)

## Live browser verification (already complete)

Navigated to `/tax-strategy` as `john@example.com` (test user with mocked Phase 3 inputs: workplace pension £500/mo not on sacrifice, GIA with £7,000 unrealised gain, single_earner_couple mode with spouse family member age 41). All three Phase 3 strategies rendered with correct copy + tax-saving badges, in the canonical category order, with no regressions to Phase 2 strategies.

## Cumulative SSH command (for the eventual dev deploy)

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/public_html/fynla
php artisan migrate --force
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

(Production target is `~/www/fynla.org/public_html/` and `chris@fynla.org` SSH alias once dev is green.)

---

# Session 120 addendum (30 April 2026, evening) — SaveTax Phase 4

**Branch:** `feature/fyn-persona-split` (still accumulating; cumulative deploy queue now 8 sessions deep — 112+113+114+115+117+118+119+120)
**Commits added today:**
- `2a210e0` — `refactor(tax): extract per-strategy classes from TaxStrategyCalculator (S-1)`
- `f007fce` — `feat(tax): SaveTax Phase 4 — Pension AA Carry-Forward (#3)`
- `94c880a` — `feat(tax): SaveTax Phase 4 — Gift Aid Higher-Rate Relief (#13)`

Backend-only set. **No frontend rebuild required** for Phase 4 — the `recommendations[]` payload shape was already migrated end-to-end in Phase 2 (sessions 117-118), and the new strategy types render through the existing dashboard component without any Vue changes.

## What changed today (full file list, generated from `git diff origin/main...HEAD`)

### New files (24)

```
NEW   app/Models/PensionInputHistory.php                                    Phase B
NEW   app/Services/Tax/TaxStrategyMath.php                                  Phase A
NEW   app/Services/Tax/Strategies/Contract/TaxStrategy.php                  Phase A
NEW   app/Services/Tax/Strategies/TaxStrategyContext.php                    Phase A
NEW   app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php           Phase A
NEW   app/Services/Tax/Strategies/BedAndIsaStrategy.php                     Phase A
NEW   app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php             Phase A
NEW   app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php     Phase A
NEW   app/Services/Tax/Strategies/IncomeBandStrategy.php                    Phase A
NEW   app/Services/Tax/Strategies/IsaTopUpStrategy.php                      Phase A
NEW   app/Services/Tax/Strategies/JointSavingsStrategy.php                  Phase A
NEW   app/Services/Tax/Strategies/LifecycleStrategy.php                     Phase A
NEW   app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php        Phase A
NEW   app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php             Phase A
NEW   app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php         Phase B
NEW   app/Services/Tax/Strategies/GiftAidHigherRateReliefStrategy.php       Phase C
NEW   database/migrations/2026_05_05_000001_create_pension_input_history_table.php   Phase B
NEW   database/migrations/2026_05_05_000002_add_charitable_donations_to_users.php    Phase C
NEW   tests/Feature/AI/DirectWrite/CapturePensionHistoryTest.php            Phase B (5 cases)
NEW   tests/Feature/AI/DirectWrite/CaptureCharitableGivingTest.php          Phase C (4 cases)
```

### Modified files (8)

```
M     app/Agents/CoordinatingAgent.php                          Phase B+C handlers + dispatch
M     app/Services/AI/AiToolDefinitions.php                     +capture_pension_history, +capture_charitable_giving
M     app/Services/AI/XaiToolDefinitions.php                    parity (matching wraps)
M     app/Services/Onboarding/OnboardingPromptBuilder.php       savetax tools list +2
M     app/Services/Onboarding/OnboardingStateMachine.php        +STATE_CAMPAIGN_PENSION_HISTORY, +STATE_CAMPAIGN_CHARITABLE_GIVING
M     app/Services/Tax/TaxStrategyCalculator.php                Phase A slim (1301→252 lines, 81% rewrite) + Phase B/C strategy registry entries
M     tests/Architecture/ApplicationArchitectureTest.php        Add TaxStrategy interface to ignoring()
M     tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php     +12 cases (7 Phase B, 5 Phase C)
```

## Migrations to run on dev + prod

The cumulative deploy now needs **4 SaveTax migrations** to run in order (the first 2 are from session 119):

```
2026_05_04_000001_add_employer_ni_rebate_pct_to_dc_pensions          (session 119)
2026_05_04_000002_add_spouse_existing_pension_balance_to_tax_strategy_household_inputs  (session 119)
2026_05_05_000001_create_pension_input_history_table                 (session 120 / Phase B)
2026_05_05_000002_add_charitable_donations_to_users                  (session 120 / Phase C)
```

`php artisan migrate --force` runs them all idempotently (each guards with `Schema::hasColumn` / `Schema::hasTable` early-returns).

## Pre-merge conflict check (cumulative, sessions 112-120 vs origin/main)

Run before opening the cumulative `feature/fyn-persona-split → dev` PR:

```bash
BASE=$(git merge-base origin/main HEAD)
MAIN_FILES=$(git diff --name-only $BASE..origin/main -- '*.php' '*.vue' '*.js')
BRANCH_FILES=$(git diff --name-only $BASE..HEAD -- '*.php' '*.vue' '*.js')
CONFLICTS=$(comm -12 <(echo "$MAIN_FILES" | sort) <(echo "$BRANCH_FILES" | sort))
if [ -n "$CONFLICTS" ]; then
  echo "WARNING: These files changed on BOTH branches — verify after merge:"
  echo "$CONFLICTS"
fi
```

## Test verification (already complete, session 120)

```bash
./vendor/bin/pest tests/Unit/Services/Tax/ tests/Feature/AI/DirectWrite/ --testsuite=Architecture --colors=never
./vendor/bin/pint app/Services/Tax/ app/Services/AI/ app/Agents/ app/Services/Onboarding/ tests/Architecture/ tests/Unit/Services/Tax/ tests/Feature/AI/DirectWrite/ --test
```

Phase 4 results: **190 passed (608 assertions)** in the combined Tax + DirectWrite + Architecture run; Pint clean across 21+ files. Pre-existing time-flake in `AdviceReviewServiceTest::annual review due` (CSJTODO Known Issues) is unrelated.

## Live browser verification (session 120)

Navigated to `/tax-strategy` as `john@example.com` after seeding:
- `annual_employment_income = 80000`, `annual_charitable_donations = 1200`
- Three `pension_input_history` rows: `2024/25 = 20000`, `2023/24 = 20000`, `2022/23 = 20000`

Both new strategy cards rendered with correct copy + saving badges:
- **Pension AA Carry-Forward** — "Carry forward up to £120,000 of unused Pension Allowance" with **£48,000/yr** badge (= 3 × (60,000 − 20,000) × 0.40)
- **Gift Aid Higher-Rate Relief** — "Reclaim £300 on your Gift Aid donations via Self Assessment" with **£300/yr** badge (= 1,200 × 0.25)

API verification:
```bash
curl -s '/api/tax-strategy' | jq '.data.recommendations[] | select(.type | startswith("pension_aa_carry_forward","gift_aid"))'
# Returns both records with the expected extra fields:
#   pension_aa_carry_forward → unused_carry_forward=120000, marginal_rate=0.4
#   gift_aid_higher_rate_relief → reclaim_factor=0.25, tax_band="higher"
```

No regressions on Phase 1/2/3 strategies for the same user (salary_sacrifice_ni, bed_and_isa, dividend_allowance_harvest still surface correctly).

## Updated cumulative SSH command

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/public_html/fynla
php artisan migrate --force                                      # runs all 4 SaveTax migrations
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Smoke-test matrix (run on csjones.co/fynla after deploy)

In addition to the Phase 3 matrix from the session 119 addendum, add two new profile checks for Phase 4:

| Profile | Expected card |
|---|---|
| Employed + workplace pension not on sacrifice | `salary_sacrifice_ni` ← Phase 3 |
| Holds non-ISA gains + ISA capacity | `bed_and_isa` ← Phase 3 |
| `single_earner_couple` mode with spouse < 75 | `non_earner_spouse_pension` ← Phase 3 |
| **Higher-rate user, prior 3 yrs unused AA, current input < £60k** | **`pension_aa_carry_forward` ← Phase 4** |
| **Higher- or additional-rate user with `annual_charitable_donations > 0`** | **`gift_aid_higher_rate_relief` ← Phase 4** |

Then verify `GET /api/tax-strategy` JSON includes the new types in `data.recommendations[]`.
