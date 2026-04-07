# Bug Fix Plan — v0.9.4

**Date**: 7 April 2026
**Sources**: QA Report (6 April, preview personas) + Brett Account Test Report (6 April, production)
**Branch**: `bugs` (from `main` at `228b38f`)

---

## Bug Inventory

12 bugs identified across both reports, deduplicated and prioritised. 5 UX observations noted for future work.

| # | Bug | Source | Priority | Severity | Est. Complexity |
|---|-----|--------|----------|----------|----------------|
| 1 | PA taper not applied in detailed tax breakdown | QA | P1 | High | Medium |
| 2 | Investment account deletion fails silently | Brett | P1 | High | Low-Medium |
| 3 | Property delete doesn't cascade to mortgage | Brett | P1 | High | Low |
| 4 | Dividend basic rate 10.75% — verify if correct for 2026/27 | Brett | P2 | Medium | Trivial |
| 5 | Fyn AI can't retrieve mortgage interest rate | Brett | P2 | Medium | Low |
| 6 | Fyn AI references "2025/26" instead of "2026/27" | Brett + QA | P2 | Low | Trivial |
| 7 | Joint badge contradicts 100% ownership | Brett | P2 | Medium | Low |
| 8 | Rental income labels inconsistent (net vs gross) | QA + Brett | P3 | Low | Low |
| 9 | regular_savings not shown as expenditure category | QA | P3 | Low | Low |
| 10 | Alex Chen missing dividend income (preview data) | QA | P3 | Low | Trivial |
| 11 | Harold Bennett estate empty state on dashboard | QA | P3 | Low | Low |
| 12 | Cash vs Savings card discrepancy on dashboard | QA | P3 | Low | Low |

---

## P1 — Critical Fixes

### BUG-1: Personal Allowance Taper Not Applied in Detailed Tax Breakdown

**Impact**: Understates tax by ~£5,656 for anyone earning above £125,140. Affects 3 of 9 preview personas (Alex Chen, David Mitchell, Sarah Mitchell) and any real user with high income.

**Root Cause**: `UKTaxCalculator::calculateDetailedNetIncome()` creates `TaxBandTracker` without first tapering the Personal Allowance. The taper logic exists in the older `calculateIncomeTax()` method (lines 609-616) but is not used by the new detailed breakdown.

`TaxBandTracker` constructor takes the PA directly from tax config (`$taxConfig['personal_allowance']`) without checking if it should be tapered.

**Files to fix**:
- `app/Services/UKTaxCalculator.php` — `calculateDetailedNetIncome()` (~line 32-45)
- `app/Services/TaxBandTracker.php` — constructor (~line 31-43)

**Fix approach**:
1. In `calculateDetailedNetIncome()`, calculate the tapered PA before creating `TaxBandTracker`:
   ```
   $taperThreshold = $taxConfig['taper_threshold'] ?? 100000;
   $personalAllowance = $taxConfig['personal_allowance'];
   if ($totalIncomePre > $taperThreshold) {
       $excess = $totalIncomePre - $taperThreshold;
       $reduction = floor($excess / 2);
       $personalAllowance = max(0, $personalAllowance - $reduction);
   }
   ```
2. Pass the tapered PA into `TaxBandTracker` instead of the raw config value
3. Ensure the `personal_allowance_used` field in the response reflects the tapered amount (so the frontend displays £0 correctly)

**Verification**: Check all 9 preview personas. The 3 high-income personas should show PA = £0 and tax matching the QA report's "Correct Tax" column.

---

### BUG-2: Investment Account Deletion Fails Silently

**Impact**: Users cannot delete investment accounts. Confirmation dialog appears, user confirms, dialog dismisses, but account remains.

**Root Cause (investigation needed)**: The InvestmentAccount model uses SoftDeletes. The controller `destroyAccount()` at `InvestmentController.php:623-650` calls `$account->delete()` which should soft-delete. The agent investigation found the implementation looks correct, so the issue may be:
- A frontend error being swallowed (no error toast shown)
- The API returning a non-success status that the frontend ignores
- A policy/authorization check failing silently
- Holdings foreign key preventing soft-delete

**Files to investigate**:
- `app/Http/Controllers/Api/InvestmentController.php` — `destroyAccount()` (~line 623)
- `resources/js/components/NetWorth/InvestmentList.vue` — `handleAccountDeleted()` (~line 404)
- `resources/js/store/modules/investment.js` — delete action (~line 495)

**Fix approach**:
1. Add logging to `destroyAccount()` to trace execution
2. Check if holdings with foreign keys to the account block soft-delete
3. Test the DELETE API endpoint directly (curl/Postman) to isolate frontend vs backend
4. If backend succeeds but frontend doesn't refresh, fix the Vue component's reload after delete
5. Add error toast if the API returns an error

