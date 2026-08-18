# Independent Mortgage Liability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record and calculate mortgage liability independently from property title ownership, so a user with any property share can be solely liable for the full mortgage.

**Architecture:** Property title continues to use `Property.ownership_type` and `Property.ownership_percentage`; mortgage liability is determined only by the linked `Mortgage` record. The property form and property-facing views must never copy or derive mortgage liability from property ownership. Backend consumer services continue to use `CalculatesOwnershipShare::calculateUserMortgageShare()` as the balance source of truth.

**Tech Stack:** Laravel/PHP 8, Pest, Vue 3, Vue Test Utils, Vitest.

## Global Constraints

- `tenants_in_common` remains a property-only ownership type; mortgages support only `individual` and `joint`.
- An individual mortgage persists with `ownership_percentage = 100` and no joint borrower.
- A joint mortgage's split is set on the mortgage itself; it never inherits a property title percentage.
- Store full property values, full mortgage balances and full mortgage payments in their existing single records; calculate user shares at read time.
- Do not infer or bulk-change historical records, because property title does not establish who is legally liable for a mortgage.
- Do not deploy to any environment as part of this change.

---

## File Structure

- `app/Services/Property/MortgageService.php` — creates a mortgage from property-form data; must default mortgage liability independently of property ownership.
- `app/Http/Controllers/Api/MortgageController.php` — creates a mortgage through the standalone property-mortgage endpoint; must not inject a property's co-owner into an individual mortgage.
- `resources/js/components/NetWorth/Property/PropertyForm.vue` — captures the two independent concepts and submits existing mortgage fields without synchronising them to title ownership.
- `resources/js/components/NetWorth/PropertyCard.vue` — displays mortgage balance/equity using the mortgage's user share rather than property percentage.
- `resources/js/components/NetWorth/Property/PropertyDetailInline.vue` — displays mortgage balance and monthly payment using mortgage liability rather than property title share.
- `resources/js/components/NetWorth/Property/PropertyFinancials.vue` — calculates monthly mortgage cost from mortgage liability, while retaining property-share calculations for non-mortgage costs and rent.
- `tests/Unit/Services/MortgageServiceTest.php` — locks the property-form service contract.
- `tests/Feature/Stores/MortgageHttpIntegrationTest.php` — locks the standalone endpoint contract.
- `tests/Feature/Stores/MortgageReadConsumerParityTest.php` — locks the net-worth and estate/IHT consumers to the individual mortgage liability.
- `tests/frontend/components/NetWorth/Property/PropertyForm.test.js` — locks the form's independent state behaviour.
- `tests/frontend/components/NetWorth/Property/PropertyMortgagePresentation.test.js` — locks property-card/detail/financial presentation for a tenants-in-common property with a sole mortgage.

## Task 1: Lock the backend mortgage-liability contract

**Files:**
- Modify: `tests/Unit/Services/MortgageServiceTest.php`
- Modify: `tests/Feature/Stores/MortgageHttpIntegrationTest.php`
- Modify: `app/Services/Property/MortgageService.php`
- Modify: `app/Http/Controllers/Api/MortgageController.php`

**Interfaces:**
- Consumes: property-form fields `mortgage_ownership_type`, `mortgage_ownership_percentage`, `mortgage_joint_owner_id`, and `mortgage_joint_owner_name`.
- Produces: a `Mortgage` with independent `ownership_type`, `ownership_percentage`, `joint_owner_id`, and `joint_owner_name`.

- [ ] **Step 1: Write the failing service test for a 30/70 tenants-in-common property and a sole mortgage**

Add a Pest test to `MortgageServiceTest.php` that creates a property with `ownership_type = 'tenants_in_common'` and `ownership_percentage = 30`, then calls `createFromPropertyData()` without any `mortgage_ownership_*` values:

```php
$property = Property::factory()->create([
    'user_id' => $this->user->id,
    'ownership_type' => 'tenants_in_common',
    'ownership_percentage' => 30,
    'joint_owner_name' => 'External co-owner',
]);

$mortgage = $this->mortgageService->createFromPropertyData($property, [
    'outstanding_mortgage' => 210000,
    'mortgage_lender_name' => 'Nationwide',
    'mortgage_monthly_payment' => 1300,
], $this->user);

expect($mortgage->ownership_type)->toBe('individual')
    ->and((float) $mortgage->ownership_percentage)->toBe(100.0)
    ->and($mortgage->joint_owner_id)->toBeNull()
    ->and($mortgage->joint_owner_name)->toBeNull();
```

- [ ] **Step 2: Run the focused service test and verify the current inheritance fails it**

