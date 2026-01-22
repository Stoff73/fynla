# Deployment - 22 January 2026

## Summary
1. UK postcode lookup feature for all address forms using GetAddress.io API
2. Fix pension projections to include percentage-based contributions (workplace pensions)
3. **CRITICAL SECURITY FIX**: Preview mode session isolation to prevent data leakage
4. Remove Diversification tab from pension detail view
5. Retirement strategy affordability enhancements (employer match, strategy flow, income gap display)
6. Priority 2 contribution increase with tax relief reinvestment (relief at source, self-assessment refund, compound projection)
7. **BUG FIX**: Compound projection growth rate calculation (was using 500% instead of 5%)
8. Persona data fixes: realistic disposable income for all personas, 50/50 expenditure split for married couples
9. ExpenditureForm household total calculation fix
10. **BUG FIX**: Cash dashboard not displaying accounts for real users (BUG-012)
11. Investment contribution tracking fields for expenditure calculations

---

## Frontend Rebuild Required: YES

Vue components and JS services were modified.

```bash
./deploy/fynla-org/build.sh
```

---

## Files to Upload

### Backend (PHP)
```
app/Http/Controllers/Api/PostcodeLookupController.php  (NEW)
app/Http/Controllers/Api/PreviewController.php         (SECURITY FIX)
app/Services/Retirement/PensionProjector.php
app/Services/Retirement/RetirementStrategyService.php  (STRATEGY ENHANCEMENTS + BUG FIX)
app/Services/UserProfile/UserProfileService.php        (INVESTMENT CONTRIBUTIONS)
app/Models/Investment/InvestmentAccount.php            (NEW CONTRIBUTION FIELDS)
config/services.php
routes/api.php
database/seeders/PreviewUserSeeder.php                 (PERSONA DATA FIX + CONTRIBUTIONS)
database/migrations/2026_01_22_162633_add_contribution_fields_to_investment_accounts_table.php (NEW)
```

### Frontend (after rebuild)
```
public/build/  (entire folder)
```

### Persona Data (for seeder - upload BEFORE running seeder)
```
resources/js/data/personas/young_family.json
resources/js/data/personas/peak_earners.json
resources/js/data/personas/entrepreneur.json
resources/js/data/personas/widow.json
```

---

## Environment Variable Required

Add to production `.env`:
```
GETADDRESS_API_KEY=UlM5caQqhUSoCCG3sZo1Yw49819
```

---

## SSH Commands After Upload

```bash
php artisan migrate --force
php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan optimize
php artisan db:seed --class=PreviewUserSeeder --force
```

---

## Files Changed (Reference)

| File | Status | Type |
|------|--------|------|
| `app/Http/Controllers/Api/PostcodeLookupController.php` | NEW | Backend |
| `app/Http/Controllers/Api/PreviewController.php` | SECURITY FIX | Backend |
| `app/Services/Retirement/PensionProjector.php` | Modified | Backend |
| `app/Services/Retirement/RetirementStrategyService.php` | Modified | Backend |
| `app/Services/UserProfile/UserProfileService.php` | Modified | Backend |
| `app/Models/Investment/InvestmentAccount.php` | Modified | Backend |
| `config/services.php` | Modified | Backend |
| `routes/api.php` | Modified | Backend |
| `database/migrations/2026_01_22_162633_add_contribution_fields...` | NEW | Backend |
| `resources/js/components/Shared/PostcodeLookup.vue` | NEW | Frontend |
| `resources/js/services/postcodeService.js` | NEW | Frontend |
| `resources/js/components/Estate/AssetForm.vue` | Modified | Frontend |
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | Modified | Frontend |
| `resources/js/components/Onboarding/steps/PersonalInfoStep.vue` | Modified | Frontend |
| `resources/js/components/UserProfile/PersonalInformation.vue` | Modified | Frontend |
| `resources/js/components/NetWorth/PensionDetailInline.vue` | Modified | Frontend |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Modified | Frontend |
| `resources/js/views/NetWorth/CashOverview.vue` | BUG FIX | Frontend |
| `resources/js/components/Investment/AccountForm.vue` | Modified | Frontend |
| `resources/js/data/personas/young_family.json` | Modified | Frontend |
| `resources/js/data/personas/peak_earners.json` | Modified | Frontend |
| `resources/js/data/personas/entrepreneur.json` | Modified | Frontend |
| `resources/js/data/personas/widow.json` | Modified | Frontend |
| `database/seeders/PreviewUserSeeder.php` | Modified | Backend |

---

## Verification

### Postcode Lookup
1. Go to User Profile → Edit → Enter postcode "SW1A 1AA" → Click "Find Address"
2. Should show dropdown with addresses
3. Select one → Address fields should auto-populate

