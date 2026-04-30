# CSJTODO — Fynla

*Last updated: 30 April 2026 — session 119 (SaveTax Phase 3 shipped, commit `2fbb4c5`).*
*Previous session: 118 (30 April — SaveTax Phase 2).*

---

## Session 119 (30 April 2026, evening) — SaveTax Phase 3

**Branch:** `feature/fyn-persona-split`. **Commits:** 1 (`2fbb4c5`).

CSJ asked for Phase 3 — the capture extensions for #4 (salary sacrifice NI), #6 (bed & ISA), #12 (non-earner spouse pension). All three landed end-to-end with two forward-only schema migrations, capture-tool extensions in Anthropic + xAI lockstep, three new calculator generators, 13 new tests, and a live browser walk on `john@example.com` confirming the strategies render correctly without regressing Phase 2.

### Completed this session

- [x] **Schema migrations (2 new, both ran cleanly with `Schema::hasColumn` early-return guards):**
  - `dc_pensions.employer_ni_rebate_pct` decimal(5,4) nullable (`2026_05_04_000001`)
  - `tax_strategy_household_inputs.spouse_existing_pension_balance` decimal(12,2) nullable (`2026_05_04_000002`)
- [x] **Models updated:** `DCPension` and `TaxStrategyHouseholdInput` extended (fillable + casts + docblock)
- [x] **Capture tool extensions** (Anthropic + xAI parity validated by `ToolCatalogueParityTest`):
  - `capture_salary_sacrifice` accepts optional `employer_ni_rebate_pct` (number)
  - `capture_spouse_non_working_assets` accepts optional `spouse_existing_pension_balance` (number)
- [x] **Handlers updated** in `CoordinatingAgent`: rebate clamped to [0, 1] before persistence, household-inputs allow-list expanded
- [x] **3 new calculator generators in `TaxStrategyCalculator`** (now 1252 lines, up from 988):
  - **#4 `salary_sacrifice_ni`** (allowance, medium) — fires for an employed user whose workplace DC pension is not on salary sacrifice. Saving = `annual_contribution × employee_NI_rate` (8% main / 2% above UEL, piecewise across the slice between (income − contribution) and income), plus (if `employer_ni_rebate_pct` set) `× employer_NI_rate × rebate_pct`. NI rates and the upper-earnings-limit are read from `TaxConfigService->getNationalInsurance()`.
  - **#6 `bed_and_isa`** (allowance, medium) — fires when non-ISA holdings have positive unrealised gains AND remaining ISA allowance. Computes per-holding gain from `cost_basis` + `current_value` (with `quantity × purchase_price / current_price` as a fallback when cost basis is null), caps at the £3,000 Annual Exempt Amount, scales proceeds by the gain-to-value ratio, and prices the saving at the user-band CGT rate (18% basic / 24% higher and additional, from `getCapitalGainsTax()`).
  - **#12 `non_earner_spouse_pension`** (household, medium) — fires in `single_earner_couple` mode unless a captured spouse age ≥ 75. £2,880 net contribution → £3,600 gross via 25% basic-rate uplift = £720/yr. New `resolveSpouseAge()` helper looks at `family_members` (relationship in spouse/partner/wife/husband/civil_partner) then falls back to a linked spouse user.
- [x] **`calculate()` wiring** — #4 and #6 added to `userLevelRecs` (fire across all modes); #12 merged into household recs in the `single_earner_couple` branch
- [x] **Tests:** 13 new Phase 3 cases across 3 describe blocks. 91/91 SaveTax tax-module tests pass; 95/95 architecture green; Pint clean on 9 files
- [x] **Live browser verification** on `john@example.com` after seeding the test user with a workplace pension, GIA holding with positive unrealised gain, and a spouse family member. All three strategies rendered with correct copy + tax-saving badges in the canonical category order
- [x] **Repo hygiene:** renamed local folder `April/April30pdates` → `April/April30Updates` (the typo crept in during session 117 mkdir; canonical name is `April30Updates` and matches the vault)

### Session 119 deliverables

