# Deployment - 23 January 2026

## Summary
1. **BUG FIX**: "Enter Holdings" links in investment detail view Diversification and Rebalancing cards now open the HoldingForm modal
2. HoldingForm auto-selects the current account when opened from a detail view
3. **BUG FIX**: Rebalance Portfolio recommendation no longer shows when user has no holdings
4. **NEW**: Review Platform Fees recommendation triggers when platform fee > 0.8%
5. **REDESIGN**: Tax Efficiency recommendations replaced with practical hierarchy (ISA → allowance usage → bonds)
6. **NEW**: Account-level Strategy Card (full-width below chart) with 10 account-specific recommendations
7. **FIX**: Detail view Monte Carlo chart now matches dashboard (4 probability bands: 95%, 90%, 85%, 80% with blue-to-green colours)
8. **BUG FIX**: IHT Calculation table gap and floating `-£0` values fixed (dynamic colspan, formatLiability method, second table v-if conditions)
9. **NEW**: IHT Calculation table concertina/accordion - two-level collapse: section headers (User's Assets, Spouse's Liabilities, etc.) collapse to show subtotals, and within each section, asset/liability type groups with > 1 item collapse under a summary heading with chevron, count, and totals (all collapsed by default). Allowances section also collapsible when married with RNRB.
10. **NEW**: IHT table ownership labels - Tenancy in Common assets show "(Tenancy in Common - XX%)" label; joint mortgages show actual percentage when not 50/50.
11. **RENAME**: "Chattels" renamed to "Personal Valuables" across all user-facing text (tabs, headings, buttons, empty states, labels, changelog, validation messages, IHT table groups).
12. **BUG FIX**: Platform fee fields (`platform_fee_amount`, `platform_fee_type`, `platform_fee_frequency`) now persist when adding/editing investment accounts (were silently dropped by backend validation)
13. **BUG FIX**: Platform fee value no longer disappears when toggling between %/£ type — value transfers to the new field
14. **BUG FIX**: Fixed (£) platform fees now display correctly across all fee cards, cost breakdowns, projections, and strategy recommendations (previously only percentage fees were shown)
15. **BUG FIX**: Editing an investment account now stays on the detail view (instead of navigating back to dashboard) and the fee card refreshes with updated data
16. **BUG FIX**: Backend FeeAnalyzer now calculates fixed (£) platform fees correctly for portfolio-level "Review Platform Fees" recommendation trigger
17. **FIX**: Consistent form scroll behaviour — all form modals now use `max-h-[90vh] overflow-y-auto` on the modal panel with buttons inside the scroll container. Removed sticky footers, moved scroll from inner divs to modal level, and fixed `overflow-hidden` clipping buttons. 16 forms standardised across all modules.
18. **FIX**: Onboarding Personal Information — required fields (DOB, Gender, Marital Status, Address Line 1, City, Postcode) now marked with red asterisks and show specific inline error messages when missing.
19. **REDESIGN**: Onboarding Employment & Income — Employment Status moved to first field; occupation/employer/industry/retirement age hidden for retired/unemployed; income sources now dynamic per status (employment income for employed, self-employment for self-employed, benefit income for unemployed, retirement info message for retired, other income as catch-all); dividend and interest income removed.
20. **BUG FIX**: Onboarding Asset cards (Retirement, Cash tabs) now open the edit form when clicked — previously always opened add form because `isEdit`/`isEditing` prop was never passed. Fixed by deriving edit mode from data prop presence (matching working Investment AccountForm pattern). Also fixed StatePensionForm prop binding (`:pension` → `:state-pension`).
21. **BUG FIX**: Onboarding Health & Lifestyle fields no longer block step progress — empty dropdown values (empty strings) were failing MySQL enum validation. Backend now only includes `health_status`, `smoking_status`, `education_level` in the update when a valid value is selected.
22. **UX**: Property form — Costs tab and BTL Details tab now show contextual info notes for shared ownership properties explaining that users should enter 100% of costs/rent, with specific split method (50/50 for joint, by ownership % for tenants in common).
23. **BUG FIX**: Rental income for joint/tenants-in-common properties now correctly applies ownership percentage when calculating the user's share for the Income section. Previously took 100% of rental income regardless of ownership type. Fixed in both backend (`UserProfileService`) and frontend (`IncomeStep`).
24. **UX**: Consistent button styling — Property form save button changed from green to blue matching investments; mortgage checkbox card styled green for visibility; Savings form buttons restyled to match investment AccountForm (smaller padding, bordered cancel button, right-aligned with Cancel/Submit order).
25. **UX**: Removed National Insurance Number and Annual Income fields from the family member form — unnecessary at this level.
26. **FIX**: Dashboard state pension line (£11,500/yr default) now only shows when user has entered at least one pension (DC, DB, or state). Previously showed for all users regardless. Also excluded from projected income calculation when no pensions exist.
27. **UX**: Removed "Regular Savings" input from Other Expenses (Monthly) section in the Expenditure form — this data is captured elsewhere (Savings module). Removed from both user and spouse forms, and excluded from monthly totals calculation.
28. **UX**: Savings account form — selecting "Notice Account" product type now auto-sets access type to "Notice Required"; selecting "Fixed Term" auto-sets to "Fixed Term".
29. **UX**: Onboarding property form — selecting "Main Residence" as property type now auto-populates address fields (line 1, line 2, city, county, postcode) from the user's personal details address entered earlier in onboarding.
30. **BUG FIX**: Investment account update now persists ownership fields (`ownership_type`, `ownership_percentage`, `joint_owner_id`). These were missing from the `updateAccount` validation rules so were silently stripped by Laravel. Also added old joint owner cache clearing when ownership changes.
31. **BUG FIX**: Savings account update now persists ownership fields — same issue as investments. Added `ownership_type`, `ownership_percentage`, `joint_owner_id` to `UpdateSavingsAccountRequest`. Properties and pensions were already correct.
32. **UX**: Spouse family member — edit and delete buttons removed from both User Profile and Onboarding family sections. Replaced with message: "Linked account — can only be edited or deleted by logging into the spouse's account." Warning added to the add form reminding users to enter correct information.

