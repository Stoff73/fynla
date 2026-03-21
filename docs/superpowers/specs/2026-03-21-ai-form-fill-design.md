# AI Form Fill — Design Spec

**Date:** 21 March 2026
**Status:** Approved
**Scope:** When Fyn creates any record via AI, the user watches the actual form fill in real-time on the page

---

## Overview

Currently when Fyn creates a record (savings account, property, goal, etc.), it saves directly to the database and shows a read-only success card in the chat. The user never sees the form or what data was entered.

This spec changes the flow so the user watches the actual form open and fill in field-by-field with a highlight animation, then auto-submit. Full visibility, full confidence.

---

## User Flow

1. User tells Fyn: "I have a Nationwide Cash ISA with £15,000 at 4.5%"
2. Fyn validates the input on the backend
3. Frontend navigates to `/net-worth/cash`
4. The "Add Account" modal opens automatically
5. Fields fill one-by-one with a violet highlight glow (250ms per field):
   - Account Name: "Nationwide Cash ISA" (highlight)
   - Balance: £15,000 (highlight moves)
   - Interest Rate: 4.5% (highlight moves)
   - ISA: checked (highlight moves)
6. 250ms pause after last field
7. Form auto-submits via the existing `@save` emit pattern
8. API saves the record (real response — success or error)
9. Fyn confirms in chat: "Done — your Nationwide Cash ISA with £15,000 has been added"

For multi-step forms (PropertyForm with 6 steps): Fyn steps through each step visually, filling fields and advancing. Steps with no data are skipped.

---

## Architecture

### Data Flow

```
Fyn AI tool call (e.g. create_savings_account)
  → Backend validates input, does NOT save to DB
  → Returns { action: 'fill_form', entity_type, fields, route }
  → Frontend AiChatPanel receives SSE event
  → aiFormFill Vuex store receives fill request
  → Router navigates to route
  → Page component watches store, opens modal
  → aiFormFill drives sequential field population + highlight
  → Form auto-submits via existing @save emit
  → Parent handler calls existing API endpoint to save
  → Real API response (success or error)
  → If success: Fyn confirms in chat, modal closes
  → If error: modal stays open with error, user can fix manually
```

### Key Design Decision

The backend tool handlers **do not save to the database**. They validate the input and return the field data to the frontend. The frontend form's normal `@save` emit then calls the existing API endpoint (e.g. `POST /api/savings/accounts`). This means:

- All existing validation rules still apply
- All existing observers fire (risk recalculation, cache invalidation, etc.)
- No new save endpoints needed
- Error handling works exactly as manual entry
- Preview users go through the same flow — `PreviewWriteInterceptor` handles the API call as it normally would

---

## Frontend Components

### 1. aiFormFill Vuex Store (`resources/js/store/modules/aiFormFill.js`)

**State:**
```javascript
{
  pendingFill: null,        // { entityType, fields, route, modalAction }
  filling: false,           // Whether a fill sequence is in progress
  currentFieldIndex: 0,     // Which field is being filled
  fieldOrder: [],           // Ordered list of field keys
  highlightedField: null,   // Currently highlighted field key
  currentStep: 0,           // For multi-step forms
}
```

**Actions:**
- `startFill({ entityType, fields, route })` — Sets pendingFill, navigates to route
- `fillNextField()` — Sets next field value + highlight, waits 250ms, advances
- `completeFill()` — Triggers form auto-submit, clears state
- `cancelFill()` — Clears state if user closes modal manually

**Getters:**
- `isFillingForm` — Whether AI fill is in progress
- `currentHighlight` — The currently highlighted field key
- `fillDataForField(key)` — Get the AI-provided value for a specific field

### 2. Field Highlighting CSS (`resources/css/app.css`)

One global class:
```css
.ai-fill-highlight {
  @apply ring-2 ring-violet-400 ring-offset-1 bg-violet-50 transition-all duration-200;
}
```

### 3. SSE Event Handling (`AiChatPanel.vue`)

When the AI tool returns `action: 'fill_form'`, dispatch to the aiFormFill store:

