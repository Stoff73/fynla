# Integrated Investment Holdings — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow users to add holdings inline when creating/editing investment accounts, and show holdings on the account detail view.

**Architecture:** New `InlineHoldingsEditor.vue` component embedded in `AccountForm.vue`. Backend `storeAccount()` accepts optional `holdings` array in a single transaction. Account detail view gets an always-visible holdings section.

**Tech Stack:** Vue.js 3, Vuex, Laravel 10, PHP 8, MySQL 8

**Spec:** `docs/superpowers/specs/2026-03-24-integrated-investment-holdings-design.md`

---

## File Structure

| File | Action | Responsibility |
|------|--------|---------------|
| `resources/js/components/Investment/InlineHoldingsEditor.vue` | **Create** | Spreadsheet-style inline holdings editor |
| `resources/js/components/Investment/AccountForm.vue` | Modify | Embed editor, add `holdings` to allowedFields, pass holdings on save |
| `resources/js/components/NetWorth/InvestmentDetailInline.vue` | Modify | Add always-visible holdings section below performance panels |
| `app/Http/Controllers/Api/InvestmentController.php` | Modify | Accept holdings array in `storeAccount()`, create in transaction |
| `app/Http/Requests/StoreInvestmentAccountRequest.php` | Modify | Add holdings array validation rules |
| `tests/Unit/Services/Investment/InlineHoldingsTest.php` | **Create** | Backend tests for holdings-in-storeAccount |

---

### Task 1: Backend — Add Holdings Validation to StoreInvestmentAccountRequest

**Files:**

- Modify: `app/Http/Requests/StoreInvestmentAccountRequest.php:30-73` (rules method)

- [ ] **Step 1: Add holdings validation rules to `rules()` method**

In `app/Http/Requests/StoreInvestmentAccountRequest.php`, add these rules inside the `rules()` return array, after the employee share scheme spread on line 71:

```php
// Inline holdings (optional — for account types that support holdings)
'holdings' => 'sometimes|array',
'holdings.*.security_name' => 'required_with:holdings|string|max:255',
'holdings.*.asset_type' => ['required_with:holdings', Rule::in([
    'equity', 'bond', 'fund', 'etf', 'alternative',
    'uk_equity', 'us_equity', 'international_equity', 'cash', 'property',
])],
'holdings.*.allocation_percent' => 'required_with:holdings|numeric|min:0|max:100',
'holdings.*.cost_basis' => 'nullable|numeric|min:0',
```

- [ ] **Step 2: Add custom validator for total allocation**

Add a `withValidator` method to the class (after the `messages()` method, before the closing brace):

```php
/**
 * Configure the validator instance.
 */
public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        if ($this->has('holdings') && is_array($this->holdings)) {
            $totalAllocation = collect($this->holdings)->sum('allocation_percent');
            if ($totalAllocation > 100) {
                $validator->errors()->add(
                    'holdings',
                    'Total allocation percentage cannot exceed 100%.'
                );
            }
        }
    });
}
```

- [ ] **Step 3: Add custom error messages**

Add to the `messages()` method return array:

```php
'holdings.*.security_name.required_with' => 'Each holding requires a security name.',
'holdings.*.asset_type.required_with' => 'Each holding requires an asset type.',
'holdings.*.allocation_percent.required_with' => 'Each holding requires an allocation percentage.',
'holdings.*.allocation_percent.max' => 'Individual holding allocation cannot exceed 100%.',
```

- [ ] **Step 4: Verify PHP syntax**

Run: `php -l app/Http/Requests/StoreInvestmentAccountRequest.php`

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/StoreInvestmentAccountRequest.php
git commit -m "feat: add holdings array validation to StoreInvestmentAccountRequest"
```

---

### Task 2: Backend — Modify storeAccount() to Create Holdings in Transaction

**Files:**

- Modify: `app/Http/Controllers/Api/InvestmentController.php:298-395` (storeAccount method)

- [ ] **Step 1: Add DB facade import**

At the top of `InvestmentController.php`, add `use Illuminate\Support\Facades\DB;` if not already imported. Check line 35 area for existing imports.

- [ ] **Step 2: Wrap account creation in a transaction and add holdings logic**

Replace lines 367-384 in `storeAccount()` (from `$account = InvestmentAccount::create($validated);` through the cache clearing) with:

```php
// Extract holdings before creating account (not a model field)
$holdings = $validated['holdings'] ?? [];
unset($validated['holdings']);

