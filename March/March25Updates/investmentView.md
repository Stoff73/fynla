# Investment Detail View Consolidation

## Context

Two investment detail views exist and conflict:

1. **InvestmentProjections.vue** (`components/NetWorth/`) — 2-column card layout: Monte Carlo chart (left) + analysis cards (right: Tax Efficiency, Holdings donut, Fees). Currently portfolio-level.
2. **InvestmentDetailInline.vue** (`components/NetWorth/`) — Per-account detail with header card (account name, badges, edit/delete, metrics), sidebar cards (diversification, rebalancing, fees), Monte Carlo, tax status. Rendered inline within InvestmentList.

**Goal:** Consolidate into InvestmentProjections.vue as the single per-account detail view. Retire InvestmentDetailInline to eliminate duplication.

## Target Layout

### Top: Account Header Card (from InvestmentDetailInline)
- Ownership + account type badges
- Provider name (title) + account name (subtitle)
- Edit + Delete buttons (with `v-preview-disabled`)
- 4 metric cards: Current Value, Annualised Return, Monthly Contribution, ISA Allowance / Joint Owner / Holdings count
- Fee metric cards when in fees drill-down mode

### Middle: Two-Column Card Layout (existing InvestmentProjections pattern, now per-account)

**LEFT COLUMN** (flex: 1):
- Projected Value card (violet-50 bg) with period selector (or "To Retirement" label)
- Per-account Monte Carlo chart (area chart, 4 probability bands)

**RIGHT COLUMN** (flex: 0 0 340px):
- **Holdings Card** (clickable) — donut chart of per-account asset allocation. Click → drill-down to AccountHoldingsPanel
- **Total Fees Card** (clickable) — per-account fee % badge + annual cost + breakdown rows. Click → drill-down to AccountFeesPanel
- **Diversification Insights Card** (clickable) — top 3 recommendations or "Well Diversified". Click → drill-down to DiversificationTab
- **Rebalancing Status Card** (clickable) — drift indicator + allocation bars. Click → drill-down to AccountRebalancingPanel

### Below Two-Column: Tax Status Section (full width)
- Tax treatment grid (first 4 items) with status icons. Clickable → drill-down to TaxStatusPanel

### Drill-Down Behaviour
- Clicking a card replaces the card layout inline with the detailed panel
- Back button: "Back to {provider}" returns to card overview
- Back button from card overview: "Back to Investments" returns to list
- Drill-down panels (no changes to these components): AccountFeesPanel, AccountHoldingsPanel, DiversificationTab, AccountRebalancingPanel, TaxStatusPanel, AccountSummaryPanel

## Implementation Steps

### Step 1: Add account prop and header card to InvestmentProjections.vue

- Add `account` prop (Object, required)
- Add `emits: ['back', 'deleted', 'updated', 'account-updated']`
- Import and register: AccountForm, ConfirmDialog, HoldingForm (for edit/delete/holding modals)
- Copy the header card template from InvestmentDetailInline (lines 20-114) including badges, metrics, edit/delete buttons
- Copy supporting computed properties: `userShareValue`, `holdingsCount`, `estimatedMonthlyContribution`, `isaRemaining`, `grossReturnPercent`, `netReturnPercent`, `platformFeeDisplay`, `weightedAverageOCF`, `advisorFeePercent`, `totalFeePercent`, `totalAnnualFeeCost`, format helpers
- Copy methods: `handleUpdate`, `handleDelete`, `handleHoldingSave`, `openHoldingModal`, `closeHoldingModal`, format helpers
- Add `activeView` data property ('main' | 'fees' | 'holdings' | 'diversification' | 'rebalancing' | 'tax-status' | 'overview')

### Step 2: Convert Monte Carlo from portfolio to per-account

Currently uses:
- `mapState('investment', ['portfolioProjections'])` + `mapGetters('investment', ['totalPortfolioValue'])`
- `fetchPortfolioProjections()` action

Replace with:
- `investmentService.getAccountProjections(this.account.id)` (endpoint already exists: `GET /api/investment/accounts/{id}/projections`)
- Store result in local `allProjections` data property
- Copy projection logic from AccountPerformancePanel: `loadProjections()`, `updateProjectionData()`, `setProjectionYearsForAccount()`
- Copy chart computed: `years()`, `series()`, `chartOptions()` from AccountPerformancePanel (per-account chart config)
- Add retirement-aware period: `isIncludedInRetirement`, `yearsToRetirement` (needs `mapState('auth', ['currentUser'])`, `mapState('retirement', ['profile'])`)

### Step 3: Convert analysis cards from portfolio to per-account

**Holdings donut:** Currently uses `analysis.asset_allocation` from portfolio analyze. Replace with per-account calculation from `account.holdings` (copy `assetAllocationSummary` computed from AccountPerformancePanel).

**Fees card:** Currently uses portfolio `analysis.fee_analysis`. Replace with per-account fee computation from `account` prop data (platform_fee, holdings OCF, advisor_fee). Already have these computed properties from Step 1.

**Tax Efficiency card:** Currently shows portfolio-wide tax-sheltered vs taxable split. For per-account, this becomes the tax treatment info for the account type. Replace with `loadTaxInfo()` from AccountPerformancePanel (calls `GET /api/tax-info/investment/{account_type}`).

### Step 4: Add new cards from InvestmentDetailInline/AccountPerformancePanel

**Diversification Insights card:**
- `loadDiversification()` → `diversificationService.getAccountDiversification(account.id)` (endpoint exists)
- Display: top 3 recommendations with type-coloured indicators
- Copy from AccountPerformancePanel lines 16-47

