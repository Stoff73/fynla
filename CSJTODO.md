# CSJTODO — Fynla

*Last updated: 30 April 2026 — session 118 (SaveTax Phase 2 shipped, commit `ab3df47`).*
*Previous session: 117 (30 April — SaveTax Phase 1 + strategy catalogue v0.2).*

---

## Session 118 (30 April 2026) — SaveTax Phase 2

**Branch:** `feature/fyn-persona-split`. **Commits:** 1 (`ab3df47`).

CSJ asked for Phase 2 — implement the 11 strategies that need no new capture and migrate the frontend from legacy fields to `recommendations[]`. Phase 2 shipped end-to-end, browser-verified live on the `peak_earners` persona, and legacy DTO fields dropped in the same commit.

### Completed this session

- [x] **2 new backed enums** — `App\Enums\StrategyCategory` (income_band, allowance, household, lifecycle, warning) + `StrategyPriority` (high, medium, low) with `sortWeight()` helpers driving canonical render order
- [x] **`StrategyRecommendation` DTO** updated to accept enum-or-string for category/priority, validate against the enum at construction, expose `categoryEnum()` + `priorityEnum()` helpers
- [x] **11 new strategy generators** in `TaxStrategyCalculator`:
  - #1 `pa_taper_rescue` (income_band, high) — escape the 60% effective marginal-rate band £100k-£125,140
  - #2 `additional_rate_avoidance` (income_band, high) — drop slice from 45% into 40%/60% bands
  - #5 `isa_topup_vs_psa` (allowance, high) — wrap non-ISA cash earning interest above PSA
  - #7 `dividend_allowance_harvest` (allowance, low) — surface unused £500 for non-ISA holders
  - #15 `joint_savings_psa_split` (household, low) — sole-name savings split for two PSAs; skipped at additional rate
  - #16 `lifetime_isa` (lifecycle, medium) — under-40s, £4k × 25% = £1k bonus per year
  - #17 `junior_isa` (lifecycle, medium) — fires per child under 18, surfaces N × £9k Junior ISA capacity
  - #18 `junior_pension` (lifecycle, medium) — £2,880 net + £720 government uplift per child
- [x] **Refined #9 + #11**:
  - #9 `savings_to_spouse` Title + Sub-line breakout naming the three buckets (PA £12,570 + Starting Rate £5,000 + PSA £1,000 = £18,570/yr) plus spousal CGT/IHT exemption note
  - #11 `gia_rebalance` and `gia_to_spouse` now carry `estimated_annual_tax_saved` based on user-band vs spouse-band dividend-rate delta
- [x] **`calculate()` rewired** — runs user-level generators (income-band, allowance, lifecycle, joint-savings) across ALL modes; household builders only fire in coupled modes; `usort` by `categoryEnum->sortWeight()` then `priorityEnum->sortWeight()`
- [x] **Frontend migration**:
  - `taxStrategy.js` Vuex now exposes `recommendations`, `recommendationsByCategory` (grouped + ordered), `individualRecommendations`, `householdRecommendations` getters
  - `StrategyRecommendationList.vue` renders by category with section headers ("Reduce your tax band", "Use your allowances", "Long-term opportunities") and tax-saving badges
  - `HouseholdView.vue` consumes `householdRecommendations` getter
- [x] **Legacy fields dropped** — `assetShiftingSuggestions` + `crossSpouseSuggestions` removed from `TaxStrategyOutputDTO` properties; JSON output drops the corresponding `asset_shifting_suggestions` / `cross_spouse_suggestions` keys
- [x] **Live browser verification** on `peak_earners` persona — all three category groups render correctly: #2 shows £30,021/yr, #7 shows £197/yr, #17/#18 surface for the household's child (Junior Pension £720/yr)
- [x] **Tests:** 14 new Phase 2 cases across 5 describe blocks; existing tests updated to use `recsOfCategory(output, 'household')` filter helper. 40/40 tax-strategy tests green (202 assertions). 95/95 architecture green. Pint clean.