$account = null;

DB::transaction(function () use ($validated, $holdings, &$account) {
    $account = InvestmentAccount::create($validated);

    if (!empty($holdings)) {
        $hasCashHolding = false;

        foreach ($holdings as $holdingData) {
            $currentValue = ($account->current_value * $holdingData['allocation_percent']) / 100;

            if (($holdingData['asset_type'] ?? '') === 'cash') {
                $hasCashHolding = true;
            }

            $account->holdings()->create([
                'holdable_type' => InvestmentAccount::class,
                'holdable_id' => $account->id,
                'security_name' => $holdingData['security_name'],
                'asset_type' => $holdingData['asset_type'],
                'allocation_percent' => $holdingData['allocation_percent'],
                'cost_basis' => $holdingData['cost_basis'] ?? null,
                'current_value' => $currentValue,
            ]);
        }

        // Auto-create cash holding for remainder — only if user didn't already add one
        $totalAllocated = collect($holdings)->sum('allocation_percent');
        if ($totalAllocated < 100 && !$hasCashHolding) {
            $remainderPercent = 100 - $totalAllocated;
            $account->holdings()->create([
                'holdable_type' => InvestmentAccount::class,
                'holdable_id' => $account->id,
                'security_name' => 'Cash',
                'asset_type' => 'cash',
                'allocation_percent' => $remainderPercent,
                'current_value' => ($account->current_value * $remainderPercent) / 100,
            ]);
        }
    }
});

// Clear cache
$this->investmentAgent->clearCache($user->id);

// If joint owner, clear their cache too
if (isset($validated['joint_owner_id'])) {
    $this->investmentAgent->clearCache($validated['joint_owner_id']);
}
```

Keep the existing lines 383-395 (load holdings, transform resource, return response) unchanged.

- [ ] **Step 3: Verify PHP syntax**

Run: `php -l app/Http/Controllers/Api/InvestmentController.php`

Expected: `No syntax errors detected`

- [ ] **Step 4: Verify routes still load**

Run: `php artisan route:list --path=investment/accounts | head -5`

Expected: Routes listed without errors.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/InvestmentController.php
git commit -m "feat: storeAccount accepts optional holdings array in single transaction"
```

---

### Task 3: Backend — Test Holdings Creation

**Files:**

- Create: `tests/Unit/Services/Investment/InlineHoldingsTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\Holding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('storeAccount with inline holdings', function () {
    it('creates account with holdings in single transaction', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'isa',
            'provider' => 'Vanguard',
            'current_value' => 50000,
            'holdings' => [
                [
                    'security_name' => 'Vanguard FTSE All-World',
                    'asset_type' => 'etf',
                    'allocation_percent' => 60,
                    'cost_basis' => 25000,
                ],
                [
                    'security_name' => 'iShares UK Gilts',
                    'asset_type' => 'bond',
                    'allocation_percent' => 25,
                    'cost_basis' => 12000,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $account = InvestmentAccount::where('user_id', $this->user->id)->first();
        expect($account)->not->toBeNull();
        expect($account->current_value)->toBe(50000.0);

        $holdings = $account->holdings;
        // 2 user holdings + 1 auto-created cash (15% remainder)
        expect($holdings)->toHaveCount(3);

        $etf = $holdings->where('asset_type', 'etf')->first();
        expect($etf->security_name)->toBe('Vanguard FTSE All-World');
        expect($etf->allocation_percent)->toBe(60.0);
        expect($etf->current_value)->toBe(30000.0);
        expect($etf->cost_basis)->toBe(25000.0);

        $cash = $holdings->where('asset_type', 'cash')->first();
        expect($cash->security_name)->toBe('Cash');
        expect($cash->allocation_percent)->toBe(15.0);
        expect($cash->current_value)->toBe(7500.0);
    });

    it('creates account without holdings when none provided', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'gia',
            'provider' => 'Hargreaves Lansdown',
            'current_value' => 10000,
        ]);

        $response->assertStatus(201);

        $account = InvestmentAccount::where('user_id', $this->user->id)->first();
        expect($account->holdings)->toHaveCount(0);
    });

    it('skips auto-cash when user explicitly adds a cash holding', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'isa',
            'provider' => 'AJ Bell',
            'current_value' => 20000,
            'holdings' => [
                [
                    'security_name' => 'Vanguard LifeStrategy 80',
                    'asset_type' => 'fund',
                    'allocation_percent' => 70,
                ],
                [
                    'security_name' => 'Cash Reserve',
                    'asset_type' => 'cash',
                    'allocation_percent' => 10,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $account = InvestmentAccount::where('user_id', $this->user->id)->first();
        $holdings = $account->holdings;

        // 2 user holdings only — no auto-created cash despite 20% remaining
        expect($holdings)->toHaveCount(2);
        expect($holdings->where('asset_type', 'cash')->count())->toBe(1);
        expect($holdings->where('asset_type', 'cash')->first()->security_name)->toBe('Cash Reserve');
    });

    it('rejects holdings exceeding 100% allocation', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'gia',
            'provider' => 'Interactive Investor',
            'current_value' => 30000,
            'holdings' => [
                [
                    'security_name' => 'Fund A',
                    'asset_type' => 'fund',
                    'allocation_percent' => 60,
                ],
                [
                    'security_name' => 'Fund B',
                    'asset_type' => 'fund',
                    'allocation_percent' => 50,
                ],
            ],
        ]);

        $response->assertStatus(422);
    });

    it('creates holdings with 100% allocation and no auto-cash', function () {
        $response = $this->postJson('/api/investment/accounts', [
            'account_type' => 'isa',
            'provider' => 'Fidelity',
            'current_value' => 40000,
            'holdings' => [
                [
                    'security_name' => 'Global Equity Fund',
                    'asset_type' => 'fund',
                    'allocation_percent' => 100,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $account = InvestmentAccount::where('user_id', $this->user->id)->first();
        expect($account->holdings)->toHaveCount(1);
        expect($account->holdings->first()->allocation_percent)->toBe(100.0);
    });
});
```

