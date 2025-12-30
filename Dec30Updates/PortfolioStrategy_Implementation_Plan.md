# Investment Strategy Tab Implementation Plan

## Overview
Replace the "Coming Soon" Strategy tab in InvestmentList.vue with a fully functional portfolio strategy panel that aggregates recommendations from tax, fee, and rebalancing services with actionable execution buttons.

## Requirements
- **Priority Order**: Tax allowances > Bond wrappers (for GIA) > Fee reduction > Rebalancing
- **Actionable**: Include execution buttons using existing modals
- **Views**: Toggle between portfolio-level and per-account breakdown
- **Real Data**: Use actual user data from existing services

---

## Files to Create

### 1. Backend Service
**`app/Services/Investment/PortfolioStrategyService.php`**
- Aggregates recommendations from existing services:
  - `TaxOptimizationAnalyzer` - ISA allowance, CGT, Bed & ISA
  - `FeeAnalyzer` - High-fee holdings, alternatives
  - `DriftAnalyzer` - Rebalancing needs
- New method: `analyzeBondWrapperOpportunities()` for GIA > Bond recommendations
- Prioritization logic based on category and potential savings

### 2. Backend Controller
**`app/Http/Controllers/Api/Investment/PortfolioStrategyController.php`**
- `index()` - Portfolio-level strategy
- `forAccount($accountId)` - Account-specific strategy

### 3. Frontend Component
**`resources/js/views/Investment/PortfolioStrategyPanel.vue`**
- Main component replacing Recommendations.vue in Strategy tab
- Summary stats (total savings, recommendation count, tax efficiency score)
- View toggle (Portfolio / Per-Account)
- Grouped recommendation sections by priority category

### 4. Supporting Components
**`resources/js/components/Investment/StrategyRecommendationCard.vue`**
- Generic card for displaying recommendations
- Priority badge, category badge, potential savings
- Action button based on recommendation type

**`resources/js/components/Investment/BondWrapperInfoModal.vue`**
- Educational modal for bond wrapper recommendations
- Explains onshore/offshore bonds and top-slicing relief

---

## Files to Modify

### `routes/api.php`
Add routes:
```php
Route::get('/portfolio-strategy', [PortfolioStrategyController::class, 'index']);
Route::get('/portfolio-strategy/account/{accountId}', [PortfolioStrategyController::class, 'forAccount']);
```

### `resources/js/services/investmentService.js`
Add methods:
```javascript
getPortfolioStrategy(params = {})
getAccountStrategy(accountId)
```

### `resources/js/components/NetWorth/InvestmentList.vue`
- Lines 206-213: Remove "Coming Soon" wrapper
- Replace `<Recommendations />` with `<PortfolioStrategyPanel />`
- Update import and component registration

---

## API Response Structure

```json
{
  "summary": {
    "total_potential_savings": 4250.00,
    "recommendation_count": 8,
    "high_priority_count": 3,
    "tax_efficiency_score": 72
  },
  "recommendations": [
    {
      "id": "isa_underutilization",
      "category": "tax",
      "priority": 1,
      "title": "ISA Allowance Available",
      "description": "...",
      "potential_saving": 250.00,
      "urgency": "high",
      "action_type": "isa_transfer",
      "action_data": { "modal": "ISATransferModal", ... }
    }
  ],
  "by_account": { ... }
}
```

---

## Bond Wrapper Recommendation Criteria
- GIA balance >= 50,000
- User is higher/additional rate taxpayer
- Show tax deferral benefits (estimated annual saving)
- Recommend onshore (default) or offshore (>100k + additional rate)

---

## UI Layout

```
+------------------------------------------------------------------+
| Investment Strategy                                    [Refresh]  |
+------------------------------------------------------------------+
| Summary: Total Savings | Count | High Priority | Tax Score       |
+------------------------------------------------------------------+
| View: [Portfolio] [Per Account]                                   |
+------------------------------------------------------------------+
| TAX ACTIONS (Priority 1-2)                                        |
|   - ISA Allowance Available              [Transfer Now]           |
|   - Bed & ISA Opportunity                [View Execution Plan]    |
+------------------------------------------------------------------+
| WRAPPER OPTIMISATION (Priority 3)                                 |
|   - Consider Bond Wrapper for GIA        [Learn More]             |
+------------------------------------------------------------------+
| FEE REDUCTION (Priority 4)                                        |
|   - High-Fee Fund: ACME Active           [View Alternatives]      |
+------------------------------------------------------------------+
| REBALANCING (Priority 5)                                          |
|   - Portfolio Drift 12%                  [View Rebalancing Plan]  |
+------------------------------------------------------------------+
```

---

## Implementation Order

1. **Backend Service** - `PortfolioStrategyService.php`
   - Inject existing services
   - Implement `getPortfolioStrategy()` aggregation
   - Implement `analyzeBondWrapperOpportunities()`

2. **Backend Controller & Routes** - `PortfolioStrategyController.php`
   - Create controller with index/forAccount methods
   - Register API routes

3. **Frontend Service** - `investmentService.js`
   - Add API methods

4. **Main Component** - `PortfolioStrategyPanel.vue`
   - Layout with summary stats
   - View toggle
   - Grouped sections
   - Loading/error states

5. **Card Component** - `StrategyRecommendationCard.vue`
   - Generic recommendation display
   - Action buttons wired to existing modals

6. **Bond Modal** - `BondWrapperInfoModal.vue`
   - Educational content for bond wrappers

7. **Integration** - `InvestmentList.vue`
   - Remove Coming Soon wrapper
   - Swap Recommendations for PortfolioStrategyPanel

8. **Testing**
   - Test with preview personas
   - Verify modal flows work

---

## Critical Existing Files Reference

| File | Purpose |
|------|---------|
| `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php` | Tax recommendations source |
| `app/Services/Investment/FeeAnalyzer.php` | Fee recommendations source |
| `app/Services/Investment/Rebalancing/DriftAnalyzer.php` | Rebalancing recommendations |
| `resources/js/components/Investment/TaxEfficiencyPanel.vue` | Pattern for tax display |
| `resources/js/components/Investment/BedAndISAWizardModal.vue` | Existing action modal |
| `resources/js/components/Investment/ISATransferModal.vue` | Existing action modal |
