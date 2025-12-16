<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-4 sm:py-8 px-4 sm:px-6">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Risk Profile</h1>
            <p class="mt-2 text-sm sm:text-base text-gray-600">
              Understanding risk is key to successful investing
            </p>
          </div>
          <router-link
            to="/net-worth/investments"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
          </router-link>
        </div>
      </div>

      <!-- Loading state -->
      <div v-if="loading" class="flex justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>

      <template v-else>
        <!-- Redirect notice (when coming from investment/retirement form) -->
        <div v-if="redirectReason" class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <p class="text-sm font-medium text-amber-800">
                {{ redirectReason === 'investment' ? 'Set your risk profile to add investments' : 'Set your risk profile to add pensions' }}
              </p>
              <p class="text-sm text-amber-700 mt-1">
                Understanding your risk tolerance helps ensure your {{ redirectReason === 'investment' ? 'investment' : 'pension' }} choices align with your goals. Select a risk level below and save to continue.
              </p>
            </div>
          </div>
        </div>

        <!-- Section 1: Introduction -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Understanding Investment Risk</h2>
          <div class="text-sm text-gray-600 space-y-3">
            <p>
              Before investing, it's important to understand your attitude to risk and your capacity
              to withstand potential losses. This helps ensure your investments are aligned with your
              goals and circumstances.
            </p>
            <p>
              Your risk profile influences the mix of assets in your portfolio - from lower-risk
              cash and bonds to higher-risk equities and alternatives. There's no right or wrong
              answer; the best approach depends on your individual situation.
            </p>
          </div>
        </div>

        <!-- Section 2: Risk Factors -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <RiskFactorsPanel />
        </div>

        <!-- Section 3: Capacity for Loss -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <CapacityForLossSection :selected-level="selectedRiskLevel" />
        </div>

        <!-- Section 4: Time Horizon -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <TimeHorizonSection v-model="selectedTimeHorizon" @change="handleTimeHorizonChange" />
        </div>

        <!-- Section 5: Choose Your Risk Level -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Choose Your Risk Level</h3>
          <p class="text-sm text-gray-600 mb-6">
            Based on your understanding of risk, capacity for loss, and time horizon,
            select the risk level that best matches your investment approach.
          </p>

          <RiskLevelSelector
            v-model="selectedRiskLevel"
            :show-allocation="true"
            :show-returns="true"
            @change="handleRiskLevelChange"
          />

          <!-- Save button -->
          <div class="mt-6 flex items-center justify-between">
            <div v-if="hasChanges" class="text-sm text-amber-600 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              You have unsaved changes
            </div>
            <div v-else></div>

            <button
              type="button"
              :disabled="saving || !selectedRiskLevel"
              class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              @click="saveRiskProfile"
            >
              <span v-if="saving">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </span>
              {{ saving ? 'Saving...' : 'Save Risk Profile' }}
            </button>
          </div>

          <!-- Success message -->
          <transition name="fade">
            <div v-if="saveSuccess" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2 text-green-800">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="text-sm font-medium">Risk profile saved successfully</span>
            </div>
          </transition>
        </div>

        <!-- Section 6: Investment Types -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <InvestmentTypesAccordion />
        </div>

        <!-- Section 7: Products with Custom Risk -->
        <div v-if="productsWithCustomRisk.length > 0" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Products with Custom Risk Settings</h3>
          <p class="text-sm text-gray-600 mb-4">
            These investments have risk levels that differ from your main profile.
          </p>

          <div class="space-y-3">
            <div
              v-for="product in productsWithCustomRisk"
              :key="product.id"
              class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="getProductIconClasses(product.type)">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path v-if="product.type === 'investment'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <p class="font-medium text-gray-900 text-sm">{{ product.name }}</p>
                  <p class="text-xs text-gray-500">{{ product.type === 'investment' ? 'Investment Account' : 'DC Pension' }}</p>
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
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import RiskLevelSelector from '@/components/Shared/RiskLevelSelector.vue';
import RiskBadge from '@/components/Shared/RiskBadge.vue';
import RiskFactorsPanel from '@/components/Risk/RiskFactorsPanel.vue';
import CapacityForLossSection from '@/components/Risk/CapacityForLossSection.vue';
import TimeHorizonSection from '@/components/Risk/TimeHorizonSection.vue';
import InvestmentTypesAccordion from '@/components/Risk/InvestmentTypesAccordion.vue';
import riskService from '@/services/riskService';

