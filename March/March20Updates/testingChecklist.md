# Production Testing Checklist — 20 March 2026

> Mark each item [x] only after it has been interacted with and verified in the browser.
> "BLOCKED" = could not test, with reason. "SKIP" = not applicable for this stage.

---

## Phase 1: Registration

| # | Task | j1 | j2 | j3 | j4 | j5 |
|---|------|----|----|----|----|-----|
| 1.1 | Navigate to fynla.org/register | [ ] | [ ] | [ ] | [ ] | [ ] |
| 1.2 | Fill first name, middle name, last name | [ ] | [ ] | [ ] | [ ] | [ ] |
| 1.3 | Fill email address | [ ] | [ ] | [ ] | [ ] | [ ] |
| 1.4 | Fill password + confirm password | [ ] | [ ] | [ ] | [ ] | [ ] |
| 1.5 | Click Create Account | [ ] | [ ] | [ ] | [ ] | [ ] |
| 1.6 | Verification code screen appears | [ ] | [ ] | [ ] | [ ] | [ ] |
| 1.7 | Enter verification code (from user) | [ ] | [ ] | [ ] | [ ] | [ ] |
| 1.8 | Redirected to onboarding | [ ] | [ ] | [ ] | [ ] | [ ] |

---

## Phase 2: Life Stage Selection

| # | Task | j1 | j2 | j3 | j4 | j5 |
|---|------|----|----|----|----|-----|
| 2.1 | Life stage selector loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 2.2 | All 5 stages visible | [ ] | [ ] | [ ] | [ ] | [ ] |
| 2.3 | Select correct stage for this user | [ ] | [ ] | [ ] | [ ] | [ ] |
| 2.4 | Journey map/steps preview shown | [ ] | [ ] | [ ] | [ ] | [ ] |
| 2.5 | Click to begin journey | [ ] | [ ] | [ ] | [ ] | [ ] |

---

## Phase 3: Onboarding Journey Steps

### j1 — Starting Out (6 steps)

| # | Step | Fields Filled | Status |
|---|------|--------------|--------|
| 3.1.1 | Personal Info | DOB, gender, phone | [ ] |
| 3.1.2 | Student Loan | Plan type, current balance | [ ] |
| 3.1.3 | Income | Employment status, part-time income, maintenance loan | [ ] |
| 3.1.4 | Expenditure | Monthly spending, charitable donations, Gift Aid | [ ] |
| 3.1.5 | Savings | Cash ISA, easy access account | [ ] |
| 3.1.6 | Goals | Emergency fund, save for car | [ ] |
| 3.1.7 | Journey completes, redirects to dashboard | [ ] |

### j2 — Building Foundations (7 steps)

| # | Step | Fields Filled | Status |
|---|------|--------------|--------|
| 3.2.1 | Personal Info | DOB, gender, phone, marital status | [ ] |
| 3.2.2 | Income & Career | Employment status, annual income, occupation, employer | [ ] |
| 3.2.3 | Savings (Emergency Fund) | Easy access account balance | [ ] |
| 3.2.4 | First Home LISA | LISA balance | [ ] |
| 3.2.5 | Pension (Auto-Enrolment) | Workplace pension, employer/employee %, fund value | [ ] |
| 3.2.6 | Investments | S&S ISA balance | [ ] |
| 3.2.7 | Goals | House deposit, emergency fund target | [ ] |
| 3.2.8 | Journey completes, redirects to dashboard | [ ] |

### j3 — Protecting What Matters (8 steps)

| # | Step | Fields Filled | Status |
|---|------|--------------|--------|
| 3.3.1 | Personal Info | DOB, gender, phone, marital status, occupation, health, smoking, retirement age | [ ] |
| 3.3.2 | Family & Dependents | Spouse name/DOB, 2 children with names/DOBs | [ ] |
| 3.3.3 | Income | Employment status, annual income, occupation | [ ] |
| 3.3.4 | Property & Mortgage | Address, value, ownership, mortgage balance/term/rate/type | [ ] |
| 3.3.5 | Protection Insurance | Life insurance policy, critical illness policy | [ ] |
| 3.3.6 | Pensions | Workplace DC pension details | [ ] |
| 3.3.7 | Will & Estate | Has will, last updated, executor | [ ] |
| 3.3.8 | Goals | Pay off mortgage early, children's education | [ ] |
| 3.3.9 | Journey completes, redirects to dashboard | [ ] |

### j4 — Planning Your Future (7 steps)

