<template>
  <AppLayout>
    <div class="max-w-3xl mx-auto py-4 sm:py-8 px-4 sm:px-6">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ factorDisplayName }}</h1>
            <p class="mt-2 text-sm sm:text-base text-gray-600">
              Understanding how this factor affects your risk profile
            </p>
          </div>
          <button
            @click="goBack"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
          </button>
        </div>
      </div>

      <!-- Loading state -->
      <div v-if="loading" class="flex justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>

      <template v-else-if="factorData">
        <!-- Your Value Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div class="flex items-center gap-4">
            <div
              class="w-16 h-16 rounded-full flex items-center justify-center"
              :class="getFactorBgClass(factorData.level)"
            >
              <!-- Icon based on factor type -->
              <svg v-if="factorKey === 'capacity_for_loss'" class="w-8 h-8" :class="getFactorColorClass(factorData.level)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              <svg v-else-if="factorKey === 'time_horizon'" class="w-8 h-8" :class="getFactorColorClass(factorData.level)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <svg v-else-if="factorKey === 'education'" class="w-8 h-8" :class="getFactorColorClass(factorData.level)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
              </svg>
              <svg v-else-if="factorKey === 'dependants'" class="w-8 h-8" :class="getFactorColorClass(factorData.level)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
              <svg v-else-if="factorKey === 'employment'" class="w-8 h-8" :class="getFactorColorClass(factorData.level)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <svg v-else-if="factorKey === 'emergency_cash'" class="w-8 h-8" :class="getFactorColorClass(factorData.level)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              <svg v-else-if="factorKey === 'surplus_cash'" class="w-8 h-8" :class="getFactorColorClass(factorData.level)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
              <svg v-else class="w-8 h-8" :class="getFactorColorClass(factorData.level)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm text-gray-500">Your current value</p>
              <p class="text-3xl font-bold text-gray-900">{{ factorData.value }}</p>
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium mt-2"
                :class="getLevelBadgeClass(factorData.level)"
              >
                {{ getLevelDisplayName(factorData.level) }} Risk
              </span>
            </div>
          </div>
        </div>

        <!-- Your Situation -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <h3 class="text-sm font-semibold text-blue-900 mb-1">Your situation</h3>
          <p class="text-sm text-blue-800">{{ factorData.description }}</p>
        </div>

        <!-- What This Measures -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-3">What this measures</h3>
          <p class="text-gray-600">{{ factorExplanation.what }}</p>
        </div>

        <!-- Why It Matters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-3">Why it matters for risk</h3>
          <p class="text-gray-600">{{ factorExplanation.why }}</p>
        </div>

        <!-- How It's Calculated -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-3">How it's calculated</h3>
          <p class="text-gray-600">{{ factorExplanation.how }}</p>
        </div>

        <!-- Risk Level Thresholds -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk level thresholds</h3>
          <p class="text-sm text-gray-600 mb-4">
            The following thresholds determine which risk level is assigned based on your value:
          </p>
          <div class="space-y-3">
            <div
              v-for="threshold in factorExplanation.thresholds"
              :key="threshold.level"
              class="flex items-center justify-between p-4 rounded-lg border"
              :class="threshold.level === factorData.level
                ? 'bg-blue-50 border-blue-300'
                : 'bg-gray-50 border-gray-200'"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-full flex items-center justify-center font-bold"
                  :class="getLevelCircleClass(threshold.level)"
                >
                  {{ getLevelNumeric(threshold.level) }}
                </div>
                <div>
                  <p class="font-medium text-gray-900">{{ threshold.range }}</p>
                  <p class="text-sm text-gray-500">{{ getLevelDisplayName(threshold.level) }} risk</p>
                </div>
              </div>
              <span
                v-if="threshold.level === factorData.level"
                class="px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"
              >
                You are here
              </span>
            </div>
          </div>
        </div>

        <!-- Other Factors -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
          <h3 class="text-sm font-semibold text-gray-900 mb-2">Other factors in your risk profile</h3>
          <div class="flex flex-wrap gap-2">
            <router-link
              v-for="factor in otherFactors"
              :key="factor.factor"
              :to="`/risk-profile/factor/${factor.factor}`"
              class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              {{ factor.display_name }}
              <span
                class="px-1.5 py-0.5 text-xs rounded"
                :class="getLevelBadgeClass(factor.level)"
              >
                {{ getLevelDisplayName(factor.level).charAt(0) }}
              </span>
            </router-link>
          </div>
        </div>
      </template>

      <!-- Factor not found -->
      <div v-else class="text-center py-12">
        <p class="text-gray-500">Factor not found. Please go back and try again.</p>
        <button
          @click="goBack"
          class="text-blue-600 hover:text-blue-800 mt-2 inline-block"
        >
          ← Go Back
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import riskService from '@/services/riskService';

