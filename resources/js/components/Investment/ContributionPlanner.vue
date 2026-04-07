<template>
  <div class="contribution-planner">
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
      <!-- Input Form Card -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-horizon-500 mb-6">Contribution Planning Inputs</h2>

        <form @submit.prevent="optimiseContributions" class="space-y-6">
          <!-- Monthly Investable Income -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Monthly Investable Income (£)
              </label>
              <input
                v-model.number="formData.monthly_investable_income"
                type="number"
                step="50"
                min="0"
                required
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 500"
              />
              <p class="text-xs text-neutral-500 mt-1">Amount available for investment after expenses</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Lump Sum Amount (£)
              </label>
              <input
                v-model.number="formData.lump_sum_amount"
                type="number"
                step="100"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 10000"
              />
              <p class="text-xs text-neutral-500 mt-1">Optional one-time investment</p>
            </div>
          </div>

          <!-- Time Horizon and Risk Tolerance -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Time Horizon (Years)
              </label>
              <select
                v-model.number="formData.time_horizon_years"
                required
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option :value="5">5 years</option>
                <option :value="10">10 years</option>
                <option :value="15">15 years</option>
                <option :value="20">20 years</option>
                <option :value="25">25 years</option>
                <option :value="30">30 years</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-2">
                Risk Tolerance
              </label>
              <select
                v-model="formData.risk_tolerance"
                required
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="conservative">Conservative (Lower risk/return)</option>
                <option value="moderately_conservative">Moderately Conservative</option>
                <option value="balanced">Balanced</option>
                <option value="moderately_aggressive">Moderately Aggressive</option>
                <option value="aggressive">Aggressive (Higher risk/return)</option>
              </select>
            </div>
          </div>

          <!-- Income Tax Band -->
          <div>
            <label class="block text-sm font-medium text-neutral-500 mb-2">
              Income Tax Band
            </label>
            <select
              v-model="formData.income_tax_band"
              required
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            >
              <option value="basic">Basic Rate (20%)</option>
              <option value="higher">Higher Rate (40%)</option>
              <option value="additional">Additional Rate (45%)</option>
            </select>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end">
            <button
              type="submit"
              :disabled="optimising"
              class="px-6 py-3 bg-raspberry-500 text-white font-medium rounded-button hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <span v-if="optimising">Optimising...</span>
              <span v-else>Optimise My Contributions</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Results Section (only shown after optimization) -->
      <div v-if="optimizationResult" class="space-y-6">
        <!-- Tax Efficiency -->
        <div class="bg-gradient-to-br from-violet-50 to-violet-100 rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Tax Efficiency</h3>
          <div class="text-center p-4">
            <p class="text-lg font-bold text-horizon-500">{{ taxEfficiencyLabel }}</p>
            <p class="text-sm text-neutral-500 mt-1">{{ taxEfficiencyDescription }}</p>
          </div>
        </div>

        <!-- Wrapper Allocation -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Recommended Wrapper Allocation</h3>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- ISA Contribution -->
            <div class="border border-light-gray rounded-lg p-4">
              <p class="text-sm text-neutral-500 mb-1">ISA (Tax-Free)</p>
              <p class="text-2xl font-bold text-spring-600">
                £{{ formatNumber(optimizationResult.wrapper_allocation.isa_contribution) }}
              </p>
              <p class="text-xs text-neutral-500 mt-1">per month</p>
            </div>

            <!-- Pension Contribution -->
            <div class="border border-light-gray rounded-lg p-4">
              <p class="text-sm text-neutral-500 mb-1">Pension (Tax Relief)</p>
              <p class="text-2xl font-bold text-violet-600">
                £{{ formatNumber(optimizationResult.wrapper_allocation.pension_contribution) }}
              </p>
              <p class="text-xs text-neutral-500 mt-1">per month</p>
            </div>

            <!-- GIA Contribution -->
            <div class="border border-light-gray rounded-lg p-4">
              <p class="text-sm text-neutral-500 mb-1">General Investment Account (Taxable)</p>
              <p class="text-2xl font-bold text-neutral-500">
                £{{ formatNumber(optimizationResult.wrapper_allocation.gia_contribution) }}
              </p>
              <p class="text-xs text-neutral-500 mt-1">per month</p>
            </div>
          </div>

          <!-- Rationale -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-horizon-500 mb-2">Rationale:</h4>
            <ul class="space-y-1">
              <li v-for="(reason, index) in optimizationResult.wrapper_allocation.rationale" :key="`rationale-${index}-${reason.slice(0, 20)}`" class="text-sm text-neutral-500 flex items-start">
                <svg class="h-4 w-4 text-violet-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                {{ reason }}
              </li>
            </ul>
          </div>
        </div>

        <!-- Tax Relief (if pension contributions) -->
        <div v-if="optimizationResult.tax_relief && optimizationResult.tax_relief.total_relief > 0" class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Pension Tax Relief</h3>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div>
              <p class="text-sm text-neutral-500 mb-1">Annual Contribution</p>
              <p class="text-xl font-semibold text-horizon-500">
                £{{ formatNumber(optimizationResult.tax_relief.annual_contribution) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">Basic Rate Relief (20%)</p>
              <p class="text-xl font-semibold text-spring-600">
                £{{ formatNumber(optimizationResult.tax_relief.basic_rate_relief) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">Higher Rate Relief</p>
              <p class="text-xl font-semibold text-spring-600">
                £{{ formatNumber(optimizationResult.tax_relief.higher_rate_relief) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">Total Relief ({{ optimizationResult.tax_relief.relief_percent }}%)</p>
              <p class="text-xl font-semibold text-spring-700">
                £{{ formatNumber(optimizationResult.tax_relief.total_relief) }}
              </p>
            </div>
          </div>

          <div class="bg-eggshell-500 rounded-lg p-4">
            <p class="text-sm text-neutral-500">
              <strong>Effective Annual Cost:</strong> £{{ formatNumber(optimizationResult.tax_relief.effective_cost) }}
              (after tax relief)
            </p>
          </div>
        </div>

        <!-- Lump Sum Analysis -->
        <div v-if="optimizationResult.lump_sum_analysis" class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Lump Sum vs Dollar-Cost Averaging</h3>

          <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-medium text-neutral-500">Recommendation:</span>
              <span class="px-3 py-1 rounded-full text-sm font-semibold" :class="optimizationResult.lump_sum_analysis.recommendation === 'lump_sum' ? 'bg-violet-500 text-white' : 'bg-spring-500 text-white'">
                {{ optimizationResult.lump_sum_analysis.recommendation === 'lump_sum' ? 'Lump Sum' : 'Dollar-Cost Averaging' }}
              </span>
            </div>
            <p class="text-sm text-neutral-500">{{ optimizationResult.lump_sum_analysis.rationale }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Lump Sum Strategy -->
            <div class="border border-light-gray rounded-lg p-4">
              <h4 class="text-md font-semibold text-horizon-500 mb-3">Lump Sum Strategy</h4>
              <div class="mb-4">
                <p class="text-sm text-neutral-500 mb-1">Expected Final Value:</p>
                <p class="text-2xl font-bold text-violet-600">
                  £{{ formatNumber(optimizationResult.lump_sum_analysis.lump_sum.expected_final_value) }}
                </p>
              </div>
              <div class="space-y-2">
                <p class="text-xs font-medium text-spring-600">Pros:</p>
                <ul class="space-y-1">
                  <li v-for="(pro, index) in optimizationResult.lump_sum_analysis.lump_sum.pros" :key="`ls-pro-${index}`" class="text-xs text-neutral-500 flex items-start">
                    <span class="mr-2">+</span>{{ pro }}
                  </li>
                </ul>
                <p class="text-xs font-medium text-raspberry-600 mt-3">Cons:</p>
                <ul class="space-y-1">
                  <li v-for="(con, index) in optimizationResult.lump_sum_analysis.lump_sum.cons" :key="`ls-con-${index}`" class="text-xs text-neutral-500 flex items-start">
                    <span class="mr-2">-</span>{{ con }}
                  </li>
                </ul>
              </div>
            </div>

            <!-- DCA Strategy -->
            <div class="border border-light-gray rounded-lg p-4">
              <h4 class="text-md font-semibold text-horizon-500 mb-3">Dollar-Cost Averaging</h4>
              <div class="mb-4">
                <p class="text-sm text-neutral-500 mb-1">Expected Final Value:</p>
                <p class="text-2xl font-bold text-spring-600">
                  £{{ formatNumber(optimizationResult.lump_sum_analysis.dca.expected_final_value) }}
                </p>
                <p class="text-xs text-neutral-500 mt-1">
                  £{{ formatNumber(optimizationResult.lump_sum_analysis.dca.monthly_amount) }}/month for {{ optimizationResult.lump_sum_analysis.dca.duration_months }} months
                </p>
              </div>
              <div class="space-y-2">
                <p class="text-xs font-medium text-spring-600">Pros:</p>
                <ul class="space-y-1">
                  <li v-for="(pro, index) in optimizationResult.lump_sum_analysis.dca.pros" :key="`dca-pro-${index}`" class="text-xs text-neutral-500 flex items-start">
                    <span class="mr-2">+</span>{{ pro }}
                  </li>
                </ul>
                <p class="text-xs font-medium text-raspberry-600 mt-3">Cons:</p>
                <ul class="space-y-1">
                  <li v-for="(con, index) in optimizationResult.lump_sum_analysis.dca.cons" :key="`dca-con-${index}`" class="text-xs text-neutral-500 flex items-start">
                    <span class="mr-2">-</span>{{ con }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div class="mt-4 p-3 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500">
              <strong>Timing Risk:</strong> {{ optimizationResult.lump_sum_analysis.timing_risk }}
              ({{ optimizationResult.lump_sum_analysis.percentage_of_portfolio }}% of current portfolio)
            </p>
          </div>
        </div>

        <!-- Projections -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Projected Outcomes ({{ optimizationResult.projections.years }} years)</h3>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="text-center p-4 bg-eggshell-500 rounded-lg">
              <p class="text-sm text-neutral-500 mb-1">Conservative Scenario (5th percentile)</p>
              <p class="text-2xl font-bold text-raspberry-600">
                £{{ formatNumber(optimizationResult.projections.conservative_value) }}
              </p>
            </div>
            <div class="text-center p-4 bg-eggshell-500 rounded-lg">
              <p class="text-sm text-neutral-500 mb-1">Expected Value (Median)</p>
              <p class="text-3xl font-bold text-violet-600">
                £{{ formatNumber(optimizationResult.projections.expected_value) }}
              </p>
            </div>
            <div class="text-center p-4 bg-eggshell-500 rounded-lg">
              <p class="text-sm text-neutral-500 mb-1">Optimistic Scenario (95th percentile)</p>
              <p class="text-2xl font-bold text-spring-600">
                £{{ formatNumber(optimizationResult.projections.optimistic_value) }}
              </p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="p-3 bg-eggshell-500 rounded-lg">
              <p class="text-sm text-neutral-500 mb-1">Total Contributions</p>
              <p class="text-xl font-semibold text-horizon-500">
                £{{ formatNumber(optimizationResult.projections.total_contributions) }}
              </p>
            </div>
            <div class="p-3 bg-eggshell-500 rounded-lg">
              <p class="text-sm text-neutral-500 mb-1">Expected Growth</p>
              <p class="text-xl font-semibold text-spring-600">
                £{{ formatNumber(optimizationResult.projections.expected_growth) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Recommendations -->
        <div v-if="optimizationResult.recommendations && optimizationResult.recommendations.length > 0" class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Recommendations</h3>

          <div class="space-y-3">
            <div v-for="(rec, index) in optimizationResult.recommendations" :key="rec.title || `rec-${index}`" class="border-l-4 p-4 rounded-r-lg" :class="getPriorityClass(rec.priority)">
              <div class="flex items-start justify-between mb-2">
                <h4 class="font-semibold text-horizon-500">{{ rec.title }}</h4>
                <span class="px-2 py-1 text-xs font-semibold rounded uppercase" :class="getPriorityBadgeClass(rec.priority)">
                  {{ rec.priority }}
                </span>
              </div>
              <p class="text-sm text-neutral-500 mb-2">{{ rec.description }}</p>
              <p class="text-sm font-medium text-neutral-500">
                <strong>Action:</strong> {{ rec.action }}
              </p>
            </div>
          </div>
        </div>

        <!-- ISA Status -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">ISA Allowance Status ({{ getCurrentTaxYear() }})</h3>

          <div class="mb-4">
            <div class="flex justify-between text-sm text-neutral-500 mb-2">
              <span>Used: £{{ formatNumber(optimizationResult.isa_status.used || 0) }}</span>
              <span>Remaining: £{{ formatNumber(optimizationResult.isa_status.remaining || 0) }}</span>
            </div>
            <div class="w-full bg-savannah-200 rounded-full h-3">
              <div
                class="h-3 rounded-full transition-all duration-300"
                :class="(optimizationResult.isa_status.used / optimizationResult.isa_status.limit * 100) > 90 ? 'bg-raspberry-600' : 'bg-raspberry-500'"
                :style="{ width: Math.min(100, (optimizationResult.isa_status.used / optimizationResult.isa_status.limit * 100)) + '%' }"
              ></div>
            </div>
            <p class="text-xs text-neutral-500 mt-1">
              {{ ((optimizationResult.isa_status.used / optimizationResult.isa_status.limit) * 100).toFixed(1) }}% of £{{ formatNumber(optimizationResult.isa_status.limit) }} allowance used
            </p>
          </div>

          <div v-if="optimizationResult.isa_status.warning" class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-sm text-violet-800">
              <strong>Warning:</strong> You are approaching your ISA allowance limit for this tax year.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'ContributionPlanner',

  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
      optimising: false,
      error: null,
      formData: {
        monthly_investable_income: 500,
        lump_sum_amount: 0,
        time_horizon_years: 20,
        risk_tolerance: 'balanced',
        income_tax_band: 'basic',
      },
      optimizationResult: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    taxEfficiencyLabel() {
      const score = this.optimizationResult?.tax_efficiency_score || 0;
      if (score >= 80) return 'Highly Efficient';
      if (score >= 60) return 'Moderately Efficient';
      if (score >= 40) return 'Could Be Improved';
      return 'Needs Attention';
    },
    taxEfficiencyDescription() {
      const score = this.optimizationResult?.tax_efficiency_score || 0;
      if (score >= 80) return 'Your contributions are well-optimised for tax efficiency.';
      if (score >= 60) return 'There may be opportunities to improve your tax position.';
      if (score >= 40) return 'Consider reviewing your contribution strategy for better tax efficiency.';
      return 'Your current strategy may not be taking full advantage of available tax reliefs.';
    },
  },

  methods: {
    async optimiseContributions() {
      // Preview users are real DB users - use normal API for calculations
      this.optimising = true;
      this.error = null;

      try {
        const response = await api.post('/investment/contribution/optimize', this.formData);
        this.optimizationResult = response.data;
      } catch (err) {
        logger.error('Error optimising contributions:', err);
        this.error = err.response?.data?.message || 'Failed to optimise contributions. Please try again.';
      } finally {
        this.optimising = false;
      }
    },

    getPriorityClass(priority) {
      const classes = {
        high: 'bg-eggshell-500',
        medium: 'bg-eggshell-500',
        low: 'bg-eggshell-500',
      };
      return classes[priority] || classes.low;
    },

    getPriorityBadgeClass(priority) {
      const classes = {
        high: 'bg-raspberry-500 text-white',
        medium: 'bg-violet-500 text-white',
        low: 'bg-violet-500 text-white',
      };
      return classes[priority] || classes.low;
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