| Artefact | Path | Status |
|---|---|---|
| Migration #1 | `database/migrations/2026_05_04_000001_add_employer_ni_rebate_pct_to_dc_pensions.php` | New |
| Migration #2 | `database/migrations/2026_05_04_000002_add_spouse_existing_pension_balance_to_tax_strategy_household_inputs.php` | New |
| Models | `app/Models/DCPension.php`, `app/Models/TaxStrategyHouseholdInput.php` | Modified (fillable + cast + docblock) |
| Tool defs | `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/XaiToolDefinitions.php` | Modified (extended 2 tools each) |
| Handler | `app/Agents/CoordinatingAgent.php` | Modified (handleCaptureSalarySacrifice + handleCaptureSpouseNonWorkingAssets) |
| Calculator | `app/Services/Tax/TaxStrategyCalculator.php` | Modified (now 1252 lines, +3 generators + resolveSpouseAge helper) |
| Tests | `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` | Modified (+13 Phase 3 cases) |
| Phase 3 commit | `2fbb4c5` (`feature/fyn-persona-split`) | Pushed to origin |
| Tech-debt report | `tech-debt-report.md` | Updated (0 critical / 2 warnings / 3 suggestions) |
| Deploy notes | `April/April30Updates/deploy.md` | Session 119 addendum added |

### Phase progression (5-phase plan from session 117)

| Phase | Scope | Status |
|---|---|---|
| 1 | Calculator refactor → flat `recommendations[]` DTO | ✅ session 117 (`7b31508`) |
| 2 | 11 strategies needing no new capture + frontend migration + legacy fields dropped | ✅ session 118 (`ab3df47`) |
| **3** | **Capture extensions for #4 (salary sacrifice NI), #6 (bed & ISA), #12 (non-earner spouse pension)** | **✅ session 119 (`2fbb4c5`)** |
| 4 | New tools for #3 (pension AA carry-forward), #13 (Gift Aid) | ⏳ next |
| 5 | Composed-income view for #14 (tapered AA warning, requires `adjusted_income` AND `threshold_income`) | ⏳ |

---

## NOT Done — Outstanding for next session

### Phase 4 — New states / tools (#3, #13)

- [ ] **#3 Pension AA Carry-Forward** — New tool `capture_pension_history` (last 3 years' pension input amounts), insert in a new `STATE_CAMPAIGN_PENSION_HISTORY` state. Schema migration for the historical fields on `dc_pensions` or new `pension_input_history` table. Calculator generator: fires when `current_year_pension_input < £60,000` AND `prior_3_years_unused_AA > 0` AND `user_band ∈ {higher, additional}` AND surplus disposable income. Saving: `unused_carry_forward × user_marginal_rate`.
- [ ] **#13 Gift Aid Higher-Rate Relief** — New tool `capture_charitable_giving` (annual £ donations) — either as a new `STATE_CAMPAIGN_*` state or as an optional dashboard input. New field on `users` (or new `charitable_giving_records` table). Calculator generator: fires when `user_band ∈ {higher, additional}` AND `annual_charitable_donations > 0`. Saving: `gross_donations × 0.25` (higher) or `× 0.3125` (additional) reclaimable.
- [ ] Add the new capture tools to `OnboardingPromptBuilder::toolsForFocus('savetax')` (currently lines 118-127)
- [ ] Migrations as needed; live browser walk; green Pest

### Phase 5 — Composed-income warnings (#14 Tapered Annual Allowance)

- [ ] Compose `adjusted_income` view (employment + bonus + employer pension contributions added back)
- [ ] Compose `threshold_income` view (employment + bonus, employee pension contributions excluded)
- [ ] Tapered Annual Allowance warning generator — fires only when **BOTH** `adjusted_income > £260,000` AND `threshold_income > £200,000` (per CSJ redline — both gates required by HMRC)
- [ ] Promote priority to `high` per redline (warning class — surfaces ahead of normal suggestions because the downside of missing the taper is a £20k+ HMRC charge)
- [ ] Live browser walk on a high-income persona; green Pest

### Tech-debt items carried forward

From session 118:
- [ ] **W-1** — Extract `dividendRateForBand(string $band): float` helper to remove the 3-site `match(band) → dividend rate` duplication (~15 min, high DRY value). After Phase 3, the same shape duplication now exists 3-way: dividend rate, income-tax marginal rate, and CGT rate. **Bundle** with W-2 and the new session-119 CGT extraction below.
- [ ] **W-2** — Read marginal income-tax rates (`0.20/0.40/0.45`) from `getIncomeTax()['bands']` rather than hardcoding in `bandRateFor()` and the income-band generators. Rule #3 compliance (~30 min).
- [ ] **S-1** — Calculator now 1252 lines after Phase 3 — extract per-strategy classes (`App\Services\Tax\Strategies\IncomeBandStrategy`, etc.) so Phases 4-5 don't push the file past 1,500 lines. **Now overdue** — was deferred to start of Phase 3 or 4. Strong recommendation: do this BEFORE Phase 4.
- [ ] **S-2** — Magic threshold `> 1000` for "worth recommending" — extract `private const MIN_TRANSFER_TO_RECOMMEND = 1000.0;`.
- [ ] **S-3** — Hardcoded Junior Pension £2,880 / £720 — add a comment citing HMRC source or expose via TaxConfigService as `pension_allowances.junior_pension_net_cap` + `junior_pension_uplift`. **Phase 3 added a second instance** for `non_earner_spouse_pension` — both call sites use the same constants and would benefit from a single TaxConfig key.

