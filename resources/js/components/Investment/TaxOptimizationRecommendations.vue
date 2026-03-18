<template>
  <div class="tax-optimization-recommendations">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Tax Optimisation Recommendations</h3>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <select
        v-model="priorityFilter"
        class="px-3 py-2 border border-horizon-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        @change="filterRecommendations"
      >
        <option value="">All Priorities</option>
        <option value="high">High Priority</option>
        <option value="medium">Medium Priority</option>
        <option value="low">Low Priority</option>
      </select>

      <select
        v-model="typeFilter"
        class="px-3 py-2 border border-horizon-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        @change="filterRecommendations"
      >
        <option value="">All Types</option>
        <option value="isa">ISA</option>
        <option value="cgt">Capital Gains Tax Harvesting</option>
        <option value="bed_and_isa">Bed & ISA</option>
        <option value="dividend">Dividend</option>
      </select>

      <button
        @click="resetFilters"
        class="px-3 py-2 text-sm text-neutral-500 hover:text-horizon-500 transition-colors duration-200"
      >
        Reset Filters
      </button>
    </div>

    <!-- No Data State -->
    <div v-if="!recommendations || recommendations.recommendations.length === 0" class="text-center py-12 text-neutral-500">
      <svg class="mx-auto h-12 w-12 text-horizon-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p>No recommendations found</p>
      <p class="text-sm mt-2">Try adjusting your filters or refresh the analysis</p>
    </div>

    <!-- Recommendations List -->
    <div v-else class="space-y-4">
      <!-- Summary Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-light-gray rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Total Recommendations</p>
          <p class="text-3xl font-bold text-horizon-500">{{ recommendations.count }}</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Potential Annual Savings</p>
          <p class="text-3xl font-bold text-spring-600">
            £{{ formatNumber(recommendations.potential_savings?.annual || 0) }}
          </p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">10-Year Projection</p>
          <p class="text-3xl font-bold text-violet-600">
            £{{ formatNumber(recommendations.potential_savings?.ten_year || 0) }}
          </p>
        </div>
      </div>

      <!-- High Priority Recommendations -->
      <div v-if="highPriorityRecs.length > 0" class="mb-6">
        <h4 class="text-md font-semibold text-raspberry-800 mb-3 flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          High Priority Actions ({{ highPriorityRecs.length }})
        </h4>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in highPriorityRecs"
            :key="'high-' + index"
            class="bg-eggshell-500 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="flex justify-between items-start mb-2">
              <div class="flex-1">
                <p class="text-md font-semibold text-horizon-500 mb-1">{{ rec.action }}</p>
                <p class="text-sm text-neutral-500">{{ rec.reason }}</p>
              </div>
              <span class="px-3 py-1 text-xs font-bold bg-raspberry-500 text-white rounded-full ml-3">HIGH</span>
            </div>
            <div v-if="rec.potential_saving || rec.tax_saving" class="mt-3 flex items-center">
              <svg class="w-4 h-4 text-spring-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-sm font-semibold text-spring-600">
                Potential saving: £{{ formatNumber(rec.potential_saving || rec.tax_saving) }}/year
              </span>
            </div>
            <div v-if="rec.notes && rec.notes.length > 0" class="mt-3 space-y-1">
              <p class="text-xs text-neutral-500" v-for="(note, i) in rec.notes" :key="i">💡 {{ note }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Medium Priority Recommendations -->
      <div v-if="mediumPriorityRecs.length > 0" class="mb-6">
        <h4 class="text-md font-semibold text-violet-800 mb-3 flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          Medium Priority Actions ({{ mediumPriorityRecs.length }})
        </h4>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in mediumPriorityRecs"
            :key="'medium-' + index"
            class="bg-eggshell-500 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="flex justify-between items-start mb-2">
              <div class="flex-1">
                <p class="text-md font-semibold text-horizon-500 mb-1">{{ rec.action }}</p>
                <p class="text-sm text-neutral-500">{{ rec.reason }}</p>
              </div>
              <span class="px-3 py-1 text-xs font-bold bg-raspberry-500 text-white rounded-full ml-3">MEDIUM</span>
            </div>
            <div v-if="rec.potential_saving || rec.tax_saving" class="mt-3 flex items-center">
              <svg class="w-4 h-4 text-spring-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-sm font-semibold text-spring-600">
                Potential saving: £{{ formatNumber(rec.potential_saving || rec.tax_saving) }}/year
              </span>
            </div>
            <div v-if="rec.notes && rec.notes.length > 0" class="mt-3 space-y-1">
              <p class="text-xs text-neutral-500" v-for="(note, i) in rec.notes" :key="i">💡 {{ note }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Priority Recommendations -->
      <div v-if="lowPriorityRecs.length > 0">
        <h4 class="text-md font-semibold text-violet-800 mb-3 flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          Low Priority Actions ({{ lowPriorityRecs.length }})
        </h4>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in lowPriorityRecs"
            :key="'low-' + index"
            class="bg-eggshell-500 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="flex justify-between items-start mb-2">
              <div class="flex-1">
                <p class="text-md font-semibold text-horizon-500 mb-1">{{ rec.action }}</p>
                <p class="text-sm text-neutral-500">{{ rec.reason }}</p>
              </div>
              <span class="px-3 py-1 text-xs font-bold bg-raspberry-500 text-white rounded-full ml-3">LOW</span>
            </div>
            <div v-if="rec.potential_saving || rec.tax_saving" class="mt-3 flex items-center">
              <svg class="w-4 h-4 text-spring-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-sm font-semibold text-spring-600">
                Potential saving: £{{ formatNumber(rec.potential_saving || rec.tax_saving) }}/year
              </span>
            </div>
            <div v-if="rec.notes && rec.notes.length > 0" class="mt-3 space-y-1">
              <p class="text-xs text-neutral-500" v-for="(note, i) in rec.notes" :key="i">💡 {{ note }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="mt-6 flex justify-end">
      <button
        @click="$emit('refresh')"
        class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors duration-200"
      >
        Refresh Recommendations
      </button>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TaxOptimizationRecommendations',

  mixins: [currencyMixin],

  emits: ['refresh', 'filter'],

  props: {
    recommendations: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      priorityFilter: '',
      typeFilter: '',
    };
  },

  computed: {
    highPriorityRecs() {
      if (!this.recommendations?.recommendations) return [];
      return this.recommendations.recommendations.filter(rec => rec.priority === 'high');
    },

    mediumPriorityRecs() {
      if (!this.recommendations?.recommendations) return [];
      return this.recommendations.recommendations.filter(rec => rec.priority === 'medium');
    },

    lowPriorityRecs() {
      if (!this.recommendations?.recommendations) return [];
      return this.recommendations.recommendations.filter(rec => rec.priority === 'low');
    },
  },

  methods: {
    filterRecommendations() {
      // Emit filter event to parent
      this.$emit('filter', {
        priority: this.priorityFilter,
        type: this.typeFilter,
      });
    },

    resetFilters() {
      this.priorityFilter = '';
      this.typeFilter = '';
      this.filterRecommendations();
    },
  },
};
</script>

<style scoped>
/* Add any scoped styles here if needed */
</style>
