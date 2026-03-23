# AI Form Fill — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When Fyn creates or updates any record via AI, the user watches the actual form fill in real-time with field-by-field highlighting, then auto-submit.

**Architecture:** New `aiFormFill` Vuex store coordinates between AI tool results and existing form components. Backend tool handlers return validated data instead of saving directly. Frontend navigates to page, opens modal, fills fields sequentially with 250ms highlight animation, then auto-submits through the existing `@save` emit pattern.

**Tech Stack:** Laravel 10, Vue 3, Vuex, SSE streaming

**Spec:** `docs/superpowers/specs/2026-03-21-ai-form-fill-design.md`

**Key conventions:**
- `declare(strict_types=1);` in all PHP files
- British spelling in user-facing text
- Forms emit `save` not `submit` (CLAUDE.md Rule #4)
- Use `currencyMixin` for formatting, never local `formatCurrency()`
- Colours from design system palette only
- `@click.stop` on interactive elements inside clickable containers

**IMPORTANT: This plan is for the NEXT SESSION. Save it and pick it up fresh.**

---

## File Structure

### New Files

| File | Purpose |
|------|---------|
| `resources/js/store/modules/aiFormFill.js` | Coordination store — manages fill state, field sequencing, highlighting |

### Modified Files — Backend

| File | Change |
|------|--------|
| `app/Traits/HasAiChat.php` | New `fill_form` SSE event type |
| `app/Agents/CoordinatingAgent.php` | All 15 create handlers + `update_record` return `fill_form` instead of saving |

### Modified Files — Frontend Core

| File | Change |
|------|--------|
| `resources/js/store/index.js` | Register aiFormFill module |
| `resources/js/components/Shared/AiChatPanel.vue` | Handle `fill_form` SSE event, dispatch to store |
| `resources/css/app.css` | Add `.ai-fill-highlight` class |

### Modified Files — Page Components (modal trigger watchers)

| File | Entity Type | Form Component |
|------|------------|----------------|
| `resources/js/views/NetWorth/CashOverview.vue` | savings_account | SaveAccountModal |
| `resources/js/components/NetWorth/InvestmentList.vue` | investment_account | AccountForm |
| `resources/js/components/NetWorth/PensionList.vue` | dc_pension, db_pension | PensionFormModal |
| `resources/js/components/NetWorth/PropertyList.vue` | property, mortgage | PropertyForm |
| `resources/js/views/Protection/ProtectionDashboard.vue` | protection_policy | PolicyFormModal |
| `resources/js/views/Goals/GoalsDashboard.vue` | goal, life_event | GoalFormModal, LifeEventForm |
| `resources/js/views/UserProfile.vue` | family_member | FamilyMemberForm |
| `resources/js/views/Trusts/TrustsDashboard.vue` | trust | TrustFormModal |
| `resources/js/components/NetWorth/BusinessInterestsList.vue` | business_interest | BusinessInterestForm |
| `resources/js/components/NetWorth/ChattelsList.vue` | chattel | ChattelFormModal |
| `resources/js/views/Estate/EstateDashboard.vue` | estate_asset, estate_gift | EstateAssetForm, GiftForm |
| `resources/js/components/NetWorth/LiabilitiesList.vue` | estate_liability | LiabilityForm |

### Modified Files — Form Components (highlight bindings + AI fill watcher)

Each form component listed above gets:
- `:class="{ 'ai-fill-highlight': highlightedField === 'field_key' }"` on each input
- Watcher on `aiFormFill` state to populate form data and trigger submit

---

## Task 1: Foundation — aiFormFill Store + CSS + SSE Event

**Files:**
- Create: `resources/js/store/modules/aiFormFill.js`
- Modify: `resources/js/store/index.js`
- Modify: `resources/css/app.css`
- Modify: `resources/js/components/Shared/AiChatPanel.vue`
- Modify: `app/Traits/HasAiChat.php`

- [ ] **Step 1: Create the aiFormFill Vuex store**

```javascript
// resources/js/store/modules/aiFormFill.js
const state = {
  pendingFill: null,      // { entityType, fields, route, mode, entityId }
  filling: false,
  currentFieldIndex: 0,
  fieldOrder: [],
  highlightedField: null,
  currentStep: 0,
};

const getters = {
  isFillingForm: (state) => state.filling,
  currentHighlight: (state) => state.highlightedField,
};

const mutations = {
  SET_PENDING_FILL(state, fill) { state.pendingFill = fill; },
  SET_FILLING(state, filling) { state.filling = filling; },
  SET_FIELD_ORDER(state, order) { state.fieldOrder = order; },
  SET_CURRENT_FIELD_INDEX(state, index) { state.currentFieldIndex = index; },
  SET_HIGHLIGHTED_FIELD(state, field) { state.highlightedField = field; },
  SET_CURRENT_STEP(state, step) { state.currentStep = step; },
  CLEAR(state) {
    state.pendingFill = null;
    state.filling = false;
    state.currentFieldIndex = 0;
    state.fieldOrder = [];
    state.highlightedField = null;
    state.currentStep = 0;
  },
};

const actions = {
  startFill({ commit, dispatch }, { entityType, fields, route, mode, entityId }) {
    commit('SET_PENDING_FILL', { entityType, fields, route, mode: mode || 'create', entityId: entityId || null });
    // Navigation happens via pendingNavigation or router — the page watcher opens the modal
  },

  beginFieldSequence({ commit, state, dispatch }, fieldOrder) {
    commit('SET_FIELD_ORDER', fieldOrder);
    commit('SET_CURRENT_FIELD_INDEX', 0);
    commit('SET_FILLING', true);
    dispatch('fillNextField');
  },

  fillNextField({ commit, state, dispatch }) {
    const index = state.currentFieldIndex;
    if (index >= state.fieldOrder.length) {
      // All fields filled — pause then signal complete
      setTimeout(() => {
        commit('SET_HIGHLIGHTED_FIELD', null);
        commit('SET_FILLING', false);
      }, 250);
      return;
    }

    const fieldKey = state.fieldOrder[index];
    commit('SET_HIGHLIGHTED_FIELD', fieldKey);

    setTimeout(() => {
      commit('SET_CURRENT_FIELD_INDEX', index + 1);
      dispatch('fillNextField');
    }, 250);
  },

  advanceStep({ commit, state }) {
    commit('SET_CURRENT_STEP', state.currentStep + 1);
  },

  cancelFill({ commit }) {
    commit('CLEAR');
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
```

- [ ] **Step 2: Register in store index**

Add `import aiFormFill from './modules/aiFormFill';` and register in modules.

- [ ] **Step 3: Add CSS class to app.css**

```css
/* AI Form Fill — field highlight during Fyn auto-fill */
.ai-fill-highlight {
  @apply ring-2 ring-violet-400 ring-offset-1 bg-violet-50 transition-all duration-200;
}
```

- [ ] **Step 4: Add fill_form SSE event in HasAiChat.php**

In `HasAiChat.php`, after the existing `navigate` and `entity_created` event handling, add:

```php
// Handle form fill results
if (isset($toolResult['action']) && $toolResult['action'] === 'fill_form') {
    yield [
        'type' => 'fill_form',
        'entity_type' => $toolResult['entity_type'],
        'route' => $toolResult['route'],
        'fields' => $toolResult['fields'],
        'mode' => $toolResult['mode'] ?? 'create',
        'entity_id' => $toolResult['entity_id'] ?? null,
    ];
}
```

- [ ] **Step 5: Handle fill_form event in AiChatPanel.vue**

In the SSE event handler in `aiChat.js` store (where `entity_created` is handled), add:

```javascript
case 'fill_form':
  // Navigate to the page first
  if (event.route) {
    commit('SET_PENDING_NAVIGATION', event.route);
  }
  // Dispatch fill after a short delay to let navigation complete
  setTimeout(() => {
    dispatch('aiFormFill/startFill', {
      entityType: event.entity_type,
      fields: event.fields,
      route: event.route,
      mode: event.mode || 'create',
      entityId: event.entity_id || null,
    }, { root: true });
  }, 500);
  break;
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/store/modules/aiFormFill.js resources/js/store/index.js resources/css/app.css resources/js/components/Shared/AiChatPanel.vue app/Traits/HasAiChat.php
git commit -m "feat: aiFormFill store, CSS highlight class, fill_form SSE event"
```

---

## Task 2: First Form — Savings Account (Proof of Concept)

Implement the full flow for one form to prove the pattern works before rolling out to all 15.

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php` — `handleCreateSavingsAccount` returns `fill_form`
- Modify: `resources/js/views/NetWorth/CashOverview.vue` — modal trigger watcher
- Modify: `resources/js/components/Savings/SaveAccountModal.vue` — AI fill integration

- [ ] **Step 1: Read the existing `handleCreateSavingsAccount` in CoordinatingAgent.php**

Understand the current flow — what fields it validates, what it saves, what it returns.

- [ ] **Step 2: Change handler to return fill_form instead of saving**

The handler should validate input and return:
```php
return [
    'action' => 'fill_form',
    'entity_type' => 'savings_account',
    'route' => '/net-worth/cash',
    'fields' => [
        'account_name' => $input['account_name'] ?? '',
        'account_type' => $input['account_type'] ?? 'easy_access',
        'institution' => $input['institution'] ?? '',
        'current_balance' => $input['current_balance'] ?? 0,
        'interest_rate' => $input['interest_rate'] ?? null,
        'is_isa' => $input['is_isa'] ?? false,
        'is_emergency_fund' => $input['is_emergency_fund'] ?? false,
        'regular_contribution_amount' => $input['regular_contribution_amount'] ?? null,
    ],
];
```

- [ ] **Step 3: Add modal trigger watcher to CashOverview.vue**

Read `CashOverview.vue`, find where `openCreateModal` or equivalent method is. Add watcher:

```javascript
watch: {
  '$store.state.aiFormFill.pendingFill'(fill) {
    if (fill && fill.entityType === 'savings_account') {
      this.showAddAccountModal = true; // or however the modal opens
    }
  },
},
```

- [ ] **Step 4: Add AI fill integration to SaveAccountModal.vue**

Read `SaveAccountModal.vue` fully. Add:

1. Import mapState for aiFormFill
2. Computed: `...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling'])`
3. Add `:class="{ 'ai-fill-highlight': highlightedField === 'account_name' }"` to each input
4. Watcher on `pendingFill` that populates form fields and starts the fill sequence:

```javascript
watch: {
  pendingFill(fill) {
    if (fill && fill.entityType === 'savings_account' && fill.fields) {
      // Build field order from available fields
      const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');

      // Start the fill sequence
      this.$store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
    }
  },

  highlightedField(fieldKey) {
    if (fieldKey && this.pendingFill?.fields) {
      // Set the form value for this field
      const value = this.pendingFill.fields[fieldKey];
      if (value !== undefined) {
        this.form[fieldKey] = value;
      }
    }
  },

  filling(isFilling) {
    if (!isFilling && this.pendingFill) {
      // Fill complete — auto submit after 250ms
      setTimeout(() => {
        this.handleSubmit();
        this.$store.dispatch('aiFormFill/cancelFill');
      }, 250);
    }
  },
},
```

- [ ] **Step 5: Test in browser**

1. Log in as test user
2. Ask Fyn: "I have a Nationwide Cash ISA with £15,000 at 4.5%"
3. Verify: navigates to /net-worth/cash, modal opens, fields fill with highlight, auto-submits
4. Take screenshot

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: AI form fill — savings account proof of concept"
```

---

## Task 3: Update Record Flow

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php` — `handleUpdateRecord` returns `fill_form` with mode `edit`

- [ ] **Step 1: Read the existing `handleUpdateRecord` handler**

Understand how it currently works — what entity types it supports, how it finds the record, how it applies changes.

- [ ] **Step 2: Change handler to return fill_form for edit**

```php
// Instead of saving directly:
return [
    'action' => 'fill_form',
    'mode' => 'edit',
    'entity_type' => $input['entity_type'],
    'entity_id' => $input['entity_id'],
    'route' => $this->getRouteForEntityType($input['entity_type']),
    'fields' => $input['fields'], // Only changed fields
];
```

Add a helper method `getRouteForEntityType()` mapping entity types to routes.

- [ ] **Step 3: Update page component watchers to handle edit mode**

Each page watcher needs to check `fill.mode`:
```javascript
'$store.state.aiFormFill.pendingFill'(fill) {
  if (fill && fill.entityType === 'savings_account') {
    if (fill.mode === 'edit' && fill.entityId) {
      // Find the record and open edit modal
      const record = this.accounts.find(a => a.id === fill.entityId);
      if (record) this.openEditModal(record);
    } else {
      this.openCreateModal();
    }
  }
}
```

- [ ] **Step 4: Update form component to handle edit fill**

In edit mode, the form is already pre-populated. The AI fill watcher only animates the CHANGED fields:

```javascript
highlightedField(fieldKey) {
  if (fieldKey && this.pendingFill?.fields) {
    const value = this.pendingFill.fields[fieldKey];
    if (value !== undefined) {
      this.form[fieldKey] = value; // Only changes the specified fields
    }
  }
},
```

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: AI form fill — edit/update flow with changed-field-only animation"
```

---

## Task 4: Roll Out to All Create Tools

Apply the same pattern from Task 2 to all remaining 14 create tool handlers and their form/page components.

**This task is best done with parallel subagents — one per form group.**

### Group A: Financial Accounts
- `handleCreateInvestmentAccount` → InvestmentList + AccountForm
- `handleCreatePension` → PensionList + PensionFormModal

### Group B: Property & Mortgage
- `handleCreateProperty` → PropertyList + PropertyForm (multi-step)
- `handleCreateMortgage` → PropertyList + PropertyForm

### Group C: Protection & Estate
- `handleCreateProtectionPolicy` → ProtectionDashboard + PolicyFormModal
- `handleCreateEstateAsset` → EstateDashboard + EstateAssetForm
- `handleCreateEstateLiability` → LiabilitiesList + LiabilityForm
- `handleCreateEstateGift` → EstateDashboard + GiftForm

### Group D: Goals, Events & Family
- `handleCreateGoal` → GoalsDashboard + GoalFormModal
- `handleCreateLifeEvent` → GoalsDashboard + LifeEventForm
- `handleCreateFamilyMember` → UserProfile + FamilyMemberForm

### Group E: Other Assets
- `handleCreateTrust` → TrustsDashboard + TrustFormModal
- `handleCreateBusinessInterest` → BusinessInterestsList + BusinessInterestForm
- `handleCreateChattel` → ChattelsList + ChattelFormModal

For each handler:
- [ ] Read the current handler, understand what it saves
- [ ] Change to return `fill_form` with validated fields
- [ ] Add page watcher (~5 lines)
- [ ] Add form highlight bindings + fill watcher
- [ ] Commit per group

---

## Task 5: Multi-Step Form — PropertyForm

PropertyForm has 6 steps. The aiFormFill store needs step awareness.

**Files:**
- Modify: `resources/js/store/modules/aiFormFill.js` — step-to-fields mapping
- Modify: `resources/js/components/NetWorth/Property/PropertyForm.vue` — step-aware fill

- [ ] **Step 1: Add step field map to aiFormFill store**

```javascript
const STEP_FIELD_MAP = {
  property: {
    1: ['property_type', 'current_value', 'address_line_1', 'city', 'postcode'],
    2: ['purchase_price', 'purchase_date'],
    3: ['outstanding_mortgage', 'mortgage_rate', 'mortgage_lender', 'monthly_payment'],
    4: ['monthly_rental_income'],
    5: ['annual_service_charge', 'annual_ground_rent', 'annual_insurance'],
    6: [], // Review step
  },
};
```

- [ ] **Step 2: Add step advancement logic**

When all fields in a step are filled, dispatch `advanceStep` and call the form's `nextStep()` method. Skip steps that have no fields in the AI data.

- [ ] **Step 3: Integrate with PropertyForm**

PropertyForm needs to watch `currentStep` from aiFormFill and call its own `nextStep()` programmatically when AI advances.

- [ ] **Step 4: Test with browser**

Ask Fyn: "I have a house at 10 Oak Street, TW1 3QR worth £450,000, bought for £300,000 in 2018 with a £200,000 mortgage at 4.2%"

Verify: navigates to /net-worth/property, PropertyForm opens, steps through with field filling, auto-submits.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: AI form fill — multi-step PropertyForm with step advancement"
```

---

## Task 6: Chat Confirmation After Save

After the form auto-submits successfully, Fyn should confirm in the chat.

**Files:**
- Modify: `resources/js/components/Shared/AiChatPanel.vue` or relevant page components

- [ ] **Step 1: Emit confirmation after successful save**

When the parent's save handler succeeds (API returns 200/201), dispatch a message to the chat:

```javascript
// In the parent save handler, after successful API call:
if (this.$store.state.aiFormFill.pendingFill) {
  this.$store.commit('aiChat/ADD_MESSAGE', {
    id: 'confirm_' + Date.now(),
    role: 'entity_created',
    content: `${entityName} saved successfully`,
    metadata: { entity_type: entityType, entity_id: response.data.id },
  });
}
```

- [ ] **Step 2: Commit**

```bash
git commit -m "feat: AI form fill — chat confirmation after successful save"
```

---

## Task 7: Integration Test & Browser Verification

- [ ] **Step 1: Run all agent tests**
```bash
./vendor/bin/pest tests/Unit/Agents/ -v
```

- [ ] **Step 2: Seed database**
```bash
php artisan db:seed
```

- [ ] **Step 3: Browser test — create flow**
Log in, ask Fyn to create a savings account, verify full form fill animation + auto-submit.

- [ ] **Step 4: Browser test — edit flow**
Ask Fyn to update the account balance, verify edit modal opens, only changed field highlights.

- [ ] **Step 5: Browser test — multi-step**
Ask Fyn to add a property with mortgage, verify PropertyForm steps through.

- [ ] **Step 6: Browser test — error handling**
Verify: if user closes modal during fill, state clears cleanly.

- [ ] **Step 7: Final commit**
```bash
git commit -m "chore: AI form fill — all tests passing, browser verified"
```