export default {
  name: 'RiskFactorDetailPage',

  components: {
    AppLayout,
  },

  data() {
    return {
      loading: true,
      factorKey: null,
      factorData: null,
      allFactors: [],
    };
  },

  computed: {
    factorDisplayName() {
      const names = {
        capacity_for_loss: 'Capacity for Loss',
        time_horizon: 'Time Horizon',
        education: 'Education Level',
        dependants: 'Dependants',
        employment: 'Employment Status',
        emergency_cash: 'Emergency Fund',
        surplus_cash: 'Monthly Surplus',
      };
      return names[this.factorKey] || 'Risk Factor';
    },

    factorExplanation() {
      const explanations = {
        capacity_for_loss: {
          what: 'This measures the proportion of your total net worth that is held in investments and pensions - assets that can fluctuate in value.',
          why: 'If a large portion of your wealth is already in volatile assets, you have less capacity to absorb further losses. Conversely, if most of your wealth is in stable assets like property or cash, you can afford to take more risk with investments.',
          how: 'We calculate: (Total Investments + Total Pensions) ÷ Net Worth × 100. This gives the percentage of your wealth that is exposed to market risk.',
          thresholds: [
            { level: 'high', range: 'Less than 30%' },
            { level: 'medium', range: '30% - 75%' },
            { level: 'lower_medium', range: 'More than 75%' },
          ],
        },
        time_horizon: {
          what: 'This is the number of years until you plan to retire and start drawing on your investments.',
          why: 'Longer time horizons allow you to ride out market volatility and recover from downturns. Shorter horizons mean you need to protect your capital as you approach retirement.',
          how: 'We calculate your target retirement age minus your current age. If you\'re already retired or have a specific retirement date set, we use that instead.',
          thresholds: [
            { level: 'high', range: '20+ years' },
            { level: 'upper_medium', range: '15-20 years' },
            { level: 'medium', range: '3-15 years' },
            { level: 'lower_medium', range: 'Less than 3 years or retired' },
          ],
        },
        education: {
          what: 'This is your highest level of formal education.',
          why: 'Higher education often correlates with greater financial literacy and understanding of complex investment concepts. This doesn\'t mean less educated people can\'t invest wisely, but it suggests familiarity with analytical thinking and complex information.',
          how: 'We check your education level from your profile. Degree-level education or higher indicates medium risk tolerance; otherwise, we suggest a more cautious approach.',
          thresholds: [
            { level: 'medium', range: 'Degree or higher' },
            { level: 'lower_medium', range: 'Below degree level' },
          ],
        },
        dependants: {
          what: 'This is the number of people who depend on you financially, such as children or elderly relatives.',
          why: 'More dependants mean more financial responsibilities and less flexibility to take risks. You need to ensure stability for those who rely on you financially.',
          how: 'We count family members in your profile who are marked as dependants.',
          thresholds: [
            { level: 'upper_medium', range: 'No dependants' },
            { level: 'medium', range: '1 dependant' },
            { level: 'lower_medium', range: '2 or more dependants' },
          ],
        },
        employment: {
          what: 'This is your current employment status and income stability.',
          why: 'Active employment provides ongoing income to rebuild investments if they fall. Without regular income, you\'re more reliant on existing capital and need to preserve it.',
          how: 'We check your employment status from your profile. Employed or self-employed indicates the ability to recover from losses; retired or unemployed suggests a need for capital preservation.',
          thresholds: [
            { level: 'medium', range: 'Employed or self-employed' },
            { level: 'lower_medium', range: 'Retired, unemployed, or other' },
          ],
        },
        emergency_cash: {
          what: 'This measures how many months of living expenses you could cover with your emergency fund savings.',
          why: 'A strong emergency fund means you won\'t need to sell investments at a bad time to cover unexpected costs. This gives you the freedom to stay invested through market downturns.',
          how: 'We calculate: Emergency Fund Balance ÷ Monthly Expenditure. This gives the number of months you could sustain your lifestyle without income.',
          thresholds: [
            { level: 'upper_medium', range: '6+ months' },
            { level: 'medium', range: '3-6 months' },
            { level: 'lower_medium', range: 'Less than 3 months' },
          ],
        },
        surplus_cash: {
          what: 'This is your monthly income minus your monthly expenditure - the amount available for saving or investing each month.',
          why: 'A healthy surplus means you can regularly add to investments and have flexibility to take more risk. If you\'re spending everything you earn, you have less room for error.',
          how: 'We calculate: (Total Annual Income ÷ 12) - Monthly Expenditure. This gives your monthly disposable income.',
          thresholds: [
            { level: 'upper_medium', range: 'More than £500/month' },
            { level: 'medium', range: '£1 - £500/month' },
            { level: 'lower_medium', range: '£0 or negative' },
          ],
        },
      };
      return explanations[this.factorKey] || {
        what: 'This factor contributes to your overall risk assessment.',
        why: 'It helps determine an appropriate level of investment risk for your situation.',
        how: 'Based on the data in your profile.',
        thresholds: [],
      };
    },

    otherFactors() {
      return this.allFactors.filter(f => f.factor !== this.factorKey);
    },
  },

  async created() {
    this.factorKey = this.$route.params.factor;
    await this.loadData();
  },

  watch: {
    '$route.params.factor'(newFactor) {
      this.factorKey = newFactor;
      this.loadData();
    },
  },

  methods: {
    goBack() {
      // Use browser history to return to previous page (Valuable Info or Risk Profile)
      this.$router.back();
    },

    async loadData() {
      this.loading = true;
      try {
        const response = await riskService.getProfile();
        if (response.data && response.data.factor_breakdown) {
          this.allFactors = response.data.factor_breakdown;
          this.factorData = this.allFactors.find(f => f.factor === this.factorKey);
        }
      } catch (error) {
        console.error('Error loading factor data:', error);
      } finally {
        this.loading = false;
      }
    },

    getLevelDisplayName(level) {
      const names = {
        low: 'Low',
        lower_medium: 'Lower-Medium',
        medium: 'Medium',
        upper_medium: 'Upper-Medium',
        high: 'High',
      };
      return names[level] || level;
    },

    getLevelNumeric(level) {
      const numerics = {
        low: 1,
        lower_medium: 2,
        medium: 3,
        upper_medium: 4,
        high: 5,
      };
      return numerics[level] || '-';
    },

    getLevelBadgeClass(level) {
      const classes = {
        low: 'bg-green-100 text-green-800',
        lower_medium: 'bg-teal-100 text-teal-800',
        medium: 'bg-blue-100 text-blue-800',
        upper_medium: 'bg-amber-100 text-amber-800',
        high: 'bg-red-100 text-red-800',
      };
      return classes[level] || 'bg-gray-100 text-gray-800';
    },

    getLevelCircleClass(level) {
      const classes = {
        low: 'bg-green-100 text-green-700',
        lower_medium: 'bg-teal-100 text-teal-700',
        medium: 'bg-blue-100 text-blue-700',
        upper_medium: 'bg-amber-100 text-amber-700',
        high: 'bg-red-100 text-red-700',
      };
      return classes[level] || 'bg-gray-100 text-gray-700';
    },

    getFactorBgClass(level) {
      const classes = {
        low: 'bg-green-100',
        lower_medium: 'bg-teal-100',
        medium: 'bg-blue-100',
        upper_medium: 'bg-amber-100',
        high: 'bg-red-100',
      };
      return classes[level] || 'bg-gray-100';
    },

    getFactorColorClass(level) {
      const classes = {
        low: 'text-green-600',
        lower_medium: 'text-teal-600',
        medium: 'text-blue-600',
        upper_medium: 'text-amber-600',
        high: 'text-red-600',
      };
      return classes[level] || 'text-gray-600';
    },
  },
};
</script>