**Rebalancing Status card:**
- `loadRebalancing()` → `rebalancingService.getAccountRebalancing(account.id)` (endpoint exists)
- Display: drift status badge + equities/bonds allocation bars
- Copy from AccountPerformancePanel lines 49-121

**Tax Status section** (full width below 2-column):
- Uses `taxInfo` loaded in Step 3
- Display: 2x2 grid of tax items with status icons + legend
- Copy from AccountPerformancePanel lines 244-292

### Step 5: Add drill-down panel rendering

Import and register all drill-down components:
```
AccountFeesPanel, AccountHoldingsPanel, DiversificationTab,
AccountRebalancingPanel, TaxStatusPanel, AccountSummaryPanel
```

When `activeView !== 'main'`, render the corresponding panel instead of the card layout.

### Step 6: Update back button logic

```javascript
backButtonText: 'main' → 'Back to Investments', other → 'Back to {provider}'
handleBackClick: 'main' → emit('back'), other → activeView = 'main'
```

### Step 7: Add specialized account type guards

Copy `detailComponentType` from InvestmentDetailInline. Employee Share Schemes and Private Investments render their specialized components instead of the card layout:
- `EmployeeShareSchemeDetail` for SAYE, CSOP, EMI, Unapproved Options, RSU
- `PrivateInvestmentDetail` for Private Company, Crowdfunding

### Step 8: Update InvestmentList.vue

Change from rendering `InvestmentDetailInline` to rendering `InvestmentProjections`:
- Update import: `InvestmentProjections` instead of `InvestmentDetailInline`
- Pass same `account` prop and handle same events (`@back`, `@deleted`, `@updated`, `@account-updated`)

### Step 9: Retire InvestmentDetailInline.vue

- Remove the file (or rename to `InvestmentDetailInline.vue.deprecated`)
- Remove its import from InvestmentList.vue
- The `/net-worth/investment-detail` route continues to work (same component, now per-account)
- Update router to pass account ID param: `/net-worth/investment-detail/:id`

### Step 10: Responsive CSS

Consolidate scoped styles from both views:
- 2-column grid: `grid-template-columns: 1fr 340px` → collapses at 1024px
- Cards wrap horizontally on tablet, stack vertically on mobile
- Use global `.card` and `.card.cursor-pointer` for clickable cards
- Keep badge classes from InvestmentDetailInline

## New Imports in InvestmentProjections.vue

```javascript
// Data loading (from AccountPerformancePanel)
import investmentService from '@/services/investmentService';
import diversificationService from '@/services/diversificationService';
import rebalancingService from '@/services/rebalancingService';
import api from '@/services/api';

// Components (from InvestmentDetailInline)
import AccountForm from '@/components/Investment/AccountForm.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import HoldingForm from '@/components/Investment/HoldingForm.vue';
import AccountSummaryPanel from '@/views/Investment/AccountSummaryPanel.vue';
import AccountHoldingsPanel from '@/views/Investment/AccountHoldingsPanel.vue';
import AccountFeesPanel from '@/views/Investment/AccountFeesPanel.vue';
import AccountRebalancingPanel from '@/views/Investment/AccountRebalancingPanel.vue';
import DiversificationTab from '@/components/Investment/DiversificationTab.vue';
import TaxStatusPanel from '@/components/Common/TaxStatusPanel.vue';
import EmployeeShareSchemeDetail from '@/views/Investment/EmployeeShareSchemeDetail.vue';
import PrivateInvestmentDetail from '@/views/Investment/PrivateInvestmentDetail.vue';

// Design system
import { CHART_COLORS, ASSET_COLORS, PRIMARY_COLORS, SUCCESS_COLORS, BORDER_COLORS } from '@/constants/designSystem';
import { TAX_CONFIG } from '@/constants/taxConfig';
```

## Files Modified

| File | Change |
|------|--------|
| `resources/js/components/NetWorth/InvestmentProjections.vue` | Major rewrite: add account prop, header card, per-account data loading, new cards, drill-downs |
| `resources/js/components/NetWorth/InvestmentList.vue` | Swap InvestmentDetailInline → InvestmentProjections, pass account prop |
| `resources/js/router/index.js` | Update `/net-worth/investment-detail` route to accept optional `:id` param |

## Files Retired

| File | Reason |
|------|--------|
| `resources/js/components/NetWorth/InvestmentDetailInline.vue` | Replaced by consolidated InvestmentProjections.vue |

## Files NOT Modified

All drill-down panel components (AccountFeesPanel, AccountHoldingsPanel, DiversificationTab, AccountRebalancingPanel, TaxStatusPanel, AccountSummaryPanel, EmployeeShareSchemeDetail, PrivateInvestmentDetail), Vuex store, backend API endpoints, API services.

## Verification

1. `php artisan db:seed`
2. `./dev.sh`
3. Navigate to Investments, click an ISA account:
   - Header shows account name, badges, edit/delete, correct metrics
   - Monte Carlo loads for THIS account (check values match account, not portfolio total)
   - Holdings donut shows THIS account's holdings
   - Fees card shows THIS account's fees
   - Diversification insights load per-account
   - Rebalancing status loads per-account
   - Tax status shows correct treatment for ISA
4. Click each card → drill-down loads → back button returns to cards
5. Click back from cards → returns to investment list
6. Test with GIA account (different tax treatment)
7. Test with SIPP account
8. Test with joint account (ownership % shown correctly)
9. Test with Employee Share Scheme account → specialized view loads
10. Test with Private Investment account → specialized view loads
11. Test responsive at 1024px and 768px breakpoints
12. Test with preview personas: peak_earners (joint accounts, multiple types), young_saver