- [ ] **Step 2: Run the tests**

Run: `./vendor/bin/pest tests/Unit/Services/Investment/InlineHoldingsTest.php`

Expected: All 5 tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/Investment/InlineHoldingsTest.php
git commit -m "test: add inline holdings creation tests for storeAccount"
```

---

### Task 4: Frontend — Create InlineHoldingsEditor.vue

**Files:**

- Create: `resources/js/components/Investment/InlineHoldingsEditor.vue`

**Dependencies:** `currencyMixin` from `@/mixins/currencyMixin`

- [ ] **Step 1: Create the component**

Create `resources/js/components/Investment/InlineHoldingsEditor.vue` with these specifications:

**Props:**
- `accountValue` (Number, required) — the account's current_value
- `holdings` (Array, default `[]`) — existing holdings for edit mode
- `accountId` (Number, default null) — existing account ID, null for create

**Emits:**
- `update:holdings` — emits array of holding objects

**Data:**
- `localHoldings` — array of `{ id, security_name, asset_type, allocation_percent, cost_basis, _isNew }` objects. Initialised from `holdings` prop. `_isNew` flags unsaved rows.

**Computed:**
- `totalAllocated` — sum of all `allocation_percent` values
- `remainingPercent` — `100 - totalAllocated`
- `remainingValue` — `(accountValue * remainingPercent) / 100`
- `showCashWarning` — true when effective cash (explicit cash holdings + remainder) < 5%
- `canAddMore` — true when `totalAllocated < 100`

**Constant (at top of script, outside export):**
```javascript
const ASSET_TYPES = [
    { value: 'equity', label: 'Equity' },
    { value: 'uk_equity', label: 'UK Equity' },
    { value: 'us_equity', label: 'US Equity' },
    { value: 'international_equity', label: 'International Equity' },
    { value: 'fund', label: 'Fund' },
    { value: 'etf', label: 'ETF' },
    { value: 'bond', label: 'Bond' },
    { value: 'cash', label: 'Cash' },
    { value: 'alternative', label: 'Alternative' },
    { value: 'property', label: 'Property' },
];
```

**Methods:**
- `addRow()` — pushes `{ id: null, security_name: '', asset_type: '', allocation_percent: null, cost_basis: null, _isNew: true }` to `localHoldings`, emits update
- `removeRow(index)` — splices from `localHoldings`, emits update
- `onFieldChange()` — emits `update:holdings` with `localHoldings` (stripped of `_isNew`)
- `maxAllocation(index)` — returns max % this row can be (100 minus other rows' total)
- `holdingValue(holding)` — returns `(accountValue * holding.allocation_percent) / 100`

**Template structure:**
- Section wrapper with violet border, rounded corners, padding
- Header row: "Holdings" label + running total ("X% allocated, Y% remaining")
- Column headers row (small muted text): Security Name | Type | Allocation % | Amount Invested | (delete)
- For each `localHoldings` row: grid row with 5 columns:
  - Text input for `security_name` (placeholder: "e.g. Vanguard FTSE All-World")
  - Select dropdown for `asset_type` (from ASSET_TYPES)
  - Number input for `allocation_percent` (min 0, max from `maxAllocation()`, step 0.1, suffix "%")
  - Currency input for `cost_basis` (placeholder "£", nullable)
  - Delete button ("x", raspberry colour)
  - Below each row: small muted text showing calculated value: "= £X,XXX" (using `holdingValue`)
- "+ Add Holding" button (dashed violet border, full width) — disabled when `!canAddMore`
- Cash remainder row (muted background, read-only): "Cash (auto-allocated)" | remainingPercent% | remainingValue — only show when `remainingPercent > 0`
- Warning banner (violet background) when `showCashWarning`: "At least 5% cash is advised — return-producing assets may need to be sold to cover fees"

**Watch:**
- Watch `holdings` prop: when it changes (edit mode loading), update `localHoldings`

**Styling:** Use Tailwind classes consistent with existing form components. Match the design system palette (violet for borders/focus, horizon for text, raspberry for delete, eggshell for muted backgrounds). No scoped styles needed.

- [ ] **Step 2: Verify the file compiles**

Check the dev server terminal (`./dev.sh`) for compilation errors. The component won't render yet since nothing imports it, but Vite should not report syntax errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Investment/InlineHoldingsEditor.vue
git commit -m "feat: create InlineHoldingsEditor component for spreadsheet-style holding entry"
```