### Pension Projections
1. Log in as James Carter (young_family persona)
2. Go to Retirement module
3. Verify TechCorp pension shows projected value ~£441,640 (not £0)
4. Log in as David Mitchell (peak_earners persona)
5. Verify Global Finance pension shows projected value ~£716,467
6. Verify SIPP shows projected value ~£1,008,646

### Preview Mode Security (CRITICAL)
1. Log in as a real user (e.g., demo@fps.com)
2. Navigate to landing page (fynla.org)
3. Click "Try the Demo"
4. Select any persona (e.g., Emily & James Carter)
5. **Verify:** Dashboard shows Carter family data (Net Worth £97,200), NOT the real user's data
6. Verify "Preview Mode" banner appears with correct persona name

### Pension Detail View
1. Go to Retirement module → Click on any DC pension
2. **Verify:** Tabs show only: Overview, Projections, Documents
3. **Verify:** No "Diversification" tab appears

### Retirement Strategy Affordability
1. Log in as James Carter (young_family persona)
2. Go to Retirement → Strategies tab
3. **Verify:** Employer match strategy shows affordability info:
   - `net_cost_annual` field present
   - `can_afford` boolean present
4. If strategy is not affordable:
   - **Verify:** `skipped_reason: 'affordability'` appears
   - **Verify:** Green message about reviewing expenditure appears
   - **Verify:** Contribution increase strategy is skipped
5. For Strategy 4 (Income Target):
   - **Verify:** Description shows gap between sustainable income and target
   - Example: "You can sustainably withdraw £X/year... This is £Y/year less than your target"

### Priority 2 Contribution Increase with Tax Relief
1. Log in as David Mitchell (peak_earners persona - higher rate taxpayer)
2. Go to Retirement → Strategies tab
3. Find the "Increase Pension Contributions" strategy (Priority 2)
4. **Verify:** `contribution_breakdown` object present with:
   - `gross_contribution` - total going into pension
   - `user_pays_upfront` - 80% of gross (net payment)
   - `hmrc_adds` - 20% relief at source
   - `self_assessment_refund` - additional relief for higher/additional rate
   - `effective_annual_cost` - user's true cost after all relief
   - `tax_band` - shows "higher" or "additional"
5. **Verify:** `refund_reinvestment` object present with:
   - `refund_amount` - annual refund amount
   - `refund_timing` - e.g., "January 2028"
   - `recommended_destination` - "pension", "isa", "bond_wrapper", or "gia"
   - `fallback_order` - ["pension", "isa", "bond_wrapper", "gia"]
6. **Verify:** `compound_projection` object present with:
   - `years_to_retirement` - years until retirement
   - `without_reinvestment.total_contributions` - base contributions
   - `without_reinvestment.projected_pot` - pot without reinvestment
   - `with_reinvestment.total_contributions` - enhanced contributions
   - `with_reinvestment.projected_pot` - pot with reinvestment
   - `with_reinvestment.additional_benefit` - extra pot from reinvestment
   - `yearly_breakdown[]` - year-by-year projection details
7. **Verify:** Description shows compound benefit message for higher/additional rate taxpayers:
   - Example: "Increase your pension contributions. As a higher rate taxpayer, £X/year costs you just £Y after tax relief. Reinvesting your refunds could add £Z to your pot."

### Compound Projection Bug Fix (BUG-003)
1. Log in as David Mitchell (peak_earners persona - higher rate taxpayer)
2. Go to Retirement → Strategies tab
3. Find the "Increase Pension Contributions" strategy
4. **Verify:** `compound_projection.with_reinvestment.additional_benefit` shows sensible value (£10k-£50k range, NOT billions)
5. **Verify:** Description mentions a realistic additional pot value

### Persona Data Fixes (DATA-004)
1. Log in as James Carter (young_family persona)
2. Go to Retirement → Strategies tab
3. **Verify:** Disposable income shows positive value (~£1,200-£1,500/month)
4. **Verify:** Contribution increase strategy is NOT skipped due to affordability
5. Log in as David Mitchell (peak_earners persona)
6. Go to Retirement → Strategies tab
7. **Verify:** Disposable income shows ~£3,500/month or higher
8. **Verify:** Can afford to increase pension contributions

### ExpenditureForm Household Total Fix (UI-019)
1. Log in as any married persona (e.g., James Carter)
2. Go to User Profile → Expenditure tab
3. **Verify:** Household total = User expenditure + Spouse expenditure
4. **Verify:** Each spouse shows 50% of household total (not 100% + 50% = 150%)
