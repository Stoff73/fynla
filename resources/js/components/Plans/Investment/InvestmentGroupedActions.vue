<template>
  <div class="mb-6">
    <PlanSectionHeader
      title="Recommended Actions"
      :subtitle="enabledCountLabel"
      color="spring"
    />

    <template v-if="hasActions">
      <!-- Per-Account Sections -->
      <div
        v-for="group in accountGroups"
        :key="group.account_id"
        class="mb-5"
      >
        <h3 class="text-sm font-semibold text-neutral-500 mb-2 px-1">{{ group.account_name }}</h3>
        <div
          v-for="action in sortByPriority(group.actions)"
          :key="action.id"
          class="mb-4"
        >
          <PlanActionCard
            :action="action"
            @toggle="$emit('toggle', $event)"
            @update-funding-source="$emit('update-funding-source', $event)"
          />
          <CascadingActionChart
            v-if="cascadedActionMap[action.id]"
            :before-series="cascadedActionMap[action.id].beforeSeries"
            :after-series="cascadedActionMap[action.id].afterSeries"
            :years="portfolioYears"
            :difference-amount="cascadedActionMap[action.id].differenceAmount"
          />
        </div>
      </div>

      <!-- Portfolio Actions (non-account-scoped) -->
      <div v-if="portfolioActions.length" class="mb-5">
        <h3 class="text-sm font-semibold text-neutral-500 mb-2 px-1">Portfolio Actions</h3>
        <div
          v-for="action in sortByPriority(portfolioActions)"
          :key="action.id"
          class="mb-4"
        >
          <PlanActionCard
            :action="action"
            @toggle="$emit('toggle', $event)"
            @update-funding-source="$emit('update-funding-source', $event)"
          />
          <CascadingActionChart
            v-if="cascadedActionMap[action.id]"
            :before-series="cascadedActionMap[action.id].beforeSeries"
            :after-series="cascadedActionMap[action.id].afterSeries"
            :years="portfolioYears"
            :difference-amount="cascadedActionMap[action.id].differenceAmount"
          />
        </div>
      </div>

      <!-- What-if metrics -->
      <div v-if="hasWhatIfData" class="bg-white rounded-lg border border-light-gray p-4 mt-3 mb-5">
        <div class="grid grid-cols-2 divide-x divide-light-gray">
          <div class="pr-4">
            <h5 class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-3">Current Position</h5>
            <InvestmentWhatIfControls :scenario="whatIf.current_scenario" label="current" />
          </div>
          <div class="pl-4">
            <h5 class="text-xs font-semibold text-spring-700 uppercase tracking-wider mb-3">With Actions</h5>
            <InvestmentWhatIfControls :scenario="whatIf.projected_scenario" label="projected" />
          </div>
        </div>
      </div>
    </template>

    <div v-else class="bg-eggshell-500 rounded-lg border border-light-gray p-6 text-center">
      <p class="text-neutral-500 text-sm">No recommendations available for this plan.</p>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';
import PlanActionCard from '@/components/Plans/Shared/PlanActionCard.vue';
import CascadingActionChart from '@/components/Plans/Retirement/CascadingActionChart.vue';
import InvestmentWhatIfControls from './InvestmentWhatIfControls.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'InvestmentGroupedActions',

  mixins: [currencyMixin],

  components: {
    PlanSectionHeader,
    PlanActionCard,
    CascadingActionChart,
    InvestmentWhatIfControls,
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

  emits: ['toggle', 'update-funding-source'],

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

    portfolioActions() {
      return this.actions.filter(a => !a.scope || a.scope === 'portfolio');
    },

    accountGroups() {
      const accountActions = this.actions.filter(a => a.scope === 'account' && a.account_id);
      const grouped = {};

      accountActions.forEach(action => {
        const id = action.account_id;
        if (!grouped[id]) {
          grouped[id] = {
            account_id: id,
            account_name: action.account_name || 'Unknown Account',
            actions: [],
          };
        }
        grouped[id].actions.push(action);
      });

      return Object.values(grouped);
    },

    // Cascading chart data — computes before/after series for each action
    cascadedActions() {
      const params = this.whatIf?.frontend_calc_params || {};
      const baseValue = params.current_value || 0;
      const growthRate = params.growth_rate || 0.05;
      const years = params.years || 10;
      const baseAnnualContrib = params.current_annual_contribution || 0;
      const sorted = this.sortByPriority(this.allActions);

      let cumulativeAdditionalMonthly = 0;

      return sorted.map((action) => {
        const beforeMonthly = cumulativeAdditionalMonthly;
        const beforeSeries = this.projectSeries(baseValue, baseAnnualContrib, beforeMonthly, growthRate, years);

        const actionMonthly = action.cascade_params?.additional_monthly || 0;
        const afterMonthly = action.enabled ? (beforeMonthly + actionMonthly) : beforeMonthly;
        const afterSeries = this.projectSeries(baseValue, baseAnnualContrib, afterMonthly, growthRate, years);

        if (action.enabled) {
          cumulativeAdditionalMonthly += actionMonthly;
        }

        const differenceAmount = afterSeries[afterSeries.length - 1] - beforeSeries[beforeSeries.length - 1];

        return {
          action,
          beforeSeries,
          afterSeries,
          differenceAmount: differenceAmount > 0 ? differenceAmount : 0,
        };
      });
    },

    // Lookup map for cascade data by action ID
    cascadedActionMap() {
      const map = {};
      this.cascadedActions.forEach(item => {
        map[item.action.id] = item;
      });
      return map;
    },

    allActions() {
      return this.actions;
    },

    hasWhatIfData() {
      return this.whatIf
        && this.whatIf.current_scenario
        && this.whatIf.projected_scenario;
    },

    portfolioYears() {
      return this.whatIf?.frontend_calc_params?.years || 10;
    },
  },

  methods: {
    sortByPriority(actions) {
      const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
      return [...actions].sort((a, b) => {
        return (priorityOrder[a.priority] ?? 2) - (priorityOrder[b.priority] ?? 2);
      });
    },

    projectSeries(startValue, baseAnnualContrib, additionalMonthly, growthRate, years) {
      const totalAnnual = baseAnnualContrib + (additionalMonthly * 12);
      const series = [];
      let value = startValue;
      for (let y = 0; y <= years; y++) {
        series.push(Math.round(value));
        value = (value + totalAnnual) * (1 + growthRate);
      }
      return series;
    },
  },
};
</script>
