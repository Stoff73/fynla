# Goals Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026 (after Grok testing — 9/9 PASS)
**Source:** `resources/js/components/Goals/GoalFormModal.vue`
**Parent:** `resources/js/views/Goals/GoalsDashboard.vue`
**Route:** `/goals`
**Entity type:** `goal`

## Form Structure

Single-step modal form. Opens on `/goals` page when "Add Goal" clicked or via AI `pendingFill` watcher in GoalsDashboard.

## Validation (handleSubmit — BLOCKING)

1. `goal_name` — required
2. `goal_type` — required
3. `target_amount` — required
4. `target_date` — required, must be future date
5. `custom_goal_type_name` — required when `goal_type === 'custom'` (backend validation)

## formData Shape

```javascript
form: {
  goal_name: '',               // text — REQUIRED
  goal_type: '',               // select — REQUIRED
  custom_goal_type_name: '',   // text — REQUIRED when goal_type === 'custom'
  description: '',             // textarea — optional
  target_amount: null,         // number — REQUIRED
  current_amount: 0,           // number — optional
  target_date: '',             // date (YYYY-MM-DD) — REQUIRED, must be future
  monthly_contribution: null,  // number — optional
  priority: 'medium',          // buttons: critical, high, medium, low
  estimated_property_price: null, // number — property goals only
  deposit_percentage: 10,      // number — property goals only
  is_first_time_buyer: false,  // checkbox — property goals only
  show_in_projection: true,    // checkbox
  show_in_household_view: true, // checkbox
}
```

## Goal Types (from GoalAssignmentService)

| `goal_type` value | Label | Auto-assigned Module | Icon |
|-------------------|-------|---------------------|------|
| `emergency_fund` | Emergency Fund | savings | 🛡️ |
| `property_purchase` | Property Purchase | property | 🏠 |
| `home_deposit` | Home Deposit | property | 🔑 |
| `education` | Education | investment | 🎓 |
| `retirement` | Retirement | retirement | ☀️ |
| `wealth_accumulation` | Wealth Building | investment | 📈 |
| `wedding` | Wedding | savings | 💍 |
| `holiday` | Holiday | savings | ✈️ |
| `car_purchase` | Car Purchase | savings | 🚗 |
| `debt_repayment` | Debt Repayment | savings | 💳 |
| `custom` | Custom Goal | none | ⭐ |

## Priority Values (button group, not select)

| Value | Label |
|-------|-------|
| `critical` | Critical |
| `high` | High |
| `medium` | Medium (default) |
| `low` | Low |

## Conditional Fields

- **Custom goal** (`goal_type === 'custom'`): shows `custom_goal_type_name` text input
- **Property goals** (`property_purchase` or `home_deposit`): shows estimated_property_price, deposit_percentage, is_first_time_buyer, and "Calculate total costs" button

## AI Tool → Handler → Form Field Map

| AI param | Handler maps to | formData key |
|----------|----------------|-------------|
| `name` | `goal_name` | `goal_name` |
| `goal_type` | `goal_type` | `goal_type` |
| `target_amount` | `target_amount` | `target_amount` |
| `target_date` | `target_date` | `target_date` |
| `priority` | `priority` | `priority` |
| `monthly_contribution` | `monthly_contribution` | `monthly_contribution` |
| (auto for custom) | `custom_goal_type_name` = `name` | `custom_goal_type_name` |

## Parent Save Flow

`GoalsDashboard.vue` → `handleSaveGoal(formData)`:
- New: `this.createGoal(formData)` (Vuex action)
- Edit: `this.updateGoal({ goalId, goalData: formData })`
- Then: `completeFill()` + `closeGoalModal()` + `loadGoalsData()`

## Pre-Set Requirements

pendingFill watcher pre-sets: `goal_name`, `goal_type`, `target_amount`, `target_date`, `custom_goal_type_name`

## Test Scenarios & Results (Grok, 9/9 PASS)

| # | Type | Prompt | Result |
|---|------|--------|--------|
| 1 | emergency_fund | "build an emergency fund of £10,000 by December 2027, saving £300 a month, critical" | PASS |
| 2 | home_deposit | "save for a house deposit of £50,000 by June 2029, £800 a month, high" | PASS |
| 3 | holiday | "save £5,000 for a family holiday by August 2027, £200 a month, low" | PASS |
| 4 | wedding | "save £20,000 for my wedding by September 2028, £500 a month, high" | PASS |
| 5 | car_purchase | "save £15,000 for a new car by March 2028, £400 a month, medium" | PASS |
| 6 | education | "save £30,000 for daughter's university fees by September 2030, £500 a month, high" | PASS |
| 7 | debt_repayment | "pay off £8,000 credit card debt by December 2027, £400 a month, critical" | PASS |
| 8 | wealth_accumulation | "build £100,000 investment portfolio by 2035, £600 a month, medium" | PASS |
| 9 | custom | "save £3,000 for home office setup by June 2027, £150 a month, low" | PASS (after custom_goal_type_name fix) |