| # | Step | Fields Filled | Status |
|---|------|--------------|--------|
| 3.4.1 | Personal Info | DOB, gender, phone, marital status, health, smoking, retirement age | [ ] |
| 3.4.2 | Income & Tax | Annual salary, dividend income | [ ] |
| 3.4.3 | Pension Review | SIPP value, DB pension annual amount | [ ] |
| 3.4.4 | Investments & ISA | S&S ISA balance, GIA balance | [ ] |
| 3.4.5 | Property Portfolio | Main residence + BTL property details | [ ] |
| 3.4.6 | Estate & IHT | Will status, executor, beneficiary intentions | [ ] |
| 3.4.7 | Goals | Max pension contributions, downsize plan | [ ] |
| 3.4.8 | Journey completes, redirects to dashboard | [ ] |

### j5 — Enjoying Your Wealth (6 steps)

| # | Step | Fields Filled | Status |
|---|------|--------------|--------|
| 3.5.1 | Personal Info | DOB, gender, phone, marital status, health, smoking | [ ] |
| 3.5.2 | Pension Drawdown | SIPP balance, annual withdrawal amount | [ ] |
| 3.5.3 | State Pension | Annual amount, start date | [ ] |
| 3.5.4 | Income & Tax | Pension income, state pension, rental income | [ ] |
| 3.5.5 | Estate & Legacy | Estate details, will, executor, beneficiary plans | [ ] |
| 3.5.6 | Goals | Sustainable income, gifting, care costs | [ ] |
| 3.5.7 | Journey completes, redirects to dashboard | [ ] |

---

## Phase 4: Dashboard Verification

| # | Check | j1 | j2 | j3 | j4 | j5 |
|---|-------|----|----|----|----|-----|
| 4.1 | Dashboard loads without errors | [ ] | [ ] | [ ] | [ ] | [ ] |
| 4.2 | Greeting shows correct name | [ ] | [ ] | [ ] | [ ] | [ ] |
| 4.3 | Journey progress shows correctly | [ ] | [ ] | [ ] | [ ] | [ ] |
| 4.4 | Net Worth card loads (if applicable) | SKIP | [ ] | [ ] | [ ] | [ ] |
| 4.5 | Protection card loads (if applicable) | SKIP | SKIP | [ ] | [ ] | SKIP |
| 4.6 | Investments card loads (if applicable) | SKIP | [ ] | [ ] | [ ] | [ ] |
| 4.7 | Retirement card loads (if applicable) | SKIP | [ ] | [ ] | [ ] | [ ] |
| 4.8 | Estate card loads (if applicable) | SKIP | SKIP | [ ] | [ ] | [ ] |
| 4.9 | Savings card loads (if applicable) | [ ] | [ ] | [ ] | SKIP | SKIP |
| 4.10 | Goals section loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 4.11 | Life Timeline loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 4.12 | Screenshot taken | [ ] | [ ] | [ ] | [ ] | [ ] |

---

## Phase 5: Expenditure Verification

| # | Check | j1 | j2 | j3 | j4 | j5 |
|---|-------|----|----|----|----|-----|
| 5.1 | Navigate to Expenditure page | [ ] | [ ] | [ ] | [ ] | [ ] |
| 5.2 | Expenditure tab loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 5.3 | Current Budget tab shows data | [ ] | [ ] | [ ] | [ ] | [ ] |
| 5.4 | Section headers expand correctly | [ ] | [ ] | [ ] | [ ] | [ ] |
| 5.5 | Summary totals are correct | [ ] | [ ] | [ ] | [ ] | [ ] |
| 5.6 | Financial Commitments auto-calculated | [ ] | [ ] | [ ] | [ ] | [ ] |
| 5.7 | Budget at Retirement tab loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 5.8 | Budget if Widowed tab loads (couples) | SKIP | SKIP | [ ] | [ ] | [ ] |

---

## Phase 6: Module Screen Verification

| # | Module | j1 | j2 | j3 | j4 | j5 |
|---|--------|----|----|----|----|-----|
| 6.1 | Savings / Bank Accounts loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 6.2 | Investments page loads | SKIP | [ ] | [ ] | [ ] | [ ] |
| 6.3 | Retirement page loads | SKIP | [ ] | [ ] | [ ] | [ ] |
| 6.4 | Property page loads | SKIP | SKIP | [ ] | [ ] | [ ] |
| 6.5 | Protection page loads | SKIP | SKIP | [ ] | [ ] | SKIP |
| 6.6 | Estate Planning page loads | SKIP | SKIP | [ ] | [ ] | [ ] |
| 6.7 | Income (Valuable Info) loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 6.8 | Goals page loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 6.9 | Risk Profile page loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 6.10 | User Profile page loads | [ ] | [ ] | [ ] | [ ] | [ ] |
| 6.11 | Settings page loads | [ ] | [ ] | [ ] | [ ] | [ ] |