### Session 118 deliverables

| Artefact | Path | Status |
|---|---|---|
| Enums | `app/Enums/StrategyCategory.php`, `app/Enums/StrategyPriority.php` | New |
| DTOs | `app/DataTransferObjects/StrategyRecommendation.php`, `TaxStrategyOutputDTO.php` | Modified |
| Calculator | `app/Services/Tax/TaxStrategyCalculator.php` | Modified (988 lines now) |
| Vuex | `resources/js/store/modules/taxStrategy.js` | Modified |
| Components | `resources/js/components/TaxStrategy/StrategyRecommendationList.vue`, `HouseholdView.vue` | Modified |
| Tests | `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`, `tests/Feature/Api/TaxStrategy/ShowEndpointTest.php` | Modified |
| Phase 2 commit | `ab3df47` (`feature/fyn-persona-split`) | Pushed to origin |
| Tech-debt report | `tech-debt-report.md` | Updated |
| Deploy notes | `April/April30pdates/deploy.md` | Session 118 addendum added |

### Phase progression (5-phase plan from session 117)

| Phase | Scope | Status |
|---|---|---|
| 1 | Calculator refactor → flat `recommendations[]` DTO | ✅ session 117 (`7b31508`) |
| **2** | **11 strategies needing no new capture + frontend migration + legacy fields dropped** | **✅ session 118 (`ab3df47`)** |
| 3 | Capture extensions for #4 (salary sacrifice NI), #6 (bed&ISA gain capture), #12 (spouse pension flag) | ⏳ next |
| 4 | New tools for #3 (pension AA carry-forward), #13 (Gift Aid) | ⏳ |
| 5 | Composed-income view for #14 (tapered AA warning, requires `adjusted_income` AND `threshold_income`) | ⏳ |

---

## NOT Done — Outstanding for next session

### Phase 3 — Capture extensions (#4, #6, #12)

- [ ] **#4 Salary Sacrifice NI Relief** — Extend `capture_salary_sacrifice` tool (or `create_pension`) with two new fields: `is_salary_sacrifice: bool` + optional `employer_ni_rebate_pct: float`. Add migration for the two columns on `dc_pensions` (or wherever the workplace-pension flag lives). Calculator generator: saving = `annual_contribution × employee_NI_rate` + (if rebate) `× 0.138`.
- [ ] **#6 Bed & ISA — Capital Gains Harvest Within Annual Exempt Amount** — Capture `unrealised_gain` per holding (or `purchase_price` + `current_value` so gain is computable). Schema migration for `holdings` and/or `investment_accounts`. Calculator generator: fires when `non_isa_holding_unrealised_gains > 0` AND `current_year_realised_gains < £3,000` AND `remaining_ISA_allowance > 0`. Saving estimate: `realisable_gains_within_AEA × CGT_rate`.
- [ ] **#12 Pension for Non-Earning Spouse** — Verify `spouse_existing_pension_balance` is captured by `capture_spouse_non_working_assets`; extend if not. Calculator generator: fires when `mode = single_earner_couple` AND spouse age < 75. Saving: £720/yr direct uplift on £2,880 net contribution + retirement-phase efficiency narrative.
- [ ] Add the new capture tools to `OnboardingPromptBuilder::toolsForFocus('savetax')` (currently lines 118-127)
- [ ] Live browser walkthrough on a SaveTax persona; green Pest sweep

Spec: `April/April30pdates/savetax-strategy-catalogue.md` v0.2 §A-D (filter to capture-extension entries) + capture-table at the bottom.

### Phase 4 — New states / tools (#3, #13)

