<template>
  <div class="life-policy-strategy-tab">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <p class="mt-4 text-gray-600">Calculating life policy strategy...</p>
    </div>

    <!-- No IHT Liability State -->
    <div v-else-if="noIHTLiability" class="bg-green-50 border border-green-200 rounded-lg p-6">
      <div class="flex items-start">
        <svg class="h-6 w-6 text-green-600 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="ml-3">
          <h3 class="text-lg font-semibold text-green-900">No Life Insurance Required</h3>
          <p class="mt-2 text-green-700">{{ noIHTMessage }}</p>
          <p class="mt-2 text-sm text-green-600">You have no projected IHT liability at expected death. Life insurance for IHT planning is not necessary.</p>
        </div>
      </div>
    </div>

    <!-- Strategy Comparison -->
    <div v-else-if="strategy" class="space-y-6">
      <!-- Header with Key Info -->
      <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-4 sm:p-6 border border-indigo-200">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4">Life Insurance vs. Self-Insurance Strategy</h2>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
          <div class="bg-white rounded-lg p-3 sm:p-4 border border-indigo-100">
            <p class="text-xs sm:text-sm text-gray-600 mb-1">IHT to Cover</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-red-600">{{ formatCurrency(strategy.cover_amount) }}</p>
          </div>
          <div class="bg-white rounded-lg p-3 sm:p-4 border border-indigo-100">
            <p class="text-xs sm:text-sm text-gray-600 mb-1">Your Current Age</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">{{ strategy.current_age }}</p>
          </div>
          <div class="bg-white rounded-lg p-3 sm:p-4 border border-indigo-100">
            <p class="text-xs sm:text-sm text-gray-600 mb-1">Years Until Death</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">{{ strategy.years_until_death }}</p>
          </div>
          <div class="bg-white rounded-lg p-3 sm:p-4 border border-indigo-100">
            <p class="text-xs sm:text-sm text-gray-600 mb-1">Policy Type</p>
            <p class="text-base sm:text-lg font-bold text-indigo-600">{{ strategy.is_joint_policy ? 'Joint Life' : 'Single Life' }}</p>
            <p v-if="strategy.is_joint_policy" class="text-xs text-indigo-500 mt-1">Second Death</p>
          </div>
        </div>
      </div>

      <!-- Recommended Approach -->
      <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-r-lg">
        <div class="flex items-start">
          <svg class="h-6 w-6 text-blue-600 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="flex-1">
            <h3 class="text-lg font-semibold text-blue-900">Recommended: {{ strategy.comparison.recommended_approach }}</h3>
            <p class="mt-2 text-blue-800">{{ strategy.comparison.summary }}</p>
          </div>
        </div>
      </div>

      <!-- Option 1: Whole of Life Insurance -->
      <div class="bg-white rounded-lg border-2 border-indigo-300 shadow-lg">
        <div class="bg-indigo-100 px-6 py-4 border-b border-indigo-200">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-xl font-bold text-indigo-900">Option 1: {{ policy.policy_type }}</h3>
              <p class="text-sm text-indigo-700 mt-1">{{ policy.description }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm text-indigo-600">Cost-Benefit Ratio</p>
              <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-indigo-900">{{ policy.cost_benefit_ratio }}:1</p>
              <p class="text-xs text-indigo-600">£{{ policy.cost_benefit_ratio }} cover per £1 premium</p>
            </div>
          </div>
        </div>

        <div class="p-4 sm:p-6">
          <!-- Key Metrics -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-indigo-50 rounded-lg p-3 sm:p-4">
              <p class="text-xs sm:text-sm text-indigo-700 font-medium mb-1">Cover Amount</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-indigo-900">{{ formatCurrency(policy.cover_amount) }}</p>
              <p class="text-xs text-indigo-600 mt-1">Guaranteed payout</p>
            </div>
            <div class="bg-green-50 rounded-lg p-3 sm:p-4">
              <p class="text-xs sm:text-sm text-green-700 font-medium mb-1">Monthly Premium</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-green-900">{{ formatCurrency(policy.monthly_premium) }}</p>
              <p class="text-xs text-green-600 mt-1">Fixed for life</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-3 sm:p-4">
              <p class="text-xs sm:text-sm text-blue-700 font-medium mb-1">Annual Premium</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-blue-900">{{ formatCurrency(policy.annual_premium) }}</p>
              <p class="text-xs text-blue-600 mt-1">Per year</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-3 sm:p-4">
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
                <span class="flex-shrink-0 w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                  {{ index + 1 }}
                </span>
                <span class="text-gray-700 pt-0.5">{{ step }}</span>
              </li>
            </ol>
          </div>
        </div>
      </div>

      <!-- Option 2: Self-Insurance -->
      <div class="bg-white rounded-lg border-2 border-amber-300 shadow-lg">
        <div class="bg-amber-100 px-6 py-4 border-b border-amber-200">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-xl font-bold text-amber-900">Option 2: {{ selfInsurance.strategy_name }}</h3>
              <p class="text-sm text-amber-700 mt-1">{{ selfInsurance.description }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm text-amber-600">Coverage at Death</p>
              <p class="text-3xl font-bold" :class="selfInsurance.is_sufficient ? 'text-green-600' : 'text-red-600'">
                {{ selfInsurance.coverage_percentage }}%
              </p>
              <p class="text-xs" :class="selfInsurance.is_sufficient ? 'text-green-600' : 'text-red-600'">
                {{ selfInsurance.confidence_level }} confidence
              </p>
            </div>
          </div>
        </div>

        <div class="p-4 sm:p-6">
          <!-- Key Metrics -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-amber-50 rounded-lg p-3 sm:p-4">
              <p class="text-xs sm:text-sm text-amber-700 font-medium mb-1">Monthly Investment</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-amber-900">{{ formatCurrency(selfInsurance.monthly_investment) }}</p>
              <p class="text-xs text-amber-600 mt-1">Same as premium</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-3 sm:p-4">
              <p class="text-xs sm:text-sm text-blue-700 font-medium mb-1">Total Invested</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-blue-900">{{ formatCurrency(selfInsurance.total_invested) }}</p>
              <p class="text-xs text-blue-600 mt-1">Over {{ selfInsurance.investment_term_years }} years</p>
            </div>
            <div class="bg-green-50 rounded-lg p-3 sm:p-4">
              <p class="text-xs sm:text-sm text-green-700 font-medium mb-1">Investment Growth</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-green-900">{{ formatCurrency(selfInsurance.investment_growth) }}</p>
              <p class="text-xs text-green-600 mt-1">At {{ selfInsurance.assumed_return_percentage }}% p.a.</p>
            </div>
            <div class="rounded-lg p-3 sm:p-4" :class="selfInsurance.is_sufficient ? 'bg-green-50' : 'bg-red-50'">
              <p class="text-xs sm:text-sm font-medium mb-1" :class="selfInsurance.is_sufficient ? 'text-green-700' : 'text-red-700'">
                Projected Value
              </p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold" :class="selfInsurance.is_sufficient ? 'text-green-900' : 'text-red-900'">
                {{ formatCurrency(selfInsurance.projected_fund_value) }}
              </p>
              <p class="text-xs mt-1" :class="selfInsurance.is_sufficient ? 'text-green-600' : 'text-red-600'">
                <span v-if="selfInsurance.surplus > 0">Surplus: {{ formatCurrency(selfInsurance.surplus) }}</span>
                <span v-else>Shortfall: {{ formatCurrency(selfInsurance.shortfall) }}</span>
              </p>
            </div>
          </div>

          <!-- Pros and Cons -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-6">
            <div>
              <h4 class="text-md font-semibold text-green-900 mb-3 flex items-center">
                <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Pros
              </h4>
              <ul class="space-y-2">
                <li v-for="(pro, index) in selfInsurance.pros" :key="index" class="flex items-start text-sm">
                  <span class="text-green-600 mr-2">✓</span>
                  <span class="text-gray-700">{{ pro }}</span>
                </li>
              </ul>
            </div>
            <div>
              <h4 class="text-md font-semibold text-red-900 mb-3 flex items-center">
                <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                Cons
              </h4>
              <ul class="space-y-2">
                <li v-for="(con, index) in selfInsurance.cons" :key="index" class="flex items-start text-sm">
                  <span class="text-red-600 mr-2">✗</span>
                  <span class="text-gray-700">{{ con }}</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- Recommended Investment Approach -->
          <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
            <h4 class="text-md font-semibold text-amber-900 mb-3">Recommended Investment Approach</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div v-for="(value, key) in selfInsurance.recommended_investment_approach" :key="key">
                <p class="text-sm font-medium text-amber-800">{{ key }}</p>
                <p class="text-sm text-gray-700 mt-1">{{ value }}</p>
              </div>
            </div>
          </div>

          <!-- Implementation Steps -->
          <div>
            <h4 class="text-md font-semibold text-gray-900 mb-3">Implementation Steps</h4>
            <ol class="space-y-2">
              <li v-for="(step, index) in selfInsurance.implementation_steps" :key="index" class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 bg-amber-100 text-amber-700 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                  {{ index + 1 }}
                </span>
                <span class="text-gray-700 pt-0.5">{{ step }}</span>
              </li>
            </ol>
          </div>
        </div>
      </div>

      <!-- Side-by-Side Comparison -->
      <div class="bg-white rounded-lg border border-gray-300 shadow-lg overflow-hidden">
        <div class="bg-gray-100 px-6 py-4 border-b border-gray-200">
          <h3 class="text-xl font-bold text-gray-900">Side-by-Side Comparison</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aspect</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 uppercase tracking-wider">Whole of Life Insurance</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-amber-600 uppercase tracking-wider">Self-Insurance</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(diff, index) in strategy.comparison.key_differences" :key="index">
                <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">{{ diff.aspect }}</td>
                <td class="px-6 py-4 text-sm text-gray-700">{{ diff.insurance }}</td>
                <td class="px-6 py-4 text-sm text-gray-700">{{ diff.self_insurance }}</td>
              </tr>
            </tbody>
          </table>
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
                'text-amber-900': decision.includes('Self-Insurance'),
                'text-purple-900': decision.includes('Hybrid')
              }">{{ decision }}</h4>
              <ul class="space-y-2">
                <li v-for="(item, index) in items" :key="index" class="flex items-start text-sm">
                  <span class="mr-2" :class="{
                    'text-indigo-600': decision.includes('Insurance'),
                    'text-amber-600': decision.includes('Self-Insurance'),
                    'text-purple-600': decision.includes('Hybrid')
                  }">•</span>
                  <span class="text-gray-700">{{ item }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- All Recommendations -->
      <div class="bg-white rounded-lg border border-gray-300 shadow-lg">
        <div class="bg-gray-100 px-6 py-4 border-b border-gray-200">
          <h3 class="text-xl font-bold text-gray-900">Prioritized Recommendations</h3>
        </div>
        <div class="p-6">
          <div class="space-y-4">
            <div v-for="rec in strategy.comparison.all_recommendations" :key="rec.priority"
                 class="border-l-4 p-4 rounded-r-lg"
                 :class="{
                   'bg-blue-50 border-blue-500': rec.priority === 1,
                   'bg-green-50 border-green-500': rec.priority === 2,
                   'bg-purple-50 border-purple-500': rec.priority === 3
                 }">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold mr-3"
                          :class="{
                            'bg-blue-200 text-blue-800': rec.priority === 1,
                            'bg-green-200 text-green-800': rec.priority === 2,
                            'bg-purple-200 text-purple-800': rec.priority === 3
                          }">
                      {{ rec.priority }}
                    </span>
                    <h4 class="text-lg font-semibold" :class="{
                      'text-blue-900': rec.priority === 1,
                      'text-green-900': rec.priority === 2,
                      'text-purple-900': rec.priority === 3
                    }">{{ rec.option }}</h4>
                  </div>
                  <p class="text-sm mt-2 ml-11" :class="{
                    'text-blue-700': rec.priority === 1,
                    'text-green-700': rec.priority === 2,
                    'text-purple-700': rec.priority === 3
                  }">
                    <strong>Rationale:</strong> {{ rec.rationale }}
                  </p>
                  <p class="text-sm mt-2 ml-11" :class="{
                    'text-blue-800': rec.priority === 1,
                    'text-green-800': rec.priority === 2,
                    'text-purple-800': rec.priority === 3
                  }">
                    <strong>Best for:</strong> {{ rec.suitability }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
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
import estateService from '../../services/estateService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'LifePolicyStrategy',

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
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
    policy() {
      return this.strategy?.whole_of_life_policy || {};
    },
    selfInsurance() {
      return this.strategy?.self_insurance || {};
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadStrategy();
  },

  methods: {
    computePreviewStrategy() {
      // Get IHT data from estate store or compute it
      const estateState = this.$store.state.estate;
      const previewData = this.$store.state.preview?.personaData;

      // Calculate total assets from estate store
      const assetsValue = estateState.assets?.reduce((sum, a) => sum + parseFloat(a.current_value || 0), 0) || 0;
      const investmentsValue = estateState.investmentAccounts?.reduce((sum, i) => sum + parseFloat(i.current_value || 0), 0) || 0;
      const totalAssets = assetsValue + investmentsValue;

      // Calculate total liabilities
      const totalLiabilities = estateState.liabilities?.reduce((sum, l) => sum + parseFloat(l.current_balance || 0), 0) || 0;

      // Calculate net estate
      const netEstate = totalAssets - totalLiabilities;

      // IHT allowances (UK 2025/26)
      const nrb = 325000;
      const hasMainResidence = estateState.assets?.some(a => a.asset_type === 'property') || false;
      const rnrb = hasMainResidence ? 175000 : 0;
      const totalAllowance = nrb + rnrb;

      // Calculate taxable estate and IHT liability
      const taxableEstate = Math.max(0, netEstate - totalAllowance);
      const ihtLiability = taxableEstate * 0.40;

      // Get user data
      const user = previewData?.user;
      const currentAge = user?.age || 40;
      const estimatedDeathAge = user?.gender === 'female' ? 84 : 81;
      const yearsUntilDeath = estimatedDeathAge - currentAge;

      if (ihtLiability === 0) {
        // No IHT liability - show "No Life Insurance Required" message
        this.noIHTLiability = true;
        this.noIHTMessage = `Your current estate of ${this.formatCurrency(netEstate)} is below the IHT threshold of ${this.formatCurrency(totalAllowance)}. No inheritance tax liability is projected.`;
        this.loading = false;
        return;
      }

      // Has IHT liability - compute strategy
      const monthlyPremium = Math.round((ihtLiability * 0.03) / 12); // Approx 3% annual premium for whole of life
      const annualPremium = monthlyPremium * 12;
      const totalPremiums = annualPremium * yearsUntilDeath;

      this.strategy = {
        cover_amount: ihtLiability,
        current_age: currentAge,
        years_until_death: yearsUntilDeath,
        is_joint_policy: previewData?.user?.marital_status === 'married',
        whole_of_life_policy: {
          policy_type: 'Whole of Life Insurance',
          description: 'Guaranteed payout on death to cover IHT liability',
          cover_amount: ihtLiability,
          monthly_premium: monthlyPremium,
          annual_premium: annualPremium,
          total_premiums_paid: totalPremiums,
          term_years: yearsUntilDeath,
          cost_benefit_ratio: Math.round(ihtLiability / totalPremiums * 10) / 10,
          key_features: [
            'Guaranteed payout regardless of when you die',
            'Fixed premiums for life',
            'Can be placed in trust to avoid IHT on payout',
            'No medical underwriting concerns once in force',
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
          description: 'Build a dedicated fund to cover IHT liability',
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
            ? 'Given the significant IHT liability, whole of life insurance provides certainty of coverage regardless of when death occurs.'
            : 'With a moderate IHT liability, self-insurance may be more cost-effective if you have the discipline to build and maintain the fund.',
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
