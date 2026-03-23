# Onboarding "Add & View Cards" Pattern — Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Make Property, Investment, and Pension onboarding steps show saved items as cards with "add another" capability, and pre-fill property with address from About You. All data via DB (save on add, fetch on mount).

**Architecture:** Wrap each existing form component in a new step component that handles the list/add toggle pattern. Follows the existing SimpleSavingsAccountStep pattern: fetch from DB on mount, show cards, show form on "add", save to DB, refresh cards.

**Tech Stack:** Vue 3 (Composition API), Vuex, existing API services

---

## File Structure

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `resources/js/components/Onboarding/steps/PropertyStep.vue` | Property cards + add form (wraps PropertyForm) |
| Create | `resources/js/components/Onboarding/steps/InvestmentStep.vue` | Investment cards + add form (wraps AccountForm) |
| Create | `resources/js/components/Onboarding/steps/PensionStep.vue` | Pension cards + add form (wraps DCPensionForm) |
| Modify | `resources/js/components/Onboarding/OnboardingWizard.vue` | Update step component mappings to use new step components |

---

### Task 1: PropertyStep.vue — Property Cards with Address Pre-fill

**Files:**
- Create: `resources/js/components/Onboarding/steps/PropertyStep.vue`
- Modify: `resources/js/components/Onboarding/OnboardingWizard.vue` (step mapping)

- [ ] **Step 1: Create PropertyStep.vue**

Template structure (follows SimpleSavingsAccountStep pattern):
```
<OnboardingStep>
  <!-- Saved properties as cards -->
  <div v-if="properties.length > 0 && !showForm">
    <div v-for="property in properties" :key="property.id" class="card">
      <!-- Badge: Main Residence / Buy to Let -->
      <!-- Address line -->
      <!-- Value, Equity, Mortgage balance -->
      <!-- Edit / Remove buttons -->
    </div>
    <button @click="showForm = true">+ Add Another Property</button>
  </div>

  <!-- PropertyForm (inline, context=onboarding) -->
  <div v-if="showForm || properties.length === 0">
    <PropertyForm
      :context="'onboarding'"
      :pre-fill-address="prefillAddress"
      :pre-fill-type="properties.length === 0 ? 'main_residence' : null"
      @save="handlePropertySaved"
      @close="showForm = false"
    />
  </div>
</OnboardingStep>
```

Script logic:
- `onMounted`: Fetch from DB via `propertyService.getProperties()` → populate `properties` ref
- `onMounted`: Fetch user address from `store.getters['userProfile/personalInfo']` → build `prefillAddress` object
- First property auto-sets type to `main_residence` with pre-filled address
- `handlePropertySaved(data)`: Call `propertyService.createProperty(data)` → refresh properties from DB → set `showForm = false`
- Card shows: property type badge, address, current value, mortgage balance (if any), equity

- [ ] **Step 2: Update PropertyForm.vue to accept pre-fill props**

Add props to PropertyForm.vue:
- `preFillAddress`: Object `{ address_line_1, city, county, postcode }` — pre-populates address fields
- `preFillType`: String — pre-selects property type dropdown

In `created`/`mounted`: if props provided, merge into form data.

- [ ] **Step 3: Update OnboardingWizard.vue step mapping**

Change the component mapping for property steps:
```javascript
// Before:
'property-mortgage': () => import('@/components/NetWorth/Property/PropertyForm.vue'),
'property-portfolio': () => import('@/components/NetWorth/Property/PropertyForm.vue'),

// After:
'property-mortgage': () => import('@/components/Onboarding/steps/PropertyStep.vue'),
'property-portfolio': () => import('@/components/Onboarding/steps/PropertyStep.vue'),
```

Also update the `handleLifeStageStepSave` to NOT handle property saves (PropertyStep handles its own saves to DB).

- [ ] **Step 4: Test in browser**
- Register new user, select "Protecting What Matters"
- Fill About You with address
- Reach Property step → address should be pre-filled, type should be Main Residence
- Fill value + mortgage → Save → Card appears
- Click "Add Another Property" → Empty form for second property
- Save second property → Two cards shown
- Click Continue → Both properties persist in DB

- [ ] **Step 5: Commit**

---

### Task 2: InvestmentStep.vue — Investment Account Cards

**Files:**
- Create: `resources/js/components/Onboarding/steps/InvestmentStep.vue`
- Modify: `resources/js/components/Onboarding/OnboardingWizard.vue` (step mapping)

- [ ] **Step 1: Create InvestmentStep.vue**

Same pattern as PropertyStep:
```
<OnboardingStep>
  <!-- Saved accounts as cards -->
  <div v-if="accounts.length > 0 && !showForm">
    <div v-for="account in accounts" :key="account.id" class="card">
      <!-- Badge: ISA / GIA / VCT etc -->
      <!-- Provider name -->
      <!-- Current value -->
      <!-- Edit / Remove buttons -->
    </div>
    <button @click="showForm = true">+ Add Another Account</button>
  </div>

  <!-- AccountForm (inline, context=onboarding) -->
  <div v-if="showForm || accounts.length === 0">
    <AccountForm context="onboarding" @save="handleAccountSaved" @close="showForm = false" />
  </div>
</OnboardingStep>
```

