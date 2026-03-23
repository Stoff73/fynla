# Production Testing Plan — 20 March 2026

## Objective

Register 5 users on fynla.org, each completing a different life stage journey end-to-end. Verify all onboarding steps, dashboard rendering, expenditure loading, and module screens.

## Test Environment

- **URL**: https://fynla.org
- **Browser**: Playwright (Chrome)
- **Method**: Full interactive testing — fill every field, submit every form, verify every result

## Email Assignments

| Email | Life Stage | Persona | Steps | Focus Areas |
|-------|-----------|---------|-------|-------------|
| j1@fynla.org | Starting Out | University student | 6 | Student loan, part-time income, basic savings, goals |
| j2@fynla.org | Building Foundations | Early career professional | 7 | Emergency fund, LISA, workplace pension, first investments |
| j3@fynla.org | Protecting What Matters | Mid-career family | 8 | Family, property, mortgage, protection policies, pensions, will |
| j4@fynla.org | Planning Your Future | Peak earners | 7 | Tax efficiency, pension review, property portfolio, estate/IHT |
| j5@fynla.org | Enjoying Your Wealth | Retired couple | 6 | Pension drawdown, state pension, estate/legacy, gifting |

## Test Data Sets

### j1 — Starting Out (Thomas Wilson, age 21)
- **DOB**: 15/06/2004
- **Gender**: Male
- **Phone**: 07700 100001
- **Student Loan**: Plan 2, balance £18,500
- **Income**: Part-time job £450/month, Maintenance loan £750/month
- **Expenditure**: £600/month total, £10 charity (Gift Aid)
- **Savings**: Cash ISA £1,200, Easy Access £800
- **Goals**: Emergency fund (3 months), save for car (£3,000)

### j2 — Building Foundations (Sophie Clarke, age 28)
- **DOB**: 22/03/1998
- **Gender**: Female
- **Phone**: 07700 200002
- **Marital Status**: Single
- **Employment**: Full-time, Data Analyst, TechCorp Ltd
- **Income**: £35,000/year gross
- **Emergency Fund**: £4,500 in easy access account
- **LISA**: £8,000 balance (first home deposit)
- **Pension**: Workplace DC, employer 5%, employee 5%, fund value £12,000
- **Investments**: S&S ISA £3,500
- **Goals**: House deposit £30,000, 6-month emergency fund

### j3 — Protecting What Matters (David & Emma Taylor, ages 38/36)
- **DOB**: 10/09/1987 (David)
- **Gender**: Male
- **Phone**: 07700 300003
- **Marital Status**: Married
- **Spouse**: Emma Taylor, DOB 14/02/1990
- **Children**: Olivia (age 6, DOB 01/03/2020), Harry (age 3, DOB 15/11/2022)
- **Employment**: Senior Manager, FinanceHouse PLC
- **Income**: £65,000/year
- **Property**: 42 Maple Road, GU2 7PQ, value £450,000, joint
- **Mortgage**: £280,000 outstanding, 25 years, 4.5% fixed, repayment
- **Protection**: Life insurance £350,000 (£45/mo), Critical illness £100,000 (£30/mo)
- **Pension**: Workplace DC, employer 6%, employee 4%, fund £85,000
- **Will**: Yes, last updated 2023, executor Emma Taylor
- **Goals**: Pay off mortgage early, children's education fund

### j4 — Planning Your Future (Richard & Catherine Moore, ages 52/50)
- **DOB**: 05/04/1974 (Richard)
- **Gender**: Male
- **Phone**: 07700 400004
- **Marital Status**: Married
- **Health**: Good, non-smoker
- **Retirement Age**: 60
- **Employment**: Director, Moore Consulting
- **Income**: £120,000/year salary, £15,000 dividends
- **Pension**: SIPP £320,000, DB pension (final salary) £18,000/yr
- **Investments**: S&S ISA £85,000, GIA £45,000
- **Property**: Main residence £750,000 (joint), BTL flat £325,000 (joint)
- **Main mortgage**: £120,000 remaining
- **BTL mortgage**: £180,000 remaining
- **Estate**: Will in place, executor Catherine, considering IHT planning
- **Goals**: Max pension contributions, downsize at 65

### j5 — Enjoying Your Wealth (Margaret & Alan Hughes, ages 71/73)
- **DOB**: 28/08/1954 (Margaret)
- **Gender**: Female
- **Phone**: 07700 500005
- **Marital Status**: Married
- **Health**: Good, non-smoker
- **Pension Drawdown**: SIPP £180,000, drawing £12,000/year
- **State Pension**: £10,600/year (full), started age 66
- **Income**: Pension £12,000, State Pension £10,600, Rental £9,600
- **Estate**: Home £550,000, savings £95,000, investments £60,000
- **Will**: Yes, last updated 2024, executor Alan + solicitor
- **Goals**: Sustainable income, gift to grandchildren, fund care costs

## Execution Order

1. Register all 5 accounts (ask user for verification codes)
2. Complete j1 journey (simplest — Starting Out)
3. Complete j3 journey (most complex — 8 steps, family/property/protection)
4. Complete j2 journey (Building Foundations)
5. Complete j4 journey (Peak — tax/pension complexity)
6. Complete j5 journey (Retirement)
7. Post-journey verification for all 5 accounts

## Post-Journey Checks (Every Account)

After completing each journey:
1. Dashboard loads with correct module cards
2. Navigate to Expenditure — verify it loads and shows data
3. Navigate to each sidebar module — verify no errors
4. Check data entered during onboarding persists in the correct modules
5. Take screenshot of dashboard for evidence

## Success Criteria

- All 5 registrations complete
- All 5 journeys completed with every field filled
- All 5 dashboards render correctly
- Expenditure loads for all 5 accounts
- No 500 errors on any module page
- All entered data visible in the correct module screens
