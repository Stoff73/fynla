# Deploy Notes - Feb 3, 2025

## Dashboard Retirement Card Redesign

### Summary
Redesigned the dashboard retirement card with a new two-column layout featuring a pension pot projection chart. Added conditional rendering to show a retirement income view for users who are already retired.

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/Dashboard/RetirementOverviewCard.vue` | Modified |
| `resources/js/components/Dashboard/NetWorthOverviewCard.vue` | Modified |
| `resources/js/components/Retirement/PensionPotProjectionChart.vue` | Modified |
| `resources/js/views/Dashboard.vue` | Modified |

### Changes Detail

#### 1. RetirementOverviewCard.vue (Major Redesign)
- **Two-column layout**: Card now spans 2 columns on desktop
- **Stats grid**: Shows 6 key metrics with colored values:
  - Target Income (blue)
  - Projected Income (green)
  - Required Capital (purple)
  - Projected Capital (teal)
  - Retirement Age (primary)
  - Allowance Used (rose)
- **Pensions list**: Shows user's DC pensions on the left side
- **Chart**: Displays pension pot projection with single 80% probability line in green
- **Retired user view**: If `current_age >= retirement_age`, shows:
  - Income stats (Target Income, Net Income, Tax Rate, Total Capital)
  - Income sources list with annual amounts
  - Tax breakdown (Gross, Tax, Net)
- **Data sources**: Uses actual API data from projections, requiredCapital, and annualAllowance endpoints

#### 2. PensionPotProjectionChart.vue
- Added new props for dashboard compact mode:
  - `showLegend` (default: true)
  - `showAxes` (default: true)
  - `showToolbar` (default: true)
  - `singleLine` (default: false) - shows only 80% probability line
- Single line mode uses green color (`SUCCESS_COLORS[500]`)
- Props control visibility of chart elements for compact dashboard view

#### 3. NetWorthOverviewCard.vue
- Simplified header: Changed from small label to bold "Net Worth" header
- Minor styling adjustments

#### 4. Dashboard.vue
- Updated retirement card wrapper to span 2 columns: `sm:col-span-2`
- Added `self-start` to prevent height stretching

### Deployment Steps

1. **Build locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. **Upload files via SiteGround File Manager:**
   - `public/build/` directory (full replacement)

3. **Clear caches:**
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan route:clear && php artisan config:clear
   ```

### Testing Checklist

- [ ] **Non-retired personas** (Carter, Mitchell, Chen, Morgan):
  - [ ] Retirement card shows pension pot projection chart
  - [ ] All 6 stats display with correct values and colors
  - [ ] Pensions list shows on left side
  - [ ] Chart shows single green 80% probability line
  - [ ] Clicking card navigates to retirement section

- [ ] **Retired personas** (Williams, Thompson):
  - [ ] Card header changes to "Retirement Income"
  - [ ] Shows income stats (4 columns)
  - [ ] Income sources list displays with amounts
  - [ ] Tax breakdown box shows gross/tax/net
  - [ ] Clicking card navigates to retirement section

- [ ] **Net Worth card**:
  - [ ] Bold "Net Worth" header displays correctly
  - [ ] All asset/liability breakdowns show

### Commit
```
70ae7da feat: Redesign dashboard retirement card with retired user support
```

---

## IHT Calculation & Projection Fixes

### Summary
Comprehensive fixes to the IHT calculation system addressing multiple issues:
1. **Retirement age bug** - Cached calculations were missing retirement_age field
2. **Projection totals mismatch** - Breakdown totals didn't match service calculations
3. **Cash projection display** - Cash assets incorrectly showed current value instead of £0
4. **Chattel projections** - Personal valuables were incorrectly projected at 4.7% growth

### Problems Fixed
1. The `iht_calculations` database table doesn't store `projected_cash`, `projected_investments`, `projected_properties`, or `retirement_age`
2. Cached results returned incomplete data, causing projection factors to be 0
3. JavaScript `||` operator treated 0 as falsy, falling back to current value
4. Chattel assets were projected at 4.7% but not included in service totals

### Files Changed

| File | Change Type |
|------|-------------|
| `app/Services/Estate/IHTCalculationService.php` | Modified |
| `app/Http/Controllers/Api/Estate/IHTController.php` | Modified |
| `resources/js/components/Estate/IHTPlanning.vue` | Modified |
| `resources/js/components/Estate/IHTAssetBreakdown.vue` | Modified |

