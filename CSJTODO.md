# CSJTODO — Fynla

*Last updated: 30 April 2026 — session 117 (SaveTax dashboard strategy expansion + Phase 1 calculator refactor shipped, commit `7b31508`).*
*Previous session: 116 (30 April — architecture map post-fix rewrite + tool catalogue, no code).*

---

## Session 117 (30 April 2026) — SaveTax dashboard strategy expansion

**Branch:** `feature/fyn-persona-split`. **Commits:** 1 (`7b31508`).

CSJ flagged that the SaveTax dashboard built post-onboarding is missing key tax strategies — concretely the £100,000-£125,140 Personal Allowance taper (60% effective rate band) and the unemployed-spouse asset-shifting trio (Personal Allowance + Starting Rate for Savings + Personal Savings Allowance = £18,570 of interest tax-free per year). He asked for analysis on whether to (a) write a campaign-specific system prompt or (b) adapt the current prompt.

### Completed this session

- [x] **Diagnosis:** the missing strategies are a **calculator-side problem, not a prompt-side problem**. Per architecture map §2.1, the LLM is deliberately removed from strategy generation — `TaxStrategyCalculator.php` is fully deterministic. Adding strategy text to a system prompt would re-introduce the exact hallucination class the deterministic layer was designed to prevent. The fix is to add strategy generators in the calculator + extend capture for the data the new strategies need.
- [x] **Drafted catalogue v0.1** at `April/April30pdates/savetax-strategy-catalogue.md` — 15 deterministic UK personal-tax strategies grouped A-D (income-band, allowance harvesting, household, other), plus implementation phasing and 6 open questions for redline.
- [x] **CSJ redlined v0.1** with 6 inline answers — LISA yes (subject to criteria), JISA + Junior Pension yes, promote tapered AA to `high`, exclude VCT/EIS/SEIS/BADR, copy cap → 220 chars + Title/Sub-line split, #14 needs both threshold-income AND adjusted-income gates.
- [x] **Catalogue v0.2 baked in** — added strategies #16 (Lifetime ISA, under-40s), #17 (Junior ISA), #18 (Junior Pension); promoted #14 to `high`; split #9 headline into Title + Sub-line; capture-table updated for #14 dual gate; resolutions log replaces open questions; bottom version stamp updated. Final structure: 18 strategies across 5 sections (A. income-band 4, B. allowance harvesting 3, C. household 5, D. other high-leverage 3, E. lifecycle/dependant 3) with closed-form saving formulas, capture-data ✓/✗, and headline copy.
- [x] **Phase 1 shipped (commit `7b31508`)** — calculator refactor to canonical flat `recommendations[]` DTO. New `app/DataTransferObjects/StrategyRecommendation.php` typed value object (carries `type`, `category`, `priority`, `title`, `description`, `estimated_annual_tax_saved`, `requires_advice`, plus extras spread on serialisation for back-compat). `TaxStrategyOutputDTO` extended with `recommendations` field as canonical source-of-truth; legacy `assetShiftingSuggestions` and `crossSpouseSuggestions` arrays preserved as filtered views (zero rendered-output change, frontend untouched). 4 categories defined: `income_band`, `allowance`, `household`, `lifecycle`, `warning`.
- [x] **Test coverage:** 22 passed (156 assertions) on the tax-strategy slice — original 17 unchanged + 4 new DTO unit tests + 4 new Phase 1 contract tests + 1 new feature test. 95/95 architecture green. Pint clean. Tech-debt audit at `tech-debt-report.md`: 0 critical, 0 warnings, 3 deferred suggestions (category/priority enums + array_map collapse — all pre-emptively resolved by Phase 2 refactor).

### Session 117 deliverables

| Artefact | Path | Status |
|---|---|---|
| Strategy catalogue v0.2 | `April/April30pdates/savetax-strategy-catalogue.md` | New (gitignored, vault synced) |
| Catalogue PDF | `April/April30pdates/savetax-strategy-catalogue.pdf` | New (gitignored, vault synced) |
| Phase 1 commit | `7b31508` (`feature/fyn-persona-split`) | Pushed to origin |
| Tech-debt report | `tech-debt-report.md` | Updated |
| Deploy notes | `April/April30pdates/deploy.md` | Session 117 addendum added |

### Phase plan (5 phases, agreed with CSJ)