Run: `php artisan test tests/Unit/Services/MortgageServiceTest.php --filter=sole_mortgage`

Expected: FAIL because the current fallback normalises the property’s `tenants_in_common` type to `joint` and takes its 30% percentage.

- [ ] **Step 3: Write the failing HTTP test for an individual mortgage on a shared property**

In `MortgageHttpIntegrationTest.php`, create a tenants-in-common property with a linked or named co-owner, then POST an explicit individual mortgage. Assert the persisted record contains no co-owner fields:

```php
$response = $this->postJson("/api/properties/{$sharedProperty->id}/mortgages", [
    'lender_name' => 'Nationwide',
    'mortgage_type' => 'repayment',
    'outstanding_balance' => 210000,
    'monthly_payment' => 1300,
    'ownership_type' => 'individual',
    'ownership_percentage' => 100,
]);

$response->assertCreated();
expect(Mortgage::latest('id')->first())
    ->ownership_type->toBe('individual')
    ->and((float) Mortgage::latest('id')->first()->ownership_percentage)->toBe(100.0)
    ->and(Mortgage::latest('id')->first()->joint_owner_id)->toBeNull();
```

- [ ] **Step 4: Run the focused HTTP test and verify the endpoint currently leaks property co-owner data into the mortgage**

Run: `php artisan test tests/Feature/Stores/MortgageHttpIntegrationTest.php --filter=individual_mortgage_on_shared_property`

Expected: FAIL because `MortgageController::store()` copies `joint_owner_id` from every shared property before normalising the mortgage.

- [ ] **Step 5: Implement independent mortgage defaults in the property-form service**

In `MortgageService::createFromPropertyData()`, replace property-field fallbacks with mortgage-only defaults. Use this contract:

```php
'ownership_type' => $this->normalizeMortgageOwnershipType(
    $validated['mortgage_ownership_type'] ?? 'individual'
),
'ownership_percentage' => $validated['mortgage_ownership_percentage'] ?? 100.00,
```

After determining the type, force an individual mortgage to `ownership_percentage = 100.00` and omit both joint-owner fields. Only resolve `mortgage_joint_owner_id` / `mortgage_joint_owner_name` when `ownership_type === 'joint'`; do not fall back to `joint_owner_id` or `joint_owner_name` from the property.

- [ ] **Step 6: Implement the same boundary rule in the standalone mortgage endpoint**

In `MortgageController::store()`, delete the unconditional “Copy joint ownership from property” block. Accept and persist only ownership fields supplied for the mortgage. Keep the existing `MortgageNormaliser` and `MortgageStore` flow unchanged.

- [ ] **Step 7: Run the focused backend tests and verify both pass**

Run: `php artisan test tests/Unit/Services/MortgageServiceTest.php --filter=sole_mortgage`

Expected: PASS.

Run: `php artisan test tests/Feature/Stores/MortgageHttpIntegrationTest.php --filter=individual_mortgage_on_shared_property`

Expected: PASS.

- [ ] **Step 8: Commit the backend contract**

```bash
git add app/Services/Property/MortgageService.php app/Http/Controllers/Api/MortgageController.php tests/Unit/Services/MortgageServiceTest.php tests/Feature/Stores/MortgageHttpIntegrationTest.php
git commit -m "fix: separate mortgage liability from property ownership"
```

## Task 2: Preserve independent mortgage state in the property form

**Files:**
- Create: `tests/frontend/components/NetWorth/Property/PropertyForm.test.js`
- Modify: `resources/js/components/NetWorth/Property/PropertyForm.vue`

**Interfaces:**
- Consumes: `form.ownership_type`, `form.ownership_percentage`, `hasMortgage`, and `mortgageForm` fields.
- Produces: a mortgage form that defaults to `individual`/`100` and retains an explicitly selected joint-mortgage configuration when property title changes.

- [ ] **Step 1: Write failing component tests for independent defaults and edits**

Mount `PropertyForm` with the same Vuex stubs used by `AccountForm.test.js`. Add these tests:

```js
it('defaults a new mortgage to individual liability on a tenants-in-common property', async () => {
  const wrapper = mountForm();
  await wrapper.find('#ownership_type').setValue('tenants_in_common');
  await wrapper.find('#ownership_percentage').setValue('30');
  await wrapper.setData({ hasMortgage: true });

  expect(wrapper.vm.mortgageForm.ownership_type).toBe('individual');
  expect(wrapper.vm.mortgageForm.ownership_percentage).toBe(100);
  expect(wrapper.vm.mortgageForm.joint_owner_id).toBeNull();
});

it('does not overwrite a joint mortgage when property title percentage changes', async () => {
  const wrapper = mountForm();
  await wrapper.setData({
    hasMortgage: true,
    mortgageForm: { ...wrapper.vm.mortgageForm, ownership_type: 'joint', ownership_percentage: 50, joint_owner_id: 42 },
  });
  await wrapper.find('#ownership_percentage').setValue('70');

  expect(wrapper.vm.mortgageForm.ownership_type).toBe('joint');
  expect(wrapper.vm.mortgageForm.ownership_percentage).toBe(50);
  expect(wrapper.vm.mortgageForm.joint_owner_id).toBe(42);
});
```