New from session 119:
- [ ] **Phase 3 W-1** — `cgtRateForBand(string $band, bool $residential = false): float` helper to bundle with the existing dividend-rate / income-tax-rate extraction work (CSJTODO **W-1** + **W-2**). One sweep, ~20 min total when actioned.
- [ ] **Phase 3 S-1** — Promote `whereIn('relationship', [...])` spouse list in `resolveSpouseAge()` to a class constant when a second caller appears. Defer.
- [ ] **Phase 3 S-2** — Extract `employeeNiSavingFor(float $income, float $contribution): float` helper if Phase 5 tapered-AA work needs NI calculations. Otherwise leave inline.
- [ ] **Phase 3 S-3** — One-line comment on the `bed_and_isa` proceeds scaling formula explaining the gain-to-value ratio. Or accept that surrounding lines convey it.

### Deploy combined sessions 112+113+114+115+117+118+119 to dev (csjones.co/fynla)

Session 119 adds **2 new migrations + 6 modified PHP** to the cumulative deploy set. **No new frontend** (Phase 2 already migrated the Vuex store + components; Phase 3 reuses existing categories).

- [ ] Open PR `feature/fyn-persona-split → dev`, merge after Stoff73 approval
- [ ] Build with `./deploy/csjones-fynla/build.sh` (Phase 2 frontend rebuild requirement carries; Phase 3 added no JS/CSS but the cumulative build still needs to ship Phase 2 assets)
- [ ] Upload `public/build/` + cumulative file set: ~36 PHP backend (incl. session 119's 2 migrations + 6 modified files) + ~12 frontend (no change since Phase 2)
- [ ] SSH: `php artisan migrate --force && php artisan db:seed --class=TaxConfigurationSeeder --force && php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize`
- [ ] Re-drive BS-26/27/28 against `csjones.co/fynla` per Rule #15
- [ ] Smoke test SaveTax dashboard with the 3 Phase-3 trigger profiles:
  - Employed + workplace pension not on sacrifice → expects #4 surfaces with NI saving badge
  - Holds non-ISA investments with positive unrealised gain + ISA capacity → expects #6 surfaces with £720/yr at higher-rate or £540/yr at basic
  - `single_earner_couple` mode with spouse < 75 → expects #12 surfaces under "Asset-shifting opportunities"
- [ ] Verify `GET /api/tax-strategy` JSON includes `salary_sacrifice_ni`, `bed_and_isa`, `non_earner_spouse_pension` types in the recommendations array

### After dev green — production deploy

- [ ] Open PR `dev → main`, merge, repeat with `./deploy/fynla-org/build.sh`
- [ ] Smoke test fynla.org
- [ ] Re-drive the same 3-profile Phase-3 matrix on prod, plus the existing Phase-2 personas

### Sprint 0 verification rollup (S0.17) — branch readiness

Once the cumulative deploy lands and BS-26/27/28 stay green:

- [ ] Full Pest sweep `./vendor/bin/pest` (expect 815+ pass with 1 pre-existing time-flake)
- [ ] Architecture suite green
- [ ] `php artisan ai:audit:verify-chain` returns `chain_valid: true`
- [ ] BS-NN browser matrix: 20/20 PASS with screenshots committed
- [ ] Rubric-A re-score: target 13-15/40 per S0.17 plan
- [ ] PR body links to verification evidence

### Sprint 1 — INV-2.3.5 structured `advice_response` SSE event

Carried over from Sprint 0 deferral. Required schema in `April/April24Updates/spec/01-invariants.md §2.3.5`:

- New SSE event type `advice_response` emitted exactly once per recommendation-mode turn
- Payload: `{headline, key_figures[], breakdowns[], recommendations[], next_steps[], signposting}`
- Rendered by new `AdviceResponsePanel.vue`
- JSON-schema validation in `tests/Feature/Fyn/AdviceResponseSseShapeTest.php`

### F-12 / F-4 / F-8 Sprint follow-ups

- [ ] **F-12** — Server-side `X-Eval-Run-Id` allowlist — currently the gate just requires the header to be present and non-empty. A stronger version would track in-flight runs in DB and reject unknown run-ids.
- [ ] **F-4** — Anthropic cache-hit dashboard — F-4 captures the data; rendering it in the admin UI is a follow-up so cache regressions are visible.
- [ ] **F-8** — Migration to rename `ai_messages.system_prompt` column to `system_prompt_hash` + backfill old rows. F-8 writes hashes to the existing column going forward; rename is cosmetic and out of audit scope.

---

## Outstanding — Tech Debt Deferred

- **`StrategyRecommendation` extras serialisation strategy** — currently spreads top-level via `array_merge`. Works but couples extras to the same namespace as canonical fields. If `extra` keys ever collide with canonical (`type`, `category`, `priority`, `title`, `description`, `requires_advice`), the canonical wins via `array_merge` order — silent bug risk. Consider explicit nesting under `extras` or per-strategy typed extension classes when Phase 4-5 add more strategy-specific fields.
- **`TaxStrategyCalculator::handleModuleAnalysis` summarisation** — INV-2.6.1 says "no `summariseToolAnalysis` stripping for this handler" but the S0.15 delivery note acknowledged it still wraps via `summariseToolAnalysis` at line 1512. Behaviour change with broader test surface; deferred follow-up.
- **Vue 3 `$listeners` warning on `<FynOnboardingChat>`** (carried from session 113-evening). Minor.
- **`StructuredResponseValidator` flagging "SIPP" as banned acronym** in LLM acks where the state's own prompt uses SIPP (carried from session 113-evening). Needs per-state allowlist or canonical exception.
- **TaxStrategyCalculator under-counts pension AA usage on initial load** — slider re-fires correctly. (Carried from session 113-evening; will be touched anyway during Phase 4-5 — fix opportunistically.)

---

## Known Issues

- **Pre-existing time-flake** in `AdviceReviewServiceTest::annual review due` — `subMonths(14)` followed by `diffInMonths(now())` can return 13 across month-end boundaries. Confirmed unrelated to any Phase 3 changes by stash-and-rerun against `2e36222`.
- **Pre-existing eval-rewrite branch failures** — `EvalAuthControllerTest::reset endpoint` and `PreviewBypassAbilityTest::preview user WITH bypass`. Tracked under memory `project_eval_http_driven_rewrite_branch.md` as part of the 4 P0/P1 defects blocking Task 16. Not Phase 3 territory.

---

## Deploy Status

- **`feature/fyn-persona-split`** — 247 commits ahead of `origin/main`, all pushed (incl. today's `2fbb4c5`). **Not yet deployed anywhere.**
- **`dev` branch** — last deployed commit unknown — needs catch-up to combined sessions 112+113+114+115+117+118+119 work.
- **`main` / production fynla.org** — last deployed somewhere mid-April (sessions 105-107). All Sprint 0 + SaveTax Phases 1+2+3 + audit-fix work pending.

---

## Context for Next Session

Session 119 closed Phase 3 of the SaveTax strategy expansion. The dashboard now surfaces **14 deterministic strategies** (8 user-level new in Phase 2 + 3 capture-extension new in Phase 3 + 6 refined household ones from Phase 2), all sorted by category + priority, all rendered with section headers in the live UI. Backend canonical contract is `recommendations[]`; legacy fields are gone end-to-end. Phase 3 strategies live-verified on `john@example.com` — #4 / #6 / #12 all surface with correct savings.

**Two clear paths for the next session:**

1. **Phase 4 — new tools (#3 pension AA carry-forward, #13 Gift Aid).** This is the natural next step in the catalogue's phase plan. Phase 4 needs new `STATE_CAMPAIGN_*` states (or dashboard-side optional inputs), new tools, schema migrations, and calculator generators. Bigger scope than Phase 3 because it adds capture-side state-machine work on top of new generators. **Strong recommendation: extract per-strategy classes (CSJTODO S-1) FIRST** — the calculator hit 1252 lines after Phase 3 and Phase 4 will push it past 1,500 if we don't refactor first.

2. **Cumulative dev deploy** (sessions 112+113+114+115+117+118+119 to csjones.co/fynla). The deploy is queued in the NOT Done section with full file lists and SSH commands ready. Phase 3 is the first session to add migrations to the cumulative queue — make sure `php artisan migrate --force` runs on the dev server. After dev verification, production deploy follows.

**Alternative**: tech-debt sweep (W-1 / W-2 / S-1 bundle ~45 min). The CGT-rate extraction from session 119 is a natural moment to bundle this.

CSJ to choose. Phase 4 is the highest-value next step but the deploy queue is starting to mature (now 7 sessions deep) and a draining cycle would be prudent before Phase 4 lands.