```javascript
case 'fill_form':
  store.dispatch('aiFormFill/startFill', {
    entityType: event.entity_type,
    fields: event.fields,
    route: event.route,
  });
  break;
```

### 4. Page Component Watchers

Each page that hosts a form watches the aiFormFill store and opens its modal:

```javascript
watch: {
  '$store.state.aiFormFill.pendingFill'(fill) {
    if (fill && fill.entityType === 'savings_account') {
      this.openCreateModal();
    }
  }
}
```

~5 lines per page component.

### 5. Form Component Integration

Each form component:

1. Watches `pendingFill` to populate its `formData` field-by-field
2. Binds `:class="{ 'ai-fill-highlight': highlightedField === 'field_key' }"` on each input
3. On fill complete, calls its own `handleSubmit()` method programmatically

```javascript
computed: {
  ...mapState('aiFormFill', ['highlightedField', 'filling']),
},
watch: {
  filling(isFilling) {
    if (!isFilling && this.wasAiFilled) {
      // Fill complete — auto submit
      this.handleSubmit();
    }
  }
}
```

---

## Backend Changes

### Tool Handler Response Change

Each `create_*` tool handler in `CoordinatingAgent.php` changes from:

**Before (saves to DB):**
```php
private function handleCreateSavingsAccount(array $input, User $user): array
{
    $account = SavingsAccount::create([...]);
    return ['created' => true, 'entity_type' => 'savings_account', 'entity_id' => $account->id];
}
```

**After (returns data for form fill):**
```php
private function handleCreateSavingsAccount(array $input, User $user): array
{
    // Validate but don't save
    $validated = $this->validateSavingsAccountInput($input);

    return [
        'action' => 'fill_form',
        'entity_type' => 'savings_account',
        'route' => '/net-worth/cash',
        'fields' => [
            'account_name' => $validated['account_name'],
            'current_balance' => $validated['current_balance'],
            'interest_rate' => $validated['interest_rate'] ?? null,
            'is_isa' => $validated['is_isa'] ?? false,
            'account_type' => $validated['account_type'] ?? 'easy_access',
            // ... all validated fields
        ],
    ];
}
```

### SSE Event Type

The `fill_form` action is yielded as a new SSE event type in `HasAiChat.php`:

```php
if (isset($toolResult['action']) && $toolResult['action'] === 'fill_form') {
    yield [
        'type' => 'fill_form',
        'entity_type' => $toolResult['entity_type'],
        'route' => $toolResult['route'],
        'fields' => $toolResult['fields'],
    ];
}
```

---

## Multi-Step Forms

PropertyForm has 6 steps. The aiFormFill store tracks `currentStep` and drives the wizard:

1. **Step 1** — Fill property_type, current_value, address_line_1, postcode (250ms each)
2. **Advance step** — Call form's `nextStep()` programmatically
3. **Step 2** — Fill purchase_price, purchase_date
4. **Continue** through each step that has fields to fill
5. **Skip steps** with no AI-provided data
6. **Auto-submit** on final step after 250ms pause

The store holds a step-to-fields mapping per entity type:

```javascript
const STEP_FIELD_MAP = {
  property: {
    1: ['property_type', 'current_value', 'address_line_1', 'postcode'],
    2: ['purchase_price', 'purchase_date'],
    3: ['outstanding_mortgage', 'mortgage_rate', 'mortgage_lender'],
    // ...
  },
};
```

---

## Entity Type → Page → Form Mapping

