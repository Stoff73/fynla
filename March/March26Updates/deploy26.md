# Deploy Guide — 26 March 2026 (Session 11)

## Summary

Pension detail view enhancements: Holdings tab, fee display improvements, OCF input on holdings form.

## Changes

### 1. PensionDetailInline.vue — Pension Detail View
**File:** `resources/js/components/NetWorth/PensionDetailInline.vue`

**Holdings Tab (new):**
- Holdings moved from overview section into dedicated "Holdings" tab
- Tab only appears for DC pensions that have holdings
- Tab order: Overview → Holdings → Projections → Documents
- Holdings table shows: Fund Name, Type, Allocation %, Value (calculated from fund value × allocation), OCF %
- Cash (unallocated) row shown in footer when allocations < 100%
- Fee summary bar at bottom: Weighted Avg OCF + Total Annual Cost

**Fees Section (updated):**
- Platform fee now handles both percentage and fixed (£) fee types with frequency
- Display format: "0.45% p.a." for percentage, "£50.00/month" for fixed
- Advisor fee row added (shown only when set, hidden when 0)
- Total annual cost now includes advisor fee in calculation
- Annual fee impact recalculated with all fee components

**Bug fixes:**
- Fixed `font-medium font-semibold` CSS conflict on 3 elements (fund value, DB pension annual, state pension forecast)
- Replaced `text-purple-600` with `text-violet-600` for palette compliance

### 2. InlineHoldingsEditor.vue — OCF Input
**File:** `resources/js/components/Investment/InlineHoldingsEditor.vue`

- Added OCF % column to the inline holdings editor grid
- Grid layout changed from 4+2+2+3+1 to 3+2+2+2+2+1 columns
- OCF field: number input, min 0, max 5, step 0.01, placeholder "e.g. 0.23"
- `ocf_percent` added to `initHoldings()`, `addRow()`, and `stripInternal()` methods
- Backend already accepts `ocf_percent` on holding creation (RetirementController line 325)

**Note:** This change affects both pension and investment holdings forms since they share `InlineHoldingsEditor`.

## Files to Upload

```
resources/js/components/NetWorth/PensionDetailInline.vue
resources/js/components/Investment/InlineHoldingsEditor.vue
```

## Build Required

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory after building.

## SSH Cache Clear

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## No Database Changes

No migrations required. The `ocf_percent` column already exists on the `holdings` table and the `dc_pensions` fee fields were added in the previous session's migration.

## Testing Performed

- Registered new user (thomas.greenfield@test.com)
- Created 3 DC pensions:
  - **SIPP** (Hargreaves Lansdown, £130,000, £600/mo, 0.45% platform + 0.50% advisor, 2 holdings with OCF)
  - **Occupational** (Scottish Widows, £85,000, 5% employee + 8% employer on £55k salary, 0.35% platform, 2 holdings with OCF)
  - **Stakeholder** (Aviva, £42,000, £150/mo, 0.30% platform + 0.25% advisor, 1 holding with OCF)
- Verified all tabs: Overview (fees correct), Holdings (table + OCF values + weighted avg), Projections (Monte Carlo chart), Documents
- Tested edit: changed SIPP fund value and contribution, saved, verified updated values
- Verified pensions without holdings (Work Pension on preview persona) show no Holdings tab
- Verified DB and State pensions unaffected (no Holdings tab, no advisor fee row)
