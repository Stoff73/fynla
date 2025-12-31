# Portfolio-Wide Diversification Score

**Date:** 2025-12-31
**Status:** Completed ✓

## Problem
The Investment Portfolio Summary card in `InvestmentList.vue` shows Diversification Score as 0/100. The Vuex getter reads from `state.analysis?.diversification_score` which is not populated.

## Solution
Calculate portfolio-wide diversification score directly in the component using the accounts' holdings data (already loaded). Use value-weighted average of per-account scores.

## Implementation

### File: `resources/js/components/NetWorth/InvestmentList.vue`

#### 1. Add computed property for portfolio diversification score

```javascript
// Replace the Vuex getter with a local computed property

portfolioDiversificationScore() {
  if (!this.accounts.length) return 0;

  let totalWeightedScore = 0;
  let totalValue = 0;

  for (const account of this.accounts) {
    const accountValue = parseFloat(account.current_value) || 0;
    if (accountValue <= 0) continue;

    const holdings = account.holdings || [];
    if (!holdings.length) continue;

    // Calculate this account's diversification score
    const accountScore = this.calculateAccountDiversificationScore(account);
    totalWeightedScore += accountScore * accountValue;
    totalValue += accountValue;
  }

  return totalValue > 0 ? Math.round(totalWeightedScore / totalValue) : 0;
},

diversificationLabel() {
  const score = this.portfolioDiversificationScore;
  if (score >= 80) return 'Excellent';
  if (score >= 60) return 'Good';
  if (score >= 40) return 'Fair';
  return 'Poor';
},
```

#### 2. Add helper method to calculate per-account diversification score

```javascript
// In methods section - mirrors backend DiversificationAnalyzer logic

calculateAccountDiversificationScore(account) {
  const holdings = account.holdings || [];
  if (!holdings.length) return 0;

  const totalValue = holdings.reduce((sum, h) => sum + (parseFloat(h.current_value) || 0), 0);
  if (totalValue <= 0) return 0;

  // Calculate HHI (Herfindahl-Hirschman Index)
  let hhi = 0;
  for (const holding of holdings) {
    const weight = (parseFloat(holding.current_value) || 0) / totalValue;
    hhi += weight * weight;
  }

  // Calculate concentration metrics
  const percentages = holdings
    .map(h => ((parseFloat(h.current_value) || 0) / totalValue) * 100)
    .sort((a, b) => b - a);

  const topHoldingPercent = percentages[0] || 0;
  const top3Percent = percentages.slice(0, 3).reduce((a, b) => a + b, 0);

  // Calculate asset class diversity
  const assetClasses = new Set();
  const classMap = {
    'uk_equity': 'equities', 'us_equity': 'equities', 'international_equity': 'equities',
    'equity': 'equities', 'fund': 'equities', 'etf': 'equities',
    'bond': 'bonds', 'cash': 'cash', 'alternative': 'alternatives', 'property': 'alternatives'
  };
  for (const holding of holdings) {
    const assetType = (holding.asset_type || 'equity').toLowerCase();
    assetClasses.add(classMap[assetType] || 'equities');
  }

  // Score calculation (mirrors backend)
  let score = 100;

  // HHI penalty (0-40 points)
  if (hhi >= 0.5) score -= 40;
  else if (hhi >= 0.25) score -= 25;
  else if (hhi >= 0.15) score -= 10;

  // Concentration penalties (0-30 points)
  if (topHoldingPercent > 40) score -= 20;
  else if (topHoldingPercent > 25) score -= 10;

  if (top3Percent > 80) score -= 10;
  else if (top3Percent > 60) score -= 5;

  // Asset class diversity bonus/penalty
  const classCount = assetClasses.size;
  if (classCount >= 4) score += 10;
  else if (classCount === 1) score -= 20;
  else if (classCount === 2) score -= 10;

  return Math.max(0, Math.min(100, score));
},
```

#### 3. Update template to use new computed property

**Lines 157-160 - Change from:**
```vue
<div class="summary-item diversification">
  <p class="summary-label">Diversification Score</p>
  <p class="summary-value">{{ diversificationScore }}/100</p>
  <p class="summary-count">{{ diversificationLabel }}</p>
</div>
```

**To:**
```vue
<div class="summary-item diversification">
  <p class="summary-label">Diversification Score</p>
  <p class="summary-value">{{ portfolioDiversificationScore }}/100</p>
  <p class="summary-count">{{ diversificationLabel }}</p>
</div>
```

#### 4. Remove unused Vuex getter import

**Lines 318-322 - Change from:**
```javascript
...mapGetters('investment', [
  'accounts',
  'totalPortfolioValue',
  'diversificationScore',
  'holdingsCount',
]),
```

**To:**
```javascript
...mapGetters('investment', [
  'accounts',
  'totalPortfolioValue',
  'holdingsCount',
]),
```

## Files to Modify
- `resources/js/components/NetWorth/InvestmentList.vue`

## Testing
After implementation, test with preview personas:
1. Login as peak_earners (has multiple accounts with holdings)
2. Navigate to Net Worth > Investments
3. Verify Portfolio Summary card shows non-zero diversification score
4. Score should be value-weighted average of per-account scores
5. Label should reflect score (Excellent/Good/Fair/Poor)

## Results
Tested with peak_earners persona:
- David's S&S ISA: 35/100 (single asset class - all equities)
- Joint GIA: 45/100 (3 asset classes - equities, bonds, alternatives)
- VCT Holdings: skipped (no holdings)
- **Portfolio Score: 40/100 (Fair)** - value-weighted average

The diversification score now displays correctly in the Investment Portfolio Summary card.
