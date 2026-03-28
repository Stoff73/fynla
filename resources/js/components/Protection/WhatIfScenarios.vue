<template>
  <div class="what-if-scenarios">
    <!-- Scenario Builder -->
    <div class="mb-8">
      <ScenarioBuilder @scenario-run="handleScenarioRun" />
    </div>

    <!-- Scenario Results -->
    <div v-if="scenarioResults" class="space-y-6">
      <!-- Comparison Summary -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Scenario Impact</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Current Situation -->
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-2">Current Coverage</p>
            <p class="text-2xl font-bold text-horizon-500">
              {{ formatCurrency(scenarioResults.current.coverage) }}
            </p>
            <p class="text-xs text-neutral-500 mt-1">
              {{ formatCurrency(scenarioResults.current.premium) }}/month
            </p>
          </div>

          <!-- Scenario Impact -->
          <div class="text-center p-4 bg-violet-50 rounded-lg">
            <p class="text-sm text-neutral-500 mb-2">Scenario Coverage</p>
            <p class="text-2xl font-bold text-violet-900">
              {{ formatCurrency(scenarioResults.scenario.coverage) }}
            </p>
            <p class="text-xs text-neutral-500 mt-1">
              {{ formatCurrency(scenarioResults.scenario.premium) }}/month
            </p>
          </div>

          <!-- Difference -->
          <div class="text-center p-4 bg-spring-50 rounded-lg">
            <p class="text-sm text-neutral-500 mb-2">Improvement</p>
            <p class="text-2xl font-bold text-spring-900">
              {{ formatCurrency(scenarioResults.difference.coverage) }}
            </p>
            <p class="text-xs text-neutral-500 mt-1">
              +{{ formatCurrency(scenarioResults.difference.premium) }}/month
            </p>
          </div>
        </div>
      </div>

      <!-- Before/After Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Coverage Comparison -->
        <div class="bg-white rounded-lg border border-light-gray p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Coverage Comparison</h3>
          <apexchart
            type="bar"
            :options="coverageComparisonOptions"
            :series="coverageComparisonSeries"
            height="300"
          />
        </div>

        <!-- Premium Comparison -->
        <div class="bg-white rounded-lg border border-light-gray p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Premium Comparison</h3>
          <apexchart
            type="bar"
            :options="premiumComparisonOptions"
            :series="premiumComparisonSeries"
            height="300"
          />
        </div>
      </div>

      <!-- Financial Impact Visualization -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Financial Impact Over Time</h3>
        <apexchart
          type="area"
          :options="financialImpactOptions"
          :series="financialImpactSeries"
          height="350"
        />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 bg-white rounded-lg border border-light-gray">
      <svg
        class="mx-auto h-12 w-12 text-horizon-400"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
        />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-horizon-500">No scenario selected</h3>
      <p class="mt-1 text-sm text-neutral-500">
        Build and run a scenario above to see the impact analysis
      </p>
    </div>
  </div>
</template>

<script>
import ScenarioBuilder from './ScenarioBuilder.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { SUCCESS_COLORS, WARNING_COLORS, PRIMARY_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'WhatIfScenarios',
  mixins: [currencyMixin],

  components: {
    ScenarioBuilder,
  },

  data() {
    return {
      scenarioResults: null,
    };
  },

  computed: {
    coverageComparisonSeries() {
      if (!this.scenarioResults) return [];

      return [
        {
          name: 'Coverage',
          data: [
            this.scenarioResults.current.coverage,
            this.scenarioResults.scenario.coverage,
          ],
        },
      ];
    },

    coverageComparisonOptions() {
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'bar' },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '50%',
          },
        },
        colours: [PRIMARY_COLORS[500]],
        xaxis: {
          categories: ['Current', 'With Scenario'],
        },
        yaxis: {
          labels: {
            formatter: (value) => {
              return '£' + (value / 1000).toFixed(0) + 'k';
            },
          },
        },
        dataLabels: {
          enabled: false,
        },
      };
    },

    premiumComparisonSeries() {
      if (!this.scenarioResults) return [];

      return [
        {
          name: 'Monthly Premium',
          data: [
            this.scenarioResults.current.premium,
            this.scenarioResults.scenario.premium,
          ],
        },
      ];
    },

    premiumComparisonOptions() {
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'bar' },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '50%',
          },
        },
        colours: [SUCCESS_COLORS[500]],
        xaxis: {
          categories: ['Current', 'With Scenario'],
        },
        yaxis: {
          labels: {
            formatter: (value) => {
              return '£' + value.toFixed(2);
            },
          },
        },
        dataLabels: {
          enabled: false,
        },
      };
    },

    financialImpactSeries() {
      if (!this.scenarioResults) return [];

      // Generate projection data for 10 years
      const years = [];
      const currentProjection = [];
      const scenarioProjection = [];

      for (let i = 0; i <= 10; i++) {
        years.push(new Date().getFullYear() + i);
        currentProjection.push(this.scenarioResults.current.coverage);
        scenarioProjection.push(this.scenarioResults.scenario.coverage);
      }

      return [
        {
          name: 'Current Coverage',
          data: currentProjection,
        },
        {
          name: 'Scenario Coverage',
          data: scenarioProjection,
        },
      ];
    },

    financialImpactOptions() {
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'area' },
        colours: [WARNING_COLORS[500], SUCCESS_COLORS[500]],
        fill: {
          type: 'gradient',
          gradient: {
            opacityFrom: 0.6,
            opacityTo: 0.1,
          },
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        xaxis: {
          categories: Array.from({ length: 11 }, (_, i) => new Date().getFullYear() + i),
        },
        yaxis: {
          labels: {
            formatter: (value) => {
              return '£' + (value / 1000).toFixed(0) + 'k';
            },
          },
        },
        dataLabels: {
          enabled: false,
        },
        legend: {
          position: 'top',
          horizontalAlign: 'right',
        },
        tooltip: {
          y: {
            formatter: (value) => {
              return this.formatCurrency(value);
            },
          },
        },
      };
    },
  },

  methods: {
    async handleScenarioRun(scenarioData) {
      // Simulate scenario analysis
      // In a real app, this would call the API
      this.scenarioResults = {
        current: {
          coverage: 250000,
          premium: 45.50,
        },
        scenario: {
          coverage: 250000 + (scenarioData.additionalCoverage || 0),
          premium: 45.50 + (scenarioData.additionalPremium || 0),
        },
        difference: {
          coverage: scenarioData.additionalCoverage || 0,
          premium: scenarioData.additionalPremium || 0,
        },
      };
    },

  },
};
</script>

<style scoped>
/* Responsive adjustments */
@media (max-width: 640px) {
  .what-if-scenarios .grid {
    gap: 1rem;
  }
}
</style>
