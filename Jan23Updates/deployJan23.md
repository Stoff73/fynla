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

---

## Frontend Rebuild Required: YES

Vue components were modified.

```bash
./deploy/fynla-org/build.sh
```

---

## Files Changed

```
resources/js/components/NetWorth/InvestmentDetailInline.vue
resources/js/components/Investment/HoldingForm.vue
resources/js/components/Investment/AccountStrategyCard.vue  (NEW)
resources/js/views/Investment/AccountPerformancePanel.vue
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
app/Agents/InvestmentAgent.php
```

---

## Files to Upload

### Backend (PHP)
```
app/Agents/InvestmentAgent.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Requests/Chattel/StoreChattelRequest.php
app/Services/Estate/AssetLiquidityAnalyzer.php
app/Services/UserProfile/ModuleDataRequirementsService.php
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