1. ✅ **Phase 1 — Calculator refactor → flat `recommendations[]` DTO** (this session, commit `7b31508`)
2. ⏳ **Phase 2 — Strategies needing no new capture (11 of 18):** #1 (PA Taper Rescue), #2 (Additional-Rate Avoidance), #5 (ISA Top-Up vs PSA), #7 (Dividend Allowance Harvest), #9 (PA + Starting Rate + PSA spouse transfer — expanded copy), #10 (spouse ISA top-up), #11 (GIA rebalance — sized), #15 (joint-savings PSA doubling), #16 (Lifetime ISA), #17 (Junior ISA), #18 (Junior Pension) — **plus frontend migration from legacy fields to `recommendations[]`** so new categories render
3. ⏳ **Phase 3 — Capture extensions:** #4 (salary sacrifice flag + employer NI rebate %), #6 (unrealised gain per holding), #12 (spouse pension flag) — extends existing `STATE_CAMPAIGN_*` tools
4. ⏳ **Phase 4 — New states / tools:** #3 (pension AA carry-forward, new tool `capture_pension_history`), #13 (Gift Aid, new tool `capture_charitable_giving`)
5. ⏳ **Phase 5 — Composed-income warnings:** #14 (Tapered Annual Allowance — needs `adjusted_income` AND `threshold_income` views)

Each phase ends with a green Pest sweep + a live SaveTax browser walkthrough per Rule #15 before the next phase starts.

---

## NOT Done — Outstanding for next session

### Phase 2 — SaveTax strategy generators + frontend migration

Strategies #1, #2, #5, #7, #9, #10, #11, #15, #16, #17, #18 (11 of the 18). All trigger on data already captured by the SaveTax campaign — no new tools or migrations needed.