### Changes Detail

#### 1. IHTCalculationService.php

**Change 1: Disable caching temporarily** (~line 973-980)
```php
// TEMPORARILY DISABLED: The database schema doesn't include projected_cash,
// projected_investments, projected_properties, or retirement_age columns.
// Until these are added via migration, we must recalculate every time.
```

**Change 2: Include chattels in projected_gross_assets** (~line 258-284)
```php
// Get current chattel and business values (these don't appreciate)
$projectedChattels = $userAssets->where('asset_type', 'chattel')
    ->reject(fn ($a) => $a->is_iht_exempt)
    ->sum('current_value');

// Calculate totals (include chattels and business at current value)
$projectedGrossAssets = $projectedCash + $projectedInvestments + $projectedProperties + $projectedChattels + $projectedBusiness;
```

#### 2. IHTController.php

**Change 1: Cast MySQL sum() to float** (~line 622-625)
```php
$currentCash = (float) $user->savingsAccounts()->sum('current_balance');
```

**Change 2: Keep chattels at current value** (~line 245-250)
```php
$projectedValue = match ($asset->asset_type) {
    'cash' => $displayValue * $cashProjectionFactor,
    'investment' => $displayValue * $investmentProjectionFactor,
    'property' => $displayValue * $propertyProjectionFactor,
    'chattel', 'business' => $displayValue, // No growth
    default => $displayValue,
};
```

#### 3. IHTPlanning.vue

**Change: Use nullish coalescing for projected values** (~lines 921, 938)
```javascript
// Before: total += (asset.projected_value || asset.value || 0);
// After:
total += (asset.projected_value ?? asset.value ?? 0);
```

#### 4. IHTAssetBreakdown.vue

**Change: Use nullish coalescing in category totals** (~line 302)
```javascript
// Before: sum + (a.projected_value || a.value || 0)
// After:
sum + (a.projected_value ?? a.value ?? 0)
```

### Deployment Steps

1. **Build frontend locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. **Upload files via SiteGround File Manager:**
   - `public/build/` directory (full replacement)
   - `app/Services/Estate/IHTCalculationService.php`
   - `app/Http/Controllers/Api/Estate/IHTController.php`

3. **Clear caches:**
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan route:clear && php artisan config:clear
   ```

### Testing Checklist

- [ ] **Mitchell persona (peak_earners)**:
  - [ ] Estate Planning > IHT page loads without errors
  - [ ] Retirement Age shows 60 (not 68)
  - [ ] Cash Projection shows Final Cash £0 with shortfall message
  - [ ] Table shows "Pre-Retirement" for Ages 49-59
  - [ ] Table shows "Retired" starting at Age 60

- [ ] **Asset breakdown verification (expand David's Assets)**:
  - [ ] Property: £755,500 → £2,696,713 (3.57x growth)
  - [ ] Investment: £172,500 → £2,518,424 (14.6x Monte Carlo)
  - [ ] Cash/Savings: £58,200 → **£0** (depleted in retirement)
  - [ ] Personal Valuables: £132,250 → £132,250 (no growth)
  - [ ] Subtotal: £1,118,450 → £5,347,388 ✓

- [ ] **Totals consistency**:
  - [ ] Total Gross Assets: £2,005,230 → £9,618,099
  - [ ] Net Estate: £1,712,230 → £9,618,099 (liabilities = £0)
  - [ ] Taxable Estate: £712,230 → £8,618,099
  - [ ] IHT Liability: £284,892 → £3,447,239

---

## Integrated Cash-Investment Drawdown Model

### Summary
Implemented an integrated cash-investment projection model that accurately handles retirement deficit drawdown. When cash goes negative during retirement (expenses exceed income), the deficit is drawn from investment accounts BEFORE applying growth. This creates a realistic projection where retirement deficits deplete investments over time.

### Problem Statement
Previously, cash and investments were projected independently:
- Cash projection calculated surplus/deficit each year, returning £0 when negative
- Investment projection applied growth to the full balance regardless of cash deficits
- This meant investment growth was overstated - in reality, deficits would be funded by selling investments

### Solution
New integrated year-by-year projection that:
1. Calculates cash surplus for each year (income - expenses)
2. If cash goes negative, draws deficit from investments BEFORE growth
3. Splits deficit equally across all investment accounts
4. Applies investment growth rate to the reduced balance
5. Repeats for each year until death

### Files Changed

| File | Change Type |
|------|-------------|
| `app/Services/Estate/IHTCalculationService.php` | Modified |

### Changes Detail

#### IHTCalculationService.php

**Change 1: New integrated projection method** (~lines 381-472)
```php
/**
 * Integrated projection: Cash deficits drawn from investments year-by-year
 */
