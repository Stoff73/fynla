<template>
  <div class="fee-savings-calculator">
    <div class="space-y-6">
      <!-- Calculator Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-horizon-500 mb-2">Fee Savings Calculator</h2>
        <p class="text-sm text-neutral-500">
          See how much you could save by reducing your investment fees
        </p>
      </div>

      <!-- Input Form -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Your Portfolio Details</h3>

        <form @submit.prevent="calculateSavings" class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Portfolio Value -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Current Portfolio Value (£)
              </label>
              <input
                v-model.number="calculator.portfolioValue"
                type="number"
                step="1000"
                min="0"
                required
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 100000"
                @input="calculateSavings"
              />
            </div>

            <!-- Monthly Contribution -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Monthly Contribution (£)
              </label>
              <input
                v-model.number="calculator.monthlyContribution"
                type="number"
                step="50"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 500"
                @input="calculateSavings"
              />
            </div>

            <!-- Current Fee Rate -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Current Annual Fee (%)
              </label>
              <div class="relative">
                <input
                  v-model.number="calculator.currentFee"
                  type="range"
                  min="0"
                  max="3"
                  step="0.1"
                  class="w-full"
                  @input="calculateSavings"
                />
                <div class="flex justify-between text-xs text-neutral-500 mt-1">
                  <span>0%</span>
                  <span class="font-semibold text-neutral-500">{{ calculator.currentFee.toFixed(2) }}%</span>
                  <span>3%</span>
                </div>
              </div>
            </div>

            <!-- Alternative Fee Rate -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Alternative Fee (%)
              </label>
              <div class="relative">
                <input
                  v-model.number="calculator.alternativeFee"
                  type="range"
                  min="0"
                  max="2"
                  step="0.05"
                  class="w-full"
                  @input="calculateSavings"
                />
                <div class="flex justify-between text-xs text-neutral-500 mt-1">
                  <span>0%</span>
                  <span class="font-semibold text-neutral-500">{{ calculator.alternativeFee.toFixed(2) }}%</span>
                  <span>2%</span>
                </div>
              </div>
            </div>

            <!-- Expected Return -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Expected Annual Return (%)
              </label>
              <select
                v-model.number="calculator.expectedReturn"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                @change="calculateSavings"
              >
                <option :value="0.04">4% (Conservative)</option>
                <option :value="0.06">6% (Moderate)</option>
                <option :value="0.07">7% (Balanced)</option>
                <option :value="0.08">8% (Growth)</option>
                <option :value="0.10">10% (Aggressive)</option>
              </select>
            </div>

            <!-- Time Horizon -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Time Horizon (Years)
              </label>
              <select
                v-model.number="calculator.years"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                @change="calculateSavings"
              >
                <option :value="10">10 years</option>
                <option :value="15">15 years</option>
                <option :value="20">20 years</option>
                <option :value="25">25 years</option>
                <option :value="30">30 years</option>
              </select>
            </div>
          </div>
        </form>
      </div>

      <!-- Results Summary Cards -->
      <div v-if="results" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Annual Savings -->
        <div class="bg-eggshell-500 rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">Annual Fee Savings</p>
          <p class="text-4xl font-bold text-spring-600 mb-2">
            £{{ formatNumber(results.annual_saving) }}
          </p>
          <p class="text-xs text-neutral-500">
            {{ formatPercent((calculator.currentFee - calculator.alternativeFee) / 100) }} reduction
          </p>
        </div>

        <!-- Total Savings Over Period -->
        <div class="bg-eggshell-500 rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">Total Savings ({{ calculator.years }} years)</p>
          <p class="text-4xl font-bold text-violet-600 mb-2">
            £{{ formatNumber(results.total_saving) }}
          </p>
          <p class="text-xs text-neutral-500">
            Compound savings with growth
          </p>
        </div>

        <!-- Percentage Gain -->
        <div class="bg-eggshell-500 rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">Portfolio Value Increase</p>
          <p class="text-4xl font-bold text-violet-600 mb-2">
            {{ formatPercent(results.percentage_gain / 100) }}
          </p>
          <p class="text-xs text-neutral-500">
            Extra growth from lower fees
          </p>
        </div>
      </div>

      <!-- Comparison Chart -->
      <div v-if="results" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Portfolio Growth Comparison</h3>

        <apexchart
          type="area"
          :options="comparisonChartOptions"
          :series="comparisonChartSeries"
          height="400"
        />

        <div class="mt-6 grid grid-cols-2 gap-6">
          <!-- Current Fees -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <p class="text-sm font-medium text-neutral-500 mb-2">With Current Fees ({{ formatPercent(calculator.currentFee / 100) }})</p>
            <p class="text-3xl font-bold text-raspberry-600 mb-1">
              £{{ formatNumber(results.current_fee_final) }}
            </p>
            <div class="text-xs text-neutral-500 space-y-1">
              <div class="flex justify-between">
                <span>Total Contributions:</span>
                <span>£{{ formatNumber(results.total_contributions) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Growth:</span>
                <span>£{{ formatNumber(results.current_fee_growth) }}</span>
              </div>
              <div class="flex justify-between text-raspberry-600 font-semibold">
                <span>Fees Paid:</span>
                <span>£{{ formatNumber(results.current_fee_total_fees) }}</span>
              </div>
            </div>
          </div>

          <!-- Alternative Fees -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <p class="text-sm font-medium text-neutral-500 mb-2">With Alternative Fees ({{ formatPercent(calculator.alternativeFee / 100) }})</p>
            <p class="text-3xl font-bold text-spring-600 mb-1">
              £{{ formatNumber(results.alternative_fee_final) }}
            </p>
            <div class="text-xs text-neutral-500 space-y-1">
              <div class="flex justify-between">
                <span>Total Contributions:</span>
                <span>£{{ formatNumber(results.total_contributions) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Growth:</span>
                <span>£{{ formatNumber(results.alternative_fee_growth) }}</span>
              </div>
              <div class="flex justify-between text-spring-600 font-semibold">
                <span>Fees Paid:</span>
                <span>£{{ formatNumber(results.alternative_fee_total_fees) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Fee Impact Examples -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Common Fee Scenarios</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <button
            @click="applyScenario('active')"
            class="text-left p-4 border border-light-gray rounded-lg hover:bg-violet-50 hover:border-violet-300 transition-colors"
          >
            <p class="font-semibold text-horizon-500 mb-2">Active Funds</p>
            <p class="text-2xl font-bold text-violet-600 mb-1">1.5% - 2.0%</p>
            <p class="text-xs text-neutral-500">Typical active management fees</p>
          </button>

          <button
            @click="applyScenario('passive')"
            class="text-left p-4 border border-light-gray rounded-lg hover:bg-violet-50 hover:border-violet-300 transition-colors"
          >
            <p class="font-semibold text-horizon-500 mb-2">Passive/Index Funds</p>
            <p class="text-2xl font-bold text-spring-600 mb-1">0.1% - 0.3%</p>
            <p class="text-xs text-neutral-500">Low-cost tracker funds</p>
          </button>

          <button
            @click="applyScenario('platform')"
            class="text-left p-4 border border-light-gray rounded-lg hover:bg-violet-50 hover:border-violet-300 transition-colors"
          >
            <p class="font-semibold text-horizon-500 mb-2">Platform + Fund</p>
            <p class="text-2xl font-bold text-raspberry-600 mb-1">0.5% - 1.0%</p>
            <p class="text-xs text-neutral-500">Combined platform & fund fees</p>
          </button>
        </div>
      </div>

      <!-- Key Insights -->
      <div class="bg-eggshell-500 rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Why Fees Matter</h3>

        <div class="space-y-3">
          <div class="flex items-start">
            <svg class="h-5 w-5 text-violet-600 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-sm font-medium text-horizon-500 mb-1">Compound Effect</p>
              <p class="text-sm text-neutral-500">
                Fees compound negatively - a 1% annual fee costs you much more than 1% of your final portfolio value.
              </p>
            </div>
          </div>

          <div class="flex items-start">
            <svg class="h-5 w-5 text-violet-600 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-sm font-medium text-horizon-500 mb-1">Lost Growth</p>
              <p class="text-sm text-neutral-500">
                Fees reduce your balance, which means less money to benefit from compound growth over time.
              </p>
            </div>
          </div>

          <div class="flex items-start">
            <svg class="h-5 w-5 text-violet-600 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-sm font-medium text-horizon-500 mb-1">Small Differences Matter</p>
              <p class="text-sm text-neutral-500">
                A 0.5% fee difference on a £100,000 portfolio can cost you over £100,000 in 30 years.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ERROR_COLORS, SUCCESS_COLORS, CHART_DEFAULTS, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'FeeSavingsCalculator',

  mixins: [currencyMixin],

  data() {
    return {
      calculator: {
        portfolioValue: 100000,
        monthlyContribution: 500,
        currentFee: 1.0,
        alternativeFee: 0.2,
        expectedReturn: 0.07,
        years: 20,
      },
      results: null,
    };
  },

  computed: {
    comparisonChartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
          stacked: false,
          toolbar: {
            show: true,
          },
        },
        stroke: {
          curve: 'smooth',
          width: [3, 3],
        },
        xaxis: {
          title: {
            text: 'Years',
          },
          type: 'numeric',
        },
        yaxis: {
          title: {
            text: 'Portfolio Value (£)',
          },
          labels: {
            formatter: (val) => '£' + this.formatNumber(val),
          },
        },
        colors: [ERROR_COLORS[500], SUCCESS_COLORS[500]],
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
        tooltip: {
          shared: true,
          intersect: false,
          y: {
            formatter: (val) => '£' + this.formatNumber(val),
          },
        },
      };
    },

    comparisonChartSeries() {
      if (!this.results) return [];

      return [
        {
          name: `Current Fees (${this.formatPercent(this.calculator.currentFee / 100)})`,
          data: this.results.current_fee_path || [],
        },
        {
          name: `Alternative Fees (${this.formatPercent(this.calculator.alternativeFee / 100)})`,
          data: this.results.alternative_fee_path || [],
        },
      ];
    },
  },

  mounted() {
    this.calculateSavings();
  },

  methods: {
    calculateSavings() {
      const { portfolioValue, monthlyContribution, currentFee, alternativeFee, expectedReturn, years } = this.calculator;

      // Current fee scenario
      const currentFeeData = this.projectGrowth(portfolioValue, monthlyContribution, expectedReturn, currentFee / 100, years);

      // Alternative fee scenario
      const alternativeFeeData = this.projectGrowth(portfolioValue, monthlyContribution, expectedReturn, alternativeFee / 100, years);

      // Calculate savings
      const totalSaving = alternativeFeeData.final - currentFeeData.final;
      const totalContributions = portfolioValue + (monthlyContribution * 12 * years);
      const annualSaving = (portfolioValue * (currentFee - alternativeFee)) / 100;

      this.results = {
        annual_saving: annualSaving,
        total_saving: totalSaving,
        percentage_gain: (totalSaving / currentFeeData.final) * 100,
        current_fee_final: currentFeeData.final,
        current_fee_growth: currentFeeData.growth,
        current_fee_total_fees: currentFeeData.totalFees,
        alternative_fee_final: alternativeFeeData.final,
        alternative_fee_growth: alternativeFeeData.growth,
        alternative_fee_total_fees: alternativeFeeData.totalFees,
        total_contributions: totalContributions,
        current_fee_path: currentFeeData.path,
        alternative_fee_path: alternativeFeeData.path,
      };
    },

    projectGrowth(initial, monthly, returnRate, feeRate, years) {
      let value = initial;
      const path = [[0, initial]];
      let totalFees = 0;

      for (let year = 1; year <= years; year++) {
        for (let month = 0; month < 12; month++) {
          value += monthly;
          const monthlyReturn = returnRate / 12;
          const monthlyFee = feeRate / 12;
          const growth = value * monthlyReturn;
          const fee = value * monthlyFee;
          value = value + growth - fee;
          totalFees += fee;
        }
        path.push([year, value]);
      }

      return {
        final: value,
        growth: value - initial - (monthly * 12 * years),
        totalFees,
        path,
      };
    },

    applyScenario(scenario) {
      if (scenario === 'active') {
        this.calculator.currentFee = 1.75;
        this.calculator.alternativeFee = 0.2;
      } else if (scenario === 'passive') {
        this.calculator.currentFee = 0.2;
        this.calculator.alternativeFee = 0.1;
      } else if (scenario === 'platform') {
        this.calculator.currentFee = 0.75;
        this.calculator.alternativeFee = 0.2;
      }
      this.calculateSavings();
    },

    formatPercent(value) {
      if (value === null || value === undefined) return 'N/A';
      return (value * 100).toFixed(2) + '%';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
