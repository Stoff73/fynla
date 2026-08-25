# SaveTax Campaign — Sections 4-6 As-Shipped Spec

**Release date:** 29 April 2026 (sessions 113)
**Branch:** `feature/fyn-persona-split`
**Plan:** `April/April29Updates/savetax-campaign-post-expenses-plan.md`
**Source whiteboards:**
- `April/April29Updates/campaignMap.jpeg` (post-expenses conversation flow)
- `April/April29Updates/houuseSpouse.jpeg` (non-working spouse asset-shifting strategy)

**Commits in this release:**

| Phase | Commit | Headline |
| --- | --- | --- |
| 1 | (this branch) | Schema migrations + models for household tax-strategy |
| 2 | (this branch) | 4 capture tools for sections 4-6 conversation flow |
| 3 | (this branch) | State machine campaign branch (9 new states) |
| 4 | (this branch) | TaxStrategyCalculator + /api/tax-strategy endpoints |
| 5 | (this branch) | Frontend dashboard + Vuex + xAI tool parity + arch fixes |
| 6 | (this branch) | BS-26/27/28 browser scenario stubs |

---

## What's new

### 1. Post-expenses conversation branch (path=campaign only)

Nine new state-machine states that fire only when `users.onboarding_fyn_path === 'campaign'` (i.e. user came from `/savetax`). For journey/focus users, the existing `STATE_PROFILE_REVIEW_EXPENDITURE → STATE_ASSET_CAPTURE` transition is unchanged.

```
STATE_PROFILE_REVIEW_EXPENDITURE
       │
       ├── path !== 'campaign' → STATE_ASSET_CAPTURE              (existing, unchanged)
       │
       └── path === 'campaign' → STATE_CAMPAIGN_OCCUPATIONAL_SCHEME (skip if not employed)
                                  ↓
                              STATE_CAMPAIGN_ISA_HOLDINGS
                                  ↓
                              STATE_CAMPAIGN_BANK_ACCOUNTS
                                  ↓
                              STATE_CAMPAIGN_INVESTMENT_ACCOUNTS
                                  ↓
                              STATE_CAMPAIGN_PENSION_CONTRIBS
                                  ↓
                              STATE_CAMPAIGN_SPOUSE_WORK         (skip if not married)
                                  ├── yes → STATE_CAMPAIGN_SPOUSE_HOUSEHOLD          (dual_earner)
                                  └── no  → STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS (single_earner_couple)
                                  ↓
                              STATE_CAMPAIGN_TERMINAL  (turn_type=terminal, navigate_to=/tax-strategy)
                                  ↓
                              STATE_DONE  (existing — onboarding_completed=true)
```

5 of the 9 states are `turn_type: delegated` — the LLM uses existing `create_pension`, `create_savings_account`, `create_investment_account`, `capture_salary_sacrifice` tools to capture multiple records per turn. 2 are `turn_type: grouped_extract` (spouse capture, single-tool composite). 1 is `bubbles` (yes/no for spouse_work). 1 is `terminal` with a navigate hook.

Routing callables: `nextFromExpenditureReview()` branches on `users.onboarding_fyn_path`; `nextFromSpouseWork()` branches on `users.household_calculation_mode`. Skip helpers: `skipIfNotEmployed`, `skipIfNotMarried`, `skipIfNotDualEarner`, `skipIfNotSingleEarnerCouple`.

### 2. Three first-class household paths

| Path | `household_calculation_mode` | Triggered by | Terminal page renders |
|---|---|---|---|
| **A — Single** | `'single'` (or null) | Single user reaches terminal | One AllowanceGrid (8 cards) + slider panel + recommendation list |
| **B — Dual-earner** | `'dual_earner'` | `capture_spouse_work_status` with `spouse_works=true` | HouseholdView (twin grids) + cross-spouse coordination panel |
| **C — Single-earner couple** | `'single_earner_couple'` | `capture_spouse_work_status` with `spouse_works=false` | HouseholdView (twin grids, spouse mostly empty) + AssetShiftingPanel |

Path C emits asset-shifting suggestions sized to the **lesser** of (a) user's at-risk holdings and (b) spouse's unused tax capacity. The whiteboard's £535k-of-gilts → £18,750 tax-free example is captured by the savings-to-spouse calculation: PA (£12,570) + Starting Rate for Savings (£5,000) + PSA basic (£1,000) = £18,570/yr interest tax-free if held in spouse's name.

### 3. New capture tools (4)

All registered in both `AiToolDefinitions` (Anthropic) and `XaiToolDefinitions` (xAI) for full provider parity. Whitelisted in `OnboardingChatDirector::captureToolSet()`.

| Tool | Purpose | Writes |
|---|---|---|
| `capture_salary_sacrifice` | Set salary_sacrifice flag on a specific DC pension | `dc_pensions.salary_sacrifice` |
| `capture_spouse_work_status` | Branch the household calculation mode | `users.marriage_allowance_eligible`, `users.household_calculation_mode` |
| `capture_spouse_household_data` | Capture working-spouse data (path B) | `tax_strategy_household_inputs.spouse_*` (working fields) |
| `capture_spouse_non_working_assets` | Capture non-working-spouse standalone assets (path C) | `tax_strategy_household_inputs.spouse_existing_*` (non-working fields) |

