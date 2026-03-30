<template>
  <AppLayout>
    <div class="py-8">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center h-64">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="moduleSections.length === 0" class="bg-light-blue-100 border border-light-gray rounded-lg flex flex-col items-center justify-center h-64 text-center">
        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-h4 font-bold text-horizon-500 mb-2">No actions needed</h3>
        <p class="text-body text-neutral-500">Your financial plans have no outstanding recommendations.</p>
      </div>

      <template v-else>
        <!-- Top Priority Actions -->
        <div class="top-priorities module-gradient">
          <h3 class="text-lg font-bold text-horizon-500 mb-4 flex items-center gap-2">
            Top Priority Actions
            <span class="text-xs font-bold text-white bg-raspberry-500 px-2 py-0.5 rounded-full">{{ topActions.length }}</span>
          </h3>
          <div class="space-y-2.5">
            <div
              v-for="(action, index) in topActions"
              :key="'top-' + action.id"
              class="action-row"
              @click="goToAction(action)"
            >
              <div class="flex items-center gap-3.5">
                <div class="order-num">{{ index + 1 }}</div>
                <div>
                  <div class="text-[15px] font-semibold text-horizon-500">{{ action.title }}</div>
                  <div class="text-xs text-neutral-500 mt-0.5">{{ action.moduleName }}</div>
                </div>
              </div>
              <div class="flex items-center gap-2.5 flex-shrink-0">
                <span
                  class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
                  :class="priorityClass(action.priority)"
                >{{ action.priority }}</span>
                <span v-if="action.estimated_impact" class="text-sm font-bold text-spring-600">{{ formatCurrency(action.estimated_impact) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Module cards grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-6">
          <div
            v-for="section in moduleSections"
            :key="section.type"
            class="module-card module-gradient"
          >
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-light-gray">
              <h2 class="text-[15px] font-bold text-horizon-500">{{ section.label }}</h2>
              <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-violet-100 text-violet-700">
                {{ section.actions.length }} {{ section.actions.length === 1 ? 'action' : 'actions' }}
              </span>
            </div>
            <div class="space-y-1.5">
              <ActionSummaryCard
                v-for="action in section.actions"
                :key="action.id"
                :action="action"
                :plan-type="section.type"
              />
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import ActionSummaryCard from '@/components/Actions/ActionSummaryCard.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'ActionsDashboard',

  components: {
    AppLayout,
    ActionSummaryCard,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
      planTypes: ['protection', 'savings', 'investment', 'retirement', 'estate'],
    };
  },

  computed: {
    moduleSections() {
      const config = [
        { type: 'protection', label: 'Protection' },
        { type: 'savings', label: 'Savings' },
        { type: 'investment', label: 'Investment' },
        { type: 'retirement', label: 'Retirement' },
        { type: 'estate', label: 'Estate Planning' },
      ];
      return config
        .map(c => {
          const plan = this.$store.getters['plans/getPlan'](c.type);
          const actions = plan?.actions || [];
          return { ...c, actions };
        })
        .filter(s => s.actions.length > 0);
    },

    totalActions() {
      return this.moduleSections.reduce((sum, s) => sum + s.actions.length, 0);
    },

    topActions() {
      const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
      const allActions = this.moduleSections.flatMap(s =>
        s.actions.map(a => ({ ...a, moduleName: s.label, moduleType: s.type }))
      );
      return allActions
        .sort((a, b) => (priorityOrder[a.priority] || 3) - (priorityOrder[b.priority] || 3))
        .slice(0, 3);
    },
  },

  methods: {
    priorityClass(priority) {
      const classes = {
        critical: 'bg-raspberry-100 text-raspberry-700',
        high: 'bg-raspberry-50 text-raspberry-600',
        medium: 'bg-violet-100 text-violet-700',
        low: 'bg-eggshell-500 text-neutral-500',
      };
      return classes[priority] || classes.medium;
    },

    goToAction(action) {
      this.$router.push(`/actions/${action.moduleType}/${action.id}`);
    },
  },

  async mounted() {
    this.loading = true;
    try {
      await Promise.all(
        this.planTypes.map(type => this.$store.dispatch('plans/fetchPlan', type))
      );
    } catch (e) {
      logger.error('[Actions] Failed to fetch plans:', e);
    } finally {
      this.loading = false;
    }
  },
};
</script>

<style scoped>
.top-priorities {
  @apply bg-white rounded-card border border-light-gray p-6;
}

.order-num {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  @apply bg-horizon-500 text-white;
  font-size: 16px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.action-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  @apply bg-eggshell-500 border border-transparent;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
}

.action-row:hover {
  @apply border-light-gray bg-white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  transform: translateY(-1px);
}

.module-card {
  @apply bg-white rounded-card border border-light-gray p-6;
  transition: all 0.2s;
}

.module-card:hover {
  @apply border-horizon-300;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}
</style>