- [ ] **#3 Pension AA Carry-Forward** — New tool `capture_pension_history` (last 3 years' pension input amounts), insert in `STATE_CAMPAIGN_PENSION_CONTRIBS`. Schema migration for the historical fields on `dc_pensions` or new `pension_input_history` table. Calculator generator: fires when `current_year_pension_input < £60,000` AND `prior_3_years_unused_AA > 0` AND user_band ∈ {higher, additional} AND surplus disposable income. Saving: `unused_carry_forward × user_marginal_rate`.
- [ ] **#13 Gift Aid Higher-Rate Relief** — New tool `capture_charitable_giving` (annual £ donations) — either as a new `STATE_CAMPAIGN_*` state or as an optional dashboard input. New field on `users` (or new `charitable_giving_records` table). Calculator generator: fires when user_band ∈ {higher, additional} AND `annual_charitable_donations > 0`. Saving: `gross_donations × 0.25` (higher) or `× 0.3125` (additional) reclaimable.
- [ ] Migrations as needed; live browser walk; green Pest

### Phase 5 — Composed-income warnings (#14 Tapered Annual Allowance)

- [ ] Compose `adjusted_income` view (employment + bonus + employer pension contributions added back)
- [ ] Compose `threshold_income` view (employment + bonus, employee pension contributions excluded)
- [ ] Tapered Annual Allowance warning generator — fires only when **BOTH** `adjusted_income > £260,000` AND `threshold_income > £200,000` (per CSJ redline — both gates required by HMRC)
- [ ] Promote priority to `high` per redline (warning class — surfaces ahead of normal suggestions because the downside of missing the taper is a £20k+ HMRC charge)
- [ ] Live browser walk on a high-income persona; green Pest

### Tech-debt items from session 118 audit

- [ ] **W-1** — Extract `dividendRateForBand(string $band): float` helper to remove the 3-site `match(band) → dividend rate` duplication. ~15 min, high DRY value.
- [ ] **W-2** — Read marginal income-tax rates (`0.20/0.40/0.45`) from `getIncomeTax()['bands']` rather than hardcoding in `bandRateFor()` and the income-band generators. Rule #3 compliance. ~30 min.
- [ ] **S-1** — Calculator at 988 lines — extract per-strategy classes (`App\Services\Tax\Strategies\IncomeBandStrategy`, etc.) so Phases 3-5 don't push the file past 1,500 lines. Defer to start of Phase 3 or 4.
- [ ] **S-2** — Magic threshold `> 1000` for "worth recommending" — extract `private const MIN_TRANSFER_TO_RECOMMEND = 1000.0;`.
- [ ] **S-3** — Hardcoded Junior Pension £2,880 / £720 — add a comment citing HMRC source or expose via TaxConfigService as `pension_allowances.junior_pension_net_cap` + `junior_pension_uplift`.

### Deploy combined sessions 112 + 113 + 114 + 115 + 117 + 118 to dev (csjones.co/fynla)

Session 118 adds **2 new files (enums) + 3 modified backend + 3 modified frontend** to the cumulative deploy set. **First frontend rebuild requirement** in this branch's deploy queue (Phase 1 was backend-only; Phase 2 touched the Vuex store + 2 components).

