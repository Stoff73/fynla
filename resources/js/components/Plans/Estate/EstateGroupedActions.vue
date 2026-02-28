<template>
  <div class="mb-6">
    <PlanSectionHeader
      title="Recommended Actions"
      :subtitle="enabledCountLabel"
      color="purple"
    />

    <template v-if="hasActions">
      <div class="mb-5">
        <div class="space-y-3">
          <PlanActionCard
            v-for="action in sortByPriority(actions)"
            :key="action.id"
            :action="action"
            @toggle="$emit('toggle', $event)"
          />
        </div>

        <!-- What-if comparison -->
        <div v-if="hasWhatIfData" class="bg-white rounded-lg border border-gray-200 p-4 mt-3">
          <div class="grid grid-cols-2 divide-x divide-gray-200">
            <div class="pr-4">
              <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Current Position</h5>
              <EstateWhatIfControls :scenario="whatIf.current_scenario" />
            </div>
            <div class="pl-4">
              <h5 class="text-xs font-semibold text-purple-700 uppercase tracking-wider mb-3">With Actions</h5>
              <EstateWhatIfControls :scenario="projectedScenario" show-savings />
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="bg-gray-50 rounded-lg border border-gray-200 p-6 text-center">
      <p class="text-gray-500 text-sm">No recommendations available for this plan.</p>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';
import PlanActionCard from '@/components/Plans/Shared/PlanActionCard.vue';
import EstateWhatIfControls from './EstateWhatIfControls.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EstateGroupedActions',

  mixins: [currencyMixin],

  components: {
    PlanSectionHeader,
    PlanActionCard,
    EstateWhatIfControls,
  },

  props: {
    actions: {
      type: Array,
      default: () => [],
    },
    whatIf: {
      type: Object,
      default: null,
    },
  },

  emits: ['toggle'],

  computed: {
    hasActions() {
      return this.actions && this.actions.length > 0;
    },

    enabledCount() {
      return this.actions.filter(a => a.enabled).length;
    },

    enabledCountLabel() {
      return `${this.enabledCount} of ${this.actions.length} actions enabled`;
    },

    hasWhatIfData() {
      return this.whatIf
        && this.whatIf.current_scenario
        && this.whatIf.projected_scenario;
    },

    projectedScenario() {
      if (!this.hasWhatIfData || !this.whatIf.frontend_calc_params) {
        return this.whatIf?.projected_scenario || {};
      }

      const params = this.whatIf.frontend_calc_params;
      const savingsMap = params.savings_map || {};
      const grossEstate = params.gross_estate || 0;
      const netEstate = params.net_estate || 0;
      const currentLiability = params.current_iht_liability || 0;

      // Sum savings from enabled actions
      let totalSavings = 0;
      this.actions.filter(a => a.enabled).forEach(action => {
        totalSavings += savingsMap[action.id] || action.estimated_impact || 0;
      });

      const projectedLiability = Math.max(0, currentLiability - totalSavings);
      const projectedRate = grossEstate > 0 ? (projectedLiability / grossEstate) * 100 : 0;
      const projectedToBeneficiaries = netEstate - projectedLiability;

      return {
        iht_liability: projectedLiability,
        effective_tax_rate: Math.round(projectedRate * 10) / 10,
        estate_to_beneficiaries: projectedToBeneficiaries,
        total_mitigation_savings: totalSavings,
      };
    },
  },

  methods: {
    sortByPriority(actions) {
      const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
      return [...actions].sort((a, b) => {
        return (priorityOrder[a.priority] ?? 2) - (priorityOrder[b.priority] ?? 2);
      });
    },
  },
};
</script>
