<template>
  <div class="recommendation-filters">
    <div class="filter-group">
      <label for="module-filter">Module</label>
      <select
        id="module-filter"
        :value="module"
        @change="$emit('update:module', $event.target.value)"
      >
        <option value="">All Modules</option>
        <option value="protection">Protection</option>
        <option value="savings">Savings</option>
        <option value="investment">Investment</option>
        <option value="retirement">Retirement</option>
        <option value="estate">Estate</option>
        <option value="property">Property</option>
      </select>
    </div>

    <div class="filter-group">
      <label for="priority-filter">Priority</label>
      <select
        id="priority-filter"
        :value="priority"
        @change="$emit('update:priority', $event.target.value)"
      >
        <option value="">All Priorities</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>
    </div>

    <div class="filter-group">
      <label for="timeline-filter">Timeline</label>
      <select
        id="timeline-filter"
        :value="timeline"
        @change="$emit('update:timeline', $event.target.value)"
      >
        <option value="">All Timelines</option>
        <option value="immediate">Immediate</option>
        <option value="short_term">Short Term</option>
        <option value="medium_term">Medium Term</option>
        <option value="long_term">Long Term</option>
      </select>
    </div>

    <div class="filter-actions">
      <button @click="$emit('filter')" class="apply-btn">Apply Filters</button>
      <button @click="clearFilters" class="clear-btn">Clear</button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RecommendationFilters',

  props: {
    module: {
      type: String,
      default: '',
    },
    priority: {
      type: String,
      default: '',
    },
    timeline: {
      type: String,
      default: '',
    },
  },

  emits: ['update:module', 'update:priority', 'update:timeline', 'filter'],

  methods: {
    clearFilters() {
      this.$emit('update:module', '');
      this.$emit('update:priority', '');
      this.$emit('update:timeline', '');
      this.$emit('filter');
    },
  },
};
</script>

<style scoped>
.recommendation-filters {
  display: flex;
  gap: 16px;
  padding: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
  min-width: 150px;
}

.filter-group label {
  font-size: 14px;
  font-weight: 500;
  @apply text-neutral-500;
}

.filter-group select {
  padding: 8px 12px;
  @apply border border-horizon-300;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  cursor: pointer;
}

.filter-group select:focus {
  outline: none;
  @apply border-violet-600;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.filter-actions {
  display: flex;
  gap: 8px;
  align-items: flex-end;
}

.apply-btn,
.clear-btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
}

.apply-btn {
  @apply bg-raspberry-600;
  color: white;
}

.clear-btn {
  @apply bg-savannah-100 text-neutral-500;
}

@media (max-width: 768px) {
  .recommendation-filters {
    flex-direction: column;
  }

  .filter-group {
    width: 100%;
  }

  .filter-actions {
    width: 100%;
  }

  .apply-btn,
  .clear-btn {
    flex: 1;
  }
}
</style>
