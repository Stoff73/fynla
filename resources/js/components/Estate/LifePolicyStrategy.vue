<template>
  <div class="life-policy-strategy-tab">
    <!-- Back to Dashboard Link -->
    <button
      @click="$emit('switch-tab', 'iht')"
      class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800 mb-4"
    >
      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Back to Estate Dashboard
    </button>
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <p class="mt-4 text-gray-600">Calculating life policy strategy...</p>
    </div>

    <!-- No Inheritance Tax Liability State -->
    <div v-else-if="noIHTLiability" class="bg-white border-2 border-green-500 rounded-lg p-6">
      <div class="flex items-start">
        <svg class="h-6 w-6 text-green-600 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="ml-3">
          <h3 class="text-lg font-semibold text-green-900">No Life Insurance Required</h3>
          <p class="mt-2 text-green-700">{{ noIHTMessage }}</p>
          <p class="mt-2 text-sm text-green-600">You have no projected Inheritance Tax liability at expected death. Life insurance for Inheritance Tax planning is not necessary.</p>
        </div>
      </div>
    </div>

    <!-- Strategy Comparison -->
    <div v-else-if="strategy" class="space-y-6">
      <!-- Whole of Life Insurance Details -->
      <div class="bg-white rounded-lg border-2 border-indigo-300 shadow-lg">
        <div class="bg-white border-b-2 border-indigo-500 px-6 py-4 border-b border-indigo-200">
          <div>
            <h3 class="text-xl font-bold text-indigo-900">{{ policy.policy_type }}</h3>
            <p class="text-sm text-indigo-700 mt-1">{{ policy.description }}</p>
          </div>
        </div>

        <div class="p-4 sm:p-6">
          <!-- Key Metrics -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-white rounded-lg p-3 sm:p-4 border-2 border-indigo-500">
              <p class="text-xs sm:text-sm text-indigo-700 font-medium mb-1">Cover Amount</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-indigo-900">{{ formatCurrency(policy.cover_amount) }}</p>
              <p class="text-xs text-indigo-600 mt-1">Guaranteed payout</p>
            </div>
            <div class="bg-white rounded-lg p-3 sm:p-4 border-2 border-green-500">
              <p class="text-xs sm:text-sm text-green-700 font-medium mb-1">Monthly Premium</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-green-900">{{ formatCurrency(policy.monthly_premium) }}</p>
            </div>
            <div class="bg-white rounded-lg p-3 sm:p-4 border-2 border-blue-500">
              <p class="text-xs sm:text-sm text-blue-700 font-medium mb-1">Annual Premium</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-blue-900">{{ formatCurrency(policy.annual_premium) }}</p>
              <p class="text-xs text-blue-600 mt-1">Per year</p>
            </div>
            <div class="bg-white rounded-lg p-3 sm:p-4 border-2 border-purple-500">
              <p class="text-xs sm:text-sm text-purple-700 font-medium mb-1">Total Premiums</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-purple-900">{{ formatCurrency(policy.total_premiums_paid) }}</p>
              <p class="text-xs text-purple-600 mt-1">Over {{ policy.term_years }} years</p>
            </div>
          </div>

          <!-- Key Features -->
          <div class="mb-6">
            <h4 class="text-md font-semibold text-gray-900 mb-3">Key Features</h4>
            <ul class="space-y-2">
              <li v-for="(feature, index) in policy.key_features" :key="index" class="flex items-start">
                <svg class="h-5 w-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-gray-700">{{ feature }}</span>
              </li>
            </ul>
          </div>

          <!-- Implementation Steps -->
          <div>
            <h4 class="text-md font-semibold text-gray-900 mb-3">Implementation Steps</h4>
            <ol class="space-y-2">
              <li v-for="(step, index) in policy.implementation_steps" :key="index" class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 bg-white border-b-2 border-indigo-500 text-indigo-700 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                  {{ index + 1 }}
                </span>
                <span class="text-gray-700 pt-0.5">{{ step }}</span>
              </li>
            </ol>
          </div>
        </div>
      </div>

      <!-- Decision Framework -->
      <div class="bg-white rounded-lg border border-gray-300 shadow-lg">
        <div class="bg-gray-100 px-6 py-4 border-b border-gray-200">
          <h3 class="text-xl font-bold text-gray-900">Decision Framework</h3>
          <p class="text-sm text-gray-600 mt-1">Use this framework to help decide which approach is best for you</p>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="(items, decision) in strategy.comparison.decision_framework" :key="decision">
              <h4 class="text-md font-semibold mb-3" :class="{
                'text-indigo-900': decision.includes('Insurance'),
                'text-blue-900': decision.includes('Self-Insurance'),
                'text-purple-900': decision.includes('Hybrid')
              }">{{ decision }}</h4>
              <ul class="space-y-2">
                <li v-for="(item, index) in items" :key="index" class="flex items-start text-sm">
                  <span class="mr-2" :class="{
                    'text-indigo-600': decision.includes('Insurance'),
                    'text-blue-600': decision.includes('Self-Insurance'),
                    'text-purple-600': decision.includes('Hybrid')
                  }">•</span>
                  <span class="text-gray-700">{{ item }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-white border-2 border-red-500 rounded-lg p-6">
      <div class="flex items-start">
        <svg class="h-6 w-6 text-red-600 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="ml-3">
          <h3 class="text-lg font-semibold text-red-900">Error Loading Strategy</h3>
          <p class="mt-2 text-red-700">{{ error }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import estateService from '../../services/estateService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'LifePolicyStrategy',

  emits: ['switch-tab'],

  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
      error: null,
      strategy: null,
      noIHTLiability: false,
      noIHTMessage: '',
    };
  },

  computed: {
    ...mapGetters('estate', ['assets', 'investmentAccounts', 'liabilities']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
    policy() {
      return this.strategy?.whole_of_life_policy || {};
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadStrategy();
  },

  methods: {
    computePreviewStrategy() {
      // Calculate total assets from estate store
      const assetsValue = this.assets?.reduce((sum, a) => sum + parseFloat(a.current_value || 0), 0) || 0;
      const investmentsValue = this.investmentAccounts?.reduce((sum, i) => sum + parseFloat(i.current_value || 0), 0) || 0;
      const totalAssets = assetsValue + investmentsValue;

      // Calculate total liabilities
      const totalLiabilities = this.liabilities?.reduce((sum, l) => sum + parseFloat(l.current_balance || 0), 0) || 0;

      // Calculate net estate
      const netEstate = totalAssets - totalLiabilities;

      // IHT allowances (UK 2025/26)
      const nrb = 325000;
      const hasMainResidence = this.assets?.some(a => a.asset_type === 'property') || false;
      const rnrb = hasMainResidence ? 175000 : 0;
      const totalAllowance = nrb + rnrb;

      // Calculate taxable estate and Inheritance Tax liability
      const taxableEstate = Math.max(0, netEstate - totalAllowance);
      const ihtLiability = taxableEstate * 0.40;

      // Get user data - personaData was always undefined, use null
      const user = null;
      const currentAge = user?.age || 40;
      const estimatedDeathAge = user?.gender === 'female' ? 84 : 81;
      const yearsUntilDeath = estimatedDeathAge - currentAge;

      if (ihtLiability === 0) {
        // No Inheritance Tax liability - show "No Life Insurance Required" message
        this.noIHTLiability = true;
        this.noIHTMessage = `Your current estate of ${this.formatCurrency(netEstate)} is below the Inheritance Tax threshold of ${this.formatCurrency(totalAllowance)}. No inheritance tax liability is projected.`;
        this.loading = false;
        return;
      }

      // Has Inheritance Tax liability - compute strategy
      const monthlyPremium = Math.round((ihtLiability * 0.03) / 12); // Approx 3% annual premium for whole of life
      const annualPremium = monthlyPremium * 12;
      const totalPremiums = annualPremium * yearsUntilDeath;

      this.strategy = {
        cover_amount: ihtLiability,
        current_age: currentAge,
        years_until_death: yearsUntilDeath,
        is_joint_policy: null,
        whole_of_life_policy: {
          policy_type: 'Whole of Life Insurance',
          description: 'Guaranteed payout on death to cover Inheritance Tax liability',
          cover_amount: ihtLiability,
          monthly_premium: monthlyPremium,
          annual_premium: annualPremium,
          total_premiums_paid: totalPremiums,
          term_years: yearsUntilDeath,
          cost_benefit_ratio: Math.round(ihtLiability / totalPremiums * 10) / 10,
          key_features: [
            'Guaranteed payout at death (whenever that occurs)',
            'Must be written in trust to keep outside estate',
          ],
          implementation_steps: [
            'Compare quotes from multiple providers',
            'Complete medical questionnaire',
            'Set up policy in trust (recommended)',
            'Set up direct debit for premiums',
          ],
        },
        self_insurance: {
          strategy_name: 'Self-Insurance Fund',
          description: 'Build a dedicated fund to cover Inheritance Tax liability',
          required_fund: ihtLiability,
          monthly_contribution: Math.round(ihtLiability / (yearsUntilDeath * 12)),
          current_fund_value: 0,
          projected_fund_value: ihtLiability,
          is_sufficient: true,
          coverage_percentage: 100,
          confidence_level: 'Medium',
          key_benefits: [
            'Money stays in your control',
            'Potential investment growth',
            'Flexible - can use for other purposes if needed',
            'No ongoing premium commitments',
          ],
          key_risks: [
            'Early death may leave shortfall',
            'Investment performance uncertainty',
            'Discipline required to maintain contributions',
          ],
        },
        comparison: {
          recommended_approach: ihtLiability > 100000 ? 'Whole of Life Insurance' : 'Self-Insurance',
          summary: ihtLiability > 100000
            ? 'Given the significant Inheritance Tax liability, whole of life insurance provides certainty of coverage regardless of when death occurs.'
            : 'With a moderate Inheritance Tax liability, self-insurance may be more cost-effective if you have the discipline to build and maintain the fund.',
        },
      };

      this.loading = false;
    },

    async loadStrategy() {
      this.loading = true;
      this.error = null;
      this.noIHTLiability = false;

      try {
        const response = await estateService.getLifePolicyStrategy();

        if (response.success) {
          if (response.no_iht_liability) {
            this.noIHTLiability = true;
            this.noIHTMessage = response.message;
          } else {
            this.strategy = response.data;
          }
        } else {
          this.error = response.message || 'Failed to load life policy strategy';
        }
      } catch (err) {
        console.error('Failed to load life policy strategy:', err);
        this.error = err.response?.data?.message || 'An error occurred while loading the strategy';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
