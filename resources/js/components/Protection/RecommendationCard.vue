<template>
  <div
    class="recommendation-card bg-white rounded-lg border shadow-sm transition-all duration-200"
    :class="[
      borderColourClass,
      isExpanded ? 'shadow-md' : 'hover:shadow-md hover:-translate-y-0.5',
    ]"
  >
    <!-- Card Header -->
    <div
      class="p-4 cursor-pointer"
      @click="isExpanded = !isExpanded"
    >
      <div class="flex items-start justify-between">
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-2">
            <span
              class="px-3 py-1 text-xs font-semibold rounded-full"
              :class="priorityBadgeClass"
            >
              {{ recommendation.priority?.toUpperCase() }}
            </span>
            <span class="text-sm text-gray-600">
              {{ recommendation.category }}
            </span>
          </div>
          <h4 class="text-lg font-semibold text-gray-900 mb-2">
            {{ recommendation.action }}
          </h4>
          <p v-if="!isExpanded" class="text-sm text-gray-600 line-clamp-2">
            {{ recommendation.rationale }}
          </p>
        </div>

        <button
          class="ml-4 text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0"
          @click.stop="isExpanded = !isExpanded"
        >
          <svg
            class="w-5 h-5 transition-transform duration-200"
            :class="{ 'transform rotate-180': isExpanded }"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 9l-7 7-7-7"
            />
          </svg>
        </button>
      </div>
    </div>

    <!-- Expanded Content -->
    <div
      v-if="isExpanded"
      class="px-4 pb-4 border-t border-gray-100"
    >
      <!-- Rationale -->
      <div class="mt-4 mb-4">
        <h5 class="text-sm font-semibold text-gray-700 mb-2">Rationale</h5>
        <p class="text-sm text-gray-600">
          {{ recommendation.rationale }}
        </p>
      </div>

      <!-- Impact -->
      <div v-if="recommendation.impact" class="mb-4">
        <h5 class="text-sm font-semibold text-gray-700 mb-2">Expected Impact</h5>
        <p class="text-sm text-gray-600">
          {{ recommendation.impact }}
        </p>
      </div>

      <!-- Estimated Cost -->
      <div v-if="recommendation.estimated_cost" class="mb-4">
        <h5 class="text-sm font-semibold text-gray-700 mb-2">Estimated Cost</h5>
        <p class="text-lg font-bold text-gray-900">
          {{ formatCurrencyWithPence(recommendation.estimated_cost) }}
          <span class="text-sm font-normal text-gray-600">per month</span>
        </p>
      </div>

      <!-- Additional Details -->
      <div v-if="recommendation.details" class="mb-4">
        <h5 class="text-sm font-semibold text-gray-700 mb-2">Additional Details</h5>
        <p class="text-sm text-gray-600">
          {{ recommendation.details }}
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3 mt-4">
        <button
          @click="handleMarkDone"
          class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
        >
          Mark as Done
        </button>
        <button
          class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors"
        >
          Learn More
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'RecommendationCard',

  mixins: [currencyMixin],

  props: {
    recommendation: {
      type: Object,
      required: true,
      validator: (value) => {
        return value.priority && value.action;
      },
    },
  },

  data() {
    return {
      isExpanded: false,
    };
  },

  computed: {
    priorityBadgeClass() {
      const classes = {
        high: 'bg-red-500 text-white',
        medium: 'bg-orange-500 text-white',
        low: 'bg-green-500 text-white',
      };
      return classes[this.recommendation.priority] || 'bg-gray-500 text-white';
    },

    borderColourClass() {
      const classes = {
        high: 'border-red-300',
        medium: 'border-orange-300',
        low: 'border-green-300',
      };
      return classes[this.recommendation.priority] || 'border-gray-200';
    },
  },

  methods: {
    handleMarkDone() {
      this.$emit('mark-done');
    },
  },
};
</script>

<style scoped>
.recommendation-card {
  transition: all 0.2s ease;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

@media (max-width: 640px) {
  .recommendation-card .flex.gap-3 {
    flex-direction: column;
  }

  .recommendation-card .flex-1 {
    width: 100%;
  }
}
</style>