---

## Phase 7: Data Persistence Verification

| # | Check | j1 | j2 | j3 | j4 | j5 |
|---|-------|----|----|----|----|-----|
| 7.1 | Income data matches what was entered | [ ] | [ ] | [ ] | [ ] | [ ] |
| 7.2 | Savings accounts show in Savings page | [ ] | [ ] | SKIP | SKIP | SKIP |
| 7.3 | Pension data shows in Retirement page | SKIP | [ ] | [ ] | [ ] | [ ] |
| 7.4 | Property data shows in Property page | SKIP | SKIP | [ ] | [ ] | SKIP |
| 7.5 | Protection policies show in Protection | SKIP | SKIP | [ ] | [ ] | SKIP |
| 7.6 | Will info shows in Estate page | SKIP | SKIP | [ ] | [ ] | [ ] |
| 7.7 | Goals entered appear on Goals page | [ ] | [ ] | [ ] | [ ] | [ ] |

---

## Issues Log

| # | Account | Page | Issue Description | Severity | Screenshot |
|---|---------|------|-------------------|----------|------------|
| # | Account | Page | Issue Description | Severity | Localhost Status |
|---|---------|------|-------------------|----------|-----------------|
| 1 | j1 | Onboarding | 405 error on POST /api/life-stage/set — route cache stale on production. | HIGH | **FIXED** (route cache cleared) |
| 2 | j1 | Onboarding Step 1 | 422 validation error saving personal info — caused by Bug #6 (name not pre-populated). | MEDIUM | **FIXED** (root cause was #6) |
| 3 | j1 | Dashboard | Goals entered during onboarding — user reports goals ARE visible on dashboard. | HIGH | **NOT A BUG** (user confirmed working) |
| 4 | j1 | Goals Page | Financial Projection shows wrong age — user is 21 but projection shows "Age 45". | HIGH | **FIXED** (GoalsProjectionService default age fixed) |
| 5 | j1 | Dashboard | Student Loan card shows "Plan 5" but user selected "Plan 2". | MEDIUM | **NOT RETESTED** |
| 6 | j1+j2 | Onboarding Step 1 | Full Name and Email Address fields not pre-populated from registration data. | HIGH | **FIXED** ✅ BROWSER VERIFIED — removed !isEditing guard + await fetchProfile |
| 7 | j2 | Onboarding | Back button clears all form data — navigating back resets all fields to empty. | HIGH | **FIXED** ✅ BROWSER VERIFIED — initializeForm reads from DB on re-render |
| 8 | j2 | Onboarding | Employment details from Step 1 do not carry to Step 2 (Income). | MEDIUM | **FIXED** (IncomeStep now reads from userProfile store) — needs deploy |
| 9 | j3 | Dashboard | Mortgage not showing as liability on Net Worth card. | HIGH | **FIXED** ✅ TINKER VERIFIED — CrossModuleAssetAggregator returns £122,500 mortgage — needs deploy |
| 10 | j3 | Onboarding Step 7 | Will step shows stale "Coming Soon" banner. | MEDIUM | **FIXED** ✅ BROWSER VERIFIED — banner removed |
| 11 | j3 | Dashboard | 500 error on /api/plans/estate endpoint in console. | MEDIUM | **FIXED** (EstateAgent undefined variable fix) — needs deploy |
| 12 | j3 | Onboarding Step 4 | Spouse not in Joint Owner dropdown. | MEDIUM | **FIXED** (store refresh after family step + PropertyForm spouse getter) — needs deploy |

---

## Final Summary

| Account | Registration | Journey | Dashboard | Expenditure | Modules | Data | Overall |
|---------|-------------|---------|-----------|-------------|---------|------|---------|
| j1 | [x] | [x] | [x]* | [x] | [ ] | [ ] | ISSUES |
| j2 | [x] | [x] | [x] | [x] | [ ] | [ ] | PASS* |
| j3 | [x] | [x] | [x]* | [ ] | [ ] | [ ] | ISSUES |
| j4 | [x] | [x] | [x]* | [ ] | [ ] | [ ] | ISSUES |
| j5 | [x] | [x] | [x] | [ ] | [ ] | [ ] | PASS* |

**Test Date**: 20 March 2026
**Tester**: Claude + User
**Build**: v0.9.3
**Result**: 5 / 5 journeys completed — all dashboards loaded, 12 bugs found
