# Fynla Vue Component Patterns

## Overview

This document covers standard patterns for creating Vue 3 components in the Fynla application.

## Component Types

### 1. Dashboard Card Component

**Purpose**: Summary cards shown on main dashboard that navigate to detailed module views.

**Location**: `resources/js/components/{ModuleName}/{ModuleName}OverviewCard.vue`

**Pattern**:
```vue
<template>
  <div
    class="bg-white rounded-lg shadow-md p-6 cursor-pointer hover:shadow-lg transition-shadow duration-200"
    @click="navigateToModule"
  >
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xl font-semibold text-gray-800">{{ title }}</h3>
      <span class="text-2xl">{{ icon }}</span>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="metric">
        <p class="text-sm text-gray-600 mb-1">{{ metric1Label }}</p>
        <p class="text-2xl font-bold text-gray-900">{{ metric1Value }}</p>
      </div>
      <div class="metric">
        <p class="text-sm text-gray-600 mb-1">{{ metric2Label }}</p>
        <p class="text-2xl font-bold text-gray-900">{{ metric2Value }}</p>
      </div>
    </div>

    <div v-if="showStatus" class="mt-4 pt-4 border-t border-gray-200">
      <div class="flex items-center">
        <span
          class="h-3 w-3 rounded-full mr-2"
          :class="statusColor"
        ></span>
        <span class="text-sm text-gray-600">{{ statusText }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: '{ModuleName}OverviewCard',

  props: {
    title: {
      type: String,
      required: true,
    },
    data: {
      type: Object,
      required: true,
    },
    icon: {
      type: String,
      default: '📊',
    },
  },

  computed: {
    metric1Label() {
      return 'Key Metric 1';
    },

    metric1Value() {
      return this.formatCurrency(this.data.metric1);
    },

    metric2Label() {
      return 'Key Metric 2';
    },

    metric2Value() {
      return this.formatNumber(this.data.metric2);
    },

    showStatus() {
      return this.data.status !== undefined;
    },

    statusText() {
      return this.data.statusText || 'Good';
    },

    statusColor() {
      const status = this.data.status || 'good';
      return {
        'bg-green-500': status === 'good',
        'bg-yellow-500': status === 'warning',
        'bg-red-500': status === 'critical',
      };
    },
  },

  methods: {
    navigateToModule() {
      this.$router.push('/{module-name}');
    },

    formatCurrency(value) {
      if (value === null || value === undefined) return '—';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

    formatNumber(value) {
      if (value === null || value === undefined) return '—';
      return new Intl.NumberFormat('en-GB').format(value);
    },
  },
};
</script>
```

**Key features**:
- Clickable card with hover effect
- 2-3 key metrics displayed prominently
- Status indicator (traffic light system)
- Navigates to module dashboard on click
- Consistent styling with Tailwind CSS

---

### 2. Dashboard View (Module Dashboard)

**Purpose**: Main view for each module with tabbed interface.

**Location**: `resources/js/views/{ModuleName}/{ModuleName}Dashboard.vue`

**Pattern**:
```vue
<template>
  <AppLayout>
    <div class="container mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">{Module Name} Planning</h1>
        <p class="text-gray-600 mt-2">Manage your {module} planning</p>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800">{{ error }}</p>
      </div>

      <!-- Content -->
      <div v-else>
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
          <nav class="-mb-px flex space-x-8">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
              ]"
            >
              {{ tab.name }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
          <OverviewTab :data="data" />
        </div>

        <div v-if="activeTab === 'analysis'" class="space-y-6">
          <AnalysisTab :data="analysis" />
        </div>

        <div v-if="activeTab === 'recommendations'" class="space-y-6">
          <RecommendationsTab :recommendations="recommendations" />
        </div>

        <div v-if="activeTab === 'details'" class="space-y-6">
          <DetailsTab :data="data" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import { mapGetters, mapActions } from 'vuex';
import OverviewTab from '@/components/{ModuleName}/OverviewTab.vue';
import AnalysisTab from '@/components/{ModuleName}/AnalysisTab.vue';
import RecommendationsTab from '@/components/{ModuleName}/RecommendationsTab.vue';
import DetailsTab from '@/components/{ModuleName}/DetailsTab.vue';

export default {
  name: '{ModuleName}Dashboard',

  components: {
    AppLayout,
    OverviewTab,
    AnalysisTab,
    RecommendationsTab,
    DetailsTab,
  },

  data() {
    return {
      activeTab: 'overview',
      tabs: [
        { id: 'overview', name: 'Overview' },
        { id: 'analysis', name: 'Analysis' },
        { id: 'recommendations', name: 'Recommendations' },
        { id: 'details', name: 'Details' },
      ],
    };
  },

  computed: {
    ...mapGetters('{moduleName}', [
      'getData',
      'getAnalysis',
      'getRecommendations',
      'isLoading',
      'getError',
    ]),

    data() {
      return this.getData || [];
    },

    analysis() {
      return this.getAnalysis || null;
    },

    recommendations() {
      return this.getRecommendations || [];
    },

    error() {
      return this.getError;
    },
  },

  methods: {
    ...mapActions('{moduleName}', ['fetchData', 'analyze']),
  },

  mounted() {
    this.fetchData();
  },
};
</script>
```

