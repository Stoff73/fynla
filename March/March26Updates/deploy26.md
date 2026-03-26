# Deploy Guide — 26 March 2026 (Session 11)

## Summary

Two phases of work:
1. **Pension UI**: Holdings tab, fee display improvements, OCF input on holdings form
2. **Fee system gaps**: Pension fee action triggers, projection fixes, protection premium actions, 10-year fee impact, IncomeProtection premium_frequency

## Phase 1 — Pension UI (merged via PR #162)

### 1. PensionDetailInline.vue — Pension Detail View
**File:** `resources/js/components/NetWorth/PensionDetailInline.vue`

**Holdings Tab (new):**
- Holdings moved from overview section into dedicated "Holdings" tab
- Tab only appears for DC pensions that have holdings
- Tab order: Overview → Holdings → Projections → Documents
- Holdings table shows: Fund Name, Type, Allocation %, Value, OCF %
- Cash (unallocated) row shown in footer when allocations < 100%
- Fee summary bar: Weighted Avg OCF + Total Annual Cost
- **10-Year Fee Impact** section: Cumulative Fees Paid, Lost Growth (Fee Drag), Total Impact

**Fees Section (updated):**
- Platform fee handles both percentage and fixed (£) types with frequency
- Advisor fee row added (shown only when > 0)
- Total annual cost includes platform + advisor + weighted OCF

**Bug fixes:**
- Fixed `font-medium font-semibold` CSS conflict on 3 elements
- Replaced `text-purple-600` with `text-violet-600` for palette compliance

### 2. InlineHoldingsEditor.vue — OCF Input
**File:** `resources/js/components/Investment/InlineHoldingsEditor.vue`

- Added OCF % column to the inline holdings editor grid
- Grid layout changed from 4+2+2+3+1 to 3+2+2+2+2+1 columns
- `ocf_percent` added to `initHoldings()`, `addRow()`, and `stripInternal()`
- **Note:** Affects both pension and investment holdings forms (shared component)

## Phase 2 — Fee System Gaps (fees branch)

### 3. Pension Fee Action Triggers (NEW)
**Files:**
- `database/seeders/RetirementActionDefinitionSeeder.php` — 3 new action definitions
- `app/Services/Retirement/RetirementActionDefinitionService.php` — 3 new evaluators + 2 helpers

| Action Key | Condition | Threshold | Priority |
|------------|-----------|-----------|----------|
| `high_pension_total_fees` | `pension_total_fee_percent_above` | 1.0% | high |
| `high_pension_platform_fees` | `pension_platform_fee_percent_above` | 0.8% | medium |
| `high_pension_fund_fees` | `pension_weighted_ocf_above` | 0.5% | medium |

Helper methods added: `calculateAnnualisedPlatformFeePercent()`, `calculateWeightedOCF()` — handle fixed/percentage fee types and holdings OCF.

DC pensions now loaded with `->with('holdings')` for OCF calculations.

### 4. PensionPortfolioAnalyzer Fix
**File:** `app/Services/Retirement/PensionPortfolioAnalyzer.php`

- `analyzeFees()` now handles `platform_fee_type === 'fixed'` with frequency conversion
- Includes `advisor_fee_percent` in total fee calculation
- Returns new `advisor_fees` field in output array

### 5. PensionProjector + ContributionOptimizer Fix
**Files:**
- `app/Services/Retirement/PensionProjector.php`
- `app/Services/Retirement/ContributionOptimizer.php`

- Net growth rate now deducts platform fee + advisor fee (was platform only)
- Handles fixed platform fees by converting to annualised percentage

### 6. Protection Premium Action Triggers (NEW)
**Files:**
- `database/seeders/ProtectionActionDefinitionSeeder.php` — 2 new action definitions
- `app/Services/Protection/ProtectionActionDefinitionService.php` — evaluator + helpers

| Action Key | Condition | Threshold | Priority |
|------------|-----------|-----------|----------|
| `high_premium_cost` | `premium_percent_of_income_above` | 5% | medium |
| `premium_affordability_warning` | `premium_percent_of_income_above` | 10% | high |

Both use the same evaluator (`evaluatePremiumAffordability`) — the higher threshold action takes priority. Calculates total annual premiums across life, CI, and IP policies.

### 7. IncomeProtectionPolicy — premium_frequency
**Files:**
- `app/Models/IncomeProtectionPolicy.php` — added `premium_frequency` to fillable
- `database/migrations/2026_03_26_103410_add_premium_frequency_to_income_protection_policies_table.php`

