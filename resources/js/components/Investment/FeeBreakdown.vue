<template>
  <div class="fee-breakdown">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-red-800">{{ error }}</span>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-6">
      <!-- Fee Summary Header -->
      <div class="bg-gradient-to-br from-orange-50 to-red-50 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">Portfolio Fee Analysis</h2>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <!-- Total Annual Fees -->
          <div class="bg-white rounded-lg p-4">
            <p class="text-sm text-gray-600 mb-2">Total Annual Fees</p>
            <p class="text-3xl font-bold text-red-600">
              £{{ formatNumber(feeData?.total_annual_fees || 0) }}
            </p>
            <p class="text-xs text-gray-500">{{ formatPercent((feeData?.total_annual_fees || 0) / (feeData?.portfolio_value || 1)) }} of portfolio</p>
          </div>

          <!-- Average Fee Rate -->
          <div class="bg-white rounded-lg p-4">
            <p class="text-sm text-gray-600 mb-2">Weighted Average Fee</p>
            <p class="text-3xl font-bold text-orange-600">
              {{ formatPercent(feeData?.weighted_average_fee || 0) }}
            </p>
            <p class="text-xs text-gray-500">across all holdings</p>
          </div>

          <!-- Potential Savings -->
          <div class="bg-white rounded-lg p-4">
            <p class="text-sm text-gray-600 mb-2">Annual Saving Potential</p>
            <p class="text-3xl font-bold text-green-600">
              £{{ formatNumber(feeData?.potential_savings?.annual || 0) }}
            </p>
            <p class="text-xs text-gray-500">by switching to low-cost options</p>
          </div>

          <!-- 30-Year Impact -->
          <div class="bg-white rounded-lg p-4">
            <p class="text-sm text-gray-600 mb-2">30-Year Fee Impact</p>
            <p class="text-3xl font-bold text-red-700">
              £{{ formatNumber(feeData?.long_term_impact?.thirty_years || 0) }}
            </p>
            <p class="text-xs text-gray-500">compound cost</p>
          </div>
        </div>
      </div>

      <!-- Fee Breakdown by Type -->
      <div v-if="feeData" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Fee Breakdown by Type</h3>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Pie Chart -->
          <div>
            <apexchart
              type="donut"
              :options="feeTypeChartOptions"
              :series="feeTypeChartSeries"
              height="300"
            />
          </div>

          <!-- Fee Type Details -->
          <div class="space-y-3">
            <div class="border border-gray-200 rounded-lg p-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700">Fund Ongoing Charges (OCF)</span>
                <span class="text-xl font-bold text-gray-800">
                  £{{ formatNumber(feeData.breakdown.fund_ocf || 0) }}
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                  class="h-2 bg-red-600 rounded-full"
                  :style="{ width: ((feeData.breakdown.fund_ocf / feeData.total_annual_fees) * 100) + '%' }"
                ></div>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                {{ formatPercent(feeData.breakdown.fund_ocf / feeData.total_annual_fees) }} of total fees
              </p>
            </div>

            <div class="border border-gray-200 rounded-lg p-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700">Platform Fees</span>
                <span class="text-xl font-bold text-gray-800">
                  £{{ formatNumber(feeData.breakdown.platform_fees || 0) }}
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                  class="h-2 bg-orange-600 rounded-full"
                  :style="{ width: ((feeData.breakdown.platform_fees / feeData.total_annual_fees) * 100) + '%' }"
                ></div>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                {{ formatPercent(feeData.breakdown.platform_fees / feeData.total_annual_fees) }} of total fees
              </p>
            </div>

            <div class="border border-gray-200 rounded-lg p-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700">Trading Costs</span>
                <span class="text-xl font-bold text-gray-800">
                  £{{ formatNumber(feeData.breakdown.trading_costs || 0) }}
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                  class="h-2 bg-yellow-600 rounded-full"
                  :style="{ width: ((feeData.breakdown.trading_costs / feeData.total_annual_fees) * 100) + '%' }"
                ></div>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                {{ formatPercent(feeData.breakdown.trading_costs / feeData.total_annual_fees) }} of total fees
              </p>
            </div>

            <div class="border border-gray-200 rounded-lg p-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700">Other Fees</span>
                <span class="text-xl font-bold text-gray-800">
                  £{{ formatNumber(feeData.breakdown.other_fees || 0) }}
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                  class="h-2 bg-gray-600 rounded-full"
                  :style="{ width: ((feeData.breakdown.other_fees / feeData.total_annual_fees) * 100) + '%' }"
                ></div>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                {{ formatPercent(feeData.breakdown.other_fees / feeData.total_annual_fees) }} of total fees
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Holdings Fee Analysis -->
      <div v-if="feeData && feeData.holdings" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Fee Analysis by Holding</h3>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="text-left py-3 px-4 font-semibold text-gray-700">Holding</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-700">Value</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-700">Ongoing Charges</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-700">Annual Fee</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-700">Fee Category</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-700">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="holding in feeData.holdings"
                :key="holding.id"
                class="border-b border-gray-100 hover:bg-gray-50"
              >
                <td class="py-3 px-4 font-medium">{{ holding.name }}</td>
                <td class="text-right py-3 px-4">£{{ formatNumber(holding.value) }}</td>
                <td class="text-right py-3 px-4">{{ formatPercent(holding.ocf) }}</td>
                <td class="text-right py-3 px-4 font-semibold">£{{ formatNumber(holding.annual_fee) }}</td>
                <td class="text-right py-3 px-4">
                  <span class="px-2 py-1 rounded text-xs font-semibold" :class="getFeeCategoryClass(holding.fee_category)">
                    {{ holding.fee_category }}
                  </span>
                </td>
                <td class="text-right py-3 px-4">
                  <button
                    v-if="holding.has_alternative"
                    @click="viewAlternatives(holding.id)"
                    class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                  >
                    View Alternatives
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Compound Impact Visualization -->
      <div v-if="feeData" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Compound Impact of Fees Over Time</h3>

        <apexchart
          type="area"
          :options="compoundImpactChartOptions"
          :series="compoundImpactChartSeries"
          height="350"
        />

        <div class="mt-6 grid grid-cols-3 gap-4">
          <div class="text-center p-4 bg-red-50 rounded-lg border border-red-200">
            <p class="text-xs text-gray-600 mb-1">10-Year Fee Impact</p>
            <p class="text-2xl font-bold text-red-600">
              £{{ formatNumber(feeData.long_term_impact?.ten_years || 0) }}
            </p>
          </div>
          <div class="text-center p-4 bg-orange-50 rounded-lg border border-orange-200">
            <p class="text-xs text-gray-600 mb-1">20-Year Fee Impact</p>
            <p class="text-2xl font-bold text-orange-600">
              £{{ formatNumber(feeData.long_term_impact?.twenty_years || 0) }}
            </p>
          </div>
          <div class="text-center p-4 bg-red-50 rounded-lg border border-red-300">
            <p class="text-xs text-gray-600 mb-1">30-Year Fee Impact</p>
            <p class="text-2xl font-bold text-red-700">
              £{{ formatNumber(feeData.long_term_impact?.thirty_years || 0) }}
            </p>
          </div>
        </div>

        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <p class="text-sm text-gray-700">
            <strong>Example:</strong> A 1% annual fee on a £100,000 portfolio growing at 7% costs you
            <strong class="text-red-600">£{{ formatNumber(feeData.example_impact || 0) }}</strong> over 30 years
            compared to a 0.2% fee.
          </p>
        </div>
      </div>

      <!-- Fee Recommendations -->
      <div v-if="feeData && feeData.recommendations" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Fee Reduction Recommendations</h3>

        <div class="space-y-3">
          <div
            v-for="(rec, index) in feeData.recommendations"
            :key="index"
            class="border-l-4 p-4 rounded-r-lg"
            :class="getPriorityClass(rec.priority)"
          >
            <div class="flex items-start justify-between mb-2">
              <h4 class="font-semibold text-gray-800">{{ rec.title }}</h4>
              <span class="px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800">
                Save £{{ formatNumber(rec.annual_saving) }}/yr
              </span>
            </div>
            <p class="text-sm text-gray-600 mb-2">{{ rec.description }}</p>
            <div class="bg-gray-50 rounded p-3 text-sm">
              <p class="font-medium text-gray-700 mb-1">Action:</p>
              <p class="text-gray-600">{{ rec.action }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';

export default {
  name: 'FeeBreakdown',

  data() {
    return {
      loading: true,
      error: null,
      feeData: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    feeTypeChartOptions() {
      return {
        chart: {
          type: 'donut',
        },
        labels: ['Fund Ongoing Charges', 'Platform Fees', 'Trading Costs', 'Other Fees'],
        colours: ['#EF4444', '#F97316', '#FBBF24', '#9CA3AF'],
        legend: {
          position: 'bottom',
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => val.toFixed(1) + '%',
        },
        tooltip: {
          y: {
            formatter: (val) => '£' + this.formatNumber(val),
          },
        },
      };
    },

    feeTypeChartSeries() {
      if (!this.feeData) return [];

      return [
        this.feeData.breakdown.fund_ocf || 0,
        this.feeData.breakdown.platform_fees || 0,
        this.feeData.breakdown.trading_costs || 0,
        this.feeData.breakdown.other_fees || 0,
      ];
    },

    compoundImpactChartOptions() {
      return {
        chart: {
          type: 'area',
          stacked: false,
          toolbar: {
            show: false,
          },
        },
        stroke: {
          curve: 'smooth',
          width: 3,
        },
        xaxis: {
          title: {
            text: 'Years',
          },
        },
        yaxis: {
          title: {
            text: 'Portfolio Value (£)',
          },
          labels: {
            formatter: (val) => '£' + this.formatNumber(val),
          },
        },
        colours: ['#10B981', '#EF4444'],
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.1,
          },
        },
        legend: {
          position: 'top',
        },
      };
    },

    compoundImpactChartSeries() {
      if (!this.feeData?.compound_impact) return [];

      return [
        {
          name: 'With Low Fees (0.2%)',
          data: this.feeData.compound_impact.low_fee_path || [],
        },
        {
          name: 'With Current Fees',
          data: this.feeData.compound_impact.current_fee_path || [],
        },
      ];
    },
  },

  mounted() {
    if (!this.isPreviewMode) {
      this.loadFeeData();
    } else {
      console.log('[FeeBreakdown] Preview mode - skipping API call');
      this.loading = false;
    }
  },

  methods: {
    async loadFeeData() {
      if (this.isPreviewMode) {
        console.log('[FeeBreakdown] Preview mode - skipping loadFeeData');
        return;
      }
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get('/investment/fee-impact/analyze');
        this.feeData = response.data.data;
      } catch (err) {
        console.error('Error loading fee data:', err);
        this.error = err.response?.data?.message || 'Failed to load fee breakdown. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async viewAlternatives(holdingId) {
      if (this.isPreviewMode) {
        console.log('[FeeBreakdown] Preview mode - skipping viewAlternatives');
        return;
      }
      try {
        const response = await api.get(`/investment/fee-impact/holdings/${holdingId}/alternatives`);
        // Open modal or navigate to alternatives view
        console.log('Alternatives:', response.data);
      } catch (err) {
        console.error('Error loading alternatives:', err);
      }
    },

    formatNumber(value) {
      if (!value && value !== 0) return '0';
      return Math.round(value).toLocaleString('en-GB');
    },

    formatPercent(value) {
      if (value === null || value === undefined) return 'N/A';
      return (value * 100).toFixed(2) + '%';
    },

    getFeeCategoryClass(category) {
      const classes = {
        'Low': 'bg-green-100 text-green-800',
        'Medium': 'bg-yellow-100 text-yellow-800',
        'High': 'bg-red-100 text-red-800',
      };
      return classes[category] || 'bg-gray-100 text-gray-800';
    },

    getPriorityClass(priority) {
      const classes = {
        high: 'border-red-500 bg-red-50',
        medium: 'border-yellow-500 bg-yellow-50',
        low: 'border-blue-500 bg-blue-50',
      };
      return classes[priority] || classes.low;
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
