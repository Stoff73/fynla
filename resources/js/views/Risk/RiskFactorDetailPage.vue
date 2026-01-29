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
        <!-- CAPACITY FOR LOSS: Custom detail view -->
        <template v-if="factorKey === 'capacity_for_loss'">
          <!-- Your Calculation -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Calculation</h3>
            <div class="flex items-center justify-center gap-2 text-center py-4">
              <div class="flex flex-col items-center">
                <div class="flex items-center gap-1 border-b-2 border-gray-300 pb-2 px-2">
                  <div class="flex flex-col items-center">
                    <span class="text-base font-semibold text-gray-900">{{ formatCurrency(factorData.components?.investments_total || 0) }}</span>
                    <span class="text-xs text-gray-400">investments</span>
                  </div>
                  <span class="text-gray-400 text-lg mx-1">+</span>
                  <div class="flex flex-col items-center">
                    <span class="text-base font-semibold text-gray-900">{{ formatCurrency(factorData.components?.pensions_total || 0) }}</span>
                    <span class="text-xs text-gray-400">pensions</span>
                  </div>
                </div>
                <div class="flex flex-col items-center pt-2">
                  <span class="text-base font-semibold text-gray-900">{{ formatCurrency(factorData.components?.net_worth || 0) }}</span>
                  <span class="text-xs text-gray-400">net worth</span>
                </div>
              </div>
              <span class="text-gray-400 text-lg mx-2">× 100</span>
              <span class="text-gray-400 text-lg mx-1">=</span>
              <div class="flex flex-col items-center">
                <span class="text-xl font-bold text-gray-900">{{ factorData.value }}</span>
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1"
                  :class="getLevelBadgeClass(factorData.level)"
                >
                  {{ getCapacityLabel(factorData.level) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Inline Thresholds -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Threshold Levels</h3>
            <div class="space-y-2">
              <div
                v-for="threshold in factorExplanation.thresholds"
                :key="threshold.level"
                class="flex items-center justify-between p-3 rounded-lg border"
                :class="threshold.level === factorData.level
                  ? 'bg-blue-50 border-blue-300'
                  : 'bg-gray-50 border-gray-200'"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-3 h-3 rounded-full"
                    :class="getThresholdDotClass(threshold.level)"
                  ></div>
                  <div>
                    <span class="text-sm font-medium text-gray-900">{{ threshold.range }}</span>
                    <span class="text-sm text-gray-500 ml-2">{{ getCapacityLabel(threshold.level) }}</span>
                  </div>
                </div>
                <span
                  v-if="threshold.level === factorData.level"
                  class="px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"
                >
                  You are here
                </span>
              </div>
            </div>
          </div>

          <!-- Concise Explanation -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="space-y-4">
              <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-1">What</h4>
                <p class="text-sm text-gray-600">{{ factorExplanation.what }}</p>
              </div>
              <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-1">Why</h4>
                <p class="text-sm text-gray-600">{{ factorExplanation.why }}</p>
              </div>
              <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-1">How</h4>
                <p class="text-sm text-gray-600 font-mono text-xs">{{ factorExplanation.how }}</p>
              </div>
            </div>
          </div>
        </template>

        <!-- ALL OTHER FACTORS: Custom concise views -->
        <template v-else>
          <!-- Your Data card -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Data</h3>

            <!-- TIME HORIZON -->
            <template v-if="factorKey === 'time_horizon'">
              <div class="flex items-center justify-center gap-2 text-center py-4">
                <div class="flex flex-col items-center">
                  <div class="flex items-center gap-1 border-b-2 border-gray-300 pb-2 px-2">
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-gray-900">{{ factorData.components?.target_retirement_age || '67' }}</span>
                      <span class="text-xs text-gray-400">retirement age</span>
                    </div>
                    <span class="text-gray-400 text-lg mx-1">&minus;</span>
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-gray-900">{{ factorData.components?.current_age || '—' }}</span>
                      <span class="text-xs text-gray-400">current age</span>
                    </div>
                  </div>
                </div>
                <span class="text-gray-400 text-lg mx-2">=</span>
                <div class="flex flex-col items-center">
                  <span class="text-xl font-bold text-gray-900">{{ factorData.value }}</span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-gray-400 text-center">Source: Your profile date of birth &amp; target retirement age</p>
            </template>

            <!-- EDUCATION -->
            <template v-else-if="factorKey === 'education'">
              <div class="divide-y divide-gray-200">
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-600">Education level</span>
                  <span class="text-sm font-semibold text-gray-900">{{ factorData.value }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-600">Degree-level or above</span>
                  <span class="text-sm font-semibold text-gray-900">{{ factorData.components?.has_degree ? 'Yes' : 'No' }}</span>
                </div>
                <div class="flex justify-between items-center pt-3">
                  <span class="text-sm font-semibold text-gray-900">Result</span>
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-gray-400 mt-3">Source: Your profile education level</p>
            </template>

            <!-- DEPENDANTS -->
            <template v-else-if="factorKey === 'dependants'">
              <div class="divide-y divide-gray-200">
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-600">Dependants found</span>
                  <span class="text-sm font-semibold text-gray-900">{{ factorData.components?.count || 0 }}</span>
                </div>
                <template v-if="factorData.components?.dependants?.length">
                  <div
                    v-for="dep in factorData.components.dependants"
                    :key="dep.name"
                    class="flex justify-between py-2"
                  >
                    <span class="text-sm text-gray-600">{{ dep.name }}</span>
                    <span class="text-sm text-gray-500">{{ formatRelationship(dep.relationship) }}</span>
                  </div>
                </template>
                <div class="flex justify-between items-center pt-3">
                  <span class="text-sm font-semibold text-gray-900">Result</span>
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-gray-400 mt-3">Source: Family members marked as dependants</p>
            </template>

            <!-- EMPLOYMENT -->
            <template v-else-if="factorKey === 'employment'">
              <div class="divide-y divide-gray-200">
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-600">Employment status</span>
                  <span class="text-sm font-semibold text-gray-900">{{ factorData.value }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-gray-600">Active income</span>
                  <span class="text-sm font-semibold text-gray-900">{{ factorData.components?.is_working ? 'Yes' : 'No' }}</span>
                </div>
                <div class="flex justify-between items-center pt-3">
                  <span class="text-sm font-semibold text-gray-900">Result</span>
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-gray-400 mt-3">Source: Your profile employment status</p>
            </template>

            <!-- EMERGENCY CASH -->
            <template v-else-if="factorKey === 'emergency_cash'">
              <div class="flex items-center justify-center gap-2 text-center py-4">
                <div class="flex flex-col items-center">
                  <div class="flex items-center gap-1 border-b-2 border-gray-300 pb-2 px-2">
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-gray-900">{{ formatCurrency(factorData.components?.emergency_fund_total || 0) }}</span>
                      <span class="text-xs text-gray-400">emergency fund</span>
                    </div>
                  </div>
                  <div class="flex flex-col items-center pt-2">
                    <span class="text-base font-semibold text-gray-900">{{ formatCurrency(factorData.components?.monthly_expenditure || 0) }}</span>
                    <span class="text-xs text-gray-400">monthly expenditure</span>
                  </div>
                </div>
                <span class="text-gray-400 text-lg mx-2">=</span>
                <div class="flex flex-col items-center">
                  <span class="text-xl font-bold text-gray-900">{{ factorData.value }}</span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-gray-400 text-center">Source: Savings accounts marked as emergency fund &amp; your monthly expenditure</p>
            </template>

            <!-- SURPLUS CASH -->
            <template v-else-if="factorKey === 'surplus_cash'">
              <div class="flex items-center justify-center gap-2 text-center py-4">
                <div class="flex flex-col items-center">
                  <div class="flex items-center gap-1">
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-gray-900">{{ formatCurrency(factorData.components?.monthly_income || 0) }}</span>
                      <span class="text-xs text-gray-400">monthly income</span>
                    </div>
                    <span class="text-gray-400 text-lg mx-1">&minus;</span>
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-gray-900">{{ formatCurrency(factorData.components?.monthly_expenditure || 0) }}</span>
                      <span class="text-xs text-gray-400">monthly expenditure</span>
                    </div>
                  </div>
                </div>
                <span class="text-gray-400 text-lg mx-2">=</span>
                <div class="flex flex-col items-center">
                  <span class="text-xl font-bold text-gray-900">{{ factorData.value }}</span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-gray-400 text-center">Source: Your profile income ({{ formatCurrency(factorData.components?.annual_income || 0) }}/yr) &amp; monthly expenditure</p>
            </template>
          </div>

          <!-- Thresholds -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Threshold Levels</h3>
            <div class="space-y-2">
              <div
                v-for="threshold in factorExplanation.thresholds"
                :key="threshold.level"
                class="flex items-center justify-between p-3 rounded-lg border"
                :class="threshold.level === factorData.level
                  ? 'bg-blue-50 border-blue-300'
                  : 'bg-gray-50 border-gray-200'"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-3 h-3 rounded-full"
                    :class="getThresholdDotClass(threshold.level)"
                  ></div>
                  <div>
                    <span class="text-sm font-medium text-gray-900">{{ threshold.range }}</span>
                    <span class="text-sm text-gray-500 ml-2">{{ getLevelDisplayName(threshold.level) }}</span>
                  </div>
                </div>
                <span
                  v-if="threshold.level === factorData.level"
                  class="px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"
                >
                  You are here
                </span>
              </div>
            </div>
          </div>

          <!-- Concise Explanation -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="space-y-4">
              <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-1">What</h4>
                <p class="text-sm text-gray-600">{{ factorExplanation.what }}</p>
              </div>
              <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-1">Why</h4>
                <p class="text-sm text-gray-600">{{ factorExplanation.why }}</p>
              </div>
            </div>
          </div>
        </template>
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
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'RiskFactorDetailPage',

  mixins: [currencyMixin],

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
          what: 'The percentage of your net worth held in investments and pensions.',
          why: 'Lower exposure means more capacity to absorb losses without affecting your lifestyle.',
          how: '(Investments + Pensions) / Net Worth × 100',
          thresholds: [
            { level: 'high', range: '0 – 15%' },
            { level: 'medium', range: '15 – 50%' },
            { level: 'lower_medium', range: '50 – 75%' },
            { level: 'low', range: 'More than 75%' },
          ],
        },
        time_horizon: {
          what: 'Years until you plan to retire and draw on investments.',
          why: 'More time means more ability to recover from downturns.',
          thresholds: [
            { level: 'high', range: '20+ years' },
            { level: 'upper_medium', range: '15–20 years' },
            { level: 'medium', range: '3–15 years' },
            { level: 'lower_medium', range: 'Less than 3 years or retired' },
          ],
        },
        education: {
          what: 'Your highest level of formal education.',
          why: 'Higher education correlates with familiarity with complex financial concepts.',
          thresholds: [
            { level: 'medium', range: 'Degree or higher' },
            { level: 'lower_medium', range: 'Below degree level' },
          ],
        },
        dependants: {
          what: 'Number of people who depend on you financially.',
          why: 'More dependants means more financial responsibility and less flexibility.',
          thresholds: [
            { level: 'upper_medium', range: 'No dependants' },
            { level: 'medium', range: '1 dependant' },
            { level: 'lower_medium', range: '2 or more dependants' },
          ],
        },
        employment: {
          what: 'Your current employment status.',
          why: 'Active income provides ability to rebuild if investments fall.',
          thresholds: [
            { level: 'medium', range: 'Employed or self-employed' },
            { level: 'lower_medium', range: 'Retired, unemployed, or other' },
          ],
        },
        emergency_cash: {
          what: 'Months of expenses covered by your emergency fund.',
          why: 'A strong buffer means you won\'t need to sell investments at a bad time.',
          thresholds: [
            { level: 'upper_medium', range: '6+ months' },
            { level: 'medium', range: '3–6 months' },
            { level: 'lower_medium', range: 'Less than 3 months' },
          ],
        },
        surplus_cash: {
          what: 'Monthly income minus monthly expenditure.',
          why: 'A surplus means you can regularly invest and have room for error.',
          thresholds: [
            { level: 'upper_medium', range: 'More than £500/month' },
            { level: 'medium', range: '£1–£500/month' },
            { level: 'lower_medium', range: '£0 or negative' },
          ],
        },
      };
      return explanations[this.factorKey] || {
        what: 'This factor contributes to your overall risk assessment.',
        why: 'It helps determine an appropriate level of investment risk for your situation.',
        thresholds: [],
      };
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
        upper_medium: 'bg-orange-100 text-orange-800',
        high: 'bg-red-100 text-red-800',
      };
      return classes[level] || 'bg-gray-100 text-gray-800';
    },

    getLevelCircleClass(level) {
      const classes = {
        low: 'bg-green-100 text-green-700',
        lower_medium: 'bg-teal-100 text-teal-700',
        medium: 'bg-blue-100 text-blue-700',
        upper_medium: 'bg-orange-100 text-orange-700',
        high: 'bg-red-100 text-red-700',
      };
      return classes[level] || 'bg-gray-100 text-gray-700';
    },

    getFactorBgClass(level) {
      const classes = {
        low: 'bg-green-100',
        lower_medium: 'bg-teal-100',
        medium: 'bg-blue-100',
        upper_medium: 'bg-orange-100',
        high: 'bg-red-100',
      };
      return classes[level] || 'bg-gray-100';
    },

    getFactorColorClass(level) {
      const classes = {
        low: 'text-green-600',
        lower_medium: 'text-teal-600',
        medium: 'text-blue-600',
        upper_medium: 'text-orange-600',
        high: 'text-red-600',
      };
      return classes[level] || 'text-gray-600';
    },

    getCapacityLabel(level) {
      const labels = {
        high: 'High Capacity',
        medium: 'Medium Capacity',
        lower_medium: 'Medium-Low Capacity',
        low: 'Low Capacity',
      };
      return labels[level] || level;
    },

    getThresholdDotClass(level) {
      const classes = {
        high: 'bg-red-500',
        upper_medium: 'bg-orange-500',
        medium: 'bg-blue-500',
        lower_medium: 'bg-teal-500',
        low: 'bg-green-500',
      };
      return classes[level] || 'bg-gray-400';
    },

    formatRelationship(rel) {
      if (!rel) return '';
      return rel.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
  },
};
</script>