All 4 reject preview users via the standard `previewBlocked()` pattern.

### 4. TaxStrategyCalculator + dashboard

**Backend service:** `App\Services\Tax\TaxStrategyCalculator` is a stateless calculator that composes `TaxConfigService` allowance values with per-user data from `users`, `dc_pensions`, `savings_accounts`, `investment_accounts`, `tax_strategy_household_inputs`. Branches on `users.household_calculation_mode` for the 3 paths. Sub-50ms benchmark — designed for slider drag responsiveness. NEVER writes to the database (overrides applied in-memory only).

Architecture compliance: tax band thresholds sourced from `TaxConfigService::getIncomeTax()['bands']` (no hardcoded 12570/50270/125140); PSA values from `getIncomeTax()['personal_savings_allowance']`. Status colours per CLAUDE.md Rule #9: `spring | violet | raspberry` only — no amber/orange.

**Endpoints (auth:sanctum):**
- `GET  /api/tax-strategy` → initial dashboard payload
- `POST /api/tax-strategy/calculate` → in-memory recalc with override DTO; validates ranges (0-100% pension, ≤£20k ISA top-up)

**Frontend:** `/tax-strategy` route under AppLayout; `TaxStrategyDashboard.vue` view + 7 sub-components; debounced (200ms) Vuex `recalculate` action drives the slider panel. Tile on `/actions` for savetax users.

### 5. Schema additions

**users:**
- `marriage_allowance_eligible BOOLEAN NULL` — set true when spouse_works=no
- `household_calculation_mode VARCHAR(32) NULL` — `single | dual_earner | single_earner_couple`

**dc_pensions:**
- `salary_sacrifice BOOLEAN NULL`

**tax_strategy_household_inputs (new table):**
- 12 nullable fields covering both paths B (working-spouse) and C (non-working-spouse standalone assets). One row per user (FK + unique).

---

## Test coverage delta

- **+8** Pest cases for the 4 new capture tools (`tests/Feature/AI/DirectWrite/Capture*`)
- **+13** Pest cases for `TaxStrategyCalculator` covering all 3 paths, override semantics, <50ms benchmark, edge cases
- **+9** Pest cases for endpoints (`Show` + `Calculate` validation + delta semantics)
- **+12** Pest cases for state machine campaign branch (routing, skip_if, terminal, all 9 states reachable)
- **+10** appended to existing `OnboardingStateMachineTest` expected state list
- **+3** browser scenario stubs (BS-26/27/28) registered for live Playwright runs

**Total: ~55 new Pest cases.** Architecture suite stays at 95/95 (after fixing hardcoded-value violations + tool catalogue parity in Phase 5). Onboarding + Fyn + Auth + AI + TaxStrategy + tax service suites all green; zero regressions.

---

## Out of scope (deferred)

| # | Item | Rationale |
|---|---|---|
| OS1 | Eval YAML scenarios for the 9 new states | Sprint 1 follow-up, gated on S1.7.a (AssertionHelpers extension) |
| OS2 | "Apply this strategy" write-back from sliders | Read-only modelling for v1 |
| OS3 | Cross-link to spouse User record via existing `spouse_id` / `SpousePermission` | Standalone capture sufficient for v1 |
| OS4 | Mobile (Capacitor iOS) rendering of `/tax-strategy` | Web-first |
| OS5 | UK Gilts-specific recommendation (whiteboard mention) | Too narrow for v1 |
| OS6 | Other campaigns (`/biggerpension`, `/paymortgage`) | State-machine pattern is reusable when those are specced |

---

## Live verification

Three browser scenarios document the end-to-end flow per path. Each is a Playwright MCP script with explicit DB assertions. They are `markPendingInteractiveRun` until CSJ drives them in a live browser session per CLAUDE.md Rule #15:

- `tests/Browser/scenarios/BS-26-savetax-single-employed.php` — Path A
- `tests/Browser/scenarios/BS-27-savetax-married-spouse-works.php` — Path B
- `tests/Browser/scenarios/BS-28-savetax-married-spouse-no-work.php` — Path C

Each scenario asserts:
1. The campaign welcome bubble fires with the correct opener.
2. The 5 capture states (occupational, ISA, banks, investments, pensions) execute in order.
3. The spouse routing branches correctly per path.
4. `STATE_CAMPAIGN_TERMINAL` navigates to `/tax-strategy`.
5. The dashboard renders the correct view per `household_calculation_mode`.
6. Sliders trigger live recalc (debounced 200ms).
7. DB state matches the expected per-path shape.

---

## Related docs

- **Plan:** `April/April29Updates/savetax-campaign-post-expenses-plan.md`
- **Sections 1-3 spec (already shipped):** `April/April28Updates/savetax-campaign-onboarding-spec.md`
- **Sections 1-3 plan:** `April/April28Updates/savetax-campaign-onboarding-plan.md`
- **Two-Fyn canonical contract:** `April/April24Updates/spec/00-canonical.md`
- **CLAUDE.md rules cited:** #9 (no amber), #11 (design system), #14 (no icons on banned surfaces), #15 (LOOP UNTIL CORRECT)