export default {
  name: 'RiskProfilePage',

  components: {
    AppLayout,
    RiskLevelSelector,
    RiskBadge,
    RiskFactorsPanel,
    CapacityForLossSection,
    TimeHorizonSection,
    InvestmentTypesAccordion,
  },

  data() {
    return {
      loading: true,
      saving: false,
      saveSuccess: false,
      selectedRiskLevel: null,
      originalRiskLevel: null,
      selectedTimeHorizon: null,
      productsWithCustomRisk: [],
      // Redirect handling
      redirectUrl: null,
      redirectReason: null,
    };
  },

  computed: {
    hasChanges() {
      return this.selectedRiskLevel !== this.originalRiskLevel;
    },
  },

  async created() {
    // Capture redirect parameters from query string
    this.redirectUrl = this.$route.query.redirect || null;
    this.redirectReason = this.$route.query.reason || null;

    await this.loadRiskProfile();
  },

  methods: {
    async loadRiskProfile() {
      this.loading = true;
      try {
        const response = await riskService.getProfile();
        if (response.data?.risk_level) {
          this.selectedRiskLevel = response.data.risk_level;
          this.originalRiskLevel = response.data.risk_level;
        }
        if (response.data?.time_horizon_years) {
          this.selectedTimeHorizon = this.mapYearsToHorizon(response.data.time_horizon_years);
        }

        // Load products with custom risk
        await this.loadProductsWithCustomRisk();
      } catch (error) {
        console.error('Error loading risk profile:', error);
      } finally {
        this.loading = false;
      }
    },

    async loadProductsWithCustomRisk() {
      try {
        // Fetch investment accounts and DC pensions with custom risk
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
              name: p.scheme_name || p.provider || 'DC Pension',
              risk_preference: p.risk_preference,
            })),
        ];
      } catch (error) {
        console.error('Error loading products:', error);
      }
    },

    mapYearsToHorizon(years) {
      if (years <= 3) return 'short';
      if (years <= 7) return 'medium';
      if (years <= 15) return 'long';
      if (years <= 25) return 'very_long';
      return 'indefinite';
    },

    mapHorizonToYears(horizon) {
      const mapping = {
        short: 2,
        medium: 5,
        long: 11,
        very_long: 20,
        indefinite: 30,
      };
      return mapping[horizon] || null;
    },

    handleRiskLevelChange(level) {
      this.saveSuccess = false;
    },

    handleTimeHorizonChange(horizon) {
      // Time horizon is informational for now
    },

    async saveRiskProfile() {
      if (!this.selectedRiskLevel) return;

      this.saving = true;
      this.saveSuccess = false;

      try {
        const data = {
          risk_level: this.selectedRiskLevel,
          is_self_assessed: true,
        };

        if (this.selectedTimeHorizon) {
          data.time_horizon_years = this.mapHorizonToYears(this.selectedTimeHorizon);
        }

        await riskService.setProfile(data);
        this.originalRiskLevel = this.selectedRiskLevel;
        this.saveSuccess = true;

        // If we came from a redirect (e.g., investment form), navigate back
        if (this.redirectUrl) {
          setTimeout(() => {
            this.$router.push(this.redirectUrl);
          }, 1000);
          return;
        }

        // Otherwise, hide success message after 3 seconds
        setTimeout(() => {
          this.saveSuccess = false;
        }, 3000);
      } catch (error) {
        console.error('Error saving risk profile:', error);
        alert('Failed to save risk profile. Please try again.');
      } finally {
        this.saving = false;
      }
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

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
