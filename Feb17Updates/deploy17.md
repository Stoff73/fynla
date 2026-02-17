# Deploy 17 February 2026

**Status:** PENDING DEPLOYMENT

---

## Changes

### 1. Conditionally hide Rent/Utilities in Expenditure based on property ownership
- Users who own a main residence no longer see Rent and Utilities inputs in Essential Living
- Buy-to-let-only owners see Utilities with clarified hint text
- Data integrity preserved - hidden field values still saved/loaded via `allEssentialFields`

### 2. Replace Net Worth line items with donut chart on Dashboard
- Net Worth card now shows an ApexCharts donut chart with all asset and liability categories
- Donut center displays the net worth value (green if positive, red if negative)
- Asset categories use design system `ASSET_COLORS`; liabilities use red shades
- Below the chart: simple Assets/Liabilities totals summary
- Empty state unchanged

### 3. Fix savings account names on Dashboard and add `account_name` field
- `SavingsAccountResource` was returning raw `account_type` (e.g. `current_account`) as `account_name` — fixed to return actual account name
- Added `account_name` column to `savings_accounts` table (migration required)
- Added `account_name` to `SavingsAccount` model fillable
- Seeder now stores `account_name` from persona JSON data
- Dashboard `formatCashAccountName` uses `account_name` first, falls back to institution
- Updated all persona JSON files with proper account names (e.g. "David's Cash ISA", "Mitchell Premium Bonds")

### 4. Replace "Items for Review" card with Allowances Tracker on Dashboard
- Removed `ActionsOverviewCard` from Dashboard
- New Allowances card shows ISA and Pension Annual Allowance usage with progress bars
- ISA data sourced from savings + investment stores (Cash ISA + Stocks & Shares ISA)
- Pension Annual Allowance fetched via `retirement/fetchAnnualAllowance`
- Progress bar colours: green (<75%), blue (75-94%), red (95%+/over limit)
- Fixed ISA route and pension annual allowance route to handle `2025/26` tax year parameter
- Seeder now populates `isa_type`, `isa_subscription_amount`, `isa_subscription_year` on savings accounts

