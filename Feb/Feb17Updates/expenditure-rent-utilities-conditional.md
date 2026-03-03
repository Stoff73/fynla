# Conditionally Hide Rent/Utilities in Expenditure Based on Property Ownership

**Date:** 17 February 2026
**File changed:** `resources/js/components/UserProfile/ExpenditureForm.vue`

## Why

Users who own a main residence should not see rent and utilities inputs in the Essential Living expenditure category - those costs are covered by their property (council tax, utilities etc. are entered in the Properties tab). Users with only buy-to-let properties (no main residence) still need these fields but with clarified helper text on utilities.

## What Changed

### 1. Property ownership detection (new computed properties)

Three new computeds added after the `userName` computed (~line 1246):

- `properties` - reads from `store.state.netWorth.properties`
- `hasMainResidence` - `true` if any property has `property_type === 'main_residence'`
- `hasOnlyBuyToLet` - `true` if properties exist, none are main residence, and at least one is buy-to-let

### 2. `essentialFields` converted from static array to computed

The original static array was renamed to `allEssentialFields` (preserves the full list for data operations). A new `essentialFields` computed was created that:

- **Main residence owner:** Filters out `rent` and `utilities` entirely
- **Buy-to-let only owner:** Shows all fields but changes the utilities hint to: *"Utilities for your rented home, not for properties owned"*
- **No properties:** Returns all fields unchanged

### 3. Subtotal calculations updated

`essentialTotal` and `spouseEssentialTotal` computeds now reference `essentialFields.value` (since it is a computed ref, not a plain array). This means subtotals automatically exclude hidden fields.

### 4. Data integrity preserved in init/save

`initializeFromProps` and `handleSave` both use `allEssentialFields` (the static full array) instead of the filtered computed. This means:

- All field values are always loaded from props (including rent/utilities even when hidden)
- All field values are always included in save payloads
- No data is lost when fields are hidden from the UI

### 5. Properties fetched on mount

`store.dispatch('netWorth/fetchProperties')` added to the existing `onMounted` block so property data is available when the component loads.

## Testing

| Persona | Expected Behaviour |
|---|---|
| `peak_earners` (David & Sarah Mitchell) | Have main residence - rent and utilities hidden in Essential Living |
| `young_saver` (John Morgan) | No properties - rent and utilities shown normally |
| Any buy-to-let-only user | Rent shown, utilities shown with updated hint text |

Also verify: saving expenditure still works correctly when fields are hidden (data should persist in the database unchanged).
