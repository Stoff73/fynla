# AI Form Fill — Development Process

**Purpose:** The step-by-step process for mapping a new form to Grok's AI tool definitions so the assistant can fill it correctly.

---

## Process

### Step 1: Read the Form Component

Read the ENTIRE Vue form component. Map every single field:

- Every `v-model` (the `formData` key)
- Every `<select>` with all `<option value="">` values
- Every `v-if` / `v-show` condition (what makes fields appear/disappear)
- Every `type` (text, number, date, checkbox, select)
- The `formData` initial shape in `data()`
- Computed properties that control visibility (e.g. `isLifeInsurance`, `showTermYears`)

### Step 2: Read the Parent Component

Find where the form emits `save` and how the parent handles it:

- What does `preparePolicyData()` / equivalent do? (maps formData → API payload)
- What API endpoint does each type call?
- What field name transformations happen? (e.g. `coverage_amount` → `sum_assured` or `benefit_amount`)

### Step 3: Read the API Service + Controller

- What validation rules does the backend enforce?
- What fields are required vs optional?
- What are the exact enum values the DB accepts?

### Step 4: Fill the Form Manually in the Browser

For EVERY type/variant:

1. Open the form
2. Select the type
3. Note which fields appear/disappear
4. Fill every field
5. Submit
6. Verify it saved to DB
7. Check the card/list shows correct data

This catches:
- Fields that the code says exist but don't render
- Select values that don't match between form and API
- Validation that blocks submission
- Fields that save with wrong names

### Step 5: Verify Database Save and Dashboard Display

After filling each form variant manually:

1. Check the database — confirm the record saved with the correct field values (correct type, amounts, provider, term, flags)
2. Check the dashboard/page — confirm the card/list entry shows the correct information (right policy type label, correct amounts, right provider name)
3. If anything is wrong — fix the form or backend BEFORE writing the algorithm. The algorithm must document a working form.

### Step 6: Write the Algorithm Document

Create `March/March24Updates/AI/{module}-form-algorithm.md` with:

1. **Form structure** — single-step or multi-step? How many variants?
2. **Type hierarchy** — if there are parent/child type selects, map them completely
3. **AI tool param → form field mapping table** — what the AI sends vs what `formData` key it maps to
4. **Every field** — grouped by visibility condition:
   - Always visible fields
   - Conditional fields (with exact condition)
   - Field type, v-model key, select option values
5. **Coverage/amount logic** — which types use `sum_assured` vs `benefit_amount` vs `coverage_amount`
6. **Validation** — what blocks submission
7. **Pre-set requirements** — which select fields MUST be set before `beginFieldSequence` (Vue reactivity)
8. **Test scenarios** — one natural-language prompt per type/variant

### Step 7: Update XaiToolDefinitions.php

Add/update the tool in `XaiToolDefinitions.php`:

- `name` — e.g. `create_protection_policy`
- `description` — tells Grok WHEN to use it and to call it IMMEDIATELY
- `parameters` — every field the AI can pass:
  - `type` — `string`, `number`, `integer`, `boolean`, or nullable `['type', 'null']`
  - `enum` — EXACT values that map to form select options
  - `description` — tells Grok what each field means, what values to use for what
- `required` — all parameter names (xAI strict mode requires this)
- Nullable enums use the `$this->nullableEnum()` helper

**Key rules:**
- Enum values must map to what the CoordinatingAgent handler expects
- Descriptions must be clear enough that Grok picks the right value from natural language
- `sum_assured` vs `benefit_amount` distinction must be explicit in descriptions
- Add "IMPORTANT: Do NOT call any other creation tools in the same turn" to prevent multi-tool calls

### Step 8: Update CoordinatingAgent.php Handler

Add/update the `handleCreate{Entity}()` method:

1. **Validation** — `validateToolInput()` with rules matching the tool enum values
2. **Type mapping** — map AI `policy_type` values to form `policyType` select values (e.g. `income_protection` → `incomeProtection`)
3. **Field mapping** — map AI params to EXACT `formData` keys:
   - AI param `sum_assured` → formData key `coverage_amount`
   - AI param `policy_term_years` → formData key `term_years`
   - etc.
