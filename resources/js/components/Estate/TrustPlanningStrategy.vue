<template>
  <div class="trust-planning-strategy">
    <!-- Loading State -->
    <div v-if="loadingTrustStrategy" class="flex items-center justify-center py-12">
      <div class="text-center">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-spring-500 mb-4"></div>
        <p class="text-neutral-500">Loading trust planning strategies...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="trustStrategyError" class="bg-white border border-light-gray rounded-lg p-6">
      <div class="flex items-start">
        <svg class="h-6 w-6 text-violet-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-violet-800">Unable to Load Trust Strategy</h3>
          <p class="mt-2 text-sm text-violet-700">{{ trustStrategyError }}</p>
          <button
            v-if="requiresAssets"
            @click="$emit('navigate-to-assets')"
            class="mt-3 text-sm font-medium text-violet-800 hover:text-violet-900 underline"
          >
            Add Assets Now
          </button>
        </div>
      </div>
    </div>

    <!-- Trust Strategy Content -->
    <div v-else-if="trustStrategy">
      <!-- Introduction -->
      <div class="mb-8 bg-white rounded-lg p-6 border border-light-gray">
        <h2 class="text-2xl font-bold text-horizon-500 mb-2">Personalized Trust Planning Strategy</h2>
        <p class="text-neutral-500 mb-4">
          Trust planning allows you to transfer assets outside your estate while retaining some control. UK tax rules for trusts differ from direct gifts:
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div class="bg-white rounded-lg p-4">
            <h4 class="font-semibold text-violet-500 mb-2">Chargeable Lifetime Transfers</h4>
            <ul class="space-y-1 text-neutral-500">
              <li>• Transfers to most trusts are immediately chargeable</li>
              <li>• <strong>20% tax</strong> on amounts exceeding £{{ ihtNilRateBand.toLocaleString() }} Nil Rate Band</li>
              <li>• <strong>25% effective rate</strong> if settlor pays the tax</li>
              <li>• 7-year rolling window for cumulative transfers</li>
            </ul>
          </div>
          <div class="bg-white rounded-lg p-4">
            <h4 class="font-semibold text-violet-500 mb-2">Death Within 7 Years</h4>
            <ul class="space-y-1 text-neutral-500">
              <li>• Tax recalculated at <strong>40% death rate</strong></li>
              <li>• Credit given for 20% already paid</li>
              <li>• Taper relief applies if death 3-7 years later</li>
              <li>• After 7 years: Chargeable Lifetime Transfer fully effective</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Liquidity Summary -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg p-4 border border-light-gray">
          <p class="text-sm text-neutral-500 mb-1">Total Estate Value</p>
          <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(trustStrategy.liquidity_analysis.summary.total_value) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-light-gray">
          <p class="text-sm text-spring-700 mb-1 font-medium">Immediately Transferable</p>
          <p class="text-2xl font-bold text-spring-900">{{ formatCurrency(trustStrategy.giftable_amounts.immediately_giftable) }}</p>
          <p class="text-xs text-spring-600">{{ trustStrategy.giftable_amounts.liquid_asset_count }} liquid assets</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-light-gray">
          <p class="text-sm text-violet-700 mb-1 font-medium">Transferable with Planning</p>
          <p class="text-2xl font-bold text-violet-900">{{ formatCurrency(trustStrategy.giftable_amounts.giftable_with_planning) }}</p>
          <p class="text-xs text-violet-600">{{ trustStrategy.giftable_amounts.semi_liquid_asset_count }} semi-liquid assets</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-light-gray">
          <p class="text-sm text-violet-700 mb-1 font-medium">Not Transferable</p>
          <p class="text-2xl font-bold text-violet-900">{{ formatCurrency(trustStrategy.giftable_amounts.not_giftable) }}</p>
          <p class="text-xs text-violet-600">{{ trustStrategy.giftable_amounts.illiquid_asset_count }} illiquid assets</p>
        </div>
      </div>

      <!-- Trust Strategies -->
      <div class="space-y-6 mb-8">
        <h3 class="text-lg font-semibold text-horizon-500">Your Trust Planning Options</h3>

        <div
          v-for="(strategy, index) in applicableStrategies"
          :key="index"
          class="bg-white rounded-lg border-2 transition-all"
          :class="getBorderColourClass(strategy.priority)"
        >
          <!-- Strategy Header -->
          <div
            class="p-6 cursor-pointer"
            @click="toggleStrategyDetails(index)"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                  <span
                    class="px-3 py-1 rounded-full text-xs font-semibold"
                    :class="getPriorityBadgeClass(strategy.priority)"
                  >
                    Priority {{ strategy.priority }}
                  </span>
                  <span
                    class="px-3 py-1 rounded-full text-xs font-semibold"
                    :class="getRiskLevelClass(strategy.risk_level)"
                  >
                    {{ strategy.risk_level }} Risk
                  </span>
                </div>
                <h4 class="text-xl font-bold text-horizon-500 mb-2">{{ strategy.strategy_name }}</h4>
                <p class="text-neutral-500 mb-4">{{ strategy.description }}</p>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div>
                    <p class="text-xs text-neutral-500">Amount to Trust</p>
                    <p class="text-lg font-bold text-violet-500">{{ formatCurrency(strategy.amount) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-neutral-500">Inheritance Tax Saving Potential</p>
                    <p class="text-lg font-bold text-spring-600">{{ formatCurrency(strategy.iht_saving_potential) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-neutral-500">Lifetime Tax Charge</p>
                    <p class="text-lg font-bold" :class="strategy.lifetime_tax_charge > 0 ? 'text-raspberry-600' : 'text-spring-600'">
                      {{ formatCurrency(strategy.lifetime_tax_charge) }}
                    </p>
                  </div>
                  <div>
                    <p class="text-xs text-neutral-500">Time Frame</p>
                    <p class="text-sm font-semibold text-neutral-500">{{ strategy.time_frame }}</p>
                  </div>
                </div>
              </div>

              <button class="ml-4 text-horizon-400 hover:text-neutral-500">
                <svg
                  class="w-6 h-6 transform transition-transform"
                  :class="{ 'rotate-180': expandedStrategies[index] }"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Expanded Strategy Details -->
          <div v-if="expandedStrategies[index]" class="border-t border-light-gray bg-eggshell-500 p-6">
            <!-- Tax Treatment -->
            <div v-if="strategy.tax_treatment" class="mb-6">
              <h5 class="font-semibold text-horizon-500 mb-3">Tax Treatment</h5>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500 mb-1">Immediate Charge (20%)</p>
                  <p class="text-xl font-bold text-raspberry-600">{{ formatCurrency(strategy.tax_treatment.immediate_charge) }}</p>
                </div>
                <div class="bg-white rounded-lg p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500 mb-1">If Death Within 7 Years (40%)</p>
                  <p class="text-xl font-bold text-raspberry-700">{{ formatCurrency(strategy.tax_treatment.death_within_7_years) }}</p>
                </div>
                <div class="bg-white rounded-lg p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500 mb-1">After 7 Years</p>
                  <p class="text-xl font-bold text-spring-600">{{ formatCurrency(strategy.tax_treatment.after_7_years) }}</p>
                  <p class="text-xs text-neutral-500 mt-1">Only lifetime charge</p>
                </div>
              </div>
            </div>

            <!-- CLT Cycle Schedule (for multi-cycle strategy) -->
            <div v-if="strategy.clt_schedule && strategy.clt_schedule.length > 0" class="mb-6">
              <h5 class="font-semibold text-horizon-500 mb-3">Chargeable Lifetime Transfer Cycle Schedule</h5>
              <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
                <table class="min-w-full divide-y divide-light-gray">
                  <thead class="bg-eggshell-500">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Cycle</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Year</th>
                      <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase">Amount</th>
                      <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase">Immediate Charge</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Description</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-light-gray">
                    <tr v-for="cycle in strategy.clt_schedule" :key="cycle.cycle">
                      <td class="px-4 py-3 text-sm font-medium text-horizon-500">{{ cycle.cycle }}</td>
                      <td class="px-4 py-3 text-sm text-neutral-500">{{ cycle.year }}</td>
                      <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">{{ formatCurrency(cycle.amount) }}</td>
                      <td class="px-4 py-3 text-sm text-right text-spring-600">£0</td>
                      <td class="px-4 py-3 text-sm text-neutral-500">{{ cycle.description }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Implementation Steps -->
            <div class="mb-6">
              <h5 class="font-semibold text-horizon-500 mb-3">Implementation Steps</h5>
              <div class="bg-white rounded-lg p-4 border border-light-gray">
                <ol class="space-y-2">
                  <li
                    v-for="(step, stepIndex) in strategy.implementation_steps"
                    :key="stepIndex"
                    class="text-sm"
                    :class="step.startsWith('**') ? 'font-bold text-horizon-500 mt-3' : 'text-neutral-500'"
                  >{{ sanitizeText(step) }}</li>
                </ol>
              </div>
            </div>

            <!-- Eligible Assets -->
            <div v-if="strategy.eligible_assets && strategy.eligible_assets.length > 0" class="mb-6">
              <h5 class="font-semibold text-horizon-500 mb-3">Eligible Assets ({{ strategy.eligible_assets.length }})</h5>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div
                  v-for="(asset, assetIndex) in strategy.eligible_assets"
                  :key="assetIndex"
                  class="bg-white rounded-lg p-3 border border-light-gray flex justify-between items-center"
                >
                  <div>
                    <p class="text-sm font-semibold text-horizon-500">{{ asset.asset_name }}</p>
                    <p class="text-xs text-neutral-500">{{ formatAssetType(asset.asset_type) }}</p>
                  </div>
                  <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(asset.current_value) }}</p>
                </div>
              </div>
            </div>

            <!-- Property Details (for strategy 5) -->
            <div v-if="strategy.property_details" class="mb-6">
              <h5 class="font-semibold text-horizon-500 mb-3">Property Details</h5>
              <div class="bg-white rounded-lg p-4 border border-light-gray">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <p class="text-xs text-neutral-500 mb-1">Property Name</p>
                    <p class="text-sm font-semibold text-horizon-500">{{ strategy.property_details.property_name }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-neutral-500 mb-1">Current Value</p>
                    <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(strategy.property_details.current_value) }}</p>
                  </div>
                </div>
                <div class="mt-3 pt-3 border-t border-light-gray">
                  <p class="text-xs text-violet-700">
                    <strong>⚠️ {{ strategy.property_details.reason_not_giftable }}</strong>
                  </p>
                </div>
              </div>
            </div>

            <!-- Benefits & Risks -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <h5 class="font-semibold text-spring-800 mb-3">Key Benefits</h5>
                <ul class="space-y-2">
                  <li
                    v-for="(benefit, benefitIndex) in strategy.key_benefits"
                    :key="benefitIndex"
                    class="text-sm text-neutral-500 flex items-start"
                  >
                    <svg class="w-5 h-5 text-spring-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ sanitizeText(benefit.replace('✓ ', '')) }}</span>
                  </li>
                </ul>
              </div>
              <div>
                <h5 class="font-semibold text-raspberry-800 mb-3">Key Risks</h5>
                <ul class="space-y-2">
                  <li
                    v-for="(risk, riskIndex) in strategy.key_risks"
                    :key="riskIndex"
                    class="text-sm text-neutral-500 flex items-start"
                  >
                    <svg class="w-5 h-5 text-raspberry-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ sanitizeText(risk.replace('✗ ', '')) }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Overall Strategy Impact -->
      <div class="bg-white rounded-lg p-6 border border-light-gray">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Overall Strategy Impact</h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
          <div class="bg-white rounded-lg p-4">
            <p class="text-xs text-neutral-500 mb-1">Total Amount Transferred</p>
            <p class="text-xl font-bold text-violet-500">{{ formatCurrency(trustStrategy.strategy_impact.total_amount_transferred) }}</p>
          </div>
          <div class="bg-white rounded-lg p-4">
            <p class="text-xs text-neutral-500 mb-1">Total Inheritance Tax Saving</p>
            <p class="text-xl font-bold text-spring-600">{{ formatCurrency(trustStrategy.strategy_impact.total_iht_saving) }}</p>
          </div>
          <div class="bg-white rounded-lg p-4">
            <p class="text-xs text-neutral-500 mb-1">Total Lifetime Charges</p>
            <p class="text-xl font-bold text-raspberry-600">{{ formatCurrency(trustStrategy.strategy_impact.total_lifetime_charges) }}</p>
          </div>
          <div class="bg-white rounded-lg p-4">
            <p class="text-xs text-neutral-500 mb-1">Net Saving</p>
            <p class="text-xl font-bold text-spring-500">{{ formatCurrency(trustStrategy.strategy_impact.net_saving) }}</p>
          </div>
        </div>

        <!-- Worst Case Scenario -->
        <div class="bg-eggshell-500 border border-violet-200 rounded-lg p-4">
          <h4 class="font-semibold text-violet-900 mb-2">Worst Case Scenario (Death Before 7 Years)</h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-violet-700 mb-1">Total Costs (20% + additional 20%)</p>
              <p class="text-lg font-bold text-violet-900">{{ formatCurrency(trustStrategy.strategy_impact.worst_case_cost) }}</p>
            </div>
            <div>
              <p class="text-xs text-violet-700 mb-1">Net Saving (Worst Case)</p>
              <p class="text-lg font-bold" :class="trustStrategy.strategy_impact.worst_case_net_saving > 0 ? 'text-spring-600' : 'text-raspberry-600'">
                {{ formatCurrency(trustStrategy.strategy_impact.worst_case_net_saving) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Summary -->
        <div class="mt-6 pt-6 border-t border-spring-200">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <p class="text-sm text-neutral-500 mb-1">Recommended Strategy</p>
              <p class="text-base font-bold text-horizon-500">{{ trustStrategy.summary.recommended_strategy }}</p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">Effectiveness Rating</p>
              <p class="text-base font-bold text-spring-500">{{ trustStrategy.summary.effectiveness_rating }}</p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">Net Benefit</p>
              <p class="text-base font-bold text-spring-600">{{ formatCurrency(trustStrategy.summary.net_benefit) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Important Disclaimer -->
      <div class="mt-8 bg-eggshell-500 rounded-lg p-4">
        <div class="flex">
          <svg class="h-5 w-5 text-violet-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <div class="ml-3">
            <h4 class="text-sm font-medium text-violet-800">Important: Seek Professional Advice</h4>
            <p class="mt-2 text-sm text-violet-700">
              Trust planning is complex and has significant legal and tax implications. The strategies shown are for educational purposes only.
              You must consult a qualified solicitor and tax adviser before implementing any trust strategy. Professional trust drafting is essential
              to ensure the trust achieves your objectives and complies with HM Revenue & Customs requirements.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import estateService from '@/services/estateService';
import { currencyMixin } from '@/mixins/currencyMixin';
import { IHT_NIL_RATE_BAND } from '@/constants/taxConfig';

export default {
  name: 'TrustPlanningStrategy',

  emits: ['navigate-to-assets'],

  mixins: [currencyMixin],

  data() {
    return {
      ihtNilRateBand: IHT_NIL_RATE_BAND,
      trustStrategy: null,
      loadingTrustStrategy: false,
      trustStrategyError: null,
      requiresAssets: false,
      expandedStrategies: {},
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
    applicableStrategies() {
      if (!this.trustStrategy?.strategies) return [];
      return this.trustStrategy.strategies.filter(s => s.applicable !== false);
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadTrustStrategy();
  },

  methods: {
    async loadTrustStrategy() {
      this.loadingTrustStrategy = true;
      this.trustStrategyError = null;

      try {
        const response = await estateService.getPersonalizedTrustStrategy();
        if (response.success) {
          this.trustStrategy = response.data;
        } else {
          this.trustStrategyError = response.message || 'Failed to load trust strategy';
          this.requiresAssets = response.requires_assets || false;
        }
      } catch (error) {
        if (error.response?.status === 422) {
          this.trustStrategyError = error.response.data.message;
          this.requiresAssets = error.response.data.requires_assets || false;
        } else {
          this.trustStrategyError = 'An error occurred while loading trust strategy';
        }
      } finally {
        this.loadingTrustStrategy = false;
      }
    },

    toggleStrategyDetails(index) {
      this.$set(this.expandedStrategies, index, !this.expandedStrategies[index]);
    },

    getBorderColourClass(priority) {
      if (priority === 1) return 'border-spring-500 shadow-md';
      if (priority === 2) return 'border-violet-300';
      if (priority === 3) return 'border-violet-200';
      return 'border-horizon-300';
    },

    getPriorityBadgeClass(priority) {
      if (priority === 1) return 'bg-spring-500 text-white';
      if (priority === 2) return 'bg-violet-500 text-white';
      if (priority === 3) return 'bg-violet-500 text-white';
      return 'bg-eggshell-5000 text-white';
    },

    getRiskLevelClass(riskLevel) {
      const riskLower = (riskLevel || '').toLowerCase();
      if (riskLower === 'low') return 'bg-spring-500 text-white';
      if (riskLower === 'medium') return 'bg-violet-500 text-white';
      if (riskLower === 'high') return 'bg-raspberry-500 text-white';
      return 'bg-eggshell-5000 text-white';
    },

    /**
     * Sanitize text by removing markdown formatting and HTML tags
     * This prevents XSS attacks while preserving readable text content
     */
    sanitizeText(text) {
      if (!text) return '';
      // Remove markdown bold formatting (**text** -> text)
      let sanitized = text.replace(/\*\*(.*?)\*\*/g, '$1');
      // Remove any HTML tags for security
      sanitized = sanitized.replace(/<[^>]*>/g, '');
      return sanitized;
    },

    formatStep(step) {
      // DEPRECATED: Use sanitizeText instead for security
      // This method kept for backward compatibility but now returns plain text
      return this.sanitizeText(step);
    },

    formatAssetType(type) {
      const types = {
        cash: 'Cash',
        investment: 'Investment',
        property: 'Property',
        pension: 'Pension',
        business: 'Business Interest',
        other: 'Other Asset',
      };
      return types[type] || type;
    },

    // formatCurrency provided by currencyMixin
  },
};
</script>

<style scoped>
.trust-planning-strategy {
  /* Component styles */
}
</style>
