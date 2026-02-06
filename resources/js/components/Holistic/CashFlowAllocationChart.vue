<template>
  <div class="cashflow-allocation">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Cashflow Allocation</h3>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-sm text-blue-600 font-medium">Available Surplus</p>
        <p class="text-2xl font-bold text-blue-900 mt-1">{{ formatCurrency(cashflowData.available_surplus) }}</p>
        <p class="text-xs text-blue-600 mt-1">per month</p>
      </div>
      <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
        <p class="text-sm text-purple-600 font-medium">Total Demand</p>
        <p class="text-2xl font-bold text-purple-900 mt-1">{{ formatCurrency(cashflowData.allocation?.total_demand || 0) }}</p>
        <p class="text-xs text-purple-600 mt-1">across all modules</p>
      </div>
      <div :class="hasShortfall ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'" class="border rounded-lg p-4">
        <p :class="hasShortfall ? 'text-red-600' : 'text-green-600'" class="text-sm font-medium">
          {{ hasShortfall ? 'Shortfall' : 'Remaining Surplus' }}
        </p>
        <p :class="hasShortfall ? 'text-red-900' : 'text-green-900'" class="text-2xl font-bold mt-1">
          {{ formatCurrency(hasShortfall ? cashflowData.allocation?.total_shortfall || 0 : cashflowData.allocation?.surplus_remaining || 0) }}
        </p>
        <p :class="hasShortfall ? 'text-red-600' : 'text-green-600'" class="text-xs mt-1">
          {{ hasShortfall ? 'additional funding needed' : 'unallocated' }}
        </p>
      </div>
    </div>

    <!-- Chart -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
      <apexchart
        :key="chartKey"
        type="bar"
        height="350"
        :options="chartOptions"
        :series="chartSeries"
      ></apexchart>
    </div>

    <!-- Allocation Details -->
    <div class="mt-6 bg-white border border-gray-200 rounded-lg overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Requested</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Allocated</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Shortfall</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Funded</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="(details, category) in cashflowData.allocation?.allocation" :key="category">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
              {{ formatCategoryName(category) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700">
              {{ formatCurrency(details.requested) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900">
              {{ formatCurrency(details.allocated) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="details.shortfall > 0 ? 'text-red-600' : 'text-green-600'">
              {{ details.shortfall > 0 ? formatCurrency(details.shortfall) : '-' }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
              <span :class="getPercentFundedClass(details.percent_funded)" class="px-2 py-1 rounded text-xs font-medium">
                {{ details.percent_funded }}%
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Shortfall Recommendations -->
    <div v-if="hasShortfall && cashflowData.shortfall_analysis?.recommendations" class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
      <h4 class="text-md font-semibold text-yellow-900 mb-3 flex items-center">
        <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        Recommendations to Address Shortfall
      </h4>
      <ul class="space-y-2">
        <li v-for="(recommendation, index) in cashflowData.shortfall_analysis.recommendations" :key="index" class="flex items-start text-sm text-yellow-800">
          <svg class="h-5 w-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
          </svg>
          {{ recommendation }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { SUCCESS_COLORS, ERROR_COLORS } from '@/constants/designSystem';

export default {
  name: 'CashFlowAllocationChart',
  mixins: [currencyMixin],

  props: {
    cashflowData: {
      type: Object,
      required: true,
    },
  },

  computed: {
    chartKey() {
      const total = this.chartSeries?.[0]?.data?.reduce((a, b) => a + b, 0) || 0;
      return `cashflow-${this.chartSeries?.[0]?.data?.length || 0}-${Math.round(total)}`;
    },

    hasShortfall() {
      return this.cashflowData.shortfall_analysis?.has_shortfall || false;
    },

    chartSeries() {
      const allocation = this.cashflowData.allocation?.allocation || {};
      const allocated = [];
      const shortfall = [];

      Object.entries(allocation).forEach(([category, details]) => {
        allocated.push(details.allocated);
        shortfall.push(details.shortfall);
      });

      return [
        {
          name: 'Allocated',
          data: allocated,
        },
        {
          name: 'Shortfall',
          data: shortfall,
        },
      ];
    },

    chartOptions() {
      const allocation = this.cashflowData.allocation?.allocation || {};
      const categories = Object.keys(allocation).map(cat => this.formatCategoryName(cat));

      return {
        chart: {
          type: 'bar',
          stacked: true,
          toolbar: {
            show: true,
          },
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '55%',
          },
        },
        colours: [SUCCESS_COLORS[500], ERROR_COLORS[500]],
        dataLabels: {
          enabled: false,
        },
        xaxis: {
          categories: categories,
        },
        yaxis: {
          title: {
            text: 'Amount (£)',
          },
          labels: {
            formatter: (value) => `£${value.toFixed(0)}`,
          },
        },
        legend: {
          position: 'top',
        },
        tooltip: {
          y: {
            formatter: (value) => `£${value.toFixed(2)}`,
          },
        },
      };
    },
  },

  methods: {
    formatCategoryName(category) {
      const names = {
        emergency_fund: 'Emergency Fund',
        protection: 'Protection',
        pension: 'Pension',
        investment: 'Investment',
        estate: 'Estate',
      };
      return names[category] || category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    getPercentFundedClass(percent) {
      if (percent >= 100) return 'bg-green-100 text-green-800';
      if (percent >= 75) return 'bg-yellow-100 text-yellow-800';
      if (percent >= 50) return 'bg-blue-100 text-blue-800';
      return 'bg-red-100 text-red-800';
    },
  },
};
</script>