---

### Task 5: Frontend — Embed InlineHoldingsEditor in AccountForm

**Files:**

- Modify: `resources/js/components/Investment/AccountForm.vue`

Key reference points in AccountForm.vue:
- Line 95-110: StandardInvestmentFields component (insert InlineHoldingsEditor after this block)
- Line 138-157: Script imports and components registration
- Line 179: `data()` method
- Line 857: `allowedFields` array in `submitForm()`
- Line 906: End of allowedFields array

- [ ] **Step 1: Add the import and register the component**

After line 142 (`import StandardInvestmentFields from './StandardInvestmentFields.vue';`), add:

```javascript
import InlineHoldingsEditor from './InlineHoldingsEditor.vue';
```

Add `InlineHoldingsEditor` to the `components` object (line 153-157 area):

```javascript
components: {
    PrivateInvestmentFields,
    EmployeeShareSchemeFields,
    StandardInvestmentFields,
    InlineHoldingsEditor,
},
```

- [ ] **Step 2: Add the HOLDABLE_ACCOUNT_TYPES constant**

Above the `export default` (after imports, before the component definition), add:

```javascript
const HOLDABLE_ACCOUNT_TYPES = ['isa', 'gia', 'onshore_bond', 'offshore_bond', 'vct', 'eis'];
```

- [ ] **Step 3: Add `holdings` to form data**

In `data()` method (line 179 area), inside the `formData` object, add:

```javascript
holdings: [],
```

- [ ] **Step 4: Add computed property for showing holdings**

In the `computed` section, add:

```javascript
showHoldingsEditor() {
    return HOLDABLE_ACCOUNT_TYPES.includes(this.formData.account_type)
        && parseFloat(this.formData.current_value) > 0;
},
```

- [ ] **Step 5: Add template for InlineHoldingsEditor**

**CRITICAL:** The `InlineHoldingsEditor` must be placed OUTSIDE the `v-if="!isPrivateInvestmentType && !isEmployeeShareScheme"` block. This is because `vct` and `eis` are classified as `isPrivateInvestmentType` in the existing code (line 370-372), but they ARE holdable account types per the spec. The holdings editor uses its own `showHoldingsEditor` computed to control visibility.

Place the editor AFTER all three field components (StandardInvestmentFields, PrivateInvestmentFields, EmployeeShareSchemeFields) — just before the closing `</div>` of the form body (line 112). This ensures it renders independently of which field component is active:

