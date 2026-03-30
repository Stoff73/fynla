<template>
  <div>
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
    </div>

    <template v-else>
      <!-- Section 1: Your Risk Level -->
      <div class="bg-eggshell-500 rounded-lg border border-light-gray p-6 mb-6">
        <h2 class="text-lg font-semibold text-horizon-500 mb-4">Your Risk Level</h2>

        <!-- Risk Level Display - Clickable -->
        <router-link to="/risk-profile/levels" class="flex items-center gap-6 group cursor-pointer">
          <div
            class="flex-shrink-0 w-20 h-20 rounded-full flex items-center justify-center transition-transform group-hover:scale-105"
            :class="riskLevelBgClass"
          >
            <span class="text-3xl font-bold" :class="riskLevelTextClass">
              {{ riskConfig?.level_numeric || '-' }}
            </span>
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <h3 class="text-xl font-bold text-horizon-500 group-hover:text-blue-600 transition-colors">
                {{ riskLevelDisplayName }}
              </h3>
              <svg class="w-5 h-5 text-horizon-400 group-hover:text-blue-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
            <p class="text-sm text-neutral-500 mt-1">{{ riskConfig?.short_description || '' }}</p>
            <p v-if="!isSelfAssessed" class="text-xs text-neutral-500 mt-2 flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
              Automatically calculated from your financial data
            </p>
            <p v-if="riskAssessedAt" class="text-xs text-horizon-400 mt-1">
              Last updated: {{ formatDate(riskAssessedAt) }}
            </p>
            <p class="text-xs text-blue-600 group-hover:text-blue-800 mt-2 flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Click to learn what risk levels mean
            </p>
          </div>
        </router-link>
      </div>

      <!-- Section 2: Factor Breakdown -->
      <div v-if="factorBreakdown && factorBreakdown.length > 0" class="bg-white rounded-lg border border-light-gray p-6 mb-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-2">How Your Risk Level is Calculated</h3>
        <p class="text-sm text-neutral-500 mb-4">
          Your risk profile is determined by analyzing 7 financial factors. The most common risk level across all factors becomes your overall risk level.
        </p>

        <!-- Factor Level Summary -->
        <div class="flex flex-wrap gap-2 mb-4 p-3 bg-eggshell-500 rounded-lg">
          <span
            v-for="(count, level) in levelCounts"
            :key="level"
            class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
            :class="getLevelBadgeClass(level)"
          >
            {{ getLevelDisplayName(level) }}: {{ count }}
          </span>
        </div>

        <!-- Factor Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <router-link
            v-for="factor in factorBreakdown"
            :key="factor.factor"
            :to="`/risk-profile/factor/${factor.factor}`"
            class="block"
          >
            <FactorBreakdownCard :factor="factor" />
          </router-link>
        </div>
        <p class="text-xs text-neutral-500 mt-3 text-center">
          Click on any factor to learn more about how it's calculated
        </p>
      </div>

      <!-- Section 3: Understanding Your Risk Level -->
      <div class="bg-white rounded-lg border border-light-gray p-6 mb-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Understanding Your Risk Level</h3>
        <div class="text-sm text-neutral-500 space-y-3">
          <p>
            Your risk profile influences the mix of assets in your portfolio - from lower-risk
            cash and bonds to higher-risk equities and alternatives. The {{ (riskLevelDisplayName || 'medium').toLowerCase() }} risk level suggests:
          </p>
          <div v-if="riskConfig" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
            <div class="text-center p-3 bg-eggshell-500 rounded-lg">
              <p class="text-2xl font-bold text-blue-600">{{ riskConfig.asset_allocation?.equities || 0 }}%</p>
              <p class="text-xs text-neutral-500">Equities</p>
            </div>
            <div class="text-center p-3 bg-eggshell-500 rounded-lg">
              <p class="text-2xl font-bold text-green-600">{{ riskConfig.asset_allocation?.bonds || 0 }}%</p>
              <p class="text-xs text-neutral-500">Bonds</p>
            </div>
            <div class="text-center p-3 bg-eggshell-500 rounded-lg">
              <p class="text-2xl font-bold text-teal-600">{{ riskConfig.asset_allocation?.cash || 0 }}%</p>
              <p class="text-xs text-neutral-500">Cash</p>
            </div>
            <div class="text-center p-3 bg-eggshell-500 rounded-lg">
              <p class="text-2xl font-bold text-purple-600">{{ riskConfig.asset_allocation?.alternatives || 0 }}%</p>
              <p class="text-xs text-neutral-500">Alternatives</p>
            </div>
          </div>
          <div v-if="riskConfig" class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded-lg">
            <p class="text-sm text-blue-800">
              <strong>Expected returns:</strong> {{ riskConfig.expected_returns?.min }}% - {{ riskConfig.expected_returns?.max }}% annually,
              with typical volatility of {{ riskConfig.volatility_percent }}%.
            </p>
          </div>
        </div>
      </div>

      <!-- Section 4: Override Notice -->
      <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p class="text-sm font-medium text-green-800">Product-Level Overrides</p>
            <p class="text-sm text-green-700 mt-1">
              When adding investments or pensions, you can choose any risk level for that specific product.
              This allows flexibility for individual products based on their purpose and time horizon.
            </p>
          </div>
        </div>
      </div>

      <!-- Section 5: Products with Custom Risk -->
      <div v-if="productsWithCustomRisk.length > 0" class="bg-white rounded-lg border border-light-gray p-6 mb-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-2">Products with Custom Risk Settings</h3>
        <p class="text-sm text-neutral-500 mb-4">
          These investments have risk levels that differ from your main profile.
        </p>

        <div class="space-y-3">
          <div
            v-for="product in productsWithCustomRisk"
            :key="product.id"
            class="flex items-center justify-between p-3 bg-eggshell-500 rounded-lg border border-light-gray"
          >
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="getProductIconClasses(product.type)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path v-if="product.type === 'investment'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                  <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <p class="font-medium text-horizon-500 text-sm">{{ product.name }}</p>
                <p class="text-xs text-neutral-500">{{ product.type === 'investment' ? 'Investment Account' : 'Defined Contribution Pension' }}</p>
              </div>
            </div>
            <RiskBadge
              :level="product.risk_preference"
              size="sm"
              :has-custom-risk="true"
            />
          </div>
        </div>
      </div>

      <!-- Section 6: Investment Types (Educational) -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <InvestmentTypesAccordion />
      </div>

      <!-- No Profile State -->
      <div v-if="!riskLevel && !loading" class="text-center py-12 bg-eggshell-500 rounded-lg border border-light-gray">
        <svg class="w-12 h-12 text-horizon-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <h3 class="text-lg font-semibold text-horizon-500 mb-2">Unable to Calculate Risk Profile</h3>
        <p class="text-sm text-neutral-500">
          Please complete your profile information (income, expenditure, retirement age) to calculate your risk profile.
        </p>
      </div>
    </template>
  </div>