private function projectCashAndInvestmentsIntegrated(
    User $user,
    ?User $spouse,
    int $currentAge,
    int $retirementAge,
    int $deathAge,
    array $assumptions,
    bool $dataSharingEnabled
): array {
    // Year-by-year projection
    for ($age = $currentAge; $age < $deathAge; $age++) {
        // Calculate cash surplus
        $surplus = $income - $expenses;
        $cashBalance += $surplus;

        // If cash goes negative, draw from investments BEFORE growth
        if ($cashBalance < 0) {
            $deficit = abs($cashBalance);
            $cashBalance = 0;

            // Distribute deficit equally across all accounts
            $deficitPerAccount = $deficit / $accountCount;
            foreach ($investments as &$account) {
                $account['balance'] = max(0, $account['balance'] - $deficitPerAccount);
            }
        }

        // Apply investment growth AFTER drawdown
        foreach ($investments as &$account) {
            $account['balance'] *= (1 + $investmentGrowthRate);
        }
    }

    return ['projected_cash' => ..., 'projected_investments' => ...];
}
```

**Change 2: Helper to get investment accounts as array** (~lines 480-508)
```php
private function getInvestmentAccountsArray(User $user, ?User $spouse, bool $dataSharingEnabled): array
```

**Change 3: Helper to derive annualised Monte Carlo rate** (~lines 516-538)
```php
private function getMonteCarloAnnualRate(User $user, ?User $spouse, bool $dataSharingEnabled): float
```

**Change 4: Updated calculateProjectedValues() to use integrated model** (~lines 232-245)
```php
// Project cash and investments together using integrated drawdown model
$integratedProjection = $this->projectCashAndInvestmentsIntegrated(...);
$projectedCash = $integratedProjection['projected_cash'];
$projectedInvestments = $integratedProjection['projected_investments'];
```

### Projection Logic Example (Mitchell persona)

```
Year  Age  Phase      Income    Expenses   Surplus    Cash      Investments
────  ───  ─────────  ────────  ─────────  ─────────  ────────  ────────────
1     49   Pre-Ret    £265k     £185k      +£80k      £138k     £305k
...
12    60   Retired    £75k      £135k      -£60k      £78k      £640k
...
27    75   Retired    £75k      £135k      -£60k      £0        £1.2M ← deficit drawn
28    76   Retired    £75k      £135k      -£60k      £0        £1.14M ← £60k drawn, then grow
...
37    85   Death      —         —          —          £0        £850k
```

### Deployment Steps

1. **Upload PHP file via SiteGround File Manager:**
   - `app/Services/Estate/IHTCalculationService.php`

2. **Clear caches:**
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan route:clear && php artisan config:clear
   ```

### Testing Checklist

- [ ] **Mitchell persona (peak_earners)**:
  - [ ] Navigate to Estate Planning > IHT
  - [ ] Verify cash projects to £0 at death
  - [ ] Verify investments are reduced by retirement deficits
  - [ ] Final investment value should be lower than pure Monte Carlo projection

- [ ] **Non-retired users with surplus (Carter, Chen)**:
  - [ ] Cash should accumulate during pre-retirement years
  - [ ] If surplus continues into retirement, no drawdown occurs
  - [ ] Investments grow at full Monte Carlo rate

- [ ] **Edge cases**:
  - [ ] User with no investments: deficit stays in cash (£0)
  - [ ] User already retired: drawdown starts immediately
  - [ ] Investments fully depleted: both cash and investments show £0

---

## Dashboard Estate Card Always Visible

### Summary
Changed the Estate Planning card on the dashboard to always be visible, regardless of IHT liability amount. Previously the card was hidden when `ihtLiability === 0`, which could hide it due to loading issues or for users with no current IHT liability.

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/views/Dashboard.vue` | Modified |

### Changes Detail

#### Dashboard.vue

**Change: Always show Estate card** (~line 260-263)
```javascript
// Before:
shouldShowEstateCard() {
  return this.estateData.ihtLiability > 0;
},

