<template>
  <div class="wrapper-optimiser">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-raspberry-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-raspberry-800">{{ error }}</span>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-6">
      <!-- Wrapper Comparison Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-horizon-500 mb-6">Wrapper Comparison: ISA vs General Investment Account vs Pension</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- ISA Wrapper -->
          <div class="bg-eggshell-500 rounded-lg p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold text-horizon-500">ISA</h3>
              <span class="px-3 py-1 bg-spring-500 text-white text-xs font-semibold rounded-full">TAX-FREE</span>
            </div>

            <div class="space-y-3 mb-4">
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Annual Limit:</span>
                <span class="font-semibold text-horizon-500">£20,000</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Tax on Growth:</span>
                <span class="font-semibold text-spring-600">£0 (0%)</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Tax on Income:</span>
                <span class="font-semibold text-spring-600">£0 (0%)</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Withdrawal:</span>
                <span class="font-semibold text-spring-600">Tax-Free</span>
              </div>
            </div>

            <div class="border-t border-light-gray pt-3">
              <p class="text-xs text-neutral-500 mb-2 font-medium">Best For:</p>
              <ul class="text-xs text-neutral-500 space-y-1">
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Medium-term goals (5-20 years)</span>
                </li>
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Dividend-paying stocks</span>
                </li>
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Bonds and fixed income</span>
                </li>
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Flexible access needed</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- GIA Wrapper -->
          <div class="bg-eggshell-500 rounded-lg p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold text-horizon-500">General Investment Account</h3>
              <span class="px-3 py-1 bg-violet-500 text-white text-xs font-semibold rounded-full">TAXABLE</span>
            </div>

            <div class="space-y-3 mb-4">
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Annual Limit:</span>
                <span class="font-semibold text-horizon-500">None</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Tax on Growth:</span>
                <span class="font-semibold text-violet-600">Capital Gains Tax (10-20%)</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Tax on Income:</span>
                <span class="font-semibold text-violet-600">Income Tax</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Withdrawal:</span>
                <span class="font-semibold text-violet-600">May Trigger Tax</span>
              </div>
            </div>

            <div class="border-t border-light-gray pt-3">
              <p class="text-xs text-neutral-500 mb-2 font-medium">Best For:</p>
              <ul class="text-xs text-neutral-500 space-y-1">
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Large portfolios (>£20k/year)</span>
                </li>
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Growth stocks (Capital Gains Tax efficient)</span>
                </li>
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Index funds (low turnover)</span>
                </li>
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>After ISA limit exhausted</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- Pension Wrapper -->
          <div class="bg-eggshell-500 rounded-lg p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold text-horizon-500">Pension</h3>
              <span class="px-3 py-1 bg-violet-500 text-white text-xs font-semibold rounded-full">TAX RELIEF</span>
            </div>

            <div class="space-y-3 mb-4">
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Annual Limit:</span>
                <span class="font-semibold text-horizon-500">£60,000</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Tax Relief:</span>
                <span class="font-semibold text-spring-600">20-45%</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Tax on Growth:</span>
                <span class="font-semibold text-spring-600">£0 (0%)</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-neutral-500">Withdrawal:</span>
                <span class="font-semibold text-violet-600">Age 55+ only</span>
              </div>
            </div>

            <div class="border-t border-light-gray pt-3">
              <p class="text-xs text-neutral-500 mb-2 font-medium">Best For:</p>
              <ul class="text-xs text-neutral-500 space-y-1">
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Retirement savings (long-term)</span>
                </li>
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Higher/additional rate taxpayers</span>
                </li>
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Aggressive growth assets</span>
                </li>
                <li class="flex items-start">
                  <span class="mr-1">✓</span>
                  <span>Maximum tax efficiency</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Interactive Scenario Calculator -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Wrapper Tax Comparison Calculator</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <!-- Input Form -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Investment Amount (£)
              </label>
              <input
                v-model.number="calculator.investment"
                type="number"
                step="1000"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                @input="calculateComparison"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Time Horizon (Years)
              </label>
              <input
                v-model.number="calculator.years"
                type="range"
                min="1"
                max="40"
                class="w-full"
                @input="calculateComparison"
              />
              <div class="flex justify-between text-xs text-neutral-500">
                <span>1 year</span>
                <span class="font-semibold text-neutral-500">{{ calculator.years }} years</span>
                <span>40 years</span>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Expected Annual Return (%)
              </label>
              <input
                v-model.number="calculator.returnRate"
                type="range"
                min="0"
                max="15"
                step="0.5"
                class="w-full"
                @input="calculateComparison"
              />
              <div class="flex justify-between text-xs text-neutral-500">
                <span>0%</span>
                <span class="font-semibold text-neutral-500">{{ calculator.returnRate }}%</span>
                <span>15%</span>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Your Income Tax Band
              </label>
              <select
                v-model="calculator.taxBand"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                @change="calculateComparison"
              >
                <option value="basic">Basic Rate (20%)</option>
                <option value="higher">Higher Rate (40%)</option>
                <option value="additional">Additional Rate (45%)</option>
              </select>
            </div>
          </div>

          <!-- Results Chart -->
          <div>
            <h4 class="text-sm font-semibold text-neutral-500 mb-3">Final Value After Tax</h4>
            <apexchart
              v-if="comparisonResults"
              type="bar"
              :options="comparisonChartOptions"
              :series="comparisonChartSeries"
              height="280"
            />
          </div>
        </div>

        <!-- Detailed Results -->
        <div v-if="comparisonResults" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- ISA Result -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-horizon-500 mb-3">ISA (Tax-Free)</h4>
            <p class="text-2xl font-bold text-spring-600 mb-2">
              £{{ formatNumber(comparisonResults.isa.finalValue) }}
            </p>
            <div class="text-xs text-neutral-500 space-y-1">
              <div class="flex justify-between">
                <span>Initial Investment:</span>
                <span>£{{ formatNumber(calculator.investment) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Growth:</span>
                <span>£{{ formatNumber(comparisonResults.isa.growth) }}</span>
              </div>
              <div class="flex justify-between font-semibold text-spring-600">
                <span>Tax Paid:</span>
                <span>£0</span>
              </div>
            </div>
          </div>

          <!-- GIA Result -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-horizon-500 mb-3">General Investment Account (Taxable)</h4>
            <p class="text-2xl font-bold text-violet-600 mb-2">
              £{{ formatNumber(comparisonResults.gia.finalValue) }}
            </p>
            <div class="text-xs text-neutral-500 space-y-1">
              <div class="flex justify-between">
                <span>Initial Investment:</span>
                <span>£{{ formatNumber(calculator.investment) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Gross Growth:</span>
                <span>£{{ formatNumber(comparisonResults.gia.grossGrowth) }}</span>
              </div>
              <div class="flex justify-between font-semibold text-raspberry-600">
                <span>Tax Paid (Capital Gains Tax):</span>
                <span>£{{ formatNumber(comparisonResults.gia.taxPaid) }}</span>
              </div>
            </div>
          </div>

          <!-- Pension Result -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-horizon-500 mb-3">Pension (With Relief)</h4>
            <p class="text-2xl font-bold text-violet-600 mb-2">
              £{{ formatNumber(comparisonResults.pension.finalValue) }}
            </p>
            <div class="text-xs text-neutral-500 space-y-1">
              <div class="flex justify-between">
                <span>Your Contribution:</span>
                <span>£{{ formatNumber(calculator.investment) }}</span>
              </div>
              <div class="flex justify-between font-semibold text-spring-600">
                <span>Tax Relief:</span>
                <span>£{{ formatNumber(comparisonResults.pension.taxRelief) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Invested Amount:</span>
                <span>£{{ formatNumber(comparisonResults.pension.investedAmount) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Growth:</span>
                <span>£{{ formatNumber(comparisonResults.pension.growth) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Winner Summary -->
        <div v-if="comparisonResults" class="mt-6 p-4 rounded-lg" :class="getWinnerClass()">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-horizon-500 mb-1">Best Choice for This Scenario:</p>
              <p class="text-lg font-bold" :class="getWinnerTextClass()">{{ getWinner() }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm text-neutral-500">Advantage over General Investment Account:</p>
              <p class="text-xl font-bold text-spring-600">£{{ formatNumber(getAdvantage()) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tax Allowances Tracker -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Tax Allowances ({{ getCurrentTaxYear() }})</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- ISA Allowance -->
          <div>
            <div class="flex justify-between items-center mb-2">
              <h4 class="text-sm font-semibold text-neutral-500">ISA Allowance</h4>
              <span class="text-sm font-semibold text-neutral-500">£20,000</span>
            </div>
            <div class="w-full bg-savannah-200 rounded-full h-3 mb-2">
              <div class="h-3 bg-spring-600 rounded-full" style="width: 45%"></div>
            </div>
            <div class="flex justify-between text-xs text-neutral-500">
              <span>Used: £9,000</span>
              <span>Remaining: £11,000</span>
            </div>
          </div>

          <!-- CGT Allowance -->
          <div>
            <div class="flex justify-between items-center mb-2">
              <h4 class="text-sm font-semibold text-neutral-500">Capital Gains Tax Allowance</h4>
              <span class="text-sm font-semibold text-neutral-500">£{{ cgtAnnualAllowance.toLocaleString() }}</span>
            </div>
            <div class="w-full bg-savannah-200 rounded-full h-3 mb-2">
              <div class="h-3 bg-violet-600 rounded-full" style="width: 20%"></div>
            </div>
            <div class="flex justify-between text-xs text-neutral-500">
              <span>Used: £600</span>
              <span>Remaining: £2,400</span>
            </div>
          </div>

          <!-- Pension Annual Allowance -->
          <div>
            <div class="flex justify-between items-center mb-2">
              <h4 class="text-sm font-semibold text-neutral-500">Pension Allowance</h4>
              <span class="text-sm font-semibold text-neutral-500">£60,000</span>
            </div>
            <div class="w-full bg-savannah-200 rounded-full h-3 mb-2">
              <div class="h-3 bg-raspberry-500 rounded-full" style="width: 30%"></div>
            </div>
            <div class="flex justify-between text-xs text-neutral-500">
              <span>Used: £18,000</span>
              <span>Remaining: £42,000</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Decision Framework -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Wrapper Selection Decision Framework</h3>

        <div class="space-y-4">
          <div class="bg-eggshell-500 p-4 rounded-lg">
            <h4 class="font-semibold text-horizon-500 mb-2">Step 1: Maximize ISA (Priority 1)</h4>
            <p class="text-sm text-neutral-500 mb-2">
              Use ISA allowance first (£20,000/year) for tax-free growth and withdrawals.
            </p>
            <p class="text-xs text-neutral-500">
              <strong>Best for:</strong> Medium-term goals, dividend stocks, bonds, flexibility needed
            </p>
          </div>

          <div class="bg-eggshell-500 p-4 rounded-lg">
            <h4 class="font-semibold text-horizon-500 mb-2">Step 2: Consider Pension (Priority 2)</h4>
            <p class="text-sm text-neutral-500 mb-2">
              If higher/additional rate taxpayer AND saving for retirement, pension offers 40-45% tax relief.
            </p>
            <p class="text-xs text-neutral-500">
              <strong>Best for:</strong> Long-term retirement savings, aggressive growth, tax efficiency
            </p>
          </div>

          <div class="bg-eggshell-500 p-4 rounded-lg">
            <h4 class="font-semibold text-horizon-500 mb-2">Step 3: Use General Investment Account (Priority 3)</h4>
            <p class="text-sm text-neutral-500 mb-2">
              After ISA limit exhausted, use a General Investment Account. Optimise with growth stocks (Capital Gains Tax-efficient) and index funds.
            </p>
            <p class="text-xs text-neutral-500">
              <strong>Best for:</strong> Large portfolios, growth investing, after ISA/Pension limits reached
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { SUCCESS_COLORS, WARNING_COLORS, PRIMARY_COLORS, TEXT_COLORS, CHART_DEFAULTS, BORDER_COLORS } from '@/constants/designSystem';
import { currencyMixin } from '@/mixins/currencyMixin';
import { CGT_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

export default {
  name: 'WrapperOptimiser',

  mixins: [currencyMixin],

  data() {
    return {
      cgtAnnualAllowance: CGT_ANNUAL_ALLOWANCE,
      loading: false,
      error: null,
      calculator: {
        investment: 10000,
        years: 20,
        returnRate: 7,
        taxBand: 'basic',
      },
      comparisonResults: null,
    };
  },

  computed: {
    comparisonChartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '70%',
            borderRadius: 4,
            dataLabels: {
              position: 'top',
            },
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => '£' + Math.round(val).toLocaleString('en-GB'),
          offsetY: -20,
          style: {
            fontSize: '11px',
            fontWeight: 'bold',
            colors: [TEXT_COLORS.primary],
          },
        },
        xaxis: {
          categories: ['ISA', 'General Investment Account', 'Pension'],
        },
        yaxis: {
          labels: {
            formatter: (val) => '£' + Math.round(val).toLocaleString('en-GB'),
          },
        },
        colors: [SUCCESS_COLORS[500], WARNING_COLORS[500], PRIMARY_COLORS[500]],
        tooltip: {
          y: {
            formatter: (val) => '£' + Math.round(val).toLocaleString('en-GB'),
          },
        },
      };
    },

    comparisonChartSeries() {
      if (!this.comparisonResults) return [];

      return [{
        name: 'Final Value',
        data: [
          this.comparisonResults.isa.finalValue,
          this.comparisonResults.gia.finalValue,
          this.comparisonResults.pension.finalValue,
        ],
      }];
    },
  },

  mounted() {
    this.calculateComparison();
  },

  methods: {
    calculateComparison() {
      const investment = this.calculator.investment;
      const years = this.calculator.years;
      const rate = this.calculator.returnRate / 100;
      const taxBand = this.calculator.taxBand;

      // ISA Calculation (tax-free)
      const isaFinalValue = investment * Math.pow(1 + rate, years);
      const isaGrowth = isaFinalValue - investment;

      // GIA Calculation (with CGT)
      const giaGrossGrowth = investment * (Math.pow(1 + rate, years) - 1);
      const cgtRate = taxBand === 'basic' ? 0.10 : 0.20;
      const cgtAllowance = CGT_ANNUAL_ALLOWANCE;
      const taxableGain = Math.max(0, giaGrossGrowth - cgtAllowance);
      const giaTax = taxableGain * cgtRate;
      const giaFinalValue = investment + giaGrossGrowth - giaTax;

      // Pension Calculation (with tax relief)
      const taxReliefRate = taxBand === 'basic' ? 0.20 : (taxBand === 'higher' ? 0.40 : 0.45);
      const taxRelief = investment * taxReliefRate;
      const investedAmount = investment + taxRelief;
      const pensionFinalValue = investedAmount * Math.pow(1 + rate, years);
      const pensionGrowth = pensionFinalValue - investedAmount;

      this.comparisonResults = {
        isa: {
          finalValue: isaFinalValue,
          growth: isaGrowth,
          taxPaid: 0,
        },
        gia: {
          finalValue: giaFinalValue,
          grossGrowth: giaGrossGrowth,
          taxPaid: giaTax,
        },
        pension: {
          finalValue: pensionFinalValue,
          taxRelief: taxRelief,
          investedAmount: investedAmount,
          growth: pensionGrowth,
        },
      };
    },

    getWinner() {
      if (!this.comparisonResults) return '';

      const values = {
        ISA: this.comparisonResults.isa.finalValue,
        GIA: this.comparisonResults.gia.finalValue,
        Pension: this.comparisonResults.pension.finalValue,
      };

      return Object.keys(values).reduce((a, b) => values[a] > values[b] ? a : b);
    },

    getAdvantage() {
      if (!this.comparisonResults) return 0;

      const winner = this.getWinner();
      const winnerValue = this.comparisonResults[winner.toLowerCase()].finalValue;
      const giaValue = this.comparisonResults.gia.finalValue;

      return winnerValue - giaValue;
    },

    getWinnerClass() {
      const winner = this.getWinner();
      if (winner === 'ISA') return 'bg-white border-l-4 border-spring-500';
      if (winner === 'Pension') return 'bg-white border-l-4 border-violet-500';
      return 'bg-white border-l-4 border-violet-500';
    },

    getWinnerTextClass() {
      const winner = this.getWinner();
      if (winner === 'ISA') return 'text-spring-600';
      if (winner === 'Pension') return 'text-violet-600';
      return 'text-violet-600';
    },

    getCurrentTaxYear() {
      const now = new Date();
      const taxYearStart = new Date(now.getFullYear(), 3, 6); // April 6

      if (now < taxYearStart) {
        return `${now.getFullYear() - 1}/${String(now.getFullYear()).slice(-2)}`;
      }

      return `${now.getFullYear()}/${String(now.getFullYear() + 1).slice(-2)}`;
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
