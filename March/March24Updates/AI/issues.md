# AI Form Fill — Known Issues

## ISSUE: TypeError in PolicyFormModal.vue during AI fill

**File:** `resources/js/components/Protection/PolicyFormModal.vue:196`
**Error:** `TypeError: Cannot convert undefined or null to object`
**Severity:** Low — does NOT block form save or dashboard display
**Frequency:** Every AI fill for protection policies (8/8 scenarios)
**Impact:** Console error only. Form fills correctly, saves to DB, card appears on dashboard.
**Likely cause:** Line 196 is in `preparePolicyData()` or `loadPolicyData()` — likely `Object.keys()` or `Object.entries()` called on a null/undefined value during the AI fill sequence. The `pendingFill` fields may include keys that don't exist in the form's expected shape.
**Fix:** Investigate line 196, add null guard before the object operation.