// After:
shouldShowEstateCard() {
  // Always show estate card - users need to see their estate planning status
  return true;
},
```

### Deployment Steps

1. **Build frontend locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. **Upload files via SiteGround File Manager:**
   - `public/build/` directory (full replacement)

### Testing Checklist

- [ ] **All personas**:
  - [ ] Estate Planning card visible on dashboard
  - [ ] Card shows correct current and projected values
  - [ ] Clicking card navigates to Estate Planning page

---

## Life Insurance Strategy Page Simplification

### Summary
Simplified the Life Insurance Strategy page by removing self-insurance options, inaccurate claims, and unnecessary UI elements.

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/Estate/LifePolicyStrategy.vue` | Modified |
| `app/Services/Estate/LifePolicyStrategyService.php` | Modified |

### Changes Detail

#### 1. LifePolicyStrategy.vue
- Removed top header card with "Life Insurance Strategy" title
- Removed cost-benefit ratio section from policy card header
- Removed "Fixed for life" label from Monthly Premium card
- Removed entire self-insurance section (Option 2)
- Removed side-by-side comparison table
- Removed prioritized recommendations section

#### 2. LifePolicyStrategyService.php
- Removed inaccurate key features:
  - "Premiums fixed for life (level premiums)" - premiums can increase
  - "Joint policy saves approximately 25%" - not always true
  - "No medical underwriting required" - medical underwriting IS required
- Updated conditional for joint policy to show factual info

### Deployment Steps

1. **Build frontend locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. **Upload files via SiteGround File Manager:**
   - `public/build/` directory (full replacement)
   - `app/Services/Estate/LifePolicyStrategyService.php`

3. **Clear caches:**
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan route:clear && php artisan config:clear
   ```

### Testing Checklist

- [ ] **Life Insurance Strategy page (Mitchell persona)**:
  - [ ] Shows "Joint Life Second Death" policy card
  - [ ] No cost-benefit ratio in header
  - [ ] Monthly Premium card shows just "£447" (no "Fixed for life")
  - [ ] Key Features shows only 3 items (no fixed premiums, no 25% savings, no medical underwriting claims)
  - [ ] Decision Framework still displays correctly

---

## Gifting Strategy UI Fix

### Summary
Fixed misleading "Immediately Giftable" and "Giftable with Planning" terminology that showed arbitrary asset liquidity values. Replaced with meaningful exemption-based metrics.

### Problem
The Gifting Strategy page showed:
- "Immediately Giftable: £513,669" - arbitrary 30% of net worth
- "Giftable with Planning" - total semi-liquid assets

These values were misleading because:
1. Users can't gift ALL their liquid assets (need money to live)
2. Values implied you SHOULD gift everything in these categories
3. Didn't align with actual UK gifting exemptions

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/Estate/GiftingStrategy.vue` | Modified |
| `resources/js/components/Estate/IHTPlanning.vue` | Modified |

### Changes Detail

#### 1. GiftingStrategy.vue
**Changed header cards from asset liquidity to strategy metrics:**
```
Before:
- Total Estate Value | Immediately Giftable | Giftable with Planning | Not Giftable

After:
- Current IHT Liability | Annual Exemption (£3,000/year) | Total to Gift | IHT Saved
```

#### 2. IHTPlanning.vue (Dashboard Card)
**Changed Gifting card metrics:**
```
Before:
- Annual Exemption: £3,000
- Immediately Giftable: {{ formatCurrency(immediatelyGiftableAmount) }}

After:
- Annual Exemption: £3,000
- IHT Liability: {{ formatCurrency(projection?.now?.iht_liability || 0) }}
```

### Deployment Steps

1. **Build frontend locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. **Upload files via SiteGround File Manager:**
   - `public/build/` directory (full replacement)

### Testing Checklist

- [ ] **Estate Dashboard (Mitchell persona)**:
  - [ ] Gifting card shows "Annual Exemption: £3,000"
  - [ ] Gifting card shows "IHT Liability: £284,892" (not "Immediately Giftable")

- [ ] **Gifting Strategy page (Mitchell persona)**:
  - [ ] Header shows: Current IHT Liability | Annual Exemption | Total to Gift | IHT Saved
  - [ ] No "Immediately Giftable" or "Giftable with Planning" cards
  - [ ] Strategy cards still display correctly with priorities and implementation steps
