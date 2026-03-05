<template>
  <div class="goals-list">
    <!-- Header with Filters and Add Button -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
      <div class="flex flex-wrap gap-2">
        <!-- Status Filter -->
        <select
          v-model="localFilters.status"
          @change="emitFilters"
          class="px-3 py-2 text-sm border border-horizon-300 rounded-lg focus:ring-violet-500 focus:border-raspberry-500"
        >
          <option value="all">All Status</option>
          <option value="active">Active</option>
          <option value="on_track">On Track</option>
          <option value="behind">Behind Schedule</option>
          <option value="completed">Completed</option>
          <option value="paused">Paused</option>
        </select>

        <!-- Module Filter -->
        <select
          v-model="localFilters.module"
          @change="emitFilters"
          class="px-3 py-2 text-sm border border-horizon-300 rounded-lg focus:ring-violet-500 focus:border-raspberry-500"
        >
          <option value="all">All Modules</option>
          <option value="savings">Savings</option>
          <option value="investment">Investment</option>
          <option value="property">Property</option>
          <option value="retirement">Retirement</option>
        </select>

        <!-- Priority Filter -->
        <select
          v-model="localFilters.priority"
          @change="emitFilters"
          class="px-3 py-2 text-sm border border-horizon-300 rounded-lg focus:ring-violet-500 focus:border-raspberry-500"
        >
          <option value="all">All Priority</option>
          <option value="critical">Critical</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
      </div>

      <div class="flex gap-2 w-full sm:w-auto">
        <!-- Search -->
        <div class="relative flex-1 sm:flex-none">
          <input
            v-model="localFilters.search"
            @input="emitFilters"
            type="text"
            placeholder="Search goals..."
            class="w-full sm:w-48 px-3 py-2 pl-9 text-sm border border-horizon-300 rounded-lg focus:ring-violet-500 focus:border-raspberry-500"
          />
          <svg class="absolute left-3 top-2.5 w-4 h-4 text-horizon-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Add Button -->
        <button
          @click="$emit('create-goal')"
          class="px-4 py-2 text-sm font-medium text-white bg-raspberry-600 rounded-button hover:bg-raspberry-700 transition-colors flex items-center gap-2 whitespace-nowrap"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Goal
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="goals.length === 0" class="text-center py-12">
      <div class="mx-auto w-12 h-12 rounded-full bg-savannah-100 flex items-center justify-center mb-4">
        <svg class="w-6 h-6 text-horizon-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
      </div>
      <h3 class="text-lg font-medium text-horizon-500 mb-1">No goals found</h3>
      <p class="text-neutral-500 mb-4">
        {{ hasActiveFilters ? 'Try adjusting your filters' : 'Create your first goal to get started' }}
      </p>
      <button
        v-if="!hasActiveFilters"
        @click="$emit('create-goal')"
        class="px-4 py-2 text-sm font-medium text-white bg-raspberry-600 rounded-button hover:bg-raspberry-700 transition-colors"
      >
        Create Goal
      </button>
    </div>

    <!-- Goals Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <GoalCard
        v-for="goal in goals"
        :key="goal.id"
        :goal="goal"
        :dependency-count="goal.dependency_count || 0"
        :is-blocked="goal.is_blocked || false"
        @view="$emit('view-goal', goal)"
        @edit="$emit('edit-goal', goal)"
        @delete="$emit('delete-goal', goal)"
        @add-contribution="$emit('add-contribution', goal)"
      />
    </div>

    <!-- Results Count -->
    <div v-if="goals.length > 0" class="mt-4 text-sm text-neutral-500">
      Showing {{ goals.length }} {{ goals.length === 1 ? 'goal' : 'goals' }}
    </div>
  </div>
</template>

<script>
import GoalCard from '@/components/Goals/GoalCard.vue';

export default {
  name: 'GoalsList',

  components: {
    GoalCard,
  },

  props: {
    goals: {
      type: Array,
      default: () => [],
    },
    filters: {
      type: Object,
      default: () => ({
        status: 'all',
        module: 'all',
        priority: 'all',
        search: '',
      }),
    },
  },

  emits: ['update-filters', 'view-goal', 'edit-goal', 'delete-goal', 'add-contribution', 'create-goal'],

  data() {
    return {
      localFilters: { ...this.filters },
    };
  },

  computed: {
    hasActiveFilters() {
      return (
        this.localFilters.status !== 'all' ||
        this.localFilters.module !== 'all' ||
        this.localFilters.priority !== 'all' ||
        this.localFilters.search !== ''
      );
    },
  },

  watch: {
    filters(newFilters) {
      this.localFilters = { ...newFilters };
    },
  },

  methods: {
    emitFilters() {
      this.$emit('update-filters', { ...this.localFilters });
    },
  },
};
</script>