</template>

<script>
import RiskBadge from '@/components/Shared/RiskBadge.vue';
import FactorBreakdownCard from '@/components/Risk/FactorBreakdownCard.vue';
import InvestmentTypesAccordion from '@/components/Risk/InvestmentTypesAccordion.vue';
import riskService from '@/services/riskService';

import logger from '@/utils/logger';
export default {
  name: 'RiskProfileSummary',

  components: {
    RiskBadge,
    FactorBreakdownCard,
    InvestmentTypesAccordion,
  },

  data() {
    return {
      loading: true,
      riskLevel: null,
      riskConfig: null,
      factorBreakdown: [],
      riskAssessedAt: null,
      isSelfAssessed: false,
      productsWithCustomRisk: [],
    };
  },

  computed: {
    riskLevelDisplayName() {
      return riskService.getDisplayName(this.riskLevel) || 'Not Set';
    },

    riskLevelBgClass() {
      const classes = {
        low: 'bg-violet-100',
        lower_medium: 'bg-pink-100',
        medium: 'bg-green-100',
        upper_medium: 'bg-teal-100',
        high: 'bg-blue-100',
      };
      return classes[this.riskLevel] || 'bg-savannah-100';
    },

    riskLevelTextClass() {
      const classes = {
        low: 'text-violet-600',
        lower_medium: 'text-pink-600',
        medium: 'text-green-600',
        upper_medium: 'text-teal-600',
        high: 'text-blue-600',
      };
      return classes[this.riskLevel] || 'text-neutral-500';
    },

    levelCounts() {
      if (!this.factorBreakdown || this.factorBreakdown.length === 0) return {};

      const counts = {};
      this.factorBreakdown.forEach((factor) => {
        counts[factor.level] = (counts[factor.level] || 0) + 1;
      });
      return counts;
    },
  },

  async created() {
    await this.loadRiskProfile();
  },

  methods: {
    async loadRiskProfile() {
      this.loading = true;
      try {
        // Always recalculate on load to ensure fresh data
        const response = await riskService.recalculate();

        if (response.data) {
          this.riskLevel = response.data.risk_level;
          this.riskConfig = response.data.config;
          this.factorBreakdown = response.data.factor_breakdown || [];
          this.riskAssessedAt = response.data.risk_assessed_at;
          this.isSelfAssessed = response.data.is_self_assessed;
        }

        await this.loadProductsWithCustomRisk();
      } catch (error) {
        logger.error('Error loading risk profile:', error);
      } finally {
        this.loading = false;
      }
    },

    async loadProductsWithCustomRisk() {
      try {
        await Promise.all([
          this.$store.dispatch('investment/fetchAccounts'),
          this.$store.dispatch('retirement/fetchRetirementData'),
        ]);

        const investments = this.$store.getters['investment/accounts'] || [];
        const pensions = this.$store.getters['retirement/dcPensions'] || [];

        this.productsWithCustomRisk = [
          ...investments
            .filter((a) => a.has_custom_risk && a.risk_preference)
            .map((a) => ({
              id: `inv-${a.id}`,
              type: 'investment',
              name: a.provider || a.platform || 'Investment Account',
              risk_preference: a.risk_preference,
            })),
          ...pensions
            .filter((p) => p.has_custom_risk && p.risk_preference)
            .map((p) => ({
              id: `pen-${p.id}`,
              type: 'pension',
              name: p.scheme_name || p.provider || 'Defined Contribution Pension',
              risk_preference: p.risk_preference,
            })),
        ];
      } catch (error) {
        logger.error('Error loading products:', error);
      }
    },

    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    getLevelDisplayName(level) {
      const names = {
        low: 'Low',
        lower_medium: 'Lower-Med',
        medium: 'Medium',
        upper_medium: 'Upper-Med',
        high: 'High',
      };
      return names[level] || level;
    },

    getLevelBadgeClass(level) {
      const classes = {
        low: 'bg-violet-100 text-violet-800',
        lower_medium: 'bg-pink-100 text-pink-800',
        medium: 'bg-green-100 text-green-800',
        upper_medium: 'bg-teal-100 text-teal-800',
        high: 'bg-blue-100 text-blue-800',
      };
      return classes[level] || 'bg-savannah-100 text-horizon-500';
    },

    getProductIconClasses(type) {
      if (type === 'investment') {
        return 'bg-blue-100 text-blue-600';
      }
      return 'bg-purple-100 text-purple-600';
    },
  },
};
</script>