- [ ] Open PR `feature/fyn-persona-split → dev`, merge after Stoff73 approval
- [ ] Build with `./deploy/csjones-fynla/build.sh` (Phase 2 frontend migration requires it)
- [ ] Upload `public/build/` + cumulative file set: ~30 PHP backend (incl. 2 new enums from session 118) + ~12 frontend (incl. 3 new touches from session 118 — `taxStrategy.js`, `StrategyRecommendationList.vue`, `HouseholdView.vue`) + 4 migrations (none new in session 118)
- [ ] SSH: `php artisan migrate --force && php artisan db:seed --class=TaxConfigurationSeeder --force && php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize`
- [ ] Re-drive BS-26/27/28 against `csjones.co/fynla` per Rule #15
- [ ] Smoke test SaveTax dashboard with the 3 personas: high earner (>£125k → expects #1+#2), single_earner_couple (expects refined #9 + #11 sized + #15), young earner with kids (expects #16 + #17 + #18)
- [ ] Verify `GET /api/tax-strategy` JSON has NO `asset_shifting_suggestions` / `cross_spouse_suggestions` keys (Phase 2 contract)

### After dev green — production deploy

- [ ] Open PR `dev → main`, merge, repeat with `./deploy/fynla-org/build.sh`
- [ ] Smoke test fynla.org
- [ ] Re-drive the same 3-persona SaveTax matrix on prod

### Sprint 0 verification rollup (S0.17) — branch readiness

Once the deploy lands and BS-26/27/28 stay green:

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

- **`StrategyRecommendation` extras serialisation strategy** — currently spreads top-level via `array_merge`. Works but couples extras to the same namespace as canonical fields. If `extra` keys ever collide with canonical (`type`, `category`, `priority`, `title`, `description`, `requires_advice`), the canonical wins via `array_merge` order — silent bug risk. Consider explicit nesting under `extras` or per-strategy typed extension classes when Phase 3-5 add more strategy-specific fields.
- **`TaxStrategyCalculator::handleModuleAnalysis` summarisation** — INV-2.6.1 says "no `summariseToolAnalysis` stripping for this handler" but the S0.15 delivery note acknowledged it still wraps via `summariseToolAnalysis` at line 1512. Behaviour change with broader test surface; deferred follow-up.
- **Vue 3 `$listeners` warning on `<FynOnboardingChat>`** (carried from session 113-evening). Minor.
- **`StructuredResponseValidator` flagging "SIPP" as banned acronym** in LLM acks where the state's own prompt uses SIPP (carried from session 113-evening). Needs per-state allowlist or canonical exception.
- **TaxStrategyCalculator under-counts pension AA usage on initial load** — slider re-fires correctly. (Carried from session 113-evening; will be touched anyway during Phase 3-5 — fix opportunistically.)

---

## Known Issues

- Pre-existing time-flake in `AdviceReviewServiceTest::annual review due` — `subMonths(14)` followed by `diffInMonths(now())` can return 13 across month-end boundaries. Unrelated to any changes; flagged for separate fix.

---

## Deploy Status

- **`feature/fyn-persona-split`** — 246 commits ahead of `origin/main`, all pushed (incl. today's `ab3df47`). **Not yet deployed anywhere.**
- **`dev` branch** — last deployed commit unknown — needs catch-up to combined 112+113+114+115+117+118 work.
- **`main` / production fynla.org** — last deployed somewhere mid-April (sessions 105-107). All Sprint 0 + SaveTax + audit-fix + SaveTax Phase 1+2 work pending.

---

## Context for Next Session

Session 118 closed Phase 2 of the SaveTax strategy expansion. The dashboard now surfaces 11 new strategies + 6 refined household ones, all sorted by category + priority, all rendered with section headers in the live UI. Backend canonical contract is `recommendations[]`; legacy fields are gone end-to-end. Live-verified on `peak_earners` persona — strategies #2 / #7 / #17 / #18 all surface correctly.

**Next session should pick Phase 3** (capture extensions for #4 salary-sacrifice NI / #6 bed&ISA / #12 spouse-pension), since it's the natural next step in the catalogue's phase plan and unblocks 3 more strategies. Phase 3 is moderately bigger than Phase 2 because it adds capture-side changes (new tool fields, schema migrations) on top of new generators — but each strategy is independent and can be tested in isolation.

**Alternative**: cumulative dev deploy (combined sessions 112+113+114+115+117+118 to csjones.co/fynla). The deploy is queued in the NOT Done section with full file lists and SSH commands ready. This is the first deploy in the queue that requires a frontend rebuild (Phase 2 touched Vuex + components). After dev verification, production deploy follows.

If CSJ wants to defer Phase 3 to drain the deploy queue first, the deploy steps are above. Both paths are well-prepared; CSJ to choose.
