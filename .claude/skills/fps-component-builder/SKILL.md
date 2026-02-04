---
name: fps-component-builder
description: Guide Claude through creating Vue 3 components for the Fynla application following established patterns. Use this skill when the user requests to create specific UI components (e.g., "Create a chart component", "Build a form modal for...", "Add a data table component"). This skill provides patterns for dashboard cards, form modals (avoiding the @submit bug), charts (ApexCharts), data tables, and common UI elements with consistent Fynla styling.
---

# Fynla Component Builder

## Overview

Create Vue 3 components for the Fynla application following consistent patterns and best practices. This skill covers all common component types used across Fynla modules with comprehensive reference documentation.

## When to Use This Skill

Use this skill when creating **individual Vue components**:

- "Create a chart component for portfolio performance"
- "Build a form modal for adding properties"
- "Create a data table to display accounts"
- "Add a gauge component for coverage score"

**Do NOT use for**: Creating complete modules (use `fps-module-builder`) or adding features to existing modules (use `fps-feature-builder`).

## Component Types Overview

### 1. Dashboard Card Component
**Purpose**: Summary cards on main dashboard that navigate to module dashboards.
**Reference**: `references/component-patterns.md` (Type 1)
**Location**: `resources/js/components/{ModuleName}/{ModuleName}OverviewCard.vue`

### 2. Form Modal Component
**Purpose**: Create/edit forms in modal dialogs.
**⚠️ CRITICAL**: Never use `@submit` event name (use `@save` instead).
**Reference**: `references/form-modal-pattern.md`
**Location**: `resources/js/components/{ModuleName}/{FormName}Modal.vue`

### 3. Chart Components (ApexCharts)
**Purpose**: Data visualizations using ApexCharts.
**Types**: Radial Bar (Gauge), Donut, Line, Area, Bar, Waterfall, Heatmap, RangeBar (Timeline).
**Reference**: `references/chart-pattern.md`
**Location**: `resources/js/components/{ModuleName}/{ChartName}Chart.vue`

### 4. Data Table Component
**Purpose**: Display lists of data with CRUD actions.
**Reference**: `references/component-patterns.md` (Type 3)

### 5. Metric Card Component
**Purpose**: Display individual metrics with trend indicators.
**Reference**: `references/component-patterns.md` (Type 4)

### 6. Progress Bar Component
**Purpose**: Visual progress towards goals.
**Reference**: `references/component-patterns.md` (Type 5)

### 7. Badge Component
**Purpose**: Small status indicators.
**Reference**: `references/component-patterns.md` (Type 6)

## Workflow: Creating a Component

### Step 1: Identify Component Type
Determine which component pattern to use from the list above.

### Step 2: Load Reference Documentation
Load the appropriate reference:
- Dashboard cards, tables, metrics → `references/component-patterns.md`
- Form modals → `references/form-modal-pattern.md`
- Charts → `references/chart-pattern.md`

### Step 3: Create Component File
**Location**: `resources/js/components/{ModuleName}/{ComponentName}.vue`

**Structure**:
```vue
<template>
  <!-- HTML structure with Tailwind CSS -->
</template>

<script>
export default {
  name: 'ComponentName',
  props: { /* Full validation */ },
  data() { return {}; },
  computed: { /* Derived data */ },
  methods: { /* Component methods */ },
  mounted() { /* Initialization */ },
};
</script>
```

### Step 4: Implement Component Logic

**Props**: Always define with type, required, and validator.

**Computed properties**: Use for derived data and formatting.

**Methods**: Single responsibility, clear names, include formatting helpers.

**Events**: Use descriptive names. NEVER use `@submit` (use `@save` instead).

### Step 5: Style Component

**Prefer Tailwind CSS classes**. Only use `<style>` for truly custom styles.

**Responsive breakpoints**:
```vue
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
```

### Step 6: Add States

Always include:
- **Loading state**: Spinner/skeleton during async operations
- **Empty state**: Helpful message + call-to-action
- **Error state**: User-friendly error message

### Step 7: Test Component

1. Import in parent component
2. Pass required props
3. Handle emitted events
4. Test in browser (`npm run dev`)
5. Check responsive behavior
6. Test all states (loading, error, empty, content)

## Critical: The @submit Bug

**⚠️ NEVER use `@submit` as a custom event name.**

### Why This Matters

Using `@submit` as a custom event name causes double submissions because Vue catches BOTH:
1. Custom `$emit('submit')` events from the component
2. Native HTML form `submit` events

This results in:
- First submission: Sends correct form data
- Second submission: Sends SubmitEvent object
- Result: 422 validation errors, duplicate API calls

### Correct Pattern

```vue
<!-- Form Modal Component -->
<template>
  <form @submit.prevent="submitForm">
    <!-- fields -->
  </form>
</template>

<script>
methods: {
  submitForm() {
    if (!this.validateForm()) return;
    this.$emit('save', this.formData); // Use 'save' NOT 'submit'
  }
}
</script>

<!-- Parent Component -->
<template>
  <FormModal @save="handleSubmit" @close="closeModal" />
</template>

<script>
methods: {
  async handleSubmit(formData) {
    try {
      await this.$store.dispatch('module/save', formData);
      this.closeModal(); // Close AFTER successful save
    } catch (error) {
      this.$refs.formModal.handleError(error.message);
      // Modal stays open on error
    }
  }
}
</script>
```