```html
<!-- Inline Holdings Editor (for eligible account types with value entered) -->
<!-- Placed outside field component conditionals because VCT/EIS are
     classified as isPrivateInvestmentType but still support holdings -->
<InlineHoldingsEditor
    v-if="showHoldingsEditor"
    :account-value="parseFloat(formData.current_value) || 0"
    :holdings="formData.holdings"
    :account-id="account?.id || null"
    @update:holdings="formData.holdings = $event"
    @open-holding-details="openHoldingDetails"
/>
```

Also add the HoldingForm modal for edit-mode "Details" links. Place it after the closing `</form>` tag (line 132), before the closing `</div>` of the modal panel:

```html
<!-- Holding Detail Modal (opened from InlineHoldingsEditor "Details" link) -->
<HoldingForm
    v-if="showHoldingDetailModal"
    :show="showHoldingDetailModal"
    :holding="editingHoldingDetail"
    :accounts="account ? [account] : []"
    :default-account-id="account?.id"
    @close="showHoldingDetailModal = false; editingHoldingDetail = null"
    @save="handleHoldingDetailSave"
/>
```

- [ ] **Step 5b: Add HoldingForm import, data properties, and methods**

Add `HoldingForm` import after the `InlineHoldingsEditor` import:

```javascript
import HoldingForm from './HoldingForm.vue';
```

Add `HoldingForm` to `components`:

```javascript
components: {
    PrivateInvestmentFields,
    EmployeeShareSchemeFields,
    StandardInvestmentFields,
    InlineHoldingsEditor,
    HoldingForm,
},
```

Add to `data()`:

```javascript
showHoldingDetailModal: false,
editingHoldingDetail: null,
```

Add to `methods`:

```javascript
openHoldingDetails(holding) {
    this.editingHoldingDetail = holding;
    this.showHoldingDetailModal = true;
},

async handleHoldingDetailSave(holdingData) {
    // For persisted holdings, save via API
    if (holdingData.id) {
        try {
            await this.$store.dispatch('investment/updateHolding', {
                id: holdingData.id,
                data: holdingData,
            });
            // Refresh the holdings in formData from the updated account
            await this.$store.dispatch('investment/fetchInvestmentData');
        } catch (error) {
            console.error('Failed to update holding:', error);
        }
    }
    this.showHoldingDetailModal = false;
    this.editingHoldingDetail = null;
},
```

- [ ] **Step 6: Add `holdings` to allowedFields**

In `submitForm()`, find the `allowedFields` array (line 857). Add `'holdings'` to the end of the array, before the closing bracket `]` (line 904 area):

```javascript
'leaver_category', 'post_termination_exercise_days', 'termination_date', 'leaver_notes',
// Inline holdings
'holdings',
```

- [ ] **Step 7: Fix the MissingValue object filter to not strip holdings array**

The filter on lines 912-916 deletes any value that is a non-null, non-array object. The `holdings` array is already safe (it IS an array), but verify the filter handles this correctly. The existing check `!Array.isArray(submitData[key])` already protects arrays. No change needed — just verify.

- [ ] **Step 8: Initialise holdings from existing account in edit mode (BOTH watchers)**

There are TWO watchers that populate `formData` from the account. BOTH must be updated.

**Watcher 1: `account` watcher (line 624-649)**