---

## Frontend Rebuild Required: YES

Vue components were modified.

```bash
./deploy/fynla-org/build.sh
```

---

## Files Changed

```
resources/js/components/Investment/AccountForm.vue
resources/js/components/Investment/HoldingForm.vue
resources/js/components/Investment/GoalForm.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/NetWorth/BusinessInterestForm.vue
resources/js/components/NetWorth/ChattelFormModal.vue
resources/js/components/Protection/PolicyFormModal.vue
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/Savings/SaveGoalModal.vue
resources/js/components/Goals/GoalFormModal.vue
resources/js/components/Goals/ContributionModal.vue
resources/js/components/Estate/AssetsLiabilities.vue
resources/js/components/Shared/DocumentUploadModal.vue
resources/js/components/Admin/UserFormModal.vue
resources/js/components/UserProfile/FamilyMemberFormModal.vue
resources/js/components/Auth/ChangePasswordModal.vue
resources/js/components/Onboarding/steps/PersonalInfoStep.vue
resources/js/components/Onboarding/steps/IncomeStep.vue
resources/js/components/Onboarding/steps/AssetsStep.vue
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/Retirement/DBPensionForm.vue
resources/js/components/Retirement/UnifiedPensionForm.vue
resources/js/components/Retirement/StatePensionForm.vue
resources/js/views/Retirement/RetirementReadiness.vue
resources/js/views/NetWorth/CashOverview.vue
resources/js/views/Savings/SavingsAccountDetailInline.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/UserProfile/FamilyMemberFormModal.vue
resources/js/components/Dashboard/RetirementOverviewCard.vue
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/Onboarding/steps/AssetsStep.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
app/Http/Controllers/Api/InvestmentController.php
app/Http/Requests/Savings/UpdateSavingsAccountRequest.php
resources/js/components/UserProfile/FamilyMembers.vue
resources/js/components/UserProfile/FamilyMemberFormModal.vue
resources/js/components/Onboarding/steps/FamilyInfoStep.vue
resources/js/components/NetWorth/InvestmentDetailInline.vue
resources/js/components/Investment/AccountStrategyCard.vue  (NEW)
resources/js/views/Investment/AccountPerformancePanel.vue
resources/js/views/Investment/AccountFeesPanel.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/InvestmentProjections.vue
resources/js/components/Investment/FeeBreakdown.vue
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/NetWorth/ChattelsList.vue
resources/js/components/NetWorth/ChattelFormModal.vue
resources/js/components/NetWorth/ChattelDetailInline.vue
resources/js/components/NetWorth/ChattelCard.vue
resources/js/components/NetWorth/NetWorthOverview.vue
resources/js/components/NetWorth/WealthSummary.vue
resources/js/components/NetWorth/AssetBreakdownBar.vue
resources/js/components/Dashboard/NetWorthOverviewCard.vue
resources/js/components/UserProfile/AssetsOverview.vue
resources/js/views/NetWorth/NetWorthDashboard.vue
resources/js/views/Version.vue
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Requests/Chattel/StoreChattelRequest.php
app/Services/Estate/AssetLiquidityAnalyzer.php
app/Services/UserProfile/ModuleDataRequirementsService.php
app/Services/Onboarding/OnboardingService.php
app/Services/UserProfile/UserProfileService.php
app/Services/Investment/FeeAnalyzer.php
app/Http/Controllers/Api/InvestmentController.php
app/Agents/InvestmentAgent.php
```

---

## Files to Upload

### Backend (PHP)
```
app/Http/Controllers/Api/InvestmentController.php
app/Services/Investment/FeeAnalyzer.php
app/Agents/InvestmentAgent.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Requests/Chattel/StoreChattelRequest.php
app/Services/Estate/AssetLiquidityAnalyzer.php
app/Services/UserProfile/ModuleDataRequirementsService.php
app/Services/Onboarding/OnboardingService.php
app/Services/UserProfile/UserProfileService.php
```

### Frontend (after rebuild)
```
public/build/  (entire folder)
```

---

## Post-Deployment

```bash
php artisan cache:clear
```

Cache clear required so cached investment analysis regenerates with the corrected recommendation logic.
