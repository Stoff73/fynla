<template>
  <div class="card">
    <div class="-m-6 p-6 rounded-lg">
      <!-- Header Row -->
      <div class="flex items-center justify-between border-b border-gray-200 pb-4 mb-4">
        <div>
          <span class="text-sm text-gray-500">Recommended Actions</span>
          <div class="flex items-baseline gap-2 mt-1">
            <span class="text-3xl font-bold text-primary-600">
              {{ totalActions }}
            </span>
            <span class="text-sm text-gray-500">actions to review</span>
          </div>
        </div>
        <div v-if="totalPotentialSavings > 0" class="text-right">
          <span class="text-sm text-gray-500">Potential savings</span>
          <p class="text-xl font-bold text-green-600">{{ formatCurrency(totalPotentialSavings) }}<span class="text-sm font-normal">/yr</span></p>
        </div>
      </div>

      <!-- Actions Grid -->
      <div v-if="displayedActions.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <div
          v-for="(action, index) in displayedActions"
          :key="index"
          class="action-card cursor-pointer"
          @click="navigateToModule(action.module)"
        >
          <div class="flex items-center gap-2 mb-2">
            <span
              class="priority-badge"
              :class="getPriorityClass(action.priority)"
            >
              {{ getPriorityLabel(action.priority) }}
            </span>
            <span class="module-badge" :class="getModuleClass(action.module)">
              {{ formatModule(action.module) }}
            </span>
          </div>
          <p class="action-text">{{ action.title }}</p>
          <p v-if="action.potential_saving" class="text-xs text-green-600 mt-2 font-medium">
            Save {{ formatCurrency(action.potential_saving) }}/yr
          </p>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!loading && displayedActions.length === 0" class="text-center py-8">
        <svg class="w-12 h-12 mx-auto text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-gray-600">All caught up!</p>
        <p class="text-xs text-gray-400">No pending actions</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
      </div>
    </div>
  </div>
</template>

<script>
import investmentService from '@/services/investmentService';
import protectionService from '@/services/protectionService';
import estateService from '@/services/estateService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ActionsOverviewCard',
  mixins: [currencyMixin],

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
      // Sort by priority (lower number = higher priority) and show all
      return [...this.allActions]
        .sort((a, b) => a.priority - b.priority);
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
          estateService.calculateSecondDeathIHTPlanning(),
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
              strategy_name: 'Life Insurance for IHT',
              description: data.life_cover_strategy.recommendation,
              iht_saved: data.life_cover_strategy.cover_required,
              category: 'life_cover',
              priority: 2,
            });
          }

          this.estateStrategies = strategies;
        }
      } catch (error) {
        console.error('Failed to load actions:', error);
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
.action-card {
  background-color: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
  transition: all 0.2s ease;
}

.action-card:hover {
  background-color: #f3f4f6;
  border-color: #d1d5db;
  transform: translateY(-1px);
}

.action-text {
  font-size: 13px;
  color: #374151;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.priority-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

.priority-critical {
  background-color: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.priority-high {
  background-color: #fffbeb;
  color: #d97706;
  border: 1px solid #fde68a;
}

.priority-medium {
  background-color: #f0f9ff;
  color: #0284c7;
  border: 1px solid #bae6fd;
}

.priority-low {
  background-color: #f0fdf4;
  color: #16a34a;
  border: 1px solid #bbf7d0;
}

.module-badge {
  font-size: 10px;
  font-weight: 500;
  padding: 2px 6px;
  border-radius: 4px;
}

.module-investment {
  background-color: #eff6ff;
  color: #2563eb;
}

.module-estate {
  background-color: #faf5ff;
  color: #7c3aed;
}

.module-protection {
  background-color: #ecfdf5;
  color: #059669;
}
</style>
