# Life Events Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026 (after Grok testing — 4/4 PASS)
**Source:** `resources/js/components/Goals/LifeEventForm.vue`
**Parent:** `resources/js/components/Goals/EventsTab.vue`
**Route:** `/goals?tab=events`
**Entity type:** `life_event`

## Form Structure

Single-step modal form. Opens on `/goals?tab=events` when "Add Life Event" clicked or via AI `pendingFill` watcher in EventsTab.

## Validation (handleSubmit — BLOCKING)

1. `event_name` — required
2. `event_type` — required
3. `amount` — required
4. `expected_date` — required

## formData Shape

```javascript
form: {
  event_name: '',              // text — REQUIRED
  event_type: '',              // select — REQUIRED
  description: '',             // textarea — optional
  amount: null,                // number — REQUIRED
  expected_date: '',           // date (YYYY-MM-DD) — REQUIRED
  certainty: 'likely',         // buttons: confirmed, likely, possible, speculative
  show_in_projection: true,    // checkbox
  show_in_household_view: true, // checkbox
}
```

## Event Types (from LifeEventService)

### Income Events
| `event_type` value | Label |
|-------------------|-------|
| `inheritance` | Inheritance |
| `gift_received` | Gift Received |
| `bonus` | Bonus |
| `redundancy_payment` | Redundancy Payment |
| `property_sale` | Property Sale |
| `business_sale` | Business Sale |
| `pension_lump_sum` | Pension Lump Sum |
| `lottery_windfall` | Lottery/Windfall |
| `custom_income` | Other Income |

### Expense Events
| `event_type` value | Label |
|-------------------|-------|
| `large_purchase` | Large Purchase |
| `home_improvement` | Home Improvement |
| `wedding` | Wedding |
| `education_fees` | Education Fees |
| `gift_given` | Gift Given |
| `medical_expense` | Medical Expense |
| `custom_expense` | Other Expense |

## Certainty Levels (button group)

| Value | Label |
|-------|-------|
| `confirmed` | Confirmed |
| `likely` | Likely (default) |
| `possible` | Possible |
| `speculative` | Speculative |

## AI Tool → Handler → Form Field Map

| AI param | Handler maps to | formData key |
|----------|----------------|-------------|
| `event_name` | `event_name` | `event_name` |
| `event_type` | `event_type` | `event_type` |
| `estimated_amount` | `amount` | `amount` |
| `event_date` | `expected_date` | `expected_date` |
| `certainty` | `certainty` | `certainty` |
| `description` | `description` | `description` |

## Parent Save Flow

`EventsTab.vue` → `handleSaveEvent(formData)`:
- New: `this.createLifeEvent(formData)` (Vuex action)
- Edit: `this.updateLifeEvent({ eventId, eventData: formData })`
- Then: `completeFill()` + `closeFormModal()`

## Test Results (Grok, 4/4 PASS)

| # | Type | Prompt | Certainty | Result |
|---|------|--------|-----------|--------|
| 1 | inheritance | "inherit £150,000 from parents' estate around March 2030, likely" | Likely | PASS |
| 2 | home_improvement | "kitchen renovation for £25,000 in January 2028, confirmed" | Confirmed | PASS |
| 3 | bonus | "£10,000 work bonus in December 2026, confirmed" | Confirmed | PASS |
| 4 | large_purchase | "buying a boat for £40,000 in summer 2029, speculative" | Speculative | PASS |
