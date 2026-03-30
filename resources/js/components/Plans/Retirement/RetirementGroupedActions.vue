<template>
  <div class="mb-6">
    <PlanSectionHeader
      title="Recommended Actions"
      :subtitle="enabledCountLabel"
      color="spring"
    />

    <template v-if="hasActions">
      <!-- Single DC pension: cascading action cards, each with own chart -->
      <template v-if="isSinglePension">
        <div class="mb-5">
          <div
            v-for="item in cascadedActions"
            :key="item.action.id"
            class="mb-4"
          >
            <PlanActionCard
              :action="item.action"
              @toggle="$emit('toggle', $event)"
              @update-funding-source="$emit('update-funding-source', $event)"
            />
            <CascadingActionChart
              :before-series="item.beforeSeries"
              :after-series="item.afterSeries"
              :years="portfolioYears"
              :difference-amount="item.differenceAmount"
            />
          </div>

          <!-- What-if metrics below all cascaded actions -->
          <div v-if="hasWhatIfData" class="bg-white rounded-lg border border-light-gray p-4 mt-3">
            <div class="grid grid-cols-2 divide-x divide-light-gray">
              <div class="pr-4">
                <h5 class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-3">Current Position</h5>
                <RetirementWhatIfControls :scenario="whatIf.current_scenario" />
              </div>
              <div class="pl-4">
                <h5 class="text-xs font-semibold text-spring-700 uppercase tracking-wider mb-3">With Actions</h5>
                <RetirementWhatIfControls :scenario="whatIf.projected_scenario" />
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Multiple DC pensions: per-pension groups, then portfolio, all with cascading charts -->
      <template v-else>
        <!-- Per-Pension Sections -->
        <div
          v-for="group in pensionGroups"
          :key="group.pension_id"
          class="mb-5"
        >
          <h3 class="text-sm font-semibold text-neutral-500 mb-2 px-1">{{ group.pension_name }}</h3>
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

        <!-- Portfolio Actions -->
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
              <RetirementWhatIfControls :scenario="whatIf.current_scenario" />
            </div>
            <div class="pl-4">
              <h5 class="text-xs font-semibold text-spring-700 uppercase tracking-wider mb-3">With Actions</h5>
              <RetirementWhatIfControls :scenario="whatIf.projected_scenario" />
            </div>
          </div>
        </div>
      </template>
    </template>

    <div v-else class="bg-savannah-100 rounded-lg border border-light-gray p-6 text-center">
      <p class="text-neutral-500 text-sm">No recommendations available for this plan.</p>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';
import PlanActionCard from '@/components/Plans/Shared/PlanActionCard.vue';
import PensionGrowthProjectionChart from './PensionGrowthProjectionChart.vue';
import CascadingActionChart from './CascadingActionChart.vue';
import RetirementWhatIfControls from './RetirementWhatIfControls.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS, CHART_DEFAULTS, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';