- [ ] **Step 2: Run the component test and verify the current watchers fail it**

Run: `npm run test:run -- tests/frontend/components/NetWorth/Property/PropertyForm.test.js`

Expected: FAIL because the `form.ownership_type`, `hasMortgage`, `form.ownership_percentage`, `form.joint_owner_id`, and `form.joint_owner_name` watchers overwrite `mortgageForm`.

- [ ] **Step 3: Change the form copy to describe liability, not title ownership**

In the “Mortgage Ownership” section of `PropertyForm.vue`, update the heading and labels to say “Mortgage liability” and “Borrower(s)”. Keep the existing individual/joint selector and joint-borrower controls; they already collect the data required by the API. Add explanatory copy stating that this is who is legally responsible for the loan and can differ from property ownership.

- [ ] **Step 4: Remove title-to-mortgage synchronisation and establish deterministic defaults**

Remove the watchers and `populateForm()` branches that copy property ownership type, percentage, or co-owner data into `mortgageForm`. For a property with no existing mortgage, initialise only:

```js
this.mortgageForm.ownership_type = 'individual';
this.mortgageForm.ownership_percentage = 100;
this.mortgageForm.joint_owner_id = null;
this.mortgageForm.joint_owner_name = '';
this.mortgageJointOwnerSelection = '';
```

When an existing mortgage is loaded, retain the persisted mortgage values exactly. When the individual option is selected, clear joint-owner fields and reset the percentage to `100`; when joint is selected, require the existing joint-borrower input and default an unset percentage to `50`.

- [ ] **Step 5: Run the focused component tests and verify they pass**

Run: `npm run test:run -- tests/frontend/components/NetWorth/Property/PropertyForm.test.js`

Expected: PASS.

- [ ] **Step 6: Commit the form behaviour**

```bash
git add resources/js/components/NetWorth/Property/PropertyForm.vue tests/frontend/components/NetWorth/Property/PropertyForm.test.js
git commit -m "fix: keep mortgage borrowers independent of property title"
```

## Task 3: Use mortgage liability in property presentations

**Files:**
- Create: `tests/frontend/components/NetWorth/Property/PropertyMortgagePresentation.test.js`
- Modify: `resources/js/components/NetWorth/PropertyCard.vue`
- Modify: `resources/js/components/NetWorth/Property/PropertyDetailInline.vue`
- Modify: `resources/js/components/NetWorth/Property/PropertyFinancials.vue`

**Interfaces:**
- Consumes: each mortgage's `ownership_type`, `ownership_percentage`, `outstanding_balance`, and `monthly_payment`.
- Produces: user mortgage balance/payment and equity values independent of `property.ownership_percentage`.

- [ ] **Step 1: Write failing presentation tests for a 30/70 property and a sole mortgage**

Use a fixture with `property.ownership_type = 'tenants_in_common'`, `property.ownership_percentage = 30`, full property value `500000`, and a mortgage owned by the current user with `ownership_type = 'individual'`, `ownership_percentage = 100`, balance `210000`, and monthly payment `1300`. Assert:

```js
expect(card.vm.mortgageAmount).toBe(210000);
expect(card.vm.mortgageLabel).toBe('Mortgage Outstanding');
expect(card.vm.equity).toBe(-60000); // (500000 * 0.30) - 210000
expect(detail.vm.calculateUserMortgageShare(mortgage)).toBe(210000);
expect(financials.vm.userMonthlyMortgagePayments).toBe(1300);
```

Add a joint-mortgage fixture with a 50% mortgage split on the same 30/70 property and assert that the balance is `105000` and the payment is `650`. This confirms mortgage percentage—not property percentage—governs the liability.

- [ ] **Step 2: Run the focused presentation test and verify it fails under the property-share calculation**

Run: `npm run test:run -- tests/frontend/components/NetWorth/Property/PropertyMortgagePresentation.test.js`

Expected: FAIL because each current component multiplies mortgage balances or payments by `property.ownership_percentage`.

- [ ] **Step 3: Add one component-local mortgage-share helper in each property presentation**

In each component, calculate a mortgage's displayed balance/payment from that mortgage's own fields:

