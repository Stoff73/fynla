<template>
  <div>
      <!-- Header -->
      <h3 class="text-lg font-semibold text-horizon-500 mb-4">Items for Review</h3>

      <!-- Actions List -->
      <div v-if="displayedActions.length > 0" class="space-y-2">
        <div
          v-for="(action, index) in displayedActions"
          :key="index"
          class="flex items-center justify-between py-2 cursor-pointer hover:bg-savannah-100 -mx-2 px-2 rounded transition-colors"
          @click="navigateToModule(action.module)"
        >
          <div class="flex items-center gap-2 min-w-0">
            <span
              class="priority-dot flex-shrink-0"
              :class="getPriorityDotClass(action.priority)"
            ></span>
            <span class="text-sm text-neutral-500 truncate">{{ action.title }}</span>
          </div>
          <span class="module-badge flex-shrink-0 ml-2" :class="getModuleClass(action.module)">
            {{ formatModule(action.module) }}
          </span>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!loading && displayedActions.length === 0" class="text-center py-8">
        <svg class="w-12 h-12 mx-auto text-spring-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-neutral-500">All caught up!</p>
        <p class="text-xs text-horizon-400">No pending actions</p>
      </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500 mx-auto"></div>
    </div>
  </div>
</template>

<script>
import investmentService from '@/services/investmentService';
import protectionService from '@/services/protectionService';
import estateService from '@/services/estateService';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'ActionsOverviewCard',
  mixins: [currencyMixin],

  props: {
    compact: {
      type: Boolean,
      default: false,
    },
    limit: {
      type: Number,
      default: 0, // 0 means no limit
    },
  },

  data() {
    return {
      investmentStrategies: [],
      protectionRecommendations: [],
      estateStrategies: [],
      loading: false,
    };
  },

  computed: {
    allActions() {
      const actions = [];

      // Investment strategies
      if (Array.isArray(this.investmentStrategies)) {
        this.investmentStrategies.forEach(strategy => {
          actions.push({
            module: 'investment',
            priority: strategy.priority || 3,
            title: strategy.title,
            description: strategy.description,
            potential_saving: strategy.potential_saving,
            urgency: strategy.urgency,
            category: strategy.category,
          });
        });
      }

      // Protection recommendations
      if (Array.isArray(this.protectionRecommendations)) {
        this.protectionRecommendations.forEach(rec => {
          actions.push({
            module: 'protection',
            priority: this.mapProtectionPriority(rec.priority),
            title: rec.action || rec.recommendation_text || rec.category,
            description: rec.rationale,
            potential_saving: null,
            category: rec.category,
          });
        });
      }

      // Estate strategies
      if (Array.isArray(this.estateStrategies)) {
        this.estateStrategies.forEach(strategy => {
          actions.push({
            module: 'estate',
            priority: strategy.priority || 3,
            title: strategy.strategy_name || strategy.title,
            description: strategy.description,
            potential_saving: strategy.iht_saved,
            category: strategy.category,
          });
        });
      }

      return actions;
    },

    displayedActions() {
      // Sort by priority (lower number = higher priority)
      const sorted = [...this.allActions].sort((a, b) => a.priority - b.priority);
      // Apply limit if specified
      if (this.limit > 0) {
        return sorted.slice(0, this.limit);
      }
      return sorted;
    },

    totalActions() {
      return this.allActions.length;
    },

    totalPotentialSavings() {
      return this.allActions.reduce((sum, action) => {
        return sum + (action.potential_saving || 0);
      }, 0);
    },
  },

  async mounted() {
    await this.loadAllActions();
  },

  methods: {
    async loadAllActions() {
      this.loading = true;

      try {
        // Fetch all in parallel
        const [investmentData, protectionData, estateData] = await Promise.allSettled([
          investmentService.getPortfolioStrategy(),
          protectionService.getRecommendations(),
          estateService.calculateIHTPlanning(),
        ]);

        // Process investment strategies
        if (investmentData.status === 'fulfilled' && investmentData.value?.recommendations) {
          this.investmentStrategies = investmentData.value.recommendations;
        }

        // Process protection recommendations
        if (protectionData.status === 'fulfilled') {
          const protectionValue = protectionData.value;
          if (protectionValue?.data?.recommendations) {
            this.protectionRecommendations = protectionValue.data.recommendations;
          } else if (Array.isArray(protectionValue?.data)) {
            this.protectionRecommendations = protectionValue.data;
          } else {
            this.protectionRecommendations = [];
          }
        }

        // Process estate strategies
        if (estateData.status === 'fulfilled' && estateData.value?.success) {
          const data = estateData.value.data;
          const strategies = [];

          // Get gifting strategies
          if (data?.gifting_strategy?.strategies) {
            strategies.push(...data.gifting_strategy.strategies.map(s => ({
              ...s,
              category: 'gifting',
              priority: s.priority || 2,
            })));
          }

          // Get life cover strategy if has recommendation
          if (data?.life_cover_strategy?.recommendation) {
            strategies.push({
              strategy_name: 'Life Insurance for Inheritance Tax',
              description: data.life_cover_strategy.recommendation,
              iht_saved: data.life_cover_strategy.cover_required,
              category: 'life_cover',
              priority: 2,
            });
          }

          this.estateStrategies = strategies;
        }
      } catch (error) {
        logger.error('Failed to load actions:', error);
      } finally {
        this.loading = false;
      }
    },

    navigateToModule(module) {
      const routes = {
        investment: '/net-worth/investments',
        protection: '/protection',
        estate: '/estate',
      };
      this.$router.push(routes[module] || '/dashboard');
    },

    getPriorityClass(priority) {
      if (priority <= 1) return 'priority-critical';
      if (priority <= 2) return 'priority-high';
      if (priority <= 3) return 'priority-medium';
      return 'priority-low';
    },

    getPriorityDotClass(priority) {
      if (priority <= 1) return 'dot-critical';
      if (priority <= 2) return 'dot-high';
      if (priority <= 3) return 'dot-medium';
      return 'dot-low';
    },

    getPriorityLabel(priority) {
      if (priority <= 1) return 'Urgent';
      if (priority <= 2) return 'High';
      if (priority <= 3) return 'Medium';
      return 'Low';
    },

    getModuleClass(module) {
      const classes = {
        investment: 'module-investment',
        estate: 'module-estate',
        protection: 'module-protection',
      };
      return classes[module] || '';
    },

    formatModule(module) {
      const labels = {
        investment: 'Investment',
        estate: 'Estate',
        protection: 'Protection',
      };
      return labels[module] || module;
    },

    mapProtectionPriority(priority) {
      // Protection uses string priorities, convert to numbers
      const mapping = {
        high: 1,
        medium: 2,
        low: 3,
      };
      return mapping[priority] || 3;
    },
  },
};
</script>

<style scoped>
.priority-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.dot-critical {
  @apply bg-raspberry-500;
}

.dot-high {
  @apply bg-violet-500;
}

.dot-medium {
  @apply bg-sky-500;
}

.dot-low {
  @apply bg-spring-500;
}

.module-badge {
  font-size: 10px;
  font-weight: 500;
  padding: 2px 6px;
  border-radius: 4px;
}

.module-investment {
  @apply bg-violet-50;
  @apply text-violet-600;
}

.module-estate {
  @apply bg-purple-50;
  @apply text-purple-600;
}

.module-protection {
  @apply bg-spring-50;
  @apply text-spring-600;
}
</style>
