<template>
  <AppLayout>
    <div class="module-gradient py-4 sm:py-8 px-4 sm:px-6">
      <ModuleStatusBar />
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-horizon-500">{{ factorDisplayName }}</h1>
            <p class="mt-2 text-sm sm:text-base text-neutral-500">
              Understanding how this factor affects your risk profile
            </p>
          </div>
          <button
            @click="goBack"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-savannah-100 transition-colors"
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
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
      </div>

      <template v-else-if="factorData">
        <!-- CAPACITY FOR LOSS: Custom detail view -->
        <template v-if="factorKey === 'capacity_for_loss'">
          <!-- Your Calculation -->
          <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-6">
            <h3 class="text-lg font-semibold text-horizon-500 mb-4">Your Calculation</h3>
            <div class="flex items-center justify-center gap-2 text-center py-4">
              <div class="flex flex-col items-center">
                <div class="flex items-center gap-1 border-b-2 border-horizon-300 pb-2 px-2">
                  <div class="flex flex-col items-center">
                    <span class="text-base font-semibold text-horizon-500">{{ formatCurrency(factorData.components?.investments_total || 0) }}</span>
                    <span class="text-xs text-horizon-400">investments</span>
                  </div>
                  <span class="text-horizon-400 text-lg mx-1">+</span>
                  <div class="flex flex-col items-center">
                    <span class="text-base font-semibold text-horizon-500">{{ formatCurrency(factorData.components?.pensions_total || 0) }}</span>
                    <span class="text-xs text-horizon-400">pensions</span>
                  </div>
                </div>
                <div class="flex flex-col items-center pt-2">
                  <span class="text-base font-semibold text-horizon-500">{{ formatCurrency(factorData.components?.net_worth || 0) }}</span>
                  <span class="text-xs text-horizon-400">net worth</span>
                </div>
              </div>
              <span class="text-horizon-400 text-lg mx-2">× 100</span>
              <span class="text-horizon-400 text-lg mx-1">=</span>
              <div class="flex flex-col items-center">
                <span class="text-xl font-bold text-horizon-500">{{ factorData.value }}</span>
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
          <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-6">
            <h3 class="text-lg font-semibold text-horizon-500 mb-4">Threshold Levels</h3>
            <div class="space-y-2">
              <div
                v-for="threshold in factorExplanation.thresholds"
                :key="threshold.level"
                class="flex items-center justify-between p-3 rounded-lg border"
                :class="threshold.level === factorData.level
                  ? 'bg-blue-50 border-blue-300'
                  : 'bg-eggshell-500 border-light-gray'"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-3 h-3 rounded-full"
                    :class="getThresholdDotClass(threshold.level)"
                  ></div>
                  <div>
                    <span class="text-sm font-medium text-horizon-500">{{ threshold.range }}</span>
                    <span class="text-sm text-neutral-500 ml-2">{{ getCapacityLabel(threshold.level) }}</span>
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

        </template>

        <!-- ALL OTHER FACTORS: Custom concise views -->
        <template v-else>
          <!-- Your Data card -->
          <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-6">
            <h3 class="text-lg font-semibold text-horizon-500 mb-4">Your Data</h3>

            <!-- TIME HORIZON -->
            <template v-if="factorKey === 'time_horizon'">
              <div class="flex items-center justify-center gap-2 text-center py-4">
                <div class="flex flex-col items-center">
                  <div class="flex items-center gap-1 border-b-2 border-horizon-300 pb-2 px-2">
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-horizon-500">{{ factorData.components?.target_retirement_age || '67' }}</span>
                      <span class="text-xs text-horizon-400">retirement age</span>
                    </div>
                    <span class="text-horizon-400 text-lg mx-1">&minus;</span>
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-horizon-500">{{ factorData.components?.current_age || '—' }}</span>
                      <span class="text-xs text-horizon-400">current age</span>
                    </div>
                  </div>
                </div>
                <span class="text-horizon-400 text-lg mx-2">=</span>
                <div class="flex flex-col items-center">
                  <span class="text-xl font-bold text-horizon-500">{{ factorData.value }}</span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-horizon-400 text-center">Source: Your profile date of birth &amp; target retirement age</p>
            </template>

            <!-- INVESTMENT KNOWLEDGE -->
            <template v-else-if="factorKey === 'knowledge_level'">
              <div class="divide-y divide-light-gray">
                <div class="flex justify-between py-2">
                  <span class="text-sm text-neutral-500">Investment knowledge</span>
                  <span class="text-sm font-semibold text-horizon-500">{{ factorData.value }}</span>
                </div>
                <div class="flex justify-between items-center pt-3">
                  <span class="text-sm font-semibold text-horizon-500">Result</span>
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-horizon-400 mt-3">Source: Your risk profile knowledge level</p>
            </template>

            <!-- DEPENDANTS -->
            <template v-else-if="factorKey === 'dependants'">
              <div class="divide-y divide-light-gray">
                <div class="flex justify-between py-2">
                  <span class="text-sm text-neutral-500">Dependants found</span>
                  <span class="text-sm font-semibold text-horizon-500">{{ factorData.components?.count || 0 }}</span>
                </div>
                <template v-if="factorData.components?.dependants?.length">
                  <div
                    v-for="dep in factorData.components.dependants"
                    :key="dep.name"
                    class="flex justify-between py-2"
                  >
                    <span class="text-sm text-neutral-500">{{ dep.name }}</span>
                    <span class="text-sm text-neutral-500">{{ formatRelationship(dep.relationship) }}</span>
                  </div>
                </template>
                <div class="flex justify-between items-center pt-3">
                  <span class="text-sm font-semibold text-horizon-500">Result</span>
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-horizon-400 mt-3">Source: Family members marked as dependants</p>
            </template>

            <!-- EMPLOYMENT -->
            <template v-else-if="factorKey === 'employment'">
              <div class="divide-y divide-light-gray">
                <div class="flex justify-between py-2">
                  <span class="text-sm text-neutral-500">Employment status</span>
                  <span class="text-sm font-semibold text-horizon-500">{{ factorData.value }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-sm text-neutral-500">Active income</span>
                  <span class="text-sm font-semibold text-horizon-500">{{ factorData.components?.is_working ? 'Yes' : 'No' }}</span>
                </div>
                <div class="flex justify-between items-center pt-3">
                  <span class="text-sm font-semibold text-horizon-500">Result</span>
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-horizon-400 mt-3">Source: Your profile employment status</p>
            </template>

            <!-- EMERGENCY CASH -->
            <template v-else-if="factorKey === 'emergency_cash'">
              <div class="flex items-center justify-center gap-2 text-center py-4">
                <div class="flex flex-col items-center">
                  <div class="flex items-center gap-1 border-b-2 border-horizon-300 pb-2 px-2">
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-horizon-500">{{ formatCurrency(factorData.components?.emergency_fund_total || 0) }}</span>
                      <span class="text-xs text-horizon-400">emergency fund</span>
                    </div>
                  </div>
                  <div class="flex flex-col items-center pt-2">
                    <span class="text-base font-semibold text-horizon-500">{{ formatCurrency(factorData.components?.monthly_expenditure || 0) }}</span>
                    <span class="text-xs text-horizon-400">monthly expenditure</span>
                  </div>
                </div>
                <span class="text-horizon-400 text-lg mx-2">=</span>
                <div class="flex flex-col items-center">
                  <span class="text-xl font-bold text-horizon-500">{{ factorData.value }}</span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-horizon-400 text-center">Source: Savings accounts marked as emergency fund &amp; your monthly expenditure</p>
            </template>

            <!-- SURPLUS CASH -->
            <template v-else-if="factorKey === 'surplus_cash'">
              <div class="flex items-center justify-center gap-2 text-center py-4">
                <div class="flex flex-col items-center">
                  <div class="flex items-center gap-1">
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-horizon-500">{{ formatCurrency(factorData.components?.monthly_income || 0) }}</span>
                      <span class="text-xs text-horizon-400">monthly income</span>
                    </div>
                    <span class="text-horizon-400 text-lg mx-1">&minus;</span>
                    <div class="flex flex-col items-center">
                      <span class="text-base font-semibold text-horizon-500">{{ formatCurrency(factorData.components?.monthly_expenditure || 0) }}</span>
                      <span class="text-xs text-horizon-400">monthly expenditure</span>
                    </div>
                  </div>
                </div>
                <span class="text-horizon-400 text-lg mx-2">=</span>
                <div class="flex flex-col items-center">
                  <span class="text-xl font-bold text-horizon-500">{{ factorData.value }}</span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1"
                    :class="getLevelBadgeClass(factorData.level)"
                  >
                    {{ getLevelDisplayName(factorData.level) }}
                  </span>
                </div>
              </div>
              <p class="text-xs text-horizon-400 text-center">Source: Your profile income ({{ formatCurrency(factorData.components?.annual_income || 0) }}/yr) &amp; monthly expenditure</p>
            </template>
          </div>

          <!-- Thresholds -->
          <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-6">
            <h3 class="text-lg font-semibold text-horizon-500 mb-4">Threshold Levels</h3>
            <div class="space-y-2">
              <div
                v-for="threshold in factorExplanation.thresholds"
                :key="threshold.level"
                class="flex items-center justify-between p-3 rounded-lg border"
                :class="threshold.level === factorData.level
                  ? 'bg-blue-50 border-blue-300'
                  : 'bg-eggshell-500 border-light-gray'"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-3 h-3 rounded-full"
                    :class="getThresholdDotClass(threshold.level)"
                  ></div>
                  <div>
                    <span class="text-sm font-medium text-horizon-500">{{ threshold.range }}</span>
                    <span class="text-sm text-neutral-500 ml-2">{{ getLevelDisplayName(threshold.level) }}</span>
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

        </template>
      </template>

      <!-- Factor not found -->
      <div v-else class="text-center py-12">
        <p class="text-neutral-500">Factor not found. Please go back and try again.</p>
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
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'RiskFactorDetailPage',

  mixins: [currencyMixin],

  components: {
    AppLayout,
    ModuleStatusBar,
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
        knowledge_level: 'Investment Knowledge',
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
        knowledge_level: {
          what: 'Your self-assessed level of investment knowledge and experience.',
          why: 'Greater investment knowledge supports understanding of risk and complex products.',
          thresholds: [
            { level: 'upper_medium', range: 'Experienced' },
            { level: 'medium', range: 'Intermediate' },
            { level: 'lower_medium', range: 'Novice or not specified' },
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
          what: 'Monthly income minus monthly expenditure, assessed relative to your income.',
          why: 'A strong surplus relative to income means you can regularly invest and absorb losses.',
          thresholds: [
            { level: 'upper_medium', range: 'More than 10% of income' },
            { level: 'medium', range: '0–10% of income' },
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
        logger.error('Error loading factor data:', error);
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
        low: 'bg-yellow-100 text-yellow-800',
        lower_medium: 'bg-pink-100 text-pink-800',
        medium: 'bg-green-100 text-green-800',
        upper_medium: 'bg-teal-100 text-teal-800',
        high: 'bg-blue-100 text-blue-800',
      };
      return classes[level] || 'bg-savannah-100 text-horizon-500';
    },

    getLevelCircleClass(level) {
      const classes = {
        low: 'bg-yellow-100 text-yellow-700',
        lower_medium: 'bg-pink-100 text-pink-700',
        medium: 'bg-green-100 text-green-700',
        upper_medium: 'bg-teal-100 text-teal-700',
        high: 'bg-blue-100 text-blue-700',
      };
      return classes[level] || 'bg-savannah-100 text-neutral-500';
    },

    getFactorBgClass(level) {
      const classes = {
        low: 'bg-yellow-100',
        lower_medium: 'bg-pink-100',
        medium: 'bg-green-100',
        upper_medium: 'bg-teal-100',
        high: 'bg-blue-100',
      };
      return classes[level] || 'bg-savannah-100';
    },

    getFactorColorClass(level) {
      const classes = {
        low: 'text-yellow-600',
        lower_medium: 'text-pink-600',
        medium: 'text-green-600',
        upper_medium: 'text-teal-600',
        high: 'text-blue-600',
      };
      return classes[level] || 'text-neutral-500';
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
        high: 'bg-blue-500',
        upper_medium: 'bg-teal-500',
        medium: 'bg-green-500',
        lower_medium: 'bg-pink-500',
        low: 'bg-yellow-500',
      };
      return classes[level] || 'bg-horizon-400';
    },

    formatRelationship(rel) {
      if (!rel) return '';
      return rel.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
  },
};
</script>
