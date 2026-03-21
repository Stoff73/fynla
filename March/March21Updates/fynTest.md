# Fyn AI Form Fill — Remaining Test Plan

**Branch:** `aiFormFill` (23 commits ahead of main)
**Date:** 21 March 2026

## Testing Method

For each entity type:
1. Navigate to the correct page manually
2. Add one manually first — understand the form fields, required values, select options
3. Then ask Fyn to add one — verify navigation, form opens, fields fill, auto-submit, data persists, page renders correctly
4. Screenshot the result
5. Check DB to confirm correct field values

## Verified Working

- [x] Savings Account — institution, account_type, balance, rate
- [x] Investment Account — provider, account_type (ISA/GIA), value
- [x] Protection Policy — policyType, life_policy_type, provider, sum_assured, premium, term
- [x] DC Pension — pension_type (workplace), scheme_name, provider, fund value, contributions
- [x] Liability — liability_type (credit card, personal loan, student loan), name, balance, payment, rate
- [x] Cross-page navigation — form fill works when navigating between pages
- [x] Chat persistence — conversation survives page navigation

## Remaining Tests

### DB Pension
- **Page:** /net-worth/retirement
- **Form:** DBPensionForm.vue
- **Test prompt:** "I have a civil service defined benefit pension with an accrued annual income of £12,000 and 15 years service"
- **Key fields:** employer_name, scheme_type (final_salary/career_average), annual_income, service_years
- **Watch for:** scheme_type select values, conditional fields

### Property
- **Page:** /net-worth/property
- **Form:** PropertyForm.vue (multi-step)
- **Test prompt:** "I have a house at 42 Oak Lane, TW1 3QR worth £450,000, bought for £300,000 in 2018"
- **Key fields:** property_type, address_line_1, postcode, current_value, purchase_price, purchase_date
- **Watch for:** Multi-step form — fields split across steps. hasMortgage toggle already pre-set. Step advancement logic

### Property with Mortgage
- **Page:** /net-worth/property
- **Form:** PropertyForm.vue (multi-step)
- **Test prompt:** "I have a house worth £350,000 with a £200,000 repayment mortgage at 4.5% with Nationwide, paying £1,200 per month"
- **Key fields:** property fields + has_mortgage, mortgage_outstanding_balance, mortgage_interest_rate, mortgage_lender_name, mortgage_type, mortgage_monthly_payment
- **Watch for:** hasMortgage toggle must be true for mortgage step to render

### Estate Asset
- **Page:** /estate (switches to IHT tab)
- **Form:** AssetForm.vue
- **Test prompt:** "I have a collection of artwork worth £25,000"
- **Key fields:** asset_name, asset_type, current_value
- **Watch for:** EstateDashboard tab switching (activeTab = 'iht')

### Estate Gift
- **Page:** /estate (switches to gifting tab)
- **Form:** GiftForm.vue
- **Test prompt:** "I gave my daughter £5,000 in January 2025"
- **Key fields:** gift_date, recipient, gift_value, gift_type
- **Watch for:** EstateDashboard tab switching (activeTab = 'gifting'), date format

### Trust
- **Page:** /trusts
- **Form:** TrustFormModal.vue
- **Test prompt:** "I have a discretionary trust called the Smith Family Trust worth £100,000"
- **Key fields:** trust_name, trust_type, current_value, trust_creation_date
- **Watch for:** trust_type select values match handler output

### Business Interest
- **Page:** /net-worth/business
- **Form:** BusinessInterestForm.vue
- **Test prompt:** "I own 60% of a limited company called Smith Consulting worth £200,000 with annual profit of £50,000"
- **Key fields:** business_name, business_type, ownership_percentage, current_valuation, annual_profit
- **Watch for:** business_type select values (sole_trader, partnership, limited_company, llp)

### Chattel (Personal Valuable)
- **Page:** /net-worth/chattels
- **Form:** ChattelFormModal.vue
- **Test prompt:** "I have a Rolex watch worth £8,000"
- **Key fields:** chattel_type, name, current_value, purchase_price
- **Watch for:** chattel_type select values, route is /net-worth/chattels (was fixed from /net-worth/valuables)

### Goal
- **Page:** /goals
- **Form:** GoalFormModal.vue
- **Test prompt:** "I want to save £20,000 for a house deposit by December 2027"
- **Key fields:** goal_name, target_amount, target_date, goal_type
- **Watch for:** Already in Group D (committed earlier) — may already work, needs verification

### Life Event
- **Page:** /goals?tab=events
- **Form:** LifeEventForm.vue
- **Test prompt:** "I'm planning to retire in June 2050"
- **Key fields:** event_name, event_type, event_date, estimated_cost
- **Watch for:** Tab switching on GoalsDashboard, event_type mapping

### Family Member
- **Page:** /profile
- **Form:** FamilyMemberFormModal.vue
- **Test prompt:** "My wife is called Sarah, born 15 March 1990"
- **Key fields:** first_name, relationship, date_of_birth
- **Watch for:** relationship select values, profile page section targeting

### Update/Edit Flow
- **Page:** Various
- **Test prompt:** "Update my Lloyds savings balance to £13,000"
- **Key fields:** entity_type, entity_id, fields (only changed values)
- **Watch for:** Edit modal opens (not create), only changed fields highlight, existing values preserved

## Known Issues to Fix

- [ ] Interest rate on liabilities displays as decimal (0.07%) instead of percentage (6.5%) — field mapping in display
- [ ] Savings accounts created before institution fix show "Savings Account" / "Cash ISA" instead of bank name (null institution in DB)
- [ ] Monte Carlo projection shows £0 for test user — no DOB set (pre-existing, not form fill issue)
