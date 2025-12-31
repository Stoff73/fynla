# December 31, 2025 Updates

## Summary

This folder documents changes made on December 31, 2025.

## Changes

### 1. Information Guide Feature
**File:** `InfoGuide_Feature.md`

Added a floating help button that shows users what data is needed for each module, with context-aware requirements and plain-language explanations.

### 2. Seeder Requirements Update
**File:** `Seeder_Requirements_Update.md`

Updated seeder classification to make `AdminUserSeeder` and `PreviewUserSeeder` required seeders (Phase 1) instead of optional development-only seeders.

### 3. Documentation Cleanup

Removed outdated documentation from Dec14-Dec30 folders:
- 50+ markdown files consolidated/archived
- Reference PDFs moved elsewhere
- Word documents removed

### 4. Investment Portfolio Return Fix
**File:** `Investment_Portfolio_Return_Fix.md`

Fixed the Investment Portfolio Summary card showing YTD Return as 0%. Now displays:
- **Gross Return** - Annualised return before fees
- **Net of Fees Return** - Annualised return after platform, advisor, and OCF fees

Calculation uses value-weighted average of individual account returns.

### 5. Diversification Tab for Investment Accounts & DC Pensions
**Files:** `Diversification_Tab_Plan.md`, `divTasks.md`

Added a new "Diversification" tab to investment account and DC pension detail views:
- **HHI Score** - Herfindahl-Hirschman Index for concentration measurement
- **Concentration Warnings** - Alerts for over-concentrated positions
- **Asset Class Breakdown** - Visual breakdown vs target allocation
- **Recommendations** - Actionable suggestions based on analysis

Backend service: `DiversificationAnalyzer.php` with 46 unit tests.

### 6. Portfolio-Wide Diversification Score
**File:** `Portfolio_Diversification_Score_Plan.md`

Fixed the Investment Portfolio Summary card showing Diversification Score as 0/100. Now calculates:
- **Value-weighted average** of per-account diversification scores
- **Score labels**: Excellent (80+), Good (60-79), Fair (40-59), Poor (<40)
- Uses HHI, concentration metrics, and asset class diversity

Example: peak_earners persona shows 40/100 (Fair).

## Required Seeders (Updated)

After these changes, the following 6 seeders are required for the app to function:

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

## Commits

| Hash | Description |
|------|-------------|
| `164b3f8` | feat: Add portfolio-wide diversification score to Investment Summary |
| `2f5ff78` | feat: Add Diversification Tab for investment accounts and DC pensions |
| `3696c74` | feat: Add portfolio-wide annualised return to Investment Summary |
| `b7f419a` | docs: Add Dec31Updates documentation |
| `f81489d` | docs: Update seeder requirements and clean up old documentation |
| `786e2d0` | feat: Add Information Guide feature for data requirements |