| Entity Type | Route | Form Component | Parent Page |
|---|---|---|---|
| `savings_account` | `/net-worth/cash` | `SaveAccountModal` | `CashOverview` |
| `investment_account` | `/net-worth/investments` | `AccountForm` | `InvestmentList` |
| `dc_pension` | `/net-worth/retirement` | `PensionFormModal` | `PensionList` |
| `db_pension` | `/net-worth/retirement` | `PensionFormModal` | `PensionList` |
| `property` | `/net-worth/property` | `PropertyForm` | `PropertyList` |
| `mortgage` | `/net-worth/property` | `PropertyForm` | `PropertyList` |
| `protection_policy` | `/protection` | `PolicyFormModal` | `ProtectionDashboard` |
| `goal` | `/goals` | `GoalFormModal` | `GoalsDashboard` |
| `life_event` | `/goals?tab=events` | `LifeEventForm` | `GoalsDashboard` |
| `family_member` | `/profile` | `FamilyMemberForm` | `UserProfile` |
| `trust` | `/trusts` | `TrustFormModal` | `TrustsDashboard` |
| `business_interest` | `/net-worth/business` | `BusinessInterestForm` | `BusinessInterestsList` |
| `chattel` | `/net-worth/chattels` | `ChattelFormModal` | `ChattelsList` |
| `estate_asset` | `/estate` | `EstateAssetForm` | `EstateDashboard` |
| `estate_liability` | `/net-worth/liabilities` | `LiabilityForm` | `LiabilitiesList` |
| `estate_gift` | `/estate` | `GiftForm` | `EstateDashboard` |

All 15 create tools covered.

---

## Animation Timing

| Event | Duration |
|---|---|
| Navigate to page | Instant (router.push) |
| Modal open | 200ms (existing transition) |
| Per field fill + highlight | 250ms |
| Pause after last field | 250ms |
| Form submit | Existing API call time |
| Modal close on success | 150ms (existing transition) |

**Total for a 5-field form:** ~200ms (modal) + 1250ms (5 fields) + 250ms (pause) + submit = ~2 seconds visible fill time.

---

## Error Handling

- **Form validation fails:** Modal stays open with validation errors. User can fix manually. Fyn says "There was an issue saving — please check the highlighted fields."
- **API error:** Same as manual entry — modal stays open, error displayed.
- **User closes modal during fill:** `cancelFill()` clears the aiFormFill store state. No record saved.
- **Navigation fails:** If page can't load, Fyn falls back to the current behaviour (saves directly, shows success card).

---

## Edit/Update Flow

When Fyn updates an existing record via the `update_record` tool, the same visual flow applies:

1. Fyn navigates to the page where the record lives
2. The edit modal opens pre-populated with existing data
3. Only the **changed fields** animate — each changed field highlights and its value updates (250ms per field). Unchanged fields remain as-is with no highlight.
4. 250ms pause, then auto-submit
5. Fyn confirms: "Updated your Nationwide Cash ISA — balance changed from £15,000 to £18,500"

**Backend change:** The `update_record` tool handler returns `{ action: 'fill_form', mode: 'edit', entity_type, entity_id, fields }` where `fields` contains only the changed key-value pairs. The frontend opens the edit modal (existing `openEditModal(record)` pattern), then animates only the changed fields.

**Store addition:** The `pendingFill` object gains:
- `mode: 'create' | 'edit'` — whether this is a new record or an update
- `entityId: number | null` — the ID of the record being edited (null for create)

The page component watches `pendingFill` and calls `openEditModal(record)` instead of `openCreateModal()` when `mode === 'edit'`. It fetches the existing record by ID to populate the form first, then the AI fill sequence animates only the changed fields.

---

## Files Affected

### New Files
- `resources/js/store/modules/aiFormFill.js` — Coordination store
- `resources/css` addition — `.ai-fill-highlight` class in `app.css`

### Modified Files — Backend (2)
- `app/Agents/CoordinatingAgent.php` — All 15 create tool handlers + `update_record` handler return `fill_form` instead of saving
- `app/Traits/HasAiChat.php` — New `fill_form` SSE event type

### Modified Files — Frontend (17+)
- `resources/js/store/index.js` — Register aiFormFill module
- `resources/js/components/Shared/AiChatPanel.vue` — Handle `fill_form` SSE event
- 15 page/form component pairs — Modal trigger watcher + field highlight bindings

---

## Out of Scope (v2)

- Undo/revert after auto-submit
- Mobile app form filling
- Batch creation (creating multiple records in sequence)
- Delete flows with visual confirmation