```js
userMortgageAmount(mortgage, field) {
  const fullAmount = Number(mortgage[field]) || 0;
  if ((mortgage.ownership_type || 'individual') === 'individual') return fullAmount;
  return fullAmount * ((Number(mortgage.ownership_percentage) || 50) / 100);
}
```

Use the helper for card mortgage amount/label/equity, detail balance and payment labels, and financials `userMonthlyMortgagePayments`. Continue using `property.ownership_percentage` only for property value, rental income, and non-mortgage property costs.

- [ ] **Step 4: Make labels disclose the liability share accurately**

Show “Mortgage outstanding” for individual liability. For joint liability, show “Your share of mortgage (X%)”, where `X` is `mortgage.ownership_percentage`, not the property percentage. Show the same liability share in the property detail/payment copy.

- [ ] **Step 5: Run the focused presentation test and verify it passes**

Run: `npm run test:run -- tests/frontend/components/NetWorth/Property/PropertyMortgagePresentation.test.js`

Expected: PASS.

- [ ] **Step 6: Commit presentation corrections**

```bash
git add resources/js/components/NetWorth/Property/PropertyCard.vue resources/js/components/NetWorth/Property/PropertyDetailInline.vue resources/js/components/NetWorth/Property/PropertyFinancials.vue tests/frontend/components/NetWorth/Property/PropertyMortgagePresentation.test.js
git commit -m "fix: calculate property mortgages from borrower liability"
```

## Task 4: Prove net-worth and estate consumers receive the full sole liability

**Files:**
- Modify: `tests/Feature/Stores/MortgageReadConsumerParityTest.php`
- Verify only: `app/Traits/CalculatesOwnershipShare.php`
- Verify only: `app/Services/Shared/CrossModuleAssetAggregator.php`
- Verify only: `app/Services/Estate/ComprehensiveEstatePlanService.php`
- Verify only: `app/Services/Estate/IHTFormattingService.php`
- Verify only: `app/Services/UserProfile/UserProfileService.php`

**Interfaces:**
- Consumes: an individual `Mortgage` attached to a shared `Property`.
- Produces: full mortgage balance in the primary borrower's net-worth, estate/IHT and monthly-commitment calculations.

- [ ] **Step 1: Write the failing consumer-parity fixture**

Add a Pest test in `MortgageReadConsumerParityTest.php` that creates a 30/70 tenants-in-common property and an `individual` mortgage of `210000` with `ownership_percentage = 100`. Assert the cross-module aggregate reports the whole balance:

```php
$total = app(CrossModuleAssetAggregator::class)->calculateMortgageTotal($user->id);
expect($total)->toBe(210000.0);
```

Call `ComprehensiveEstatePlanService` through its public plan-building method used by existing tests, then assert its mortgage liability row has `balance = 210000` and `ownership_type = 'individual'`. Invoke `IHTFormattingService::formatUserLiabilities()` through the existing reflection pattern and assert its mortgage total is `210000` with `ownership_percentage = 100`.

- [ ] **Step 2: Run the parity test before and after correcting stored record construction**

Run: `php artisan test tests/Feature/Stores/MortgageReadConsumerParityTest.php --filter=sole_mortgage_on_tenants_in_common_property`

Expected before Task 1: the fixture itself confirms the consumer trait is correct, but the browser form cannot construct this record safely. Expected after Task 1: PASS and documents the end-to-end record contract.

- [ ] **Step 3: Confirm no consumer code derives mortgage share from property ownership**

Run: `rg -n 'mortgage.*ownership_percentage|ownership_percentage.*mortgage' app/`

Expected: all user-level mortgage totals route through `calculateUserMortgageShare()` or use `Mortgage.ownership_percentage`; no backend consumer uses `Property.ownership_percentage` to split a mortgage.

- [ ] **Step 4: Run the relevant backend regression suite**

Run: `php artisan test tests/Feature/Stores/MortgageReadConsumerParityTest.php tests/Feature/Stores/MortgageHttpIntegrationTest.php tests/Unit/Services/MortgageServiceTest.php`

Expected: PASS.

- [ ] **Step 5: Run the relevant frontend regression suite and build**

Run: `npm run test:run -- tests/frontend/components/NetWorth/Property/PropertyForm.test.js tests/frontend/components/NetWorth/Property/PropertyMortgagePresentation.test.js`

Expected: PASS.

Run: `npm run build`

Expected: production build completes with no Vue compilation error.

- [ ] **Step 6: Commit regression coverage**

```bash
git add tests/Feature/Stores/MortgageReadConsumerParityTest.php
git commit -m "test: cover sole mortgage on shared property"
```