### 5. Spell out all acronyms in user-facing text (108 files)
- Added no-acronyms rule to CLAUDE.md (rule #11): all acronyms must be spelled out except ISA
- Replaced all acronyms across 108 Vue components, views, mixins, and persona JSON files
- Key replacements: CGT→Capital Gains Tax, IHT→Inheritance Tax, SIPP→Self-Invested Personal Pension, GIA→General Investment Account, NRB/RNRB→Nil Rate Band/Residence Nil Rate Band, MPAA→Money Purchase Annual Allowance, PCLS→Pension Commencement Lump Sum, DC/DB→Defined Contribution/Defined Benefit, SDLT→Stamp Duty Land Tax, VCT/EIS/SEIS→spelled out, NS&I→National Savings & Investments, SAYE/CSOP/EMI/RSU/ERS→spelled out
- Only user-facing text changed (template HTML, labels, headings, tooltips, display values) — variable names, CSS classes, comments left unchanged
- ISA preserved as-is throughout

### 6. Show carry forward when pension contributions exceed annual allowance
- When contributions exceed the standard £60,000 allowance, a separate Carry Forward block appears below the pension row
- Shows the excess amount used against carry forward from 3 years prior (e.g. 2022/23)
- Same visual format: label with tax year, used/limit, progress bar, remaining
- Uses `carry_forward_available` / `carry_forward` from the backend `annualAllowance` API response
- Standard pension row now capped at £60,000 (no longer shows negative remaining)
- Tax year labels dynamically calculated from current date (April 6 boundary)
- Only appears when needed — personas within the standard limit (e.g. David Mitchell) unchanged

### 7. Conditionally hide dashboard cards based on user context
- Estate Planning card hidden for users 35 or younger (e.g. James Carter age 34, John Morgan age 24)
- Estate Planning card also hidden when taxable estate is £0 — only shown when user is 36+ AND has a taxable estate or IHT liability
- Retirement card hidden for users under 35 years old (e.g. John Morgan, age 24)
- Age calculated dynamically from `currentUser.date_of_birth`; cards shown if DOB is unavailable

### 8. Fix lump sum contributions inflating monthly expenditure totals
- Investment lump sums (e.g. David Mitchell's £5,000 ISA) were being spread monthly and added to `monthly_amount`, inflating monthly and annual totals
- Lump sums now tracked separately as `lump_sum_amount` — excluded from monthly totals, included once in annual totals
- Investment display shows regular contributions as `£X/month` and lump sums as `£X lump sum`
- Expandable grid rows show lump sum annotation beneath monthly amounts when applicable

### 9. LISA splits the £20k ISA allowance
- Lifetime ISA £4,000 is a sub-allocation of the overall £20,000 ISA allowance
- When a user is LISA-eligible (under 40, no property), the ISA allowance now displays as £16,000 (not £20,000) to avoid implying £24,000 total
- Dashboard: ISA Allowance label changes to "ISA Allowance (excl. Lifetime ISA)" and bar shows £16k when LISA block is present
- Savings ISAAllowanceTracker: adds LISA section above progress bar with its own bar/bonus info; reduces ISA bar to £16k; info text explains "£20,000 total (£4,000 Lifetime ISA + £16,000 other ISAs)"
- Investment AccountSummaryPanel: ISA detail section shows £16k allowance and label "(excl. Lifetime ISA)" for eligible users
- Non-eligible users (age 40+, or owns property) see unchanged £20,000 everywhere

---

## Files Changed

| File | Change |
|------|--------|
| `resources/js/views/Dashboard.vue` | Net Worth donut chart; fix cash account name formatting; Allowances Tracker card; carry forward block for pension excess; LISA splits ISA allowance to £16k |
| `app/Http/Resources/SavingsAccountResource.php` | Return actual `account_name` instead of raw `account_type`; add `institution` field |
| `app/Models/SavingsAccount.php` | Add `account_name` to fillable |
| `database/migrations/2026_02_17_120040_add_account_name_to_savings_accounts_table.php` | Add `account_name` column |
| `database/seeders/PreviewUserSeeder.php` | Pass `account_name`; populate ISA subscription fields |
| `resources/js/data/personas/*.json` | Account names; ISA subscription data for peak_earners |
| `routes/api.php` | Fix ISA allowance and pension annual allowance routes for tax year parameter |
| `CLAUDE.md` | Add no-acronyms rule |
| `resources/js/mixins/currencyMixin.js` | Spell out account type display names |
| `resources/js/components/**/*.vue` (95 files) | Spell out all acronyms in user-facing text |
| `resources/js/views/**/*.vue` (20 files) | Spell out all acronyms in user-facing text |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Rent/utilities conditional; lump sum computed totals, annual calculation, detail display, widowed merge fix |
| `resources/js/components/UserProfile/ExpenditureExpandableGridRow.vue` | Track and display lump sum amounts in merged items and expanded rows |
| `app/Services/UserProfile/UserProfileService.php` | Separate lump sums from monthly_amount, add `lump_sum_amount`/`lump_sum_date` fields, add `annual_lump_sum` to totals |
| `resources/js/components/Savings/ISAAllowanceTracker.vue` | LISA eligibility check; reduce ISA allowance to £16k when eligible; add LISA section with progress bar and bonus info |
| `resources/js/views/Investment/AccountSummaryPanel.vue` | Dynamic ISA annual allowance (£16k/£20k) based on LISA eligibility; label clarifies exclusion |

---

## Rebuild Required

Yes - frontend Vue components changed.

```bash
./deploy/fynla-org/build.sh
```

---

## Files to Upload

1. `public/build/` (full directory after rebuild)
2. `app/Services/UserProfile/UserProfileService.php`
3. `app/Http/Resources/SavingsAccountResource.php`
4. `app/Models/SavingsAccount.php`
5. `database/migrations/2026_02_17_120040_add_account_name_to_savings_accounts_table.php`
6. `database/seeders/PreviewUserSeeder.php`
7. `routes/api.php`
8. `CLAUDE.md`

---

## Post-Upload

Run migration (new column), clear caches (including route cache for api.php changes), reseed preview users:

```bash
php artisan migrate && php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan db:seed --class=PreviewUserSeeder --force
```
