# j3 Test Findings — David Taylor (Protecting What Matters)

**Date**: 20 March 2026
**Email**: j3@fynla.org
**Journey**: Protecting What Matters (mid_career) — 7 steps

## No New Bugs Found

All previously deployed fixes working. No 500 errors, sessions clean, focus area mapping correct.

## j3 Journey Results

### Phase 1: Registration — PASS
- David Taylor, j3@fynla.org registered
- Verification code accepted
- Redirected to onboarding

### Phase 2: Life Stage Selection — PASS
- Selected "Protecting What Matters"
- Journey map showed 7 steps: About You, Family, Income, Assets, Protection, Will & Estate, Goals

### Phase 3: Onboarding Steps — PASS (all 7 steps)

| Step | Fields Filled | Saved |
|------|--------------|-------|
| 1. Personal Info | DOB: 1987-09-10, Male, Married, 42 Maple Road, Guildford, Surrey, GU2 7PQ, Phone: 07700300003, Good health, Never smoked, Undergraduate Degree | ✅ |
| 2. Family | Emma Taylor (Spouse, DOB 1990-02-14, Female), Olivia Taylor (Child, DOB 2020-03-01, dependent), Harry Taylor (Child, DOB 2022-11-15, dependent) | ✅ |
| 3. Income | Employed, FinanceHouse PLC, Financial Services, £65,000/yr | ✅ |
| 4a. Assets (Retirement) | Scottish Widows Occupational "FinanceHouse Pension" £85,000, 4% employee + 6% employer, salary £65,000, beneficiary: Emma Taylor | ✅ |
| 4b. Assets (Property) | 42 Maple Road, Main Residence, £450,000 (purchased £350,000 in 2018), Joint Tenancy 50/50 with Emma, Mortgage: Nationwide Repayment £280,000 @ 4.5% fixed, £1,667/mo, Council Tax £180, Gas £85, Electric £95, Water £45, Buildings £35, Contents £20 | ✅ |
| 5. Protection | Life Insurance: Legal & General Level Term £350,000 @ £45/mo, beneficiary Emma. Critical Illness: Aviva £100,000 @ £30/mo | ✅ |
| 6. Will & Estate | Has will (last updated Jun 2023), Executor: Emma Taylor | ✅ |
| 7. Goals | Debt Repayment (mortgage) £280,000, target Jun 2038 | ✅ |

### Phase 4: Dashboard — PASS

- "Good evening, David" — correct
- "Protecting What Matters · 7 of 7 steps complete" — 100%
- **Net Worth**: Assets £310,000, Liabilities £0
- **Protection**: Total Coverage £450,000, Monthly Premiums £75/mo, 2 policies
  - Recommended: "Increase life insurance by £584,253", "Add critical illness cover for £95,000"
- **Retirement**: Projected Income £20,396/yr vs Required £36,973/yr, Projected Capital £433,952 vs Required £995,745
- **Goals chart**: ages 38-90 with retirement marker
- **Suggested goals**: Pay off mortgage early, Children's education fund, Retire at 60, Close protection gaps
- Screenshot: `j3-dashboard.png`

### Notable Observations

1. **Spouse linking works**: Emma Taylor appeared in beneficiary dropdowns and joint owner selectors throughout the journey — pension beneficiary, property joint owner, mortgage joint owner all linked correctly.
2. **Address auto-populated**: Property form pre-filled address from personal info step.
3. **Property form multi-step wizard**: 3-step property form (Basic Info → Ownership → Costs) worked smoothly with proper back/next navigation.
4. **Protection modal**: Policy form correctly expanded to show Life Policy Type, Policy Term, Trust, Mortgage link fields when "Life Insurance" was selected.
5. **Net Worth liabilities £0**: Mortgage not showing as liability in Net Worth card. Property equity shows £310,000 (likely £225,000 property share + £85,000 pension). The £280,000 mortgage should reduce net worth. This is the same issue noted in the previous test round.
6. **No Cash & Savings card**: David has no savings accounts, so the card correctly doesn't appear.

## Overall: PASS
