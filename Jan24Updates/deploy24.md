# Deployment - 24 January 2026

## Summary
1. **NEW**: BTL Management Agent tab — Buy-to-let properties now show a "Management Agent" tab (4th tab) with agent name, company, email, phone, monthly/annual fee, and ownership share calculation. Empty state shown when no agent data exists.
2. **FIX**: Managing agent fee now included in net rental yield calculation (`PropertyService::calculateTotalMonthlyCosts()`)
3. **FIX**: Managing agent fee flows through to User Profile Expenditure tab with correct ownership multiplier
4. **NEW**: Managing agent fee editable in the Financials tab Edit Costs modal (BTL only)
5. **NEW**: Managing agent fee shown as a row in the Financials tab Monthly Costs grid (BTL only)
6. **CLEANUP**: Removed dead `PropertyDetail.vue` component and its unused `/property/:id` route — app only uses `PropertyDetailInline.vue`
7. **FIX**: Expenditure property costs now individually itemised — Gas, Electricity, Water shown separately (not grouped as "Utilities"); Building Insurance and Contents Insurance shown separately (not grouped as "Insurance")
8. **FIX**: Management Agent fee now displays as a named line item in the Expenditure property cost breakdown
9. **BUG FIX**: Spouse expenditure tab now shows correct ownership percentage for joint/TiC properties — was applying primary owner's percentage to both users instead of inverting for the joint owner
10. **BUG FIX**: Spouse expenditure tab now shows all individual property cost breakdown items (gas, electricity, water, building insurance, contents insurance, management agent) — template was still using old grouped keys
11. **UX**: Mortgage tab co-owner dropdown now auto-defaults from the ownership tab selection (spouse or "Other" with name) — previously required re-selection
12. **UX**: Management Agent tab now positioned between Mortgage and Financials; hidden when no agent data exists (fee field remains in Financials as a reminder)
13. **UX**: Financials tab Management Agent Fee field now always visible for BTL properties — shows "Not set" when no fee entered, serving as a reminder
14. **NEW**: `monthly_interest_portion` field added to mortgages table — used for Section 24 tax credit calculation on repayment/mixed BTL mortgages
15. **NEW**: BTL Financials tab restructured — clear cash flow breakdown (income → costs → mortgage → net) separated from tax position (taxable rental income + Section 24 credit)
16. **NEW**: Section 24 Tax Credit calculation — 20% of mortgage interest shown in a separate "For Your Income Tax Calculation" box, with taxable rental income (excl. mortgage costs). These two figures feed into Income & Occupation tab
17. **NEW**: Interest portion field on mortgage form — shown for BTL repayment/mixed mortgages, with helper text explaining Section 24 usage
18. **NEW**: Amber reminder when repayment/mixed mortgage has no interest portion entered
19. **NEW**: Learning Centre — Section 24 educational content added to Tax Planning category (before/after, 3-step process, mortgage type table, tax band impact, worked example)

---

## Frontend Rebuild Required: YES

Vue components and router were modified.

```bash
./deploy/fynla-org/build.sh
```

---

## Files Changed

```
app/Services/Property/PropertyService.php
app/Services/UserProfile/UserProfileService.php
app/Models/Mortgage.php
app/Http/Requests/StoreMortgageRequest.php
app/Http/Requests/UpdateMortgageRequest.php
database/migrations/2026_01_24_091552_add_monthly_interest_portion_to_mortgages_table.php (NEW)
resources/js/components/NetWorth/Property/PropertyDetailInline.vue
resources/js/components/NetWorth/Property/PropertyFinancials.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/views/Public/LearningCentre.vue
resources/js/components/NetWorth/Property/PropertyDetail.vue  (DELETED)
resources/js/router/index.js
```

---

## Files to Upload

### Backend (PHP)
```
app/Services/Property/PropertyService.php
app/Services/UserProfile/UserProfileService.php
app/Models/Mortgage.php
app/Http/Requests/StoreMortgageRequest.php
app/Http/Requests/UpdateMortgageRequest.php
database/migrations/2026_01_24_091552_add_monthly_interest_portion_to_mortgages_table.php
```

### Frontend (after rebuild)
```
public/build/  (entire folder)
```

---

## Files to Remove from Server

```
resources/js/components/NetWorth/Property/PropertyDetail.vue
```

---

## Post-Deployment

```bash
php artisan migrate --force && php artisan cache:clear
```

- Migration adds `monthly_interest_portion` column to `mortgages` table
- Cache clear required so cached property analysis regenerates with the updated cost calculation