Inside the `if (newAccount)` block (after line 645's `planned_lump_sum_date` assignment), add:

```javascript
// Load existing holdings for edit mode
if (newAccount.holdings?.length) {
    this.formData.holdings = newAccount.holdings
        .filter(h => h.asset_type !== 'cash') // Don't show auto-created cash holdings
        .map(h => ({
            id: h.id,
            security_name: h.security_name,
            asset_type: h.asset_type,
            allocation_percent: h.allocation_percent,
            cost_basis: h.cost_basis,
        }));
} else {
    this.formData.holdings = [];
}
```

**Watcher 2: `show` watcher (line 651-684)**

Inside the `if (this.account)` block (after line 671's `planned_lump_sum_date` assignment), add the same holdings initialisation:

```javascript
// Load existing holdings for edit mode
if (this.account.holdings?.length) {
    this.formData.holdings = this.account.holdings
        .filter(h => h.asset_type !== 'cash')
        .map(h => ({
            id: h.id,
            security_name: h.security_name,
            asset_type: h.asset_type,
            allocation_percent: h.allocation_percent,
            cost_basis: h.cost_basis,
        }));
} else {
    this.formData.holdings = [];
}
```

Also ensure `resetForm()` resets `holdings: []`.

- [ ] **Step 9: Verify compilation**

Check dev server terminal for errors. Open browser, navigate to Investments, click "Add Account", select ISA, enter a value, and verify the holdings section appears.

- [ ] **Step 10: Commit**

```bash
git add resources/js/components/Investment/AccountForm.vue
git commit -m "feat: embed InlineHoldingsEditor in AccountForm for eligible account types"
```

---

### Task 6: Frontend — Add Always-Visible Holdings Section to Account Detail View

**Files:**

- Modify: `resources/js/components/NetWorth/InvestmentDetailInline.vue`

Key reference points:
- Line 128-190: Standard tab content area (where AccountHoldingsPanel, etc. render)
- Line 190: Closing `</div>` of the tab content area
- Line 193-219: Edit modal, delete confirm, and HoldingForm modal
- Line 224-257: Script imports and components
- Line 283-294: `detailComponentType` computed

- [ ] **Step 1: Add the always-visible holdings section**

After the closing `</div>` of the standard tab content area (line 190) and before the Edit Modal comment (line 193), add a new section. This goes OUTSIDE the tab content `<div>` so it's always visible regardless of active tab:

```html
<!-- Always-visible Holdings Section (for holdable standard accounts only) -->
<div v-if="isHoldableAccountType" class="bg-white rounded-lg shadow-md p-4 sm:p-6">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-horizon-500">Holdings</h3>
        <button
            v-preview-disabled="'add'"
            @click="openHoldingModal(null)"
            class="text-sm text-violet-600 hover:text-violet-700 font-medium"
        >
            + Add Holding
        </button>
    </div>

    <!-- Holdings list -->
    <div v-if="account.holdings && account.holdings.length > 0">
        <!-- Header row -->
        <div class="grid grid-cols-12 gap-2 text-xs text-neutral-500 font-medium pb-2 border-b border-light-gray mb-2">
            <div class="col-span-4 sm:col-span-5">Security</div>
            <div class="col-span-2">Type</div>
            <div class="col-span-2 text-right">Allocation</div>
            <div class="col-span-2 text-right">Value</div>
            <div class="col-span-2 sm:col-span-1 text-right"></div>
        </div>

        <!-- Holding rows -->
        <div
            v-for="holding in account.holdings"
            :key="holding.id"
            class="grid grid-cols-12 gap-2 items-center py-2 border-b border-light-gray last:border-b-0 text-sm"
        >
            <div class="col-span-4 sm:col-span-5 font-medium text-horizon-500 truncate">
                {{ holding.security_name }}
            </div>
            <div class="col-span-2 text-neutral-500 capitalize text-xs">
                {{ formatAssetType(holding.asset_type) }}
            </div>
            <div class="col-span-2 text-right text-horizon-500">
                {{ holding.allocation_percent }}%
            </div>
            <div class="col-span-2 text-right text-horizon-500 font-medium">
                {{ formatCurrency(holding.current_value) }}
            </div>
            <div class="col-span-2 sm:col-span-1 text-right">
                <button
                    @click="openHoldingModal(holding)"
                    class="text-xs text-violet-600 hover:text-violet-700 hover:underline"
                >
                    Details
                </button>
            </div>
        </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-6 text-neutral-500">
        <p class="text-sm">No holdings — default allocation is 100% cash</p>
        <button
            v-preview-disabled="'add'"
            @click="openHoldingModal(null)"
            class="mt-2 text-sm text-violet-600 hover:text-violet-700 font-medium"
        >
            Add your first holding
        </button>
    </div>
</div>
```

- [ ] **Step 2: Add `isHoldableAccountType` computed property**

In the `computed` section of `InvestmentDetailInline.vue`, add:

```javascript
isHoldableAccountType() {
    const holdableTypes = ['isa', 'gia', 'onshore_bond', 'offshore_bond', 'vct', 'eis'];
    return holdableTypes.includes(this.account.account_type);
},
```

This ensures the holdings section only appears for account types that support holdings — not for `nsi`, `other`, or employee share schemes that happen to render as `'standard'` detail type.

- [ ] **Step 3: Add the `formatAssetType` helper method**

In the `methods` section of the component, add:

```javascript
formatAssetType(type) {
    const labels = {
        equity: 'Equity',
        uk_equity: 'UK Equity',
        us_equity: 'US Equity',
        international_equity: 'Intl Equity',
        fund: 'Fund',
        etf: 'ETF',
        bond: 'Bond',
        cash: 'Cash',
        alternative: 'Alternative',
        property: 'Property',
    };
    return labels[type] || type;
},
```

- [ ] **Step 4: Verify the `openHoldingModal` method already exists**

Check the existing code — `InvestmentDetailInline.vue` already has `openHoldingModal` (used by AccountHoldingsPanel). It should accept a holding object (for edit) or null (for add). The existing `HoldingForm` modal at lines 212-219 already handles this. No changes needed to the modal infrastructure.

- [ ] **Step 5: Verify compilation and test visually**

Check dev server for errors. Navigate to an investment account detail view (e.g. Hargreaves Lansdown ISA). The new "Holdings" section should appear below the existing panels.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/NetWorth/InvestmentDetailInline.vue
git commit -m "feat: add always-visible holdings section to investment account detail view"
```

---

### Task 7: Integration Testing — Full Flow in Browser

**Files:** No new files. This is manual verification.

- [ ] **Step 1: Seed the database**

Run: `php artisan db:seed`

- [ ] **Step 2: Test creating an account with holdings**

1. Login as `chris@fynla.org` / `Password1!` (ask user for verification code)
2. Navigate to Investments (`/net-worth/investments`)
3. Click "Add Account"
4. Select "ISA (Stocks & Shares)"
5. Enter provider: "Test Provider"
6. Enter current value: 100000
7. Verify the Holdings section appears below the value field
8. Click "+ Add Holding"
9. Enter: Security = "Vanguard Global All Cap", Type = "Fund", Allocation = 60%
10. Click "+ Add Holding" again
11. Enter: Security = "iShares Core UK Gilts", Type = "Bond", Allocation = 25%
12. Verify running total shows "85% allocated, 15% remaining (£15,000)"
13. Verify Cash remainder row shows "Cash (auto-allocated) 15% — £15,000"
14. Click Save
15. Verify account is created with 3 holdings (2 user + 1 auto-cash)

- [ ] **Step 3: Test the account detail holdings section**

1. Click on the newly created account
2. Verify the always-visible "Holdings" section appears
3. Verify all 3 holdings are listed with correct names, types, allocations, and values
4. Click "Details" on one holding — verify HoldingForm modal opens pre-populated
5. Close the modal

- [ ] **Step 4: Test validation — exceeding 100%**

1. Click "Add Account" again
2. Select GIA, enter provider and value
3. Add a holding with 60%
4. Add another holding — try entering 50% (would total 110%)
5. Verify the input is capped/prevented at 40% max
6. Verify the "+ Add Holding" button disables when at 100%

- [ ] **Step 5: Test cash warning**

1. Add holdings totalling 98% allocation
2. Verify the warning appears: "At least 5% cash is advised..."
3. Adjust to 95% — verify warning disappears (5% cash remainder)

- [ ] **Step 6: Test edit mode**

1. Click on an existing account with holdings
2. Click "Edit"
3. Verify existing holdings appear in the inline editor
4. Modify an allocation %
5. Save — verify changes persist

- [ ] **Step 7: Test onboarding context**

1. Navigate to `/onboarding`
2. Progress to the Assets/Investments step
3. Click "+ Add Investment Account"
4. Select ISA, enter details and value
5. Verify the inline holdings editor appears
6. Add a holding and save
7. Verify it works in the onboarding inline context (no modal wrapper)

- [ ] **Step 8: Test ineligible account types**

1. Click "Add Account"
2. Select "Private Company" — verify NO holdings section appears
3. Select "SAYE" — verify NO holdings section appears
4. Select "ISA" — verify holdings section appears after entering a value

- [ ] **Step 9: Seed the database (final)**

Run: `php artisan db:seed`

---

### Task 8: Cleanup and Final Commit

- [ ] **Step 1: Run full Pest test suite**

Run: `./vendor/bin/pest`

Expected: All tests pass (existing + new inline holdings tests).

- [ ] **Step 2: Run PHP code formatter**

Run: `./vendor/bin/pint`

- [ ] **Step 3: Final commit if any formatting changes**

```bash
git add -A
git commit -m "style: apply PSR-12 formatting"
```

- [ ] **Step 4: Update TODO.md**

Add the completed work to TODO.md under a new "Completed" section.
