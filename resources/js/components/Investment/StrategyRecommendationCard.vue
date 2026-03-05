<template>
  <div class="strategy-recommendation-card p-4 hover:bg-eggshell-500 transition-colors">
    <div class="flex items-start justify-between">
      <!-- Content -->
      <div class="flex-1 pr-4">
        <!-- Badges -->
        <div class="flex items-center gap-2 mb-2">
          <!-- Priority Badge -->
          <span :class="priorityBadgeClass">
            {{ priorityLabel }}
          </span>

          <!-- Category Badge -->
          <span :class="categoryBadgeClass">
            {{ categoryLabel }}
          </span>

          <!-- Urgency Badge -->
          <span v-if="recommendation.urgency === 'high'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-raspberry-500 text-white">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3 mr-1">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            Urgent
          </span>

          <!-- Days Remaining -->
          <span v-if="recommendation.days_remaining && recommendation.days_remaining <= 90" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-500 text-white">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3 mr-1">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ recommendation.days_remaining }}d left
          </span>
        </div>

        <!-- Title -->
        <h4 class="font-medium text-horizon-500">{{ recommendation.title }}</h4>

        <!-- Description -->
        <p class="text-sm text-neutral-500 mt-1">{{ recommendation.description }}</p>

        <!-- Potential Saving -->
        <div v-if="recommendation.potential_saving" class="mt-2 flex items-center">
          <span class="text-sm text-neutral-500">Potential saving:</span>
          <span class="ml-2 font-semibold text-spring-600">{{ formatCurrency(recommendation.potential_saving) }}/{{ recommendation.timeframe || 'year' }}</span>
        </div>
      </div>

      <!-- Action Button -->
      <div class="flex-shrink-0">
        <button
          @click="handleAction"
          :class="actionButtonClass"
        >
          {{ actionButtonLabel }}
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 ml-1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'StrategyRecommendationCard',

  mixins: [currencyMixin],

  props: {
    recommendation: {
      type: Object,
      required: true,
    },
  },

  emits: ['action'],

  computed: {
    priorityLabel() {
      const priority = this.recommendation.priority;
      if (priority <= 2) return 'Priority 1';
      if (priority <= 3) return 'Priority 2';
      if (priority <= 4) return 'Priority 3';
      return 'Priority 4';
    },

    priorityBadgeClass() {
      const priority = this.recommendation.priority;
      const base = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium';
      if (priority <= 2) return `${base} bg-raspberry-500 text-white`;
      if (priority <= 3) return `${base} bg-violet-500 text-white`;
      return `${base} bg-violet-500 text-white`;
    },

    categoryLabel() {
      const labels = {
        tax: 'Tax',
        wrapper: 'Wrapper',
        fees: 'Fees',
        rebalancing: 'Rebalancing',
      };
      return labels[this.recommendation.category] || this.recommendation.category;
    },

    categoryBadgeClass() {
      const base = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium';
      const colors = {
        tax: 'bg-violet-500 text-white',
        wrapper: 'bg-violet-500 text-white',
        fees: 'bg-violet-500 text-white',
        rebalancing: 'bg-spring-500 text-white',
      };
      return `${base} ${colors[this.recommendation.category] || 'bg-eggshell-500 text-white'}`;
    },

    actionButtonLabel() {
      switch (this.recommendation.action_type) {
        case 'isa_transfer':
          return 'Transfer Now';
        case 'bed_and_isa':
          return 'View Plan';
        case 'harvest_loss':
          return 'Harvest Loss';
        case 'navigate':
          return 'View Details';
        case 'info':
          return 'Learn More';
        default:
          return 'View';
      }
    },

    actionButtonClass() {
      const base = 'inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors';

      // Primary action buttons for executable actions
      if (['isa_transfer', 'bed_and_isa', 'harvest_loss'].includes(this.recommendation.action_type)) {
        return `${base} bg-violet-600 text-white hover:bg-violet-700`;
      }

      // Secondary buttons for navigation/info
      return `${base} bg-savannah-100 text-neutral-500 hover:bg-savannah-200`;
    },
  },

  methods: {
    handleAction() {
      this.$emit('action', this.recommendation);
    },
  },
};
</script>

<style scoped>
.strategy-recommendation-card {
  @apply border-b border-savannah-100 last:border-b-0;
}
</style>
