<template>
  <AppLayout>
    <div class="module-gradient py-4 sm:py-8">
      <ModuleStatusBar />
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-h2 font-display text-horizon-500">What If Scenarios</h1>
          <p class="mt-1 text-body-base text-neutral-500">
            Explore how changes would affect your financial plan
          </p>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading && !scenarios.length" class="flex justify-center py-12">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!scenarios.length" class="card p-8 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-violet-100 flex items-center justify-center mb-3">
          <svg class="w-6 h-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-base font-semibold text-horizon-500 mb-1">No scenarios yet</h3>
        <p v-if="!isPreviewMode" class="text-sm text-neutral-500">
          Ask Fyn a "what if" question to create your first scenario
        </p>
        <p v-else class="text-sm text-neutral-500">
          <span class="text-neutral-400">Register to create what-if scenarios</span>
        </p>
      </div>

      <!-- Scenario Cards Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <ScenarioCard
          v-for="scenario in scenarios"
          :key="scenario.id"
          :scenario="scenario"
          @select="viewScenario"
          @delete="handleDelete"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import ScenarioCard from '@/components/WhatIf/ScenarioCard.vue';
import { previewModeMixin } from '@/mixins/previewModeMixin';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

export default {
  name: 'WhatIfDashboard',

  components: {
    AppLayout,
    ScenarioCard,
    ModuleStatusBar,
  },

  mixins: [previewModeMixin],

  computed: {
    ...mapState('whatIf', ['scenarios', 'loading']),
  },

  mounted() {
    this.fetchScenarios();

    // If AI navigated here with a scenario ID, go straight to detail
    const scenarioId = this.$route.query.scenario;
    if (scenarioId) {
      this.$router.replace({ name: 'WhatIfScenarioDetail', params: { id: scenarioId } });
    }
  },

  methods: {
    ...mapActions('whatIf', ['fetchScenarios', 'deleteScenario']),

    viewScenario(id) {
      this.$router.push({ name: 'WhatIfScenarioDetail', params: { id } });
    },

    async handleDelete(id) {
      if (confirm('Are you sure you want to delete this scenario?')) {
        await this.deleteScenario(id);
      }
    },
  },
};
</script>
