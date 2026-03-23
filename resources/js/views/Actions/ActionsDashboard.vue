<template>
  <AppLayout>
    <div class="py-8">
      <!-- Page header -->
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Actions</h1>
        <p class="text-body-sm text-neutral-500 mt-2">Recommended actions from your financial plans</p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center h-64">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="moduleSections.length === 0" class="flex flex-col items-center justify-center h-64 text-center">
        <div class="w-16 h-16 bg-savannah-100 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-h4 font-bold text-horizon-500 mb-2">No actions needed</h3>
        <p class="text-body text-neutral-500">Your financial plans have no outstanding recommendations.</p>
      </div>

      <!-- Two-column grid -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div
          v-for="section in moduleSections"
          :key="section.type"
          class="bg-white rounded-card shadow-card border border-light-gray p-6"
        >
          <!-- Module header -->
          <div class="flex items-center justify-between mb-4 pb-3 border-b border-light-gray">
            <h2 class="text-h4 font-bold text-horizon-500">{{ section.label }}</h2>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-caption font-medium bg-violet-100 text-violet-700">
              {{ section.actions.length }} {{ section.actions.length === 1 ? 'action' : 'actions' }}
            </span>
          </div>

          <!-- Action list -->
          <div class="space-y-1">
            <ActionSummaryCard
              v-for="action in section.actions"
              :key="action.id"
              :action="action"
              :plan-type="section.type"
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import ActionSummaryCard from '@/components/Actions/ActionSummaryCard.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

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
  },

  async mounted() {
    this.loading = true;
    try {
      await Promise.all(
        this.planTypes.map(type => this.$store.dispatch('plans/fetchPlan', type))
      );
    } catch (e) {
      console.error('[Actions] Failed to fetch plans:', e);
    } finally {
      this.loading = false;
    }
  },
};
</script>