**Key features**:
- AppLayout wrapper for consistent page structure
- Tab navigation for different views
- Loading and error states
- Vuex integration for state management
- Fetch data on mount

---

### 3. Data Table Component

**Purpose**: Display lists of data with CRUD actions.

**Location**: `resources/js/components/{ModuleName}/DataTable.vue`

**Pattern**:
```vue
<template>
  <div class="bg-white rounded-lg shadow">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
      <button
        @click="$emit('add')"
        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
      >
        + Add New
      </button>
    </div>

    <!-- Empty State -->
    <div v-if="items.length === 0" class="px-6 py-12 text-center">
      <p class="text-gray-500 mb-4">{{ emptyMessage }}</p>
      <button
        @click="$emit('add')"
        class="text-blue-600 hover:text-blue-700 font-medium"
      >
        Add your first item
      </button>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th
              v-for="column in columns"
              :key="column.key"
              class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              {{ column.label }}
            </th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
            <td
              v-for="column in columns"
              :key="column.key"
              class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
            >
              {{ formatValue(item[column.key], column.format) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button
                @click="$emit('edit', item)"
                class="text-blue-600 hover:text-blue-900 mr-4"
              >
                Edit
              </button>
              <button
                @click="$emit('delete', item)"
                class="text-red-600 hover:text-red-900"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DataTable',

  props: {
    title: {
      type: String,
      required: true,
    },
    items: {
      type: Array,
      required: true,
    },
    columns: {
      type: Array,
      required: true,
      // Example: [{ key: 'name', label: 'Name', format: 'text' }]
    },
    emptyMessage: {
      type: String,
      default: 'No items found',
    },
  },

  methods: {
    formatValue(value, format) {
      if (value === null || value === undefined) return '—';

      switch (format) {
        case 'currency':
          return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
          }).format(value);

        case 'number':
          return new Intl.NumberFormat('en-GB').format(value);

        case 'percent':
          return `${value}%`;

        case 'date':
          return new Date(value).toLocaleDateString('en-GB');

        default:
          return value;
      }
    },
  },
};
</script>
```

**Key features**:
- Dynamic columns configuration
- Empty state with CTA
- Format values based on column type
- Emit events for CRUD actions
- Responsive table with overflow scroll

---

### 4. Metric Card Component

**Purpose**: Display individual metrics with optional trend indicators.

**Location**: `resources/js/components/Common/MetricCard.vue`

**Pattern**:
```vue
<template>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-2">
      <h4 class="text-sm font-medium text-gray-600">{{ label }}</h4>
      <span v-if="icon" class="text-2xl">{{ icon }}</span>
    </div>

    <div class="flex items-baseline justify-between">
      <p class="text-3xl font-bold text-gray-900">{{ formattedValue }}</p>

      <div v-if="showTrend" class="flex items-center ml-2">
        <span
          :class="[
            'text-sm font-medium',
            trendDirection === 'up' ? 'text-green-600' : 'text-red-600',
          ]"
        >
          {{ trendDirection === 'up' ? '↑' : '↓' }}
          {{ Math.abs(trend) }}%
        </span>
      </div>
    </div>

    <p v-if="subtitle" class="text-sm text-gray-500 mt-2">{{ subtitle }}</p>
  </div>
</template>

<script>
export default {
  name: 'MetricCard',

  props: {
    label: {
      type: String,
      required: true,
    },
    value: {
      type: [Number, String],
      required: true,
    },
    format: {
      type: String,
      default: 'number',
      validator: (value) => ['number', 'currency', 'percent', 'text'].includes(value),
    },
    trend: {
      type: Number,
      default: null,
    },
    icon: {
      type: String,
      default: null,
    },
    subtitle: {
      type: String,
      default: null,
    },
  },

  computed: {
    formattedValue() {
      switch (this.format) {
        case 'currency':
          return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
          }).format(this.value);

        case 'number':
          return new Intl.NumberFormat('en-GB').format(this.value);

        case 'percent':
          return `${this.value}%`;

        default:
          return this.value;
      }
    },

    showTrend() {
      return this.trend !== null && this.trend !== undefined;
    },

    trendDirection() {
      return this.trend >= 0 ? 'up' : 'down';
    },
  },
};
</script>
```

**Key features**:
- Flexible formatting (currency, number, percent, text)
- Optional trend indicator
- Optional icon
- Optional subtitle for context
- Consistent card styling

---

### 5. Progress Bar Component

**Purpose**: Visual indicator of progress towards a goal.

**Location**: `resources/js/components/Common/ProgressBar.vue`