**Verification**: On Brett's account (or a test account), add a test investment, then delete it. Confirm it disappears on page refresh.

---

### BUG-3: Property Delete Doesn't Cascade to Mortgage

**Impact**: Orphaned mortgages inflate liabilities and corrupt net worth.

**Root Cause (likely false positive)**: The database schema has `ON DELETE CASCADE` on the `mortgages.property_id` foreign key. The `PropertyController::destroy()` method explicitly comments that mortgages will cascade. HOWEVER — the Property model uses SoftDeletes. A soft delete sets `deleted_at` but does NOT trigger the SQL CASCADE (CASCADE only fires on hard DELETE).

**Files to fix**:
- `app/Http/Controllers/Api/PropertyController.php` — `destroy()` (~line 299-320)

**Fix approach**:
1. In `PropertyController::destroy()`, explicitly delete associated mortgages before soft-deleting the property:
   ```php
   $property->mortgages()->delete(); // Soft-deletes all linked mortgages
   $property->delete(); // Soft-deletes the property
   ```
2. Alternatively, add an Observer on Property that cascades soft-deletes to mortgages on the `deleting` event

**Verification**: Add a test property with mortgage, delete the property, confirm the mortgage is also removed from the liabilities page and dashboard.

---

## P2 — Medium Fixes

### BUG-4: Dividend Basic Rate — Verify 10.75% vs 8.75%

**Context**: The 2026/27 TaxConfigurationSeeder has `dividend_tax.basic_rate = 0.1075` with comment "+2pp on basic and higher rates". This was an intentional change in the seeder.

**Action**: Verify against HMRC 2026/27 rates. If HMRC confirms 8.75% (no change from 2025/26), update the seeder. If the +2pp increase is correct per the Autumn Budget 2025, mark as NOT A BUG.

**File**: `database/seeders/TaxConfigurationSeeder.php` (~line 1246)

**Fix (if confirmed wrong)**:
```php
$config['dividend_tax']['basic_rate'] = 0.0875;   // 8.75%
$config['dividend_tax']['higher_rate'] = 0.3375;   // 33.75%
```
Then reseed: `php artisan db:seed --class=TaxConfigurationSeeder --force`

---

### BUG-5: Fyn AI Can't Retrieve Mortgage Interest Rate

**Impact**: Fyn tells users "no interest rate recorded" when the data exists.

**Root Cause**: `CoordinatingAgent::handleListRecords()` has no case for `mortgage` entity type. Mortgages are only accessible indirectly via `get_module_analysis(estate)`, and even then the interest rate may not be in the response fields.

**Files to fix**:
- `app/Agents/CoordinatingAgent.php` — `handleListRecords()` method
- `app/Services/AI/AiToolDefinitions.php` — add `mortgage` to the `entity_type` enum for `list_records`
- `app/Services/AI/XaiToolDefinitions.php` — same

**Fix approach**:
1. Add a `mortgage` case to `handleListRecords()`:
   ```php
   'mortgage' => Mortgage::where('user_id', $userId)
       ->orWhereHas('property', fn($q) => $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId))
       ->get()
       ->map(fn($m) => [
           'id' => $m->id,
           'property' => $m->property->address_line_1 ?? 'Unknown',
           'lender' => $m->lender,
           'outstanding_balance' => $m->outstanding_balance,
           'interest_rate' => $m->interest_rate,
           'monthly_payment' => $m->monthly_payment,
           'term_remaining_months' => $m->remaining_term_months,
           'mortgage_type' => $m->mortgage_type,
       ]),
   ```
2. Add `mortgage` to the tool definition enum

---

### BUG-6: Fyn AI References "2025/26" Instead of "2026/27"

**Impact**: Minor confusion — tax rules are looked up correctly via tools, but the caveat text says the wrong year.

**Root Cause**: Hardcoded string in `ComplianceRules.php` line 37.

**File to fix**: `app/Services/AI/Prompts/ComplianceRules.php`

**Fix approach**: Make the tax year dynamic by injecting it from `TaxConfigService`:
1. Change `ComplianceRules::get()` to accept a `string $taxYear` parameter
2. Replace the hardcoded "2025/26" with `{$taxYear}`
3. In `SystemPromptBuilder::build()`, pass `$this->taxConfig->getTaxYear()` to `ComplianceRules::get()`

---

### BUG-7: Joint Badge Contradicts 100% Ownership

**Impact**: Confusing UI — shows "Joint" badge AND "Your Share (100.00%)".

**Root Cause**: The badge renders when `ownership_type === 'joint'`, but the account has `ownership_percentage = 100`. This is likely a data issue — the account was created with `ownership_type: 'joint'` but no `joint_owner_id` or with 100% share, which is contradictory.