4. **Sub-type handling** — set `life_policy_type` for life insurance sub-types
5. **Coverage logic** — use `benefit_amount` for income-based types, `sum_assured` for lump sum types
6. **Return `fill_form` action** with `entity_type`, `route`, `fields`

**The `fields` array keys MUST exactly match the form component's `formData` keys.** This is where most bugs happen.

### Step 9: Update Form Component (if needed)

If the form doesn't already have AI fill watchers, add:

1. `mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling'])` in computed
2. `pendingFill` watcher — pre-set select fields, call `beginFieldSequence`
3. `highlightedField` watcher — `this.formData[fieldKey] = value`
4. `filling` watcher — auto-submit after 500ms when `false`
5. `:class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'fieldName' }"` on field containers

### Step 10: Test with Grok

For each test scenario from the algorithm:

1. Open fresh chat (or new conversation if degradation risk)
2. Type the natural-language prompt
3. Watch the form fill animate
4. Verify ALL fields are set correctly in the form
5. Verify the record saved to DB (check the card/list on the page)
6. Record PASS/FAIL with details

**Test in batches of ~5 per conversation** to avoid degradation.

---

## File Checklist Per Module

| File | What to check/update |
|------|---------------------|
| `resources/js/components/{Module}/Form.vue` | formData shape, select options, v-if conditions, watchers |
| `resources/js/views/{Module}/Dashboard.vue` | handleSave switch/routing, API calls |
| `resources/js/services/{module}Service.js` | API endpoints per type |
| `app/Services/AI/XaiToolDefinitions.php` | Tool params, enums, descriptions |
| `app/Agents/CoordinatingAgent.php` | Handler: validation, field mapping, type mapping |
| `app/Http/Controllers/Api/{Module}Controller.php` | Backend validation rules |
| `March/March24Updates/AI/{module}-form-algorithm.md` | Algorithm document |

---

## Inline Sub-Entity Pattern (Holdings)

Some forms support creating sub-entities inline during the parent entity creation. This avoids a separate tool call and multi-step navigation.

**Investment Holdings Example:**

The `create_investment_account` tool accepts an optional `holdings` array. When present:

1. The CoordinatingAgent passes `holdings` through in the `fields` array
2. The `highlightedField` watcher in `AccountForm.vue` sets `formData.holdings = [{...}]`
3. `InlineHoldingsEditor.vue` renders the holdings rows from the prop
4. On submit, the backend `storeAccount()` creates account + holdings in a single DB transaction
5. Any unallocated remainder auto-creates a Cash holding

**When to use inline vs standalone:**
- **Inline (preferred):** User mentions holdings AND account in the same message, account doesn't exist yet → pass `holdings` in `create_investment_account`
- **Standalone:** User wants to add holdings to an existing account → use `create_holding` tool

**Eligible types for inline holdings:** ISA, GIA, onshore/offshore bonds, VCT, EIS

This pattern could be extended to other entities with sub-entities (e.g. properties with mortgages) in future.

---

## Common Pitfalls

1. **Select values mismatch** — AI sends `income_protection`, form expects `incomeProtection`. The handler must translate.
2. **Conditional fields not rendering** — `policyType` must be set BEFORE `beginFieldSequence` so `v-if` conditions evaluate and sub-fields mount.
3. **coverage_amount vs sum_assured vs benefit_amount** — the form uses `coverage_amount` for display, but `preparePolicyData()` maps it to `sum_assured` or `benefit_amount` depending on type. The handler must set `coverage_amount` in fields.
4. **Dropdown value not in options** — if AI sends `family_income_benefit` but the `<select>` only has `decreasing_term`, `level_term`, `whole_of_life`, the value won't stick. Must add the option to the dropdown.
5. **xAI returns `"null"` string** — handled globally in `executeTool()` but watch for it.
6. **xAI HTML entities** — `&amp;` in "NS&I" — handled globally in `executeTool()`.
7. **Array fields in AI fill** — when the AI sends an array (e.g. `holdings`), the `highlightedField` watcher sets `formData[key] = arrayValue` directly. The child component must watch its prop for changes. The `allowedFields` filter in `submitForm()` must include the array field name, and the MissingValue object filter already skips arrays (`!Array.isArray()` check).