- [ ] Calculator-side: add 11 generator methods to `TaxStrategyCalculator.php`, each returning `StrategyRecommendation[]` with appropriate category. Existing `buildAssetShiftingSuggestions` + `buildCrossSpouseSuggestions` collapse into `buildHouseholdRecommendations`.
- [ ] Frontend-side: migrate `taxStrategy.js` Vuex getters from `assetShiftingSuggestions` / `crossSpouseSuggestions` to a single `recommendations` getter sortable by `category` + `priority`. Update `StrategyRecommendationList.vue` and `HouseholdView.vue` to consume the new shape.
- [ ] Drop the deprecated `assetShiftingSuggestions` and `crossSpouseSuggestions` fields from `TaxStrategyOutputDTO` once frontend migration is verified live.
- [ ] Promote `category` and `priority` to PHP backed enums (StrategyCategory, StrategyPriority) — tech-debt suggestions #1 and #2 from session 117.
- [ ] Live browser walkthrough on the SaveTax persona to confirm all 11 strategies surface correctly (per Rule #15).
- [ ] Pest green: tax-strategy slice + architecture suite.

Spec: `April/April30pdates/savetax-strategy-catalogue.md` v0.2 sections A-E (filtered to the 11 no-capture entries).

### Phase 3 — Capture extensions (#4, #6, #12)

- [ ] Extend `capture_salary_sacrifice` tool with `is_salary_sacrifice: bool` + optional `employer_ni_rebate_pct: float` (#4)
- [ ] Capture `unrealised_gain` per holding (or `purchase_price` + `current_value`) on `create_holding` / `create_investment_account` (#6)
- [ ] Verify `spouse_existing_pension_balance` is captured for non-working spouses; extend `capture_spouse_non_working_assets` if not (#12)
- [ ] Calculator generators for these three strategies
- [ ] Migrations as needed; live browser walk; green Pest

### Phase 4 — New states / tools (#3, #13)

- [ ] New tool `capture_pension_history` — captures last 3 years' pension input amounts; insert in `STATE_CAMPAIGN_PENSION_CONTRIBS` (#3)
- [ ] New tool `capture_charitable_giving` — captures annual £ donations; either as new `STATE_CAMPAIGN_*` state or as optional dashboard input (#13)
- [ ] Add to `OnboardingPromptBuilder::toolsForFocus('savetax')` lines 118-127
- [ ] Calculator generators; live browser walk; green Pest

### Phase 5 — Composed-income warnings (#14)

- [ ] Compose `adjusted_income` view (employment + bonus + employer pension contributions added back)
- [ ] Compose `threshold_income` view (employment + bonus, employee pension contributions excluded)
- [ ] Tapered Annual Allowance warning generator — fires only when BOTH `adjusted_income > £260,000` AND `threshold_income > £200,000` (per CSJ redline)
- [ ] Live browser walk on a high-income persona; green Pest

### Deploy combined sessions 112 + 113 + 114 + 115 + 117 to dev (csjones.co/fynla)

Session 117 modified files added to the cumulative deploy set: 1 new (`StrategyRecommendation.php`) + 2 modified (`TaxStrategyOutputDTO.php`, `TaxStrategyCalculator.php`). No frontend rebuild for session 117 specifically (Phase 2 will need one). All deploy notes at `April/April30pdates/deploy.md` (now contains session-115 + session-117 sections).

- [ ] Open PR `feature/fyn-persona-split → dev`, merge after Stoff73 approval
- [ ] Build with `./deploy/csjones-fynla/build.sh` (Vue store change in `aiChat.js` from session 115 still requires frontend rebuild)
- [ ] Upload `public/build/` + cumulative file set: ~30 PHP backend (incl. 3 new from session 115 + 1 new from session 117) + ~12 frontend + 4 migrations (none new)
- [ ] SSH: `php artisan migrate --force && php artisan db:seed --class=TaxConfigurationSeeder --force && php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize`
- [ ] Re-drive BS-26/27/28 against `csjones.co/fynla` per Rule #15
- [ ] Spot-check: try a prompt-injection name like "François <script>alert(1)</script>" in registration to verify F-2 preserves Unicode
- [ ] Spot-check: hit `GET /api/tax-strategy` for a `single_earner_couple` user, confirm `recommendations` key present in JSON, length matches `asset_shifting_suggestions` (Phase 1 contract verification on the deployed environment)

### After dev green — production deploy

- [ ] Open PR `dev → main`, merge, repeat with `./deploy/fynla-org/build.sh`
- [ ] Smoke test fynla.org

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

- **`StrategyRecommendation::category` / `priority` are unenforced strings** — convert to PHP backed enums during Phase 2 (tech-debt suggestions #1 and #2 from this session's audit)
- **`TaxStrategyCalculator::calculate()` two-step `array_map`** — collapse to single map per collection during Phase 2 refactor (tech-debt suggestion #3 from this session)
- **`handleModuleAnalysis` summarisation** — INV-2.6.1 says "no `summariseToolAnalysis` stripping for this handler" but the S0.15 delivery note acknowledged it still wraps via `summariseToolAnalysis` at line 1512. Behaviour change with broader test surface; deferred follow-up.
- **Vue 3 `$listeners` warning on `<FynOnboardingChat>`** (carried from session 113-evening). Minor.
- **`StructuredResponseValidator` flagging "SIPP" as banned acronym** in LLM acks where the state's own prompt uses SIPP (carried from session 113-evening). Needs per-state allowlist or canonical exception.
- **TaxStrategyCalculator under-counts pension AA usage on initial load** — slider re-fires correctly. (Carried from session 113-evening; will be touched anyway during Phase 2 refactor — fix opportunistically.)

---

## Known Issues

- Pre-existing time-flake in `AdviceReviewServiceTest::annual review due` — `subMonths(14)` followed by `diffInMonths(now())` can return 13 across month-end boundaries. Unrelated to any changes; flagged for separate fix.

---

## Deploy Status

- **`feature/fyn-persona-split`** — 245 commits ahead of `origin/main`, all pushed (incl. today's `7b31508`). **Not yet deployed anywhere.**
- **`dev` branch** — last deployed commit unknown — needs catch-up to combined 112+113+114+115+117 work.
- **`main` / production fynla.org** — last deployed somewhere mid-April (sessions 105-107). All Sprint 0 + SaveTax + audit-fix + SaveTax Phase 1 work pending.

---

## Context for Next Session

Session 117 closed the loop on CSJ's flagged gap: the SaveTax dashboard's tax-strategy generation isn't a prompt problem, it's a calculator problem. The 18-strategy catalogue is now spec'd at v0.2 (post-redline) with closed-form formulas and a 5-phase implementation plan. Phase 1 (calculator refactor) is complete and committed (`7b31508`) — `recommendations[]` is the canonical source-of-truth on `TaxStrategyOutputDTO`, with legacy fields preserved as views for back-compat. Frontend untouched, zero rendered-output change.

**Next session should start Phase 2** — implement the 11 strategies that need no new capture (#1, #2, #5, #7, #9 expanded, #10, #11 sized, #15, #16, #17, #18) and migrate the frontend from legacy fields to `recommendations[]`. Spec in `April/April30pdates/savetax-strategy-catalogue.md` §A-E (filter to no-capture entries). Phase 2 is the bigger of the upcoming chunks because it touches both calculator and frontend, and it's where users first see the new strategies render. Each generator is small and independent — good candidate for parallel sub-agent work or a clean sequential execution with browser walks between every 2-3 strategies.

If CSJ wants to defer Phase 2 and ship the cumulative dev deploy first instead, the deploy steps are in the NOT Done section above. Both options are well-prepared.
