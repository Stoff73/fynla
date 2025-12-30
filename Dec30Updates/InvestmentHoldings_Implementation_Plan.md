# Investment Holdings Detail Enhancement Plan

## Summary
Enhance the Holdings tab in the Investment detail view to display complete holding data including fund names, units, prices, values, and allocations.

## Current Issues
1. **NAME column empty**: Vue uses `holding.name` but database field is `security_name`
2. **UNITS shows 0**: Vue uses `holding.units` but database field is `quantity`, and seeder doesn't populate it
3. **UNIT COST shows £0**: Vue uses `holding.unit_cost` but database field is `purchase_price`, and seeder doesn't populate it
4. **Missing columns**: No current unit price, initial value, or initial allocation columns

## Target Table Structure
| Column | Source | Calculation |
|--------|--------|-------------|
| Name | `security_name` | Direct from DB |
| Type | `asset_type` | Direct from DB |
| Units | `quantity` | Direct from DB |
| Initial Unit Cost | `purchase_price` | Direct from DB |
| Current Unit Price | `current_price` | Direct from DB |
| Initial Value | calculated | `quantity × purchase_price` |
| Current Value | `current_value` | Direct from DB |
| Initial Allocation | calculated | `initial_value / total_initial_value × 100` |
| Current Allocation | calculated | `current_value / total_current_value × 100` |

## Files to Modify

### 1. Persona JSON Files (4 files)
Add holding detail data including ticker, isin, units, prices:
- `resources/js/data/personas/young_family.json`
- `resources/js/data/personas/peak_earners.json`
- `resources/js/data/personas/widow.json`
- `resources/js/data/personas/entrepreneur.json`

**New holding structure:**
```json
{
  "holding_name": "Fundsmith Equity",
  "ticker": "FUND",
  "isin": "GB00B41YBW71",
  "asset_type": "fund",
  "units": 350.5,
  "initial_unit_cost": 85.50,
  "current_unit_price": 99.86,
  "current_value": 35000,
  "allocation_percentage": 36.8,
  "annual_fee": 0.95
}
```

### 2. PreviewUserSeeder.php
Map new JSON fields to database columns:
- `database/seeders/PreviewUserSeeder.php`

**Update Holding::create() call (around line 497):**
```php
Holding::create([
    'holdable_type' => InvestmentAccount::class,
    'holdable_id' => $investmentAccount->id,
    'security_name' => $holding['holding_name'] ?? '',
    'ticker' => $holding['ticker'] ?? null,
    'isin' => $holding['isin'] ?? null,
    'asset_type' => $holding['asset_type'] ?? 'fund',
    'quantity' => $holding['units'] ?? null,
    'purchase_price' => $holding['initial_unit_cost'] ?? null,
    'current_price' => $holding['current_unit_price'] ?? null,
    'current_value' => $holding['current_value'] ?? 0,
    'cost_basis' => ($holding['units'] ?? 0) * ($holding['initial_unit_cost'] ?? 0),
    'allocation_percent' => $holding['allocation_percentage'] ?? null,
    'ocf_percent' => $holding['annual_fee'] ?? null,
]);
```

### 3. AccountHoldingsPanel.vue
Update the holdings table with correct field mappings and new columns:
- `resources/js/views/Investment/AccountHoldingsPanel.vue`

**Changes:**
1. Fix field mappings:
   - `holding.name` → `holding.security_name`
   - `holding.units` → `holding.quantity`
   - `holding.unit_cost` → `holding.purchase_price`

2. Update table headers:
   - "Unit Cost" → "Initial Unit Cost"
   - Add "Current Unit Price" column
   - Add "Initial Value" column
   - "Value" → "Current Value"
   - "Allocation" → "Current Allocation"
   - Add "Initial Allocation" column

3. Add computed properties:
   - `getInitialValue(holding)` = `quantity × purchase_price`
   - `totalInitialValue` = sum of all initial values
   - `getInitialAllocationPercentage(holding)` = `initialValue / totalInitialValue × 100`

4. Update mobile card view with same changes

## Implementation Steps

1. **Update persona JSON files** with realistic holding data:
   - Add ticker symbols (e.g., FUND, SMT, VWRL)
   - Add ISIN codes
   - Calculate units based on current_value / current_unit_price
   - Set initial_unit_cost slightly lower than current (to show growth)

2. **Update PreviewUserSeeder.php**:
   - Add new field mappings in createInvestmentAccounts()
   - Calculate cost_basis from units × initial_unit_cost

3. **Update AccountHoldingsPanel.vue**:
   - Fix template field name references
   - Add new columns to desktop table
   - Add new computed properties for calculations
   - Update mobile card view
   - Update totals row

4. **Reseed database**:
   - Delete existing preview users
   - Run PreviewUserSeeder

## Sample Holding Data (peak_earners - David's ISA)

| Fund | Type | Units | Initial Cost | Current Price | Initial Value | Current Value | Initial Alloc | Current Alloc |
|------|------|-------|--------------|---------------|---------------|---------------|---------------|---------------|
| Fundsmith Equity | Fund | 350.5 | £85.50 | £99.86 | £29,968 | £35,000 | 37.2% | 36.8% |
| Scottish Mortgage IT | UK Equity | 2,500 | £8.40 | £10.00 | £21,000 | £25,000 | 26.1% | 26.3% |
| Vanguard FTSE All-World | ETF | 318.2 | £93.00 | £109.99 | £29,592 | £35,000 | 36.7% | 36.9% |
| **Total** | | | | | **£80,560** | **£95,000** | **100%** | **100%** |

**Allocation Calculations:**
- Initial Allocation = Initial Value / Total Initial Value × 100
- Current Allocation = Current Value / Total Current Value × 100

The slight differences between initial and current allocation show how the portfolio has drifted over time as different holdings grow at different rates.

## Testing
1. Run `php artisan db:seed --class=PreviewUserSeeder --force`
2. Login as peak_earners persona
3. Navigate to Investments → David's S&S ISA → Holdings tab
4. Verify all columns display correctly with data
