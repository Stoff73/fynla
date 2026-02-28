<template>
  <div class="mb-6">
    <PlanSectionHeader
      title="Recommended Actions"
      :subtitle="enabledCountLabel"
      color="green"
    />

    <template v-if="hasActions">
      <!-- Single DC pension: no portfolio split, actions + chart + what-if inline -->
      <template v-if="isSinglePension">
        <div class="mb-5">
          <div class="space-y-3">
            <PlanActionCard
              v-for="action in sortByPriority(allActions)"
              :key="action.id"
              :action="action"
              @toggle="$emit('toggle', $event)"
            />
          </div>
          <PensionGrowthProjectionChart
            v-if="singlePensionProjection"
            :projection="singlePensionProjection"
            :enabled-action-count="singlePensionEnabledCount"
            :total-action-count="singlePensionTotalAccountCount"
          />

          <!-- What-if metrics below the chart -->
          <div v-if="hasWhatIfData" class="bg-white rounded-lg border border-gray-200 p-4 mt-3">
            <div class="grid grid-cols-2 divide-x divide-gray-200">
              <div class="pr-4">
                <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Current Position</h5>
                <RetirementWhatIfControls :scenario="whatIf.current_scenario" />
              </div>
              <div class="pl-4">
                <h5 class="text-xs font-semibold text-green-700 uppercase tracking-wider mb-3">With Actions</h5>
                <RetirementWhatIfControls :scenario="whatIf.projected_scenario" />
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Multiple DC pensions: per-pension groups, then portfolio -->
      <template v-else>
        <!-- Per-Pension Sections -->
        <div
          v-for="group in pensionGroups"
          :key="group.pension_id"
          class="mb-5"
        >
          <h3 class="text-sm font-semibold text-gray-700 mb-2 px-1">{{ group.pension_name }}</h3>
          <div class="space-y-3">
            <PlanActionCard
              v-for="action in sortByPriority(group.actions)"
              :key="action.id"
              :action="action"
              @toggle="$emit('toggle', $event)"
            />
          </div>
          <PensionGrowthProjectionChart
            v-if="group.projection"
            :projection="group.projection"
            :enabled-action-count="group.enabledCount"
            :total-action-count="group.totalCount"
          />
        </div>

        <!-- Portfolio Actions -->
        <div v-if="portfolioActions.length" class="mb-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-2 px-1">Portfolio Actions</h3>
          <div class="space-y-3">
            <PlanActionCard
              v-for="action in sortByPriority(portfolioActions)"
              :key="action.id"
              :action="action"
              @toggle="$emit('toggle', $event)"
            />
          </div>
        </div>

        <!-- Portfolio Projection (total DC pot growth) -->
        <div v-if="hasWhatIfData" class="bg-white rounded-lg border border-gray-200 p-4 mt-3 mb-5">
          <div class="flex items-center justify-between mb-1">
            <h4 class="text-sm font-semibold text-gray-900">Portfolio Projection</h4>
            <span
              v-if="portfolioProjectionDifference > 0"
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800"
            >
              +{{ formatCurrency(portfolioProjectionDifference) }} over {{ portfolioYears }} years
            </span>
          </div>
          <p class="text-xs text-gray-500 mb-3">
            Total pension pot growth &middot;
            5% assumed growth
          </p>
          <apexchart
            type="line"
            :height="220"
            :options="portfolioChartOptions"
            :series="portfolioChartSeries"
          />

          <!-- Side-by-side metrics -->
          <div class="grid grid-cols-2 divide-x divide-gray-200 border-t border-gray-200 mt-4 pt-4">
            <div class="pr-4">
              <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Current Position</h5>
              <RetirementWhatIfControls :scenario="whatIf.current_scenario" />
            </div>
            <div class="pl-4">
              <h5 class="text-xs font-semibold text-green-700 uppercase tracking-wider mb-3">With Actions</h5>
              <RetirementWhatIfControls :scenario="whatIf.projected_scenario" />
            </div>
          </div>
        </div>
      </template>
    </template>

    <div v-else class="bg-gray-50 rounded-lg border border-gray-200 p-6 text-center">
      <p class="text-gray-500 text-sm">No recommendations available for this plan.</p>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';
import PlanActionCard from '@/components/Plans/Shared/PlanActionCard.vue';
import PensionGrowthProjectionChart from './PensionGrowthProjectionChart.vue';
import RetirementWhatIfControls from './RetirementWhatIfControls.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS } from '@/constants/designSystem';

export default {
  name: 'RetirementGroupedActions',

  mixins: [currencyMixin],

  components: {
    PlanSectionHeader,
    PlanActionCard,
    PensionGrowthProjectionChart,
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

      // Also add projection groups that have no actions (still want the chart)
      if (this.pensionProjections && this.pensionProjections.length) {
        this.pensionProjections.forEach(proj => {
          if (!grouped[proj.pension_id]) {
            grouped[proj.pension_id] = {
              pension_id: proj.pension_id,
              pension_name: proj.pension_name,
              actions: [],
              projection: proj,
            };
          }
        });
      }

      return Object.values(grouped).map(group => ({
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
          type: 'line',
          toolbar: { show: false },
          zoom: { enabled: false },
          fontFamily: 'Inter, system-ui, sans-serif',
        },
        colors: [CHART_COLORS[1], CHART_COLORS[2]],
        stroke: {
          curve: 'smooth',
          width: 2.5,
        },
        xaxis: {
          categories: Array.from({ length: this.portfolioYears + 1 }, (_, i) => `Year ${i}`),
          labels: {
            style: { fontSize: '11px', colors: '#64748B' },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            formatter: (val) => self.formatCurrencyCompact(val),
            style: { fontSize: '11px', colors: '#64748B' },
          },
        },
        grid: {
          borderColor: '#E2E8F0',
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
  },
};
</script>