New column: `premium_frequency` (string, default 'monthly')

### 8. IPT (Insurance Premium Tax)
No code change needed — life, critical illness, and income protection are all exempt per TaxConfig.

## Files to Upload

### PHP Files
```
app/Models/IncomeProtectionPolicy.php
app/Services/Protection/ProtectionActionDefinitionService.php
app/Services/Retirement/ContributionOptimizer.php
app/Services/Retirement/PensionPortfolioAnalyzer.php
app/Services/Retirement/PensionProjector.php
app/Services/Retirement/RetirementActionDefinitionService.php
database/migrations/2026_03_26_103410_add_premium_frequency_to_income_protection_policies_table.php
database/seeders/ProtectionActionDefinitionSeeder.php
database/seeders/RetirementActionDefinitionSeeder.php
```

### Frontend (via build)
```
resources/js/components/NetWorth/PensionDetailInline.vue
resources/js/components/Investment/InlineHoldingsEditor.vue
```

## Build Required

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory after building.

## SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration
php artisan migrate

# Reseed action definitions
php artisan db:seed --class=RetirementActionDefinitionSeeder --force
php artisan db:seed --class=ProtectionActionDefinitionSeeder --force

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Phase 3 — Protection UI (protectionUI branch)

### 9. ProfileCompletenessChecker — Remove Domicile
**File:** `app/Services/UserProfile/ProfileCompletenessChecker.php`

- Removed `domicile_info` check from both `checkMarriedUser()` and `checkSingleUser()`
- Removed dead `hasDomicileInfo()` private method
- Domicile is irrelevant for protection planning — remains in estate/profile services where it belongs

### 10. ProtectionDashboard.vue — Remove Redundant Completeness UI
**File:** `resources/js/views/Protection/ProtectionDashboard.vue`

- Removed `ProfileCompletenessAlert` component (was top-of-page card)
- Removed custom info icon + popover next to heading (was added then removed in same session)
- Removed `profileCompleteness` data, `loadProfileCompleteness()` method, `userProfileService` import
- Data completeness is now handled by the existing **"What powers this view"** InfoGuidePanel (question mark icon in top nav) — one central system for all modules

### 11. CurrentSituation.vue — Simplified No Coverage Card
**File:** `resources/js/components/Protection/CurrentSituation.vue`

- Replaced 2 buttons ("View Gap Analysis" + "I Have Protection to Add") with single **"Add Protection"** raspberry CTA
- Removed "I currently have no protection policies" checkbox and message
- Removed dead code: `hasNoPoliciesChecked` data, watcher, `updateHasNoPoliciesFlag()` method, `delayTimeout` data/cleanup, unused `protectionService` import

### 12. ProfileCompletenessCheckerTest — Updated
**File:** `tests/Unit/Services/ProfileCompletenessCheckerTest.php`

- Domicile test rewritten from `toHaveKey('domicile_info')` to `not->toHaveKey('domicile_info')`
- All 12 tests pass (39 assertions)

### Phase 3 Files to Upload

```
app/Services/UserProfile/ProfileCompletenessChecker.php
resources/js/components/Protection/CurrentSituation.vue
resources/js/views/Protection/ProtectionDashboard.vue
```

(Test file not deployed — dev only)

## Testing Performed

### Browser (Playwright)

**Pension (Phase 1+2):**
- Logged in as thomas.greenfield@test.com
- Clicked into all 3 pensions (SIPP, Occupational, Stakeholder)
- Clicked Holdings tab on each — verified holdings table, OCF values, fee summary, 10-year impact
- SIPP: Cumulative £14,651, Lost Growth £12,835, Total Impact £27,486
- Occupational: Cumulative £5,024, Lost Growth £5,657, Total Impact £10,680
- Stakeholder: Cumulative £4,032, Lost Growth £3,218, Total Impact £7,250

**Protection (Phase 3):**
- Logged in as john@example.com
- Protection page loads with zero console errors
- "No Protection Coverage" card shows single "Add Protection" raspberry button
- No gap analysis button, no "I Have Protection" button, no checkbox
- No info icon or popover next to heading
- "What powers this view" question mark in top nav works — shows 56% completeness, data requirements, "Add now" links
- "Add Protection" button opens PolicyFormModal correctly

### Backend
- All seeders ran without errors
- PHP syntax check passed on all changed PHP files
- Route list loads (no broken references)
- Migration ran successfully (premium_frequency)
- ProfileCompletenessCheckerTest: 12/12 pass (39 assertions)
