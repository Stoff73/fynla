# Mortgage Not Showing in Net Worth — Root Cause & Fix

**Date**: 20 March 2026
**Severity**: HIGH
**Affects**: All onboarding property saves with mortgages (j3, j4, and any production user)

## Symptom

Net Worth card on dashboard always shows "Liabilities £0" even when the user added a property with a mortgage during onboarding. The mortgage data entered in the property wizard (lender, balance, rate, term) is silently lost.

## Root Cause

**File**: `resources/js/components/Onboarding/steps/AssetsStep.vue`, line 784

The PropertyController returns the created property nested inside `data.property`:
```json
{
  "success": true,
  "data": {
    "property": { "id": 123, "address_line_1": "42 Maple Road", ... }
  }
}
```

But the AssetsStep extracted the property ID as:
```javascript
const propertyId = editingProperty.value?.id || propertyResponse.data?.id || propertyResponse.id;
```

`propertyResponse.data` is `{ property: { id: 123 } }` — there is no `.id` at that level. So:
- `propertyResponse.data?.id` → `undefined`
- `propertyResponse.id` → `undefined`
- `propertyId` → `undefined`

Then the mortgage save was gated by:
```javascript
if (data.mortgage && propertyId) {
    await propertyService.createPropertyMortgage(propertyId, data.mortgage);
}
```

Since `propertyId` was `undefined` (falsy), this condition was **always false**, and the mortgage was **never saved**. The property itself saved correctly, and the try/catch swallowed the issue because no error was actually thrown — the code just skipped the mortgage creation.

## Fix

Added `propertyResponse.data?.property?.id` to the extraction chain:

```javascript
const propertyId = editingProperty.value?.id
    || propertyResponse.data?.property?.id  // ← Added
    || propertyResponse.data?.id
    || propertyResponse.id;
```

## Impact

- **All users who added properties with mortgages during onboarding** have the property saved but no mortgage record.
- This affects Net Worth (liabilities always £0), Estate Planning (IHT calculations missing mortgage liability), Protection analysis (mortgage cover recommendations wrong), and Retirement projections.
- Users who added mortgages OUTSIDE of onboarding (via the Property module directly) are NOT affected — those use a different save path that works correctly.
- Preview personas are NOT affected — their mortgages are created by the seeder, not through the onboarding flow.

## Deployment

Requires frontend rebuild (`./deploy/fynla-org/build.sh`) and upload of `public/build/` directory.

## Verification

After deployment, users who re-enter their property details through the Property module (not onboarding) will get their mortgage saved correctly. Existing users who went through onboarding will need to add their mortgage manually via the Property page.
