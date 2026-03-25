# Expenditure Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026
**Sources:**
- `resources/js/components/UserProfile/ExpenditureForm.vue` (Composition API)
- `resources/js/components/UserProfile/ExpenditureOverview.vue`
- `resources/js/views/ValuableInfo.vue`

## Form Structure

Inline edit form (NOT a modal). Located at `/valuable-info?section=expenditure`.
- View mode: shows category totals, Edit button
- Edit mode: shows individual fields per category, Save/Reset buttons
- Entry modes: Detailed (individual categories) or Simple (single total)

## Navigation Path

1. User on any page → AI calls tool → navigate to `/valuable-info?section=expenditure`
2. ValuableInfo.vue sets `activeTab = 'expenditure'` → renders ExpenditureOverview
3. ExpenditureOverview renders ExpenditureForm with `isEditing = false` (view mode)
4. Must click Edit or auto-trigger editing to show the form fields
5. Fill individual category fields
6. Click Save Changes

## Form Fields (formData)

All fields are monthly amounts in pounds (numbers, default 0):

### Essential Living
| Field Key | Label | Notes |
|-----------|-------|-------|
| `rent` | Rent | Monthly rent if not homeowner |
| `utilities` | Utilities | Gas, electricity, water, council tax |
| `food_groceries` | Food & Groceries | |
| `transport_fuel` | Transport & Fuel | Petrol, public transport, parking |
| `healthcare_medical` | Healthcare & Medical | Prescriptions, dental, optician |
| `insurance` | Insurance (non-property) | Car, private medical, mobile phone |

### Communication & Technology
| Field Key | Label |
|-----------|-------|
| `mobile_phones` | Mobile Phones |
| `internet_tv` | Internet & TV |
| `subscriptions` | Subscriptions (Netflix, gym etc.) |

### Personal & Lifestyle
| Field Key | Label |
|-----------|-------|
| `clothing_personal_care` | Clothing & Personal Care |
| `entertainment_dining` | Entertainment & Dining |
| `holidays_travel` | Holidays & Travel |
| `pets` | Pets |

### Children & Dependents
| Field Key | Label |
|-----------|-------|
| `childcare` | Childcare |
| `school_fees` | School Fees |
| `school_lunches` | School Lunches |
| `school_extras` | School Extras |
| `university_fees` | University Fees |
| `children_activities` | Children's Activities |

### Other Expenses
| Field Key | Label |
|-----------|-------|
| `gifts_charity` | Gifts & Presents |
| `charitable_donations` | Charitable Donations |
| `other_expenditure` | Other Expenditure |

## Save Flow

ExpenditureForm emits `save` → ExpenditureOverview.handleSave → `store.dispatch('userProfile/updateExpenditure', formData)` → API PUT

## Implementation Plan

### 1. New tool: `create_expenditure` in XaiToolDefinitions.php
- All 21 category fields as nullable numbers
- Description tells Grok to call immediately, fill what user mentions, set 0 for unmentioned

### 2. Backend handler in CoordinatingAgent.php
- `handleCreateExpenditure` registered in executeTool match
- Returns `fill_form` with entity_type `expenditure`, route `/valuable-info?section=expenditure`
- Maps AI field names directly to formData field names (they match)

### 3. ValuableInfo.vue — watch pendingFill
- If entity_type is `expenditure`, set `activeTab = 'expenditure'`

### 4. ExpenditureOverview.vue — watch pendingFill
- If entity_type is `expenditure`, trigger editing mode on ExpenditureForm
- Pass a prop or set a ref to auto-start editing

### 5. ExpenditureForm.vue — add AI fill watchers
- Import aiFormFill state from Vuex store
- pendingFill watcher: start field sequence when entity_type is `expenditure`
- highlightedField watcher: set `formData.value[fieldKey] = value`
- filling watcher: auto-trigger handleSave when filling completes
- Auto-enter edit mode when pendingFill arrives (set `isEditing = true`)