export default {
  name: 'RetirementGroupedActions',

  mixins: [currencyMixin],

  components: {
    PlanSectionHeader,
    PlanActionCard,
    PensionGrowthProjectionChart,
    CascadingActionChart,
    RetirementWhatIfControls,
  },

  props: {
    actions: {
      type: Array,
      default: () => [],
    },
    pensionProjections: {
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

    allActions() {
      return this.actions;
    },

    dcPensionCount() {
      return this.pensionProjections.length;
    },

    isSinglePension() {
      return this.dcPensionCount <= 1;
    },

    // For single pension mode
    singlePensionProjection() {
      return this.pensionProjections[0] || null;
    },

    singlePensionAccountActions() {
      return this.actions.filter(a => a.scope === 'account' && a.account_id);
    },

    singlePensionEnabledCount() {
      return this.singlePensionAccountActions.filter(a => a.enabled).length;
    },

    singlePensionTotalAccountCount() {
      return this.singlePensionAccountActions.length;
    },

    // Cascading action cards for single-pension mode
    cascadedActions() {
      const params = this.whatIf?.frontend_calc_params || {};
      const baseValue = params.current_dc_value || 0;
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

    // Lookup map for cascade data by action ID (used by multi-pension template)
    cascadedActionMap() {
      const map = {};
      this.cascadedActions.forEach(item => {
        map[item.action.id] = item;
      });
      return map;
    },

    // For multiple pension mode
    portfolioActions() {
      return this.actions.filter(a => !a.scope || a.scope === 'portfolio');
    },

    pensionGroups() {
      const accountActions = this.actions.filter(a => a.scope === 'account' && a.account_id);
      const grouped = {};

      accountActions.forEach(action => {
        const id = action.account_id;
        if (!grouped[id]) {
          grouped[id] = {
            pension_id: id,
            pension_name: action.account_name || 'Unknown Pension',
            actions: [],
            projection: null,
          };
        }
        grouped[id].actions.push(action);
      });

      // Match projections to groups
      if (this.pensionProjections && this.pensionProjections.length) {
        this.pensionProjections.forEach(proj => {
          if (grouped[proj.pension_id]) {
            grouped[proj.pension_id].projection = proj;
          }
        });
      }

      return Object.values(grouped).filter(group => group.actions.length > 0).map(group => ({
        ...group,
        enabledCount: group.actions.filter(a => a.enabled).length,
        totalCount: group.actions.length,
      }));
    },

    // Portfolio projection computeds
    hasWhatIfData() {
      return this.whatIf
        && this.whatIf.current_scenario
        && this.whatIf.projected_scenario;
    },

    portfolioYears() {
      return this.whatIf?.frontend_calc_params?.years || 10;
    },

    portfolioCurrentSeries() {
      if (!this.hasWhatIfData) return [];
      const params = this.whatIf.frontend_calc_params || {};
      const startValue = params.current_dc_value || 0;
      const growthRate = params.growth_rate || 0.05;
      const series = [];
      for (let y = 0; y <= this.portfolioYears; y++) {
        series.push(Math.round(startValue * Math.pow(1 + growthRate, y)));
      }
      return series;
    },

    portfolioProjectedSeries() {
      if (!this.hasWhatIfData) return [];
      const params = this.whatIf.frontend_calc_params || {};
      const startValue = params.current_dc_value || 0;
      const growthRate = params.growth_rate || 0.05;

      // Factor in additional contributions from enabled actions
      const additionalMonthly = this.whatIf.projected_scenario.additional_monthly_contribution || 0;
      const additionalAnnual = additionalMonthly * 12;

      const series = [];
      let value = startValue;
      for (let y = 0; y <= this.portfolioYears; y++) {
        series.push(Math.round(value));
        value = (value + additionalAnnual) * (1 + growthRate);
      }
      return series;
    },

    portfolioProjectionDifference() {
      const current = this.portfolioCurrentSeries;
      const projected = this.portfolioProjectedSeries;
      if (!current.length || !projected.length) return 0;
      const diff = projected[projected.length - 1] - current[current.length - 1];
      return diff > 0 ? diff : 0;
    },

    portfolioChartSeries() {
      return [
        { name: 'Current Trajectory', data: this.portfolioCurrentSeries },
        { name: 'With Actions', data: this.portfolioProjectedSeries },
      ];
    },

    portfolioChartOptions() {
      const self = this;
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'line',
        },
        colors: [CHART_COLORS[1], CHART_COLORS[2]],
        stroke: {
          curve: 'smooth',
          width: 2.5,
        },
        xaxis: {
          categories: Array.from({ length: this.portfolioYears + 1 }, (_, i) => `Year ${i}`),
          labels: {
            style: { fontSize: '11px', colors: TEXT_COLORS.muted },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            formatter: (val) => self.formatCurrencyCompact(val),
            style: { fontSize: '11px', colors: TEXT_COLORS.muted },
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 3,
        },
        tooltip: {
          y: {
            formatter: (val) => self.formatCurrency(val),
          },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'left',
          fontSize: '12px',
          markers: { size: 4 },
        },
        dataLabels: { enabled: false },
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
