<template>
  <div class="recommendations-section">
    <h4 class="text-md font-semibold text-horizon-500 mb-4">Personalized Recommendations</h4>

    <div v-if="!data || !data.all_recommendations || data.all_recommendations.length === 0" class="text-center py-8 text-neutral-500">
      <p>No recommendations available</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Summary Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white border border-light-gray rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Total Recommendations</p>
          <p class="text-3xl font-bold text-horizon-500">{{ data.total_count || 0 }}</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">High Priority</p>
          <p class="text-3xl font-bold text-raspberry-600">{{ data.high_priority_count || 0 }}</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Potential Annual Savings</p>
          <p class="text-2xl font-bold text-spring-600">£{{ formatNumber(data.total_potential_saving || 0) }}</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">10-Year Impact</p>
          <p class="text-2xl font-bold text-violet-600">£{{ formatNumber(data.ten_year_impact || 0) }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-3">
        <select
          v-model="categoryFilter"
          class="px-3 py-2 border border-horizon-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        >
          <option value="">All Categories</option>
          <option value="rebalancing">Rebalancing</option>
          <option value="tax">Tax</option>
          <option value="fees">Fees</option>
          <option value="risk">Risk</option>
          <option value="goal">Goal</option>
          <option value="contribution">Contribution</option>
        </select>

        <select
          v-model="priorityFilter"
          class="px-3 py-2 border border-horizon-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        >
          <option value="">All Priorities</option>
          <option value="high">High Priority</option>
          <option value="medium">Medium Priority</option>
          <option value="low">Low Priority</option>
        </select>

        <button
          @click="resetFilters"
          class="px-3 py-2 text-sm text-neutral-500 hover:text-horizon-500 transition-colors duration-200"
        >
          Reset Filters
        </button>
      </div>

      <!-- High Priority Recommendations -->
      <div v-if="filteredHighPriorityRecs.length > 0" class="mb-6">
        <h5 class="text-md font-semibold text-raspberry-800 mb-3 flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          High Priority Actions ({{ filteredHighPriorityRecs.length }})
        </h5>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in filteredHighPriorityRecs"
            :key="'high-' + index"
            class="bg-eggshell-500 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="flex justify-between items-start mb-2">
              <div class="flex-1">
                <div class="flex items-center mb-1">
                  <span class="px-2 py-1 text-xs font-semibold bg-savannah-200 text-neutral-500 rounded mr-2">
                    {{ rec.category }}
                  </span>
                  <span class="px-2 py-1 text-xs font-bold bg-raspberry-500 text-white rounded">HIGH</span>
                </div>
                <p class="text-md font-semibold text-horizon-500 mb-1">{{ rec.title }}</p>
                <p class="text-sm text-neutral-500">{{ rec.description }}</p>
              </div>
            </div>
            <div v-if="rec.action_required" class="mt-3 p-3 bg-white rounded-md border border-raspberry-200">
              <p class="text-sm text-horizon-500"><strong>Action:</strong> {{ rec.action_required }}</p>
            </div>
            <div v-if="rec.potential_saving" class="mt-3 flex items-center">
              <svg class="w-4 h-4 text-spring-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-sm font-semibold text-spring-600">
                Potential saving: £{{ formatNumber(rec.potential_saving) }}/year
              </span>
              <span v-if="rec.estimated_effort" class="ml-4 text-xs text-neutral-500">
                Effort: {{ rec.estimated_effort }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Medium Priority Recommendations -->
      <div v-if="filteredMediumPriorityRecs.length > 0" class="mb-6">
        <h5 class="text-md font-semibold text-violet-800 mb-3 flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          Medium Priority Actions ({{ filteredMediumPriorityRecs.length }})
        </h5>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in filteredMediumPriorityRecs"
            :key="'medium-' + index"
            class="bg-eggshell-500 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="flex justify-between items-start mb-2">
              <div class="flex-1">
                <div class="flex items-center mb-1">
                  <span class="px-2 py-1 text-xs font-semibold bg-savannah-200 text-neutral-500 rounded mr-2">
                    {{ rec.category }}
                  </span>
                  <span class="px-2 py-1 text-xs font-bold bg-raspberry-500 text-white rounded">MEDIUM</span>
                </div>
                <p class="text-md font-semibold text-horizon-500 mb-1">{{ rec.title }}</p>
                <p class="text-sm text-neutral-500">{{ rec.description }}</p>
              </div>
            </div>
            <div v-if="rec.action_required" class="mt-3 p-3 bg-white rounded-md border border-violet-200">
              <p class="text-sm text-horizon-500"><strong>Action:</strong> {{ rec.action_required }}</p>
            </div>
            <div v-if="rec.potential_saving" class="mt-3 flex items-center">
              <svg class="w-4 h-4 text-spring-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-sm font-semibold text-spring-600">
                Potential saving: £{{ formatNumber(rec.potential_saving) }}/year
              </span>
              <span v-if="rec.estimated_effort" class="ml-4 text-xs text-neutral-500">
                Effort: {{ rec.estimated_effort }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Priority Recommendations -->
      <div v-if="filteredLowPriorityRecs.length > 0">
        <h5 class="text-md font-semibold text-violet-800 mb-3 flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          Low Priority Actions ({{ filteredLowPriorityRecs.length }})
        </h5>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in filteredLowPriorityRecs"
            :key="'low-' + index"
            class="bg-eggshell-500 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="flex justify-between items-start mb-2">
              <div class="flex-1">
                <div class="flex items-center mb-1">
                  <span class="px-2 py-1 text-xs font-semibold bg-savannah-200 text-neutral-500 rounded mr-2">
                    {{ rec.category }}
                  </span>
                  <span class="px-2 py-1 text-xs font-bold bg-raspberry-500 text-white rounded">LOW</span>
                </div>
                <p class="text-md font-semibold text-horizon-500 mb-1">{{ rec.title }}</p>
                <p class="text-sm text-neutral-500">{{ rec.description }}</p>
              </div>
            </div>
            <div v-if="rec.action_required" class="mt-3 p-3 bg-white rounded-md border border-violet-200">
              <p class="text-sm text-horizon-500"><strong>Action:</strong> {{ rec.action_required }}</p>
            </div>
            <div v-if="rec.potential_saving" class="mt-3 flex items-center">
              <svg class="w-4 h-4 text-spring-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-sm font-semibold text-spring-600">
                Potential saving: £{{ formatNumber(rec.potential_saving) }}/year
              </span>
              <span v-if="rec.estimated_effort" class="ml-4 text-xs text-neutral-500">
                Effort: {{ rec.estimated_effort }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'RecommendationsSection',

  mixins: [currencyMixin],

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      categoryFilter: '',
      priorityFilter: '',
    };
  },

  computed: {
    filteredRecommendations() {
      if (!this.data?.all_recommendations) return [];

      let filtered = this.data.all_recommendations;

      if (this.categoryFilter) {
        filtered = filtered.filter(rec => rec.category === this.categoryFilter);
      }

      if (this.priorityFilter) {
        filtered = filtered.filter(rec => rec.priority === this.priorityFilter);
      }

      return filtered;
    },

    filteredHighPriorityRecs() {
      return this.filteredRecommendations.filter(rec => rec.priority === 'high');
    },

    filteredMediumPriorityRecs() {
      return this.filteredRecommendations.filter(rec => rec.priority === 'medium');
    },

    filteredLowPriorityRecs() {
      return this.filteredRecommendations.filter(rec => rec.priority === 'low');
    },
  },

  methods: {
    resetFilters() {
      this.categoryFilter = '';
      this.priorityFilter = '';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