Script logic:
- `onMounted`: Fetch from DB via `investmentService.getInvestmentData()` → extract accounts array
- `handleAccountSaved(data)`: Call `investmentService.createAccount(data)` → refresh from DB → `showForm = false`
- Card shows: account type badge, provider, current value, monthly contribution (if any)

- [ ] **Step 2: Update OnboardingWizard.vue step mapping**

```javascript
// Before:
'investments': () => import('@/components/Investment/AccountForm.vue'),
'investments-isa': () => import('@/components/Investment/AccountForm.vue'),

// After:
'investments': () => import('@/components/Onboarding/steps/InvestmentStep.vue'),
'investments-isa': () => import('@/components/Onboarding/steps/InvestmentStep.vue'),
```

Remove investment save handling from `handleLifeStageStepSave` (InvestmentStep handles its own DB saves).

- [ ] **Step 3: Test in browser**
- Add S&S ISA → Card shows → Add GIA → Two cards → Continue → Both persist

- [ ] **Step 4: Commit**

---

### Task 3: PensionStep.vue — Pension Cards

**Files:**
- Create: `resources/js/components/Onboarding/steps/PensionStep.vue`
- Modify: `resources/js/components/Onboarding/OnboardingWizard.vue` (step mapping)

- [ ] **Step 1: Create PensionStep.vue**

Same pattern:
```
<OnboardingStep>
  <!-- Saved pensions as cards -->
  <div v-if="pensions.length > 0 && !showForm">
    <div v-for="pension in pensions" :key="pension.id" class="card">
      <!-- Badge: Workplace / SIPP / Personal -->
      <!-- Scheme name, Provider -->
      <!-- Fund value -->
      <!-- Contributions (employee + employer if workplace) -->
      <!-- Edit / Remove buttons -->
    </div>
    <button @click="showForm = true">+ Add Another Pension</button>
  </div>

  <!-- DCPensionForm (inline, context=onboarding) -->
  <div v-if="showForm || pensions.length === 0">
    <DCPensionForm context="onboarding" @save="handlePensionSaved" @close="showForm = false" />
  </div>
</OnboardingStep>
```

Script logic:
- `onMounted`: Fetch from DB via `retirementService.getRetirementData()` → extract DC pensions array
- `handlePensionSaved(data)`: Call `retirementService.createDCPension(data)` → refresh from DB → `showForm = false`
- Card shows: pension type badge, scheme name, provider, fund value, contributions

- [ ] **Step 2: Update OnboardingWizard.vue step mapping**

```javascript
// Before:
'pensions': () => import('@/components/Retirement/DCPensionForm.vue'),
'pension-auto-enrolment': () => import('@/components/Retirement/DCPensionForm.vue'),
'pension-review': () => import('@/components/Retirement/DCPensionForm.vue'),

// After:
'pensions': () => import('@/components/Onboarding/steps/PensionStep.vue'),
'pension-auto-enrolment': () => import('@/components/Onboarding/steps/PensionStep.vue'),
'pension-review': () => import('@/components/Onboarding/steps/PensionStep.vue'),
```

Remove pension save handling from `handleLifeStageStepSave`.

- [ ] **Step 3: Test in browser**
- Add Workplace pension → Card shows → Add SIPP → Two cards → Continue → Both persist

- [ ] **Step 4: Commit**

---

### Task 4: Clean up OnboardingWizard.vue save handlers

**Files:**
- Modify: `resources/js/components/Onboarding/OnboardingWizard.vue`

- [ ] **Step 1: Remove redundant save handlers**

In `handleLifeStageStepSave`, remove the property/investment/pension save blocks since these steps now handle their own saves to DB. Keep the personal-info save handler (PersonalInformation still emits to parent).

- [ ] **Step 2: Verify all journeys still work**
- Starting Out (6 steps) — savings step uses SimpleSavingsAccountStep (unchanged)
- Building Foundations (7 steps) — savings, pension, investment steps now use new components
- Protecting What Matters (8 steps) — property, protection, pension steps use new/existing components
- Planning Your Future (7 steps) — pension, investment, property steps use new components
- Enjoying Your Wealth (6 steps) — pension step uses new component

- [ ] **Step 3: Commit**

---

## Implementation Notes

- All three new step components follow the EXACT same pattern as SimpleSavingsAccountStep.vue
- Key difference: PropertyStep pre-fills address from userProfile store for the first property
- Cards use the design system: `rounded-lg border border-light-gray p-4` with raspberry badges
- The existing form components (PropertyForm, AccountForm, DCPensionForm) already support `context="onboarding"` mode
- No new API endpoints needed — all use existing service methods
- The "Continue" button on OnboardingStep advances to the next step; the step itself handles save internally
