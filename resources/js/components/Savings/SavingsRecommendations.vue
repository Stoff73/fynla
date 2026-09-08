<template>
  <div class="recommendations">
    <h3 class="text-lg font-semibold text-horizon-500 mb-6">Savings Recommendations</h3>

    <div v-if="loading" class="flex items-center justify-center h-32">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <div v-else-if="error" class="bg-white rounded-lg border border-light-gray p-6">
      <p class="text-sm text-raspberry-600">{{ error }}</p>
      <button type="button" class="mt-3 text-sm font-semibold text-raspberry-500 hover:text-raspberry-600" @click="load">
        Try again
      </button>
    </div>

    <div v-else-if="recommendations.length" class="space-y-4">
      <div
        v-for="(rec, index) in recommendations"
        :key="rec.definition_key || index"
        class="bg-white border border-light-gray rounded-lg p-6"
        :data-test="`savings-rec-${rec.definition_key || index}`"
      >
        <div class="flex items-start justify-between gap-4 mb-2">
          <h4 class="text-lg font-semibold text-horizon-500">{{ rec.title }}</h4>
          <span
            v-if="rec.impact"
            class="flex-shrink-0 px-3 py-1 text-xs font-semibold rounded-full"
            :class="impactClass(rec.impact)"
          >
            {{ rec.impact }} impact
          </span>
        </div>
        <p class="text-sm text-neutral-500">{{ rec.description }}</p>
        <p v-if="rec.action" class="text-sm text-horizon-500 mt-2">{{ rec.action }}</p>
        <p v-if="rec.estimated_impact" class="text-sm font-semibold text-spring-700 mt-2">
          Estimated impact: {{ formatCurrency(rec.estimated_impact) }}
        </p>

        <button
          type="button"
          class="mt-4 text-sm font-semibold text-raspberry-500 hover:text-raspberry-600"
          @click="toggleTrace(index)"
        >
          {{ openTraces.includes(index) ? 'Hide how we worked this out' : 'Show how we worked this out' }}
        </button>
        <div v-if="openTraces.includes(index)" class="mt-4">
          <DecisionTraceTimeline
            :steps="rec.decision_trace || []"
            :outcome="{ title: rec.title, description: rec.action }"
          />
        </div>
      </div>
    </div>

    <div v-else class="text-center py-12 bg-white rounded-lg border border-light-gray">
      <h3 class="text-sm font-medium text-horizon-500">No recommendations</h3>
      <p class="mt-1 text-sm text-neutral-500">Your savings strategy looks good!</p>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import DecisionTraceTimeline from '@/components/Actions/DecisionTraceTimeline.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import logger from '@/utils/logger';

/**
 * The Strategy tab. Reads the ONE savings recommendations endpoint
 * (GET /savings/recommendations -> SavingsPlanService -> SavingsActionDefinitionService),
 * the same list Fyn ranks and the dashboard aggregates, and shows each item's
 * decision trace on request.
 */
export default {
  name: 'SavingsRecommendations',

  components: { DecisionTraceTimeline },

  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
      error: '',
      openTraces: [],
    };
  },

  computed: {
    ...mapState('savings', ['recommendations']),
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      this.error = '';
      try {
        await this.$store.dispatch('savings/fetchRecommendations');
      } catch (e) {
        logger.error('[SavingsRecommendations] load failed:', e);
        this.error = 'We could not load your savings recommendations.';
      } finally {
        this.loading = false;
      }
    },

    toggleTrace(index) {
      this.openTraces = this.openTraces.includes(index)
        ? this.openTraces.filter((i) => i !== index)
        : [...this.openTraces, index];
    },

    impactClass(impact) {
      const classes = {
        critical: 'bg-raspberry-500 text-white',
        high: 'bg-raspberry-500 text-white',
        medium: 'bg-violet-500 text-white',
        low: 'bg-spring-500 text-white',
      };
      return classes[String(impact).toLowerCase()] || 'bg-eggshell-500 text-horizon-500';
    },
  },
};
</script>
