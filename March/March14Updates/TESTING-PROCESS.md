# Decision Engine Upgrade — Testing Process

> **Date:** 2026-03-14 | **Branch:** `engineUpgrade` | **Commit:** `446b04d`
> **Automated Tests:** 1871 passed, 0 failures (8179 assertions)
> **Manual Testing Required:** Yes — 6 personas across 5 modules

---

## Table of Contents

1. [Pre-Testing Setup](#1-pre-testing-setup)
2. [Automated Test Suite](#2-automated-test-suite)
3. [Module-by-Module Manual Testing](#3-module-by-module-manual-testing)
4. [Cross-Module Integration Testing](#4-cross-module-integration-testing)
5. [Data Readiness Gate Testing](#5-data-readiness-gate-testing)
6. [Persona End-to-End Testing](#6-persona-end-to-end-testing)
7. [Notification Testing](#7-notification-testing)
8. [Frontend Component Testing](#8-frontend-component-testing)
9. [Regression Checklist](#9-regression-checklist)
10. [Edge Cases & Error Handling](#10-edge-cases--error-handling)

---

## 1. Pre-Testing Setup

### 1.1 Start Development Server

```bash
php artisan db:seed
./dev.sh
```

Wait for both Laravel and Vite to confirm ready.

### 1.2 Verify Database State

```bash
php artisan tinker --execute="
echo 'TaxConfig: ' . \App\Models\TaxConfiguration::where('is_active', true)->count() . PHP_EOL;
echo 'Savings triggers: ' . \App\Models\SavingsActionDefinition::where('is_enabled', true)->count() . PHP_EOL;
echo 'Investment triggers: ' . \App\Models\InvestmentActionDefinition::where('is_enabled', true)->count() . PHP_EOL;
echo 'Protection triggers: ' . \App\Models\ProtectionActionDefinition::where('is_enabled', true)->count() . PHP_EOL;
echo 'Retirement triggers: ' . \App\Models\RetirementActionDefinition::count() . PHP_EOL;
echo 'Preview users: ' . \App\Models\User::where('is_preview_user', true)->count() . PHP_EOL;
"
```

**Expected:**
- TaxConfig: 1
- Savings triggers: 41 (or subset enabled)
- Investment triggers: 14 (7 savings-overlap triggers DISABLED)
- Protection triggers: 28
- Retirement triggers: 18
- Preview users: 9+

### 1.3 Verify Disabled Investment Triggers

```bash
php artisan tinker --execute="
\$disabled = \App\Models\InvestmentActionDefinition::whereIn('key', [
    'emergency_runway_below', 'emergency_runway_between', 'has_poor_rate_accounts',
    'isa_remaining_and_runway_above', 'surplus_exists_and_isa_remaining',
    'surplus_exceeds_isa', 'surplus_exceeds_pension'
])->where('is_enabled', false)->count();
echo 'Disabled overlap triggers: ' . \$disabled . '/7' . PHP_EOL;
"
```

**Expected:** 7/7

---

## 2. Automated Test Suite

### 2.1 Full Suite

```bash
./vendor/bin/pest
```

**Expected:** 1871+ tests pass, 0 failures

### 2.2 Architecture Tests Only

```bash
./vendor/bin/pest --testsuite=Architecture
```

**Expected:** All pass including:
- No hardcoded Personal Savings Allowance values in services
- No hardcoded 0.06 growth rates in Investment
- No hardcoded 0.047 withdrawal rates in Retirement
- No hardcoded protection values
- No `EstateDefaults::ESTIMATED_*` references
- All services use strict types

### 2.3 Integration Tests Only

```bash
./vendor/bin/pest tests/Integration/CrossModuleIntegrationTest.php
```

**Expected:** All pass including:
- Emergency fund owned by Savings engine
- ISA allowance shared across modules
- Priority ranker handles all module shapes
- No duplicate recommendations

### 2.4 Module-Specific Tests

```bash
./vendor/bin/pest tests/Unit/Services/Savings/
./vendor/bin/pest tests/Unit/Services/Estate/
./vendor/bin/pest tests/Unit/Services/Investment/
./vendor/bin/pest tests/Unit/Services/Protection/
./vendor/bin/pest tests/Unit/Services/Retirement/
./vendor/bin/pest tests/Unit/Agents/
```

---

## 3. Module-by-Module Manual Testing

### 3.1 Savings Module

**Test URL:** Log in as any persona, navigate to Savings

#### API Endpoints to Test

| Method | Endpoint | What Changed |
|--------|----------|-------------|
| `GET` | `/api/savings` | Now includes `psa_position`, `fscs_exposure`, `emergency_fund_target`, `children_savings` |
| `POST` | `/api/savings/analyze` | Now returns `can_proceed`, `readiness_checks` when data incomplete |
| `GET` | `/api/savings/recommendations` | Now uses `SavingsPlanService` with 41 DB-driven triggers |

#### Manual Checks

- [ ] **Savings dashboard loads** — no errors, no blank sections
- [ ] **Personal Savings Allowance position** — shows tax band, allowance amount, annual interest, breach/headroom
- [ ] **Financial Services Compensation Scheme exposure** — shows per-institution totals, breach warnings if any exceed £85,000
- [ ] **Emergency fund target** — shows employment-based target (6 months for employed, 9 for self-employed, 3 for retired)
- [ ] **Recommendations load** — structured cards with priority, title, description, action links
- [ ] **Decision paths** — collapsible "What Drives This" sections on recommendation cards
- [ ] **Missing data cards** — if data incomplete, shows what's missing with links to input forms
- [ ] **Add a new savings account** — form works, account appears in list
- [ ] **Delete a savings account** — removes correctly, recommendations update

#### Specific Trigger Verification

For a persona with savings accounts:
- [ ] Rate below market triggers show if account rate is low
- [ ] Emergency fund trigger shows if runway < target months
- [ ] ISA recommendation shows if not fully using ISA allowance
- [ ] No duplicate recommendations between Savings and Investment

### 3.2 Estate Planning Module

**Test URL:** Log in as peak_earners or widow, navigate to Estate Planning

#### API Endpoints to Test

| Method | Endpoint | What Changed |
|--------|----------|-------------|
| `GET` | `/api/estate` | Now returns `can_proceed`, `readiness_checks`, `pension_amendment` |

#### Manual Checks

- [ ] **Estate dashboard loads** — no errors
- [ ] **Inheritance Tax calculation** — NRB correctly shows £325,000 minus any PET/CLT gifts
- [ ] **2027 pension amendment banner** — violet banner appears showing dual scenario (current vs post-2027 liability)
- [ ] **14-year rule** — if persona has CLTs 7-14 years old, they reduce NRB for PETs
- [ ] **Residence Nil Rate Band** — message clarifies direct descendants only (children, grandchildren — not nieces/nephews)
- [ ] **Liquidity classification** — investments shown as semi-liquid, pensions as illiquid
- [ ] **Life insurance checks** — warnings for policies not in trust, single-life policies for married users
- [ ] **Trust projection** — if trust data exists, shows year-by-year growth trajectory
- [ ] **Missing data alert** — if data incomplete, shows grouped checks (blocking/warning/info) with form links

### 3.3 Investment Module

**Test URL:** Log in as peak_earners, navigate to Investment

#### API Endpoints to Test

| Method | Endpoint | What Changed |
|--------|----------|-------------|
| `GET` | `/api/investment` | Now includes `can_proceed`, readiness gate |
| `POST` | `/api/investment/analyze` | Readiness gate at start of analysis |
| `GET` | `/api/investment/recommendations` | Now runs full pipeline (waterfall + triggers + transfers + spouse) |

#### Manual Checks

- [ ] **Investment dashboard loads** — no errors
- [ ] **Readiness gate** — if missing risk profile or income, shows InvestmentReadinessGate component instead of empty state
- [ ] **Recommendations load** — structured cards from pipeline
- [ ] **Contribution waterfall** — recommendations follow priority order (ISA → Pension → Premium Bonds → etc.)
- [ ] **Transfer recommendations** — bed and ISA, consolidation, fee reduction suggestions
- [ ] **Spouse optimisation** — if married persona, shows joint planning recommendations
- [ ] **No emergency fund recommendations** — Investment should NOT show standalone emergency fund cards (Savings owns those)
- [ ] **Growth rates** — no hardcoded 6% visible; rates should reflect user's risk profile
- [ ] **Fee analysis** — OCF threshold from config (0.15%), not hardcoded

### 3.4 Protection Module

**Test URL:** Log in as young_family, navigate to Protection

#### API Endpoints to Test

| Method | Endpoint | What Changed |
|--------|----------|-------------|
| `GET` | `/api/protection` | Now includes `employer_benefits`, `state_benefits`, `can_proceed` |
| `POST` | `/api/protection/analyze` | Readiness gate + employer benefits in gap analysis |
| `GET` | `/api/protection/recommendations` | 28 DB-driven triggers (was 11) |

#### Manual Checks

- [ ] **Protection dashboard loads** — no errors
- [ ] **No numeric scores displayed** — adequacy shown as text (Excellent/Good/Fair/Critical), NOT numbers
- [ ] **Gap analysis** — shows coverage gaps with employer benefits deducted
- [ ] **Employer benefits section** — death in service, group income protection, group critical illness displayed if data entered
- [ ] **State benefits** — Statutory Sick Pay offset shown (weekly rate × max weeks)
- [ ] **Self-employed handling** — if self-employed persona (entrepreneur), no SSP shown
- [ ] **Recommendations** — 28 triggers available, only relevant ones fire
- [ ] **Policy form** — joint life checkbox available on life insurance form
- [ ] **No hardcoded values visible** — withdrawal rate, final expenses, education costs all from config

### 3.5 Retirement Module

**Test URL:** Log in as retired_couple or peak_earners, navigate to Retirement

#### API Endpoints to Test

| Method | Endpoint | What Changed |
|--------|----------|-------------|
| `GET` | `/api/retirement` | Now includes `can_proceed`, enhanced analysis |
| `POST` | `/api/retirement/analyze` | Readiness gate + salary sacrifice + auto-enrolment + enhanced annuity |
| `GET` | `/api/retirement/recommendations` | 18 triggers (was 10) |
| `GET` | `/api/retirement/strategies` | Withdrawal rates from TaxConfigService (4.7% sustainable, 4.0% safe) |

#### Manual Checks

- [ ] **Retirement dashboard loads** — no errors
- [ ] **Salary sacrifice display** — shows National Insurance savings (employee + employer), net cost comparison
- [ ] **Auto-enrolment status** — shows if meeting minimum 8% total, employer 3%, employee 5%
- [ ] **Enhanced annuity** — if smoker/health conditions on protection profile, shows enhanced rate
- [ ] **Care costs** — if modelled in retirement profile, factored into decumulation
- [ ] **State Pension Age** — shows 66 (current SPA from config), not hardcoded 67 or 68
- [ ] **Withdrawal rates** — 4.7% sustainable rate from config, not hardcoded
- [ ] **Inflation rate** — 2.5% from config (was 2.0% in some places — now consistent)
- [ ] **Annuity rates** — age-based estimates from config, not hardcoded lookup tables

---

## 4. Cross-Module Integration Testing

### 4.1 ISA Allowance Sharing

- [ ] Log in as a persona with both Cash ISA and Stocks & Shares ISA
- [ ] Navigate to Savings — check ISA allowance remaining
- [ ] Navigate to Investment — check ISA allowance remaining
- [ ] **Both should show the same remaining amount** (£20,000 minus combined ISA contributions)

### 4.2 Emergency Fund Ownership

- [ ] Navigate to Savings — should show emergency fund recommendations with actionable cards
- [ ] Navigate to Investment — should NOT show standalone emergency fund recommendations
- [ ] Investment may show "emergency fund gates surplus" context note but not an action card

### 4.3 CoordinatingAgent Priority

- [ ] Navigate to the main dashboard or financial plan view
- [ ] Verify recommendations from all modules appear in priority order
- [ ] Emergency fund (from Savings) should be highest priority when applicable
- [ ] No duplicate recommendations across modules

### 4.4 Surplus Allocation

- [ ] For a persona with excess savings above emergency fund target
- [ ] Savings engine should recommend moving excess to ISA/pension/bonds
- [ ] Investment engine should show the waterfall allocation for new contributions
- [ ] The two should complement, not contradict each other

---

## 5. Data Readiness Gate Testing

For each module, test with a user missing required data:

### 5.1 Create Test User (Missing Data)

```bash
php artisan tinker --execute="
\$user = \App\Models\User::factory()->create(['email' => 'readiness-test@test.com', 'date_of_birth' => null, 'annual_employment_income' => 0]);
echo 'User ID: ' . \$user->id;
"
```

### 5.2 Test Each Module's Gate

Log in as this user (or use API):

| Module | Blocking Checks | Expected Behaviour |
|--------|----------------|-------------------|
| Savings | DOB, income, expenditure | Shows MissingDataCard with links to `/profile/personal` and `/profile/employment` |
| Estate | DOB, marital status, at least one asset | Shows missing data alert grouped by severity |
| Investment | DOB, income, risk profile, expenditure | Shows InvestmentReadinessGate component |
| Protection | DOB, income, marital status | Shows readiness gate, no auto-created ProtectionProfile |
| Retirement | DOB, marital status, income | Shows readiness gate, no false 100% readiness score |

### 5.3 Progressive Data Entry

- [ ] Add date of birth → re-test each module (blocking count should decrease)
- [ ] Add income → re-test (more modules should unlock)
- [ ] Add marital status → estate and protection should unlock
- [ ] Add risk profile → investment should unlock
- [ ] Verify each module transitions from "blocked" to "analysis available" smoothly

---

## 6. Persona End-to-End Testing

**CRITICAL:** Run `php artisan db:seed` before EVERY persona test.

### 6.1 Young Family — James & Emily Carter

**Focus:** Mortgage, workplace pensions, children's savings

- [ ] Log in via landing page persona selector → Young Family
- [ ] **Savings:** Emergency fund target = 6 months (employed), children's savings suggestions (Junior ISA for each child)
- [ ] **Protection:** Death in service from employer, gap analysis with dependants, education funding gaps
- [ ] **Investment:** ISA and pension recommendations, spouse ISA coordination
- [ ] **Retirement:** Auto-enrolment compliance check, salary sacrifice analysis
- [ ] **Estate:** 2027 pension amendment banner, life insurance trust check

### 6.2 Peak Earners — David & Sarah Mitchell

**Focus:** Multiple properties, SIPP + NHS pension, spouse optimisation

- [ ] Log in → Peak Earners
- [ ] **Savings:** Personal Savings Allowance breach likely (higher/additional rate), ISA recommended, Financial Services Compensation Scheme exposure if large balances
- [ ] **Investment:** Full waterfall (ISA exhausted → pension → VCT/EIS → GIA), spouse optimisation (CGT sharing, ISA coordination, Marriage Allowance)
- [ ] **Estate:** Inheritance Tax calculation with Nil Rate Band deduction for gifts, Residence Nil Rate Band for main residence to children
- [ ] **Retirement:** Multiple pension consolidation opportunity, salary sacrifice for higher earner
- [ ] **Protection:** Higher income multipliers, comprehensive cover needs

### 6.3 Widow — Margaret Thompson

**Focus:** Estate planning, 2027 pension amendment

- [ ] Log in → Widow
- [ ] **Estate:** Transferred Nil Rate Band from deceased spouse, 2027 pension amendment dual scenario, gifting strategies
- [ ] **Savings:** Reduced emergency fund target if retired (3 months)
- [ ] **Protection:** Reduced protection needs (no dependants), review existing policies

### 6.4 Entrepreneur — Alex Chen

**Focus:** SIPP, self-employed protection, salary sacrifice N/A

- [ ] Log in → Entrepreneur
- [ ] **Savings:** Emergency fund target = 9 months (self-employed)
- [ ] **Protection:** No Statutory Sick Pay (self-employed), income protection critical, no employer benefits
- [ ] **Retirement:** Salary sacrifice NOT available (self-employed), SIPP contribution recommendations
- [ ] **Investment:** Business Relief awareness, VCT/EIS opportunities

### 6.5 Young Saver — John Morgan

**Focus:** Emergency fund, first-time savings, Lifetime ISA eligibility

- [ ] Log in → Young Saver
- [ ] **Savings:** Emergency fund building recommendations, Lifetime ISA suggestion (if under 40)
- [ ] **Investment:** Limited — may trigger readiness gate if no risk profile
- [ ] **Protection:** Basic needs, few dependants

### 6.6 Retired Couple — Robert & Patricia Williams

**Focus:** Decumulation, care costs, enhanced annuity

- [ ] Log in → Retired Couple
- [ ] **Savings:** Emergency fund target = 3 months (retired)
- [ ] **Retirement:** Decumulation analysis, care cost modelling, enhanced annuity if health conditions, State Pension forecast check
- [ ] **Estate:** Estate planning focus, gifting strategies, 2027 amendment impact
- [ ] **Investment:** Withdrawal focus, not accumulation

---

## 7. Notification Testing

### 7.1 Verify Commands Exist

```bash
php artisan list | grep -E 'savings:|estate:|protection:'
```

**Expected:**
```
savings:send-alerts
estate:send-alerts
protection:send-alerts
```

### 7.2 Test Savings Alerts

```bash
php artisan savings:send-alerts
```

**Expected:** Runs without errors. Reports counts for maturity, rate expiry, ISA allowance, and emergency fund alerts.

### 7.3 Test Estate Alerts

```bash
php artisan estate:send-alerts
```

**Expected:** Runs without errors. Reports gift exemption, trust anniversary, and annual recalculation checks.

### 7.4 Test Protection Alerts

```bash
php artisan protection:send-alerts
```

**Expected:** Runs without errors. Reports expired policies, approaching renewals, and annual review prompts.

### 7.5 Verify Scheduled Registration

```bash
php artisan schedule:list | grep -E 'savings|estate|protection'
```

**Expected:**
```
savings:send-alerts ........ Daily at 10:00
estate:send-alerts ......... Daily at 10:30
protection:send-alerts ..... Daily at 09:15
```

---

## 8. Frontend Component Testing

### 8.1 SavingsDecisionPath

- [ ] Appears on savings recommendation cards when decision path data exists
- [ ] Collapsible — click expands/collapses
- [ ] Green dots for passed steps, red dots for failed steps
- [ ] Outcome bar shows recommendation headline

### 8.2 MissingDataCard

- [ ] Appears when module has incomplete data
- [ ] Groups by severity: blocking (red border), warning (violet border), info (grey border)
- [ ] "Add this information" links navigate to correct forms
- [ ] Disappears when all checks pass

### 8.3 PensionAmendmentBanner

- [ ] Appears on Estate dashboard when pension amendment data exists
- [ ] Shows current vs amended liability amounts
- [ ] Violet background, dismissible with X button
- [ ] After dismiss, does not reappear (until page refresh)

### 8.4 InvestmentReadinessGate

- [ ] Appears when `canProceed = false` on Investment dashboard
- [ ] Shows progress bar with check counts
- [ ] Lists blocking/warning/info checks with form links
- [ ] Normal content appears when all blocking checks pass

### 8.5 SalarySacrificeDisplay

- [ ] Appears on Retirement dashboard for employed users
- [ ] Shows side-by-side: current vs sacrifice scenario
- [ ] National Insurance savings highlighted in green
- [ ] Warning badges for >20% sacrifice, below Personal Allowance, etc.
- [ ] Does NOT appear for self-employed users

### 8.6 FinancialHealthScore

- [ ] Shows "Incomplete" for modules where `canProceed = false`
- [ ] Composite score excludes incomplete modules (re-normalises weights)
- [ ] Protection score shows text rating (Excellent/Good/Fair/Critical), not a number

---

## 9. Regression Checklist

These existing features must still work correctly:

### General

- [ ] Login and authentication works
- [ ] Preview mode persona selection works from landing page
- [ ] Dashboard loads without errors for all personas
- [ ] Net worth calculation is correct
- [ ] All CRUD operations (create/edit/delete) work for accounts, policies, pensions, properties, goals

### Savings

- [ ] Account CRUD works (create, edit, delete savings accounts)
- [ ] ISA tracker shows correct allowance usage
- [ ] Market rate comparison works
- [ ] Goal linking works (new pivot table + backwards compatibility)

### Estate

- [ ] Asset CRUD works
- [ ] Gift recording works
- [ ] Inheritance Tax calculation produces correct results
- [ ] Trust analysis works
- [ ] Will and Power of Attorney recording works

### Investment

- [ ] Account CRUD works
- [ ] Holding CRUD works (including new fund type dropdown from Stage 1)
- [ ] Portfolio analysis loads
- [ ] Fee analysis works
- [ ] Rebalancing recommendations work
- [ ] Monte Carlo simulation works

### Protection

- [ ] Policy CRUD works (all 5 types: life, critical illness, income protection, disability, sickness)
- [ ] Gap analysis loads
- [ ] Scenario builder works
- [ ] Plan service generates structured output

### Retirement

- [ ] Pension CRUD works (DC and DB)
- [ ] Projection calculations work
- [ ] Required capital calculations work
- [ ] Strategy analysis works
- [ ] Income planning works

---

## 10. Edge Cases & Error Handling

### 10.1 Empty User (No Data)

- [ ] Create user with no profile data
- [ ] All 5 modules show readiness gates (not errors)
- [ ] No 500 errors, no blank pages
- [ ] All readiness messages are clear and actionable

### 10.2 Single User (No Spouse)

- [ ] Spouse optimisation recommendations do not appear
- [ ] Joint ownership features work correctly
- [ ] No errors when spouse data is null

### 10.3 Self-Employed User

- [ ] Emergency fund target = 9 months
- [ ] No Statutory Sick Pay in protection gap analysis
- [ ] Salary sacrifice shows "Not available" for self-employed
- [ ] No auto-enrolment compliance check

### 10.4 Additional Rate Taxpayer

- [ ] Personal Savings Allowance = £0
- [ ] Cash ISA strongly recommended
- [ ] Pension contributions highlighted for tax relief

### 10.5 Null/Missing Fields

- [ ] Protection profile with null employer benefit columns → no errors, fields treated as not set
- [ ] Life insurance policy with null `joint_life` → defaults to false
- [ ] Goals with no linked savings accounts → shows "link an account" recommendation
- [ ] User with null `employment_status` → defaults to 6-month emergency fund, shows warning

### 10.6 Large Data Sets

- [ ] User with 20+ savings accounts → no performance issues
- [ ] User with 50+ goals → recommendations don't overwhelm
- [ ] Financial Services Compensation Scheme check with 10+ institutions → groups correctly

---

## Sign-Off Checklist

| Check | Status |
|-------|--------|
| Automated tests pass (1871+) | |
| Architecture tests pass (no hardcoded values) | |
| Integration tests pass (no duplicates) | |
| All 6 personas tested end-to-end | |
| All 5 module readiness gates work | |
| All 3 notification commands run without errors | |
| All 5 new Vue components render correctly | |
| No numeric scores in Protection UI | |
| No amber/orange colours anywhere | |
| No acronyms in user-facing text (except ISA) | |
| British spelling in all user-facing text | |
| No 500 errors for any persona | |
| Emergency fund owned by Savings only | |
| ISA allowance shared correctly | |
| `php artisan db:seed` runs clean | |
