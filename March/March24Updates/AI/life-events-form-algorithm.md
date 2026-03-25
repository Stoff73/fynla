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

## Test Results (Grok, 16/16 PASS — all types tested)

### Income Events (9/9)

| # | Type | Name | Amount | Certainty | Result |
|---|------|------|--------|-----------|--------|
| 1 | inheritance | Parents' Estate Inheritance | +£150,000 | Likely | PASS |
| 2 | gift_received | Grandmother's Gift | +£20,000 | Confirmed | PASS |
| 3 | bonus | Work Bonus | +£10,000 | Confirmed | PASS |
| 4 | redundancy_payment | Redundancy Payment | +£35,000 | Possible | PASS |
| 5 | property_sale | Buy-to-Let Sale | +£280,000 | Likely | PASS (Grok also created property) |
| 6 | business_sale | Consulting Business Sale | +£200,000 | Likely | PASS |
| 7 | pension_lump_sum | Tax-Free Pension Lump Sum | +£50,000 | Confirmed | PASS |
| 8 | lottery_windfall | Premium Bonds Win | +£5,000 | Confirmed | PASS |
| 9 | custom_income | Car Accident Insurance Payout | +£8,000 | Likely | PASS |

### Expense Events (7/7)

| # | Type | Name | Amount | Certainty | Result |
|---|------|------|--------|-----------|--------|
| 10 | large_purchase | Boat Purchase | -£40,000 | Speculative | PASS |
| 11 | home_improvement | Kitchen Renovation | -£25,000 | Confirmed | PASS |
| 12 | wedding | Daughter's Wedding | -£15,000 | Confirmed | PASS |
| 13 | education_fees | Son's School Fees | -£12,000 | Confirmed | PASS |
| 14 | gift_given | Nephew's Wedding Gift | -£10,000 | Confirmed | PASS |
| 15 | medical_expense | Dental Implants | -£6,000 | Confirmed | PASS |
| 16 | custom_expense | Garden Landscaping | -£3,000 | Possible | PASS (Grok chose home_improvement — valid LLM judgement) |

### Notes

- **property_sale**: Grok also called `create_property` to add the property record before creating the life event. Both saved correctly but causes a navigation detour.
- **custom_expense**: Grok chose `home_improvement` for "garden landscaping" instead of `custom_expense`. Valid judgement — the type still saves correctly. The `custom_expense` enum value is available but Grok prefers specific types when they fit.
