<template>
  <div class="net-worth-projection">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Net Worth Projection</h3>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <p class="text-sm text-gray-600 font-medium">Current Net Worth</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(projectionData.current_net_worth) }}</p>
      </div>
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-sm text-blue-600 font-medium">Projected (Baseline)</p>
        <p class="text-2xl font-bold text-blue-900 mt-1">{{ formatCurrency(finalBaselineValue) }}</p>
        <p class="text-xs text-blue-600 mt-1">in {{ projectionYears }} years</p>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <p class="text-sm text-green-600 font-medium">Projected (Optimised)</p>
        <p class="text-2xl font-bold text-green-900 mt-1">{{ formatCurrency(finalOptimisedValue) }}</p>
        <p class="text-xs text-green-600 mt-1">{{ formatCurrency(projectionData.improvement) }} improvement</p>
      </div>
    </div>

    <!-- Chart -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
      <apexchart
        type="area"
        height="400"
        :options="chartOptions"
        :series="chartSeries"
      ></apexchart>
    </div>

    <!-- Improvement Summary -->
    <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-6">
      <div class="flex items-start">
        <svg class="h-6 w-6 text-green-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <div class="flex-1">
          <h4 class="text-md font-semibold text-green-900 mb-2">Impact of Recommendations</h4>
          <p class="text-sm text-green-800">
            By implementing the recommended actions, your net worth could increase by an additional
            <span class="font-bold">{{ formatCurrency(projectionData.improvement) }}</span>
            ({{ projectionData.improvement_percent }}%) over the next {{ projectionYears }} years.
          </p>
        </div>
      </div>
    </div>

    <!-- Assumptions -->
    <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
      <details>
        <summary class="text-sm font-medium text-gray-700 cursor-pointer">View Assumptions</summary>
        <ul class="mt-3 space-y-2 text-xs text-gray-600">
          <li>• Baseline projection assumes current savings rate and 4% average annual growth</li>
          <li>• Optimised projection assumes recommended contribution increases and 6% average annual growth</li>
          <li>• Projections do not account for inflation</li>
          <li>• Investment returns are assumed to be consistent (actual returns will vary)</li>
          <li>• Does not include potential inheritance or one-off windfalls</li>
        </ul>
      </details>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { PRIMARY_COLORS, SUCCESS_COLORS, BORDER_COLORS } from '@/constants/designSystem';

export default {
  name: 'NetWorthProjectionChart',
  mixins: [currencyMixin],

  props: {
    projectionData: {
      type: Object,
      required: true,
    },
  },

  computed: {
    projectionYears() {
      return this.projectionData.baseline_projections?.length - 1 || 20;
    },

    finalBaselineValue() {
      const projections = this.projectionData.baseline_projections || [];
      return projections.length > 0 ? projections[projections.length - 1].value : 0;
    },

    finalOptimisedValue() {
      const projections = this.projectionData.optimized_projections || [];
      return projections.length > 0 ? projections[projections.length - 1].value : 0;
    },

    chartSeries() {
      const baseline = this.projectionData.baseline_projections || [];
      const optimised = this.projectionData.optimized_projections || [];

      return [
        {
          name: 'Baseline',
          data: baseline.map(p => ({ x: p.year, y: p.value })),
        },
        {
          name: 'Optimised',
          data: optimised.map(p => ({ x: p.year, y: p.value })),
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          type: 'area',
          toolbar: {
            show: true,
          },
          zoom: {
            enabled: true,
          },
        },
        colours: [PRIMARY_COLORS[500], SUCCESS_COLORS[500]],
        dataLabels: {
          enabled: false,
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        fill: {
          type: 'gradient',
          gradient: {
            opacityFrom: 0.6,
            opacityTo: 0.1,
          },
        },
        xaxis: {
          type: 'numeric',
          title: {
            text: 'Years from Now',
          },
        },
        yaxis: {
          title: {
            text: 'Net Worth (£)',
          },
          labels: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
        legend: {
          position: 'top',
        },
        tooltip: {
          x: {
            formatter: (value) => `Year ${value}`,
          },
          y: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
        grid: {
          borderColour: BORDER_COLORS.default,
        },
      };
    },
  },

};
</script>
