<template>
  <AppLayout>
    <div class="py-2 sm:py-6">
      <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
          <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500 mb-2">Actions</h1>
          <p class="text-neutral-500">
            Personalised financial planning recommendations from all modules
          </p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-lg shadow-sm border border-light-gray p-4">
            <div class="text-2xl font-bold text-horizon-500">{{ summary.total_count }}</div>
            <div class="text-sm text-neutral-500">Total</div>
          </div>
          <div class="bg-white rounded-lg shadow-sm border border-light-gray p-4">
            <div class="text-2xl font-bold text-raspberry-500">{{ summary.by_priority.high }}</div>
            <div class="text-sm text-neutral-500">High Priority</div>
          </div>
          <div class="bg-white rounded-lg shadow-sm border border-light-gray p-4">
            <div class="text-2xl font-bold text-violet-500">{{ summary.by_priority.medium }}</div>
            <div class="text-sm text-neutral-500">Medium Priority</div>
          </div>
          <div class="bg-white rounded-lg shadow-sm border border-light-gray p-4">
            <div class="text-2xl font-bold text-spring-500">{{ summary.by_priority.low }}</div>
            <div class="text-sm text-neutral-500">Low Priority</div>
          </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-light-gray p-4 mb-6">
          <div class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[150px]">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Module</label>
              <select
                v-model="filters.module"
                class="w-full rounded-md border border-light-gray px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
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
            <div class="flex-1 min-w-[150px]">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Priority</label>
              <select
                v-model="filters.priority"
                class="w-full rounded-md border border-light-gray px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
              >
                <option value="">All Priorities</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>
            <div class="flex-1 min-w-[150px]">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Timeline</label>
              <select
                v-model="filters.timeline"
                class="w-full rounded-md border border-light-gray px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
              >
                <option value="">All Timelines</option>
                <option value="immediate">Immediate</option>
                <option value="short_term">Short Term</option>
                <option value="medium_term">Medium Term</option>
                <option value="long_term">Long Term</option>
              </select>
            </div>
            <div class="flex gap-2">
              <button
                @click="applyFilters"
                class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-md hover:bg-raspberry-600 transition-colors"
              >
                Apply
              </button>
              <button
                @click="clearFilters"
                class="px-4 py-2 bg-savannah-100 text-neutral-500 text-sm font-medium rounded-md hover:bg-savannah-200 transition-colors"
              >
                Clear
              </button>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
          <div class="text-center">
            <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
            <p class="mt-4 text-sm text-neutral-500">Loading actions...</p>
          </div>
        </div>

        <!-- Error State -->
        <div
          v-else-if="error"
          class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-4 mb-6"
        >
          <div class="flex items-center">
            <svg class="w-5 h-5 text-raspberry-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm text-raspberry-700">{{ error }}</p>
            <button @click="fetchRecommendations" class="ml-auto text-sm text-raspberry-600 hover:text-raspberry-700 font-medium">Retry</button>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="filteredRecommendations.length === 0"
          class="bg-white rounded-lg shadow-sm border border-light-gray p-12 text-center"
        >
          <svg class="w-12 h-12 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-neutral-500 mb-2">No actions match your filters</p>
          <button
            v-if="hasActiveFilters"
            @click="clearFilters"
            class="text-sm text-raspberry-500 hover:text-raspberry-600 font-medium"
          >
            Clear filters
          </button>
        </div>

        <!-- Recommendations List -->
        <div v-else class="space-y-4">
          <div
            v-for="rec in filteredRecommendations"
            :key="rec.recommendation_id"
            class="bg-white rounded-lg shadow-sm border border-light-gray p-5 hover:shadow-md transition-shadow"
          >
            <!-- Header badges -->
            <div class="flex flex-wrap gap-2 mb-3">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                :class="priorityClass(rec.impact)"
              >
                {{ capitalise(rec.impact) }}
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-horizon-100 text-horizon-700">
                {{ capitalise(rec.module) }}
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-savannah-100 text-neutral-500">
                {{ formatTimeline(rec.timeline) }}
              </span>
              <span
                v-if="rec.status && rec.status !== 'pending'"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                :class="statusClass(rec.status)"
              >
                {{ formatStatus(rec.status) }}
              </span>
            </div>

            <!-- Recommendation text -->
            <p class="text-sm text-horizon-500 mb-3">{{ rec.recommendation_text }}</p>

            <!-- Financials -->
            <div v-if="rec.estimated_cost || rec.potential_benefit" class="flex gap-4 mb-4 text-sm">
              <div v-if="rec.estimated_cost" class="text-raspberry-600">
                Estimated cost: {{ formatCurrency(rec.estimated_cost) }}
              </div>
              <div v-if="rec.potential_benefit" class="text-spring-600">
                Potential benefit: {{ formatCurrency(rec.potential_benefit) }}
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-2">
              <button
                v-if="rec.status !== 'completed'"
                @click="markDone(rec)"
                class="px-3 py-1.5 bg-spring-500 text-white text-xs font-medium rounded-md hover:bg-spring-600 transition-colors"
              >
                Mark Done
              </button>
              <button
                v-if="rec.status !== 'in_progress' && rec.status !== 'completed'"
                @click="markInProgress(rec)"
                class="px-3 py-1.5 bg-violet-100 text-violet-700 text-xs font-medium rounded-md hover:bg-violet-200 transition-colors"
              >
                In Progress
              </button>
              <button
                v-if="rec.status !== 'dismissed'"
                @click="dismiss(rec)"
                class="px-3 py-1.5 text-neutral-500 text-xs font-medium hover:text-horizon-500 transition-colors"
              >
                Dismiss
              </button>
            </div>
          </div>
        </div>

        <!-- Spousal Optimisations Section (married users only) -->
        <section v-if="isMarriedWithSpouse" class="mt-8">
          <h2 class="text-xl font-bold text-horizon-500 mb-2">Spousal Optimisations</h2>
          <p class="text-sm text-neutral-500 mb-4">
            Strategies to optimise your household's financial position through asset transfers, allowance sharing, and tax-efficient arrangements between you and your partner.
          </p>
          <SpousalOptimisations />
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import SpousalOptimisations from '@/components/Dashboard/SpousalOptimisations.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ActionsDashboard',

  components: {
    AppLayout,
    SpousalOptimisations,
  },

  mixins: [currencyMixin],

  data() {
    return {
      filters: {
        module: '',
        priority: '',
        timeline: '',
      },
    };
  },

  computed: {
    ...mapState('recommendations', ['recommendations', 'summary', 'loading', 'error']),
    ...mapGetters('auth', ['currentUser']),

    isMarriedWithSpouse() {
      const user = this.currentUser;
      return user && user.marital_status === 'married' && user.spouse_id;
    },

    filteredRecommendations() {
      return this.recommendations.filter(rec => {
        if (this.filters.module && rec.module !== this.filters.module) return false;
        if (this.filters.priority && rec.impact !== this.filters.priority) return false;
        if (this.filters.timeline && rec.timeline !== this.filters.timeline) return false;
        return true;
      });
    },

    hasActiveFilters() {
      return this.filters.module || this.filters.priority || this.filters.timeline;
    },
  },

  mounted() {
    this.fetchRecommendations();
    this.fetchSummary();
  },

  methods: {
    ...mapActions('recommendations', [
      'fetchRecommendations',
      'fetchSummary',
      'markRecommendationDone',
      'markRecommendationInProgress',
      'dismissRecommendation',
    ]),

    applyFilters() {
      const params = {};
      if (this.filters.module) params.module = this.filters.module;
      if (this.filters.priority) params.priority = this.filters.priority;
      if (this.filters.timeline) params.timeline = this.filters.timeline;
      this.fetchRecommendations(params);
    },

    clearFilters() {
      this.filters = { module: '', priority: '', timeline: '' };
      this.fetchRecommendations();
    },

    async markDone(rec) {
      await this.markRecommendationDone(rec);
      this.fetchRecommendations();
      this.fetchSummary();
    },

    async markInProgress(rec) {
      await this.markRecommendationInProgress(rec);
      this.fetchRecommendations();
      this.fetchSummary();
    },

    async dismiss(rec) {
      await this.dismissRecommendation(rec);
      this.fetchRecommendations();
      this.fetchSummary();
    },

    priorityClass(impact) {
      const classes = {
        high: 'bg-raspberry-100 text-raspberry-700',
        medium: 'bg-violet-100 text-violet-700',
        low: 'bg-spring-100 text-spring-700',
      };
      return classes[impact] || 'bg-savannah-100 text-neutral-500';
    },

    statusClass(status) {
      const classes = {
        in_progress: 'bg-violet-100 text-violet-700',
        completed: 'bg-spring-100 text-spring-700',
        dismissed: 'bg-savannah-100 text-neutral-500',
      };
      return classes[status] || '';
    },

    capitalise(str) {
      if (!str) return '';
      return str.charAt(0).toUpperCase() + str.slice(1);
    },

    formatTimeline(timeline) {
      if (!timeline) return '';
      return timeline.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },

    formatStatus(status) {
      if (!status) return '';
      return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
  },
};
</script>
