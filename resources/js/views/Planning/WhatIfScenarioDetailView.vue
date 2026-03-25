<template>
  <AppLayout>
    <div class="module-gradient py-4 sm:py-8">
      <ModuleStatusBar />
      <!-- Back Button -->
      <button
        @click="$router.push({ name: 'WhatIfDashboard' })"
        class="detail-inline-back mb-4"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to scenarios
      </button>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Detail Content -->
      <div v-else-if="comparisonData">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-h2 font-display text-horizon-500">{{ scenarioName }}</h1>
            <p class="mt-1 text-body-base text-neutral-500">
              {{ affectedModuleCount }} modules compared · Created {{ formatDate(comparisonData.scenario?.created_at) }}
            </p>
          </div>
          <span class="text-xs px-3 py-1 rounded-full bg-savannah-100 text-neutral-500 capitalize">
            {{ comparisonData.scenario?.type || 'custom' }}
          </span>
        </div>

        <!-- AI Narrative -->
        <div v-if="comparisonData.ai_narrative" class="card p-6 mb-6">
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-full bg-raspberry-100 flex items-center justify-center flex-shrink-0">
              <svg class="w-4 h-4 text-raspberry-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" />
              </svg>
            </div>
            <div>
              <p class="text-xs font-medium text-neutral-500 mb-1">Fyn's Analysis</p>
              <p class="text-body-base text-horizon-500 leading-relaxed">{{ comparisonData.ai_narrative }}</p>
            </div>
          </div>
        </div>

        <!-- Module Comparisons -->
        <div class="space-y-4">
          <ModuleComparison
            v-for="module in comparisonData.affected_modules"
            :key="module"
            :module="module"
            :current-metrics="comparisonData.current?.[module] || {}"
            :what-if-metrics="comparisonData.what_if?.[module] || {}"
            :deltas="comparisonData.deltas?.[module] || {}"
          />
        </div>
      </div>

      <!-- Error State -->
      <div v-else class="card p-8 text-center">
        <p class="text-neutral-500">Unable to load scenario comparison</p>
        <button
          @click="$router.push({ name: 'WhatIfDashboard' })"
          class="mt-4 text-sm text-raspberry-500 hover:text-raspberry-600"
        >
          Back to scenarios
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import ModuleComparison from '@/components/WhatIf/ModuleComparison.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

export default {
  name: 'WhatIfScenarioDetailView',

  components: {
    AppLayout,
    ModuleComparison,
    ModuleStatusBar,
  },

  computed: {
    ...mapState('whatIf', ['comparisonData', 'loading']),

    scenarioName() {
      return this.comparisonData?.scenario?.name || 'Scenario';
    },

    affectedModuleCount() {
      return this.comparisonData?.affected_modules?.length || 0;
    },
  },

  mounted() {
    const id = this.$route.params.id;
    if (id) {
      this.fetchScenarioComparison(Number(id));
    }
  },

  methods: {
    ...mapActions('whatIf', ['fetchScenarioComparison']),

    formatDate(dateString) {
      if (!dateString) return '';
      return new Date(dateString).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },
  },
};
</script>