**Files to fix**:
- `resources/js/components/NetWorth/InvestmentList.vue` (~line 56-61) — add guard: only show Joint badge if `ownership_percentage < 100`
- OR fix the data: if `ownership_percentage === 100` and no `joint_owner_id`, set `ownership_type` to `individual`

**Fix approach**: Both — fix the display logic AND add a backend validation rule:
1. Frontend: `v-if="account.ownership_type === 'joint' && account.ownership_percentage < 100"`
2. Backend: In InvestmentAccount validation, if `ownership_percentage === 100`, force `ownership_type` to `individual`

---

## P3 — Low Priority

### BUG-8: Rental Income Labels Inconsistent

**Impact**: Same page shows different rental income figures (e.g. £14,290 vs £27,000) without labelling which is gross and which is net.

**Fix**: Add labels to the income page. Tax section: "Net rental income (after expenses)". Definitions section: "Gross rental income".

**File**: `resources/js/components/UserProfile/TaxIncomeCard.vue` — rental income rows

---

### BUG-9: regular_savings Not Shown as Expenditure Category

**Impact**: Store total includes regular_savings but the expenditure page doesn't display it as a line item. Data is correct, display is incomplete.

**Fix**: Add `regular_savings` as a visible category row in the expenditure breakdown, under a "Savings & Investments" or "Other" section.

**File**: `resources/js/components/UserProfile/ExpenditureForm.vue`

---

### BUG-10: Alex Chen Missing Dividend Income

**Impact**: Preview persona missing £60,000 dividend income specified in reference data.

**Fix**: Update `PreviewUserSeeder` to add `annual_dividend_income: 60000` for Alex Chen.

**File**: `database/seeders/PreviewUserSeeder.php`

---

### BUG-11: Harold Bennett Estate Empty State

**Impact**: Dashboard shows "Add your assets" despite having £437,250 in assets. Estate widget may not render for individual views of joint personas.

**Fix**: Check estate widget's data query — likely filtering by `user_id` only and missing joint assets. Add `orWhere('joint_owner_id', $userId)` or check if estate module configuration needs explicit setup.

**Files**: Estate dashboard widget component + backend aggregator

---

### BUG-12: Cash vs Savings Card Discrepancy

**Impact**: Dashboard "Cash & Savings" card shows different total than net worth "Cash" category.

**Fix**: Align the two by using the same aggregation, or add a label clarifying the difference (e.g. "Cash & Savings (inc. Cash ISAs)").

**Files**: Dashboard component + Net Worth widget

---

## UX Observations (Future Work, Not This Sprint)

| # | Observation | Source |
|---|-------------|--------|
| UX-1 | Fyn chat panel obscures edit/delete buttons on detail pages | Brett |
| UX-2 | Liabilities page shows full joint amounts vs dashboard user share | Brett |
| UX-3 | No success toast on add/delete actions | Brett |
| UX-4 | Investment page needs scroll hint for 4+ accounts | Brett |
| UX-5 | Monthly shortfall not prominently flagged on dashboard | Brett |
| UX-6 | Section 24 tax credit needs tooltip explanation | QA |
| UX-7 | Spouse view toggle should be more prominent | QA |
| UX-8 | Expenditure budget tabs need brief explainer text | QA |
| UX-9 | Threshold/Adjusted Income definitions need tooltips | QA |
| UX-10 | Negative disposable income needs visual indicator | QA |

---

## Execution Order

**Sprint 1 — P1 fixes (do first, test thoroughly)**:
1. BUG-1: PA taper fix (backend calculation)
2. BUG-3: Property cascade soft-delete fix
3. BUG-2: Investment delete investigation + fix

**Sprint 2 — P2 fixes**:
4. BUG-4: Verify dividend rate against HMRC
5. BUG-6: Dynamic tax year in Fyn AI prompt
6. BUG-5: Add mortgage to Fyn AI list_records
7. BUG-7: Joint badge display logic fix

**Sprint 3 — P3 fixes**:
8. BUG-8: Rental income labels
9. BUG-9: regular_savings display
10. BUG-10: Alex Chen dividend data
11. BUG-11: Harold Bennett estate widget
12. BUG-12: Cash vs Savings alignment

---

## Testing Strategy

After each sprint:
1. Reseed database: `php artisan db:seed`
2. Run Pest tests: `./vendor/bin/pest`
3. Browser test all 9 preview personas (tax values, net worth, expenditure)
4. Browser test Brett's account for delete flows
5. Test Fyn AI with mortgage rate and tax year questions

---

*Plan generated from QA Report + Brett Test Report, 7 April 2026*
