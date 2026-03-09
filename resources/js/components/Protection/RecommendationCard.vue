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
            <span class="text-sm text-neutral-500">
              {{ recommendation.category }}
            </span>
          </div>
          <h4 class="text-lg font-semibold text-horizon-500 mb-2">
            {{ recommendation.action }}
          </h4>
          <p v-if="!isExpanded" class="text-sm text-neutral-500 line-clamp-2">
            {{ recommendation.rationale }}
          </p>
        </div>

        <button
          class="ml-4 text-horizon-400 hover:text-neutral-500 transition-colors flex-shrink-0"
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
      class="px-4 pb-4 border-t border-light-gray"
    >
      <!-- Rationale -->
      <div class="mt-4 mb-4">
        <h5 class="text-sm font-semibold text-neutral-500 mb-2">Rationale</h5>
        <p class="text-sm text-neutral-500">
          {{ recommendation.rationale }}
        </p>
      </div>

      <!-- Impact -->
      <div v-if="recommendation.impact" class="mb-4">
        <h5 class="text-sm font-semibold text-neutral-500 mb-2">Expected Impact</h5>
        <p class="text-sm text-neutral-500">
          {{ recommendation.impact }}
        </p>
      </div>

      <!-- Estimated Cost -->
      <div v-if="recommendation.estimated_cost" class="mb-4">
        <h5 class="text-sm font-semibold text-neutral-500 mb-2">Estimated Cost</h5>
        <p class="text-lg font-bold text-horizon-500">
          {{ formatCurrencyWithPence(recommendation.estimated_cost) }}
          <span class="text-sm font-normal text-neutral-500">per month</span>
        </p>
      </div>

      <!-- Personalised Context -->
      <div v-if="hasPersonalisedContext" class="mb-4 bg-savannah-100 rounded-lg p-3">
        <h5 class="text-sm font-semibold text-horizon-500 mb-2">Why this matters for you</h5>
        <ul class="space-y-1">
          <li
            v-for="(item, index) in recommendation.personalised_context"
            :key="index"
            class="text-sm text-neutral-500 flex items-start gap-2"
          >
            <span class="text-violet-500 mt-0.5 flex-shrink-0">&bull;</span>
            <span>{{ item }}</span>
          </li>
        </ul>
      </div>

      <!-- Additional Details -->
      <div v-if="recommendation.details" class="mb-4">
        <h5 class="text-sm font-semibold text-neutral-500 mb-2">Additional Details</h5>
        <p class="text-sm text-neutral-500">
          {{ recommendation.details }}
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3 mt-4">
        <button
          @click="handleMarkDone"
          class="flex-1 px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors"
        >
          Mark as Done
        </button>
        <button
          class="px-4 py-2 bg-savannah-100 text-neutral-500 text-sm font-medium rounded-lg hover:bg-savannah-200 transition-colors"
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

  emits: ['mark-done'],

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
        high: 'bg-raspberry-500 text-white',
        medium: 'bg-violet-500 text-white',
        low: 'bg-spring-500 text-white',
      };
      return classes[this.recommendation.priority] || 'bg-eggshell-5000 text-white';
    },

    borderColourClass() {
      const classes = {
        high: 'border-raspberry-300',
        medium: 'border-violet-300',
        low: 'border-spring-300',
      };
      return classes[this.recommendation.priority] || 'border-light-gray';
    },

    hasPersonalisedContext() {
      return Array.isArray(this.recommendation.personalised_context)
        && this.recommendation.personalised_context.length > 0;
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

@media (max-width: 640px) {
  .recommendation-card .flex.gap-3 {
    flex-direction: column;
  }

  .recommendation-card .flex-1 {
    width: 100%;
  }
}
</style>