## Form Modal Pattern

### Complete Pattern

See `references/form-modal-pattern.md` for full implementation.

**Key features**:
- Modal overlay with backdrop click to close
- Form validation before submission
- Error message display
- Loading state during submission
- `@save` event (NOT `@submit`)
- Parent handles success/error state
- `submitting` flag prevents double submission

**Form Fields**: See reference for patterns for text, number, select, date, checkbox, textarea.

**Validation**: Client-side validation with error display per field.

**Multi-step Forms**: See reference for multi-step form pattern with step indicator.

## Chart Patterns

### Available Chart Types

All charts use ApexCharts. See `references/chart-pattern.md` for complete examples.

1. **Radial Bar (Gauge)**: Scores, percentages (0-100%)
2. **Donut**: Asset allocation, breakdowns
3. **Line**: Performance over time, trends
4. **Area (Stacked)**: Projections, income sources
5. **Bar**: Comparisons, categorical data
6. **Waterfall**: Step-by-step calculations (IHT)
7. **Heatmap**: Risk matrices, gap analysis
8. **RangeBar (Timeline)**: Time-based events, gifting timeline

### Fynla Color Scheme

```javascript
const Fynla_COLORS = {
  primary: '#3B82F6',    // Blue
  success: '#10B981',    // Green
  warning: '#F59E0B',    // Amber
  danger: '#EF4444',     // Red
  purple: '#8B5CF6',
  pink: '#EC4899',
};
```

### Chart Best Practices

- Responsive design
- Currency/number formatting in tooltips and labels
- Loading states
- Empty states
- Export functionality (toolbar)
- Consistent color usage

## Best Practices

### Props Validation

```javascript
props: {
  amount: {
    type: Number,
    required: true,
    validator: (value) => value >= 0,
  },
  status: {
    type: String,
    default: 'pending',
    validator: (value) => ['pending', 'active', 'complete'].includes(value),
  },
}
```

### Computed Properties

```javascript
computed: {
  formattedAmount() {
    return new Intl.NumberFormat('en-GB', {
      style: 'currency',
      currency: 'GBP',
    }).format(this.amount);
  },
}
```

### Event Naming

```javascript
// Good
this.$emit('save', data);
this.$emit('delete', id);
this.$emit('filter-change', filters);

// Bad
this.$emit('submit', data);  // ❌ Conflicts with native form submit
this.$emit('click');         // ❌ Too generic
```

### Formatting Helpers

```javascript
methods: {
  formatCurrency(value) {
    return new Intl.NumberFormat('en-GB', {
      style: 'currency',
      currency: 'GBP',
    }).format(value);
  },

  formatNumber(value) {
    return new Intl.NumberFormat('en-GB').format(value);
  },

  formatDate(value) {
    return new Date(value).toLocaleDateString('en-GB');
  },

  formatPercent(value) {
    return `${value.toFixed(2)}%`;
  },
}
```

## References

### references/component-patterns.md
Complete patterns for:
1. Dashboard card component
2. Dashboard view (module dashboard with tabs)
3. Data table component
4. Metric card component
5. Progress bar component
6. Badge component

Load this reference for standard component patterns.

### references/form-modal-pattern.md
Complete form modal pattern including:
- Avoiding the @submit bug (critical)
- Parent/child communication
- Form validation patterns
- Error handling
- All form field types
- Multi-step forms

Load this reference when creating any form modal.

### references/chart-pattern.md
All ApexCharts patterns:
- Radial Bar (Gauge)
- Donut
- Line
- Area (Stacked)
- Bar
- Waterfall
- Heatmap
- RangeBar (Timeline)

Load this reference when creating any chart visualization.

## Key Principles

1. **Consistent Patterns**: Follow established component patterns
2. **Props Validation**: Always validate with types and validators
3. **Event Naming**: Descriptive names, NEVER use `@submit`
4. **Tailwind CSS**: Use utility classes for styling
5. **Responsive Design**: Mobile-first with breakpoints
6. **Loading States**: Always show loading indicators
7. **Empty States**: Provide helpful messages
8. **Error Handling**: Display user-friendly errors
9. **Format Values**: Use Intl formatters
10. **Color Consistency**: Use Fynla color scheme

## Common Pitfalls to Avoid

1. **Don't use `@submit`** - Use `@save` for custom form events
2. **Don't skip props validation** - Always define type, required, validator
3. **Don't hardcode colors** - Use Fynla color scheme
4. **Don't forget loading states** - Show spinners
5. **Don't skip empty states** - Provide helpful messages
6. **Don't use inline styles** - Prefer Tailwind classes
7. **Don't forget responsive design** - Test on different sizes
8. **Don't skip error states** - Handle errors gracefully
9. **Don't forget accessibility** - Use semantic HTML
10. **Don't overcomplicate** - Keep components focused

## Quick Reference: Component Selection

**Need a summary card?** → Dashboard Card (Type 1)
**Need a form?** → Form Modal (Type 2) - Use `@save` NOT `@submit`!
**Need a chart?** → Select chart type, see `references/chart-pattern.md`
**Need a data list?** → Data Table (Type 4)
**Need to show a metric?** → Metric Card (Type 5)
**Need progress indicator?** → Progress Bar (Type 6)
**Need a status label?** → Badge (Type 7)