**Pattern**:
```vue
<template>
  <div class="progress-bar">
    <div class="flex justify-between items-center mb-2">
      <span class="text-sm font-medium text-gray-700">{{ label }}</span>
      <span class="text-sm font-medium text-gray-900">
        {{ formattedCurrent }} / {{ formattedTotal }}
      </span>
    </div>

    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
      <div
        class="h-full rounded-full transition-all duration-500 ease-out"
        :class="progressColor"
        :style="{ width: `${percentage}%` }"
      ></div>
    </div>

    <p v-if="subtitle" class="text-xs text-gray-500 mt-1">{{ subtitle }}</p>
  </div>
</template>

<script>
export default {
  name: 'ProgressBar',

  props: {
    label: {
      type: String,
      required: true,
    },
    current: {
      type: Number,
      required: true,
    },
    total: {
      type: Number,
      required: true,
    },
    format: {
      type: String,
      default: 'currency',
    },
    subtitle: {
      type: String,
      default: null,
    },
  },

  computed: {
    percentage() {
      if (this.total === 0) return 0;
      return Math.min(100, (this.current / this.total) * 100);
    },

    progressColor() {
      if (this.percentage >= 100) return 'bg-green-500';
      if (this.percentage >= 75) return 'bg-blue-500';
      if (this.percentage >= 50) return 'bg-yellow-500';
      return 'bg-red-500';
    },

    formattedCurrent() {
      return this.formatValue(this.current);
    },

    formattedTotal() {
      return this.formatValue(this.total);
    },
  },

  methods: {
    formatValue(value) {
      switch (this.format) {
        case 'currency':
          return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
          }).format(value);

        case 'number':
          return new Intl.NumberFormat('en-GB').format(value);

        default:
          return value;
      }
    },
  },
};
</script>
```

**Key features**:
- Dynamic width based on percentage
- Color coding (traffic light system)
- Formatted current/total values
- Optional subtitle
- Smooth transitions

---

### 6. Badge Component

**Purpose**: Small status indicators for labels and tags.

**Location**: `resources/js/components/Common/Badge.vue`

**Pattern**:
```vue
<template>
  <span
    :class="[
      'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium',
      colorClasses,
    ]"
  >
    {{ text }}
  </span>
</template>

<script>
export default {
  name: 'Badge',

  props: {
    text: {
      type: String,
      required: true,
    },
    variant: {
      type: String,
      default: 'default',
      validator: (value) =>
        ['default', 'success', 'warning', 'danger', 'info'].includes(value),
    },
  },

  computed: {
    colorClasses() {
      const variants = {
        default: 'bg-gray-100 text-gray-800',
        success: 'bg-green-100 text-green-800',
        warning: 'bg-yellow-100 text-yellow-800',
        danger: 'bg-red-100 text-red-800',
        info: 'bg-blue-100 text-blue-800',
      };

      return variants[this.variant];
    },
  },
};
</script>
```

**Example usage**:
```vue
<Badge text="Emergency Fund" variant="success" />
<Badge text="ISA" variant="info" />
<Badge text="Overdrawn" variant="danger" />
```

---

## Component Best Practices

### 1. Props Validation

Always define props with full specifications:

```javascript
props: {
  title: {
    type: String,
    required: true,
  },
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

### 2. Computed Properties

Use computed properties for derived data:

```javascript
computed: {
  formattedAmount() {
    return this.formatCurrency(this.amount);
  },

  isComplete() {
    return this.status === 'complete';
  },
}
```

### 3. Event Naming

Use descriptive, specific event names:

```javascript
// Good
this.$emit('save', formData);
this.$emit('delete', itemId);
this.$emit('close');

// Bad
this.$emit('submit', formData);  // Conflicts with native form submit
this.$emit('click');             // Too generic
```

### 4. Component Organization

Follow consistent order in component options:

```javascript
export default {
  name: 'ComponentName',        // 1. Name
  components: {},               // 2. Components
  props: {},                    // 3. Props
  data() { return {}; },        // 4. Data
  computed: {},                 // 5. Computed
  watch: {},                    // 6. Watch
  methods: {},                  // 7. Methods
  mounted() {},                 // 8. Lifecycle hooks
};
```

### 5. Formatting Helpers

Create reusable formatting methods:

```javascript
methods: {
  formatCurrency(value) {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('en-GB', {
      style: 'currency',
      currency: 'GBP',
    }).format(value);
  },

  formatNumber(value) {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('en-GB').format(value);
  },

  formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-GB');
  },

  formatPercent(value) {
    if (value === null || value === undefined) return '—';
    return `${value.toFixed(2)}%`;
  },
}
```

### 6. Responsive Design

Use Tailwind responsive classes:

```vue
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  <!-- Content adapts to screen size -->
</div>
```

### 7. Loading States

Always show loading indicators:

```vue
<div v-if="isLoading" class="flex justify-center items-center py-12">
  <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
</div>
```

### 8. Error Handling

Display user-friendly error messages:

```vue
<div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
  <p class="text-red-800">{{ error }}</p>
</div>
```

### 9. Empty States

Provide helpful empty states:

```vue
<div v-if="items.length === 0" class="text-center py-12">
  <p class="text-gray-500 mb-4">You haven't added any items yet</p>
  <button @click="openAddModal" class="btn-primary">
    Add Your First Item
  </button>
</div>
```
