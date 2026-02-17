<template>
  <div class="account-rebalancing-panel">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <span class="ml-3 text-gray-600">Analysing allocation...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center py-8">
      <div class="text-red-500 mb-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <p class="text-gray-700 font-medium">{{ error }}</p>
      <button @click="loadRebalancingData" class="mt-4 px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">
        Retry
      </button>
    </div>

    <!-- Content -->
    <div v-else-if="rebalancingData" class="space-y-6">
      <!-- Settings Section -->
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Rebalancing Settings</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Risk Profile -->
          <div>
            <label class="block text-sm text-gray-600 mb-1">Risk Profile</label>
            <!-- Show effective risk level (account custom or user default) -->
            <div class="flex items-center">
              <span class="text-lg font-semibold" :class="effectiveRiskLevelClass">
                {{ effectiveRiskLabel }}
              </span>
              <span class="ml-2 text-sm text-gray-500">(Level {{ effectiveRiskLevel }})</span>
            </div>
            <!-- Show user's main profile if account has custom -->
            <div v-if="hasCustomRisk" class="mt-2 text-sm">
              <span class="text-purple-600 font-medium">Account Override</span>
              <span class="text-gray-500 ml-1">
                (Your profile: {{ userRiskLabel }})
              </span>
            </div>
            <div v-else class="mt-1 text-xs text-gray-500">
              Using your main risk profile
            </div>
          </div>

          <!-- Drift Threshold -->
          <div>
            <label class="block text-sm text-gray-600 mb-1">Drift Threshold</label>
            <div class="flex items-center gap-2">
              <input
                v-model.number="editableThreshold"
                type="number"
                min="1"
                max="50"
                step="1"
                class="w-20 px-2 py-1 border border-gray-300 rounded text-center"
                @change="handleThresholdChange"
              />
              <span class="text-gray-600">%</span>
              <span v-if="thresholdSaving" class="text-sm text-blue-600">Saving...</span>
              <span v-if="thresholdSaved" class="text-sm text-green-600">Saved</span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Rebalance when allocation drifts above this</p>
          </div>

          <!-- Tax Status -->
          <div>
            <label class="block text-sm text-gray-600 mb-1">Tax Status</label>
            <div v-if="rebalancingData.is_tax_free" class="flex items-center text-green-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="font-medium">Tax-Free Account</span>
            </div>
            <div v-else class="flex items-center text-blue-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="font-medium">Subject to Capital Gains Tax</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Drift Status -->
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Allocation Drift</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="text-center p-4 rounded-lg" :class="driftStatusBgClass">
            <p class="text-sm text-gray-600 mb-1">Drift Score</p>
            <p class="text-3xl font-bold" :class="driftStatusClass">
              {{ rebalancingData.drift_analysis.drift_score.toFixed(1) }}%
            </p>
          </div>
          <div class="text-center p-4 bg-gray-100 rounded-lg">
            <p class="text-sm text-gray-600 mb-1">Maximum Drift</p>
            <p class="text-3xl font-bold text-gray-800">
              {{ rebalancingData.drift_analysis.max_drift.toFixed(1) }}%
            </p>
          </div>
          <div class="text-center p-4 rounded-lg" :class="needsRebalancingBgClass">
            <p class="text-sm text-gray-600 mb-1">Rebalancing</p>
            <p class="text-xl font-bold" :class="needsRebalancingClass">
              {{ rebalancingData.drift_analysis.needs_rebalancing ? 'Recommended' : 'Not Needed' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Allocation Comparison -->
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Current vs Target Allocation</h3>
        <div class="space-y-4">
          <div v-for="asset in assetClasses" :key="asset.key" class="relative">
            <div class="flex justify-between text-sm mb-1">
              <span class="font-medium text-gray-700">{{ asset.label }}</span>
              <span class="text-gray-600">
                Current: {{ formatAllocation(rebalancingData.current_allocation[asset.key]) }}% |
                Target: {{ formatAllocation(rebalancingData.target_allocation[asset.key]) }}%
              </span>
            </div>
            <div class="h-6 bg-gray-200 rounded overflow-hidden relative">
              <!-- Target line -->
              <div
                class="absolute h-full w-0.5 bg-gray-800 z-10"
                :style="{ left: formatAllocation(rebalancingData.target_allocation[asset.key]) + '%' }"
              ></div>
              <!-- Current bar -->
              <div
                class="h-full rounded transition-all duration-300"
                :class="getAssetBarClass(asset.key)"
                :style="{ width: formatAllocation(rebalancingData.current_allocation[asset.key]) + '%' }"
              ></div>
            </div>
          </div>
        </div>
        <div class="flex items-center justify-center mt-4 text-sm text-gray-500">
          <div class="flex items-center mr-4">
            <div class="w-4 h-4 bg-blue-500 rounded mr-2"></div>
            <span>Current Allocation</span>
          </div>
          <div class="flex items-center">
            <div class="w-4 h-1 bg-gray-800 mr-2"></div>
            <span>Target</span>
          </div>
        </div>
      </div>

      <!-- Trade Recommendations (if needed) -->
      <div v-if="rebalancingData.drift_analysis.needs_rebalancing && rebalancingData.rebalancing_actions.length > 0" class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 bg-blue-50 border-b border-blue-200">
          <h3 class="text-lg font-semibold text-blue-800">Recommended Trades</h3>
          <p class="text-sm text-blue-700">Execute these trades to realign with your target allocation</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 bg-gray-50">
                <th class="text-left py-3 px-4 font-semibold text-gray-700">Action</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-700">Holding</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-700">Current Value</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-700">Trade Amount</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-700">Target Value</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(action, index) in rebalancingData.rebalancing_actions"
                :key="index"
                class="border-b border-gray-100 hover:bg-gray-50"
              >
                <td class="py-3 px-4">
                  <span
                    class="px-2 py-1 rounded text-xs font-semibold"
                    :class="action.action === 'buy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                  >
                    {{ action.action.toUpperCase() }}
                  </span>
                </td>
                <td class="py-3 px-4 font-medium text-gray-900">{{ action.holding_name || action.symbol || 'Unknown' }}</td>
                <td class="text-right py-3 px-4">{{ formatCurrency(action.current_value) }}</td>
                <td class="text-right py-3 px-4 font-semibold" :class="action.action === 'buy' ? 'text-green-600' : 'text-red-600'">
                  {{ action.action === 'buy' ? '+' : '-' }}{{ formatCurrency(Math.abs(action.trade_value || action.amount || 0)) }}
                </td>
                <td class="text-right py-3 px-4">{{ formatCurrency(action.target_value) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- CGT Impact (for taxable accounts) -->
      <div v-if="!rebalancingData.is_tax_free && rebalancingData.cgt_analysis" class="bg-white rounded-lg border border-gray-200 p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Capital Gains Tax Impact</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
            <p class="text-sm text-gray-600 mb-1">Total Gains</p>
            <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(rebalancingData.cgt_analysis.total_gains) }}</p>
          </div>
          <div class="text-center p-4 bg-green-50 rounded-lg border border-green-200">
            <p class="text-sm text-gray-600 mb-1">Allowance Used</p>
            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(rebalancingData.cgt_analysis.allowance_used) }}</p>
            <p class="text-xs text-gray-500">of £3,000 annual allowance</p>
          </div>
          <div class="text-center p-4 rounded-lg border" :class="rebalancingData.cgt_analysis.cgt_liability > 0 ? 'bg-red-50 border-red-200' : 'bg-gray-100 border-gray-200'">
            <p class="text-sm text-gray-600 mb-1">Capital Gains Tax Liability</p>
            <p class="text-2xl font-bold" :class="rebalancingData.cgt_analysis.cgt_liability > 0 ? 'text-red-600' : 'text-gray-600'">
              {{ formatCurrency(rebalancingData.cgt_analysis.cgt_liability) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Tax-Free Notice -->
      <div v-if="rebalancingData.is_tax_free && rebalancingData.drift_analysis.needs_rebalancing" class="bg-green-50 rounded-lg border border-green-200 p-4">
        <div class="flex items-start">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <h4 class="font-semibold text-green-800">Tax-Free Rebalancing</h4>
            <p class="text-sm text-green-700 mt-1">
              This {{ accountTypeName }} account is tax-sheltered. You can rebalance without any Capital Gains Tax implications.
              Trades executed within this wrapper are completely tax-free.
            </p>
          </div>
        </div>
      </div>

      <!-- No Action Needed Notice -->
      <div v-if="!rebalancingData.drift_analysis.needs_rebalancing" class="bg-blue-50 rounded-lg border border-blue-200 p-4">
        <div class="flex items-start">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <h4 class="font-semibold text-blue-800">Portfolio On Track</h4>
            <p class="text-sm text-blue-700 mt-1">
              Your current allocation is within the {{ rebalancingData.threshold_percent }}% drift threshold.
              No rebalancing action is required at this time.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- No Holdings State -->
    <div v-else class="text-center py-12">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
      </svg>
      <p class="text-lg font-medium text-gray-700">No Holdings Found</p>
      <p class="text-sm text-gray-500 mt-1">Add holdings to this account to enable rebalancing analysis.</p>
    </div>
  </div>
</template>

<script>
import rebalancingService from '@/services/rebalancingService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AccountRebalancingPanel',

  mixins: [currencyMixin],

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      loading: false,
      error: null,
      rebalancingData: null,
      editableThreshold: 10,
      thresholdSaving: false,
      thresholdSaved: false,
      thresholdSavedTimeout: null,
      assetClasses: [
        { key: 'equities', label: 'Equities' },
        { key: 'bonds', label: 'Bonds' },
        { key: 'cash', label: 'Cash' },
        { key: 'alternatives', label: 'Alternatives' },
      ],
    };
  },

  computed: {
    // Risk profile computed properties
    riskProfile() {
      return this.rebalancingData?.risk_profile || {};
    },

    effectiveRiskLevel() {
      return this.riskProfile.effective_risk_level || 3;
    },

    effectiveRiskLabel() {
      return this.riskProfile.effective_risk_label || 'Moderate';
    },

    userRiskLevel() {
      return this.riskProfile.user_risk_level || 3;
    },

    userRiskLabel() {
      return this.riskProfile.user_risk_label || 'Moderate';
    },

    hasCustomRisk() {
      return this.riskProfile.has_custom_risk || false;
    },

    effectiveRiskLevelClass() {
      const level = this.effectiveRiskLevel;
      return {
        1: 'text-blue-600',
        2: 'text-green-600',
        3: 'text-yellow-600',
        4: 'text-blue-600',
        5: 'text-red-600',
      }[level] || 'text-gray-600';
    },

    driftStatusClass() {
      const score = this.rebalancingData?.drift_analysis?.drift_score || 0;
      const threshold = this.rebalancingData?.threshold_percent || 10;
      if (score >= threshold) return 'text-red-600';
      if (score >= threshold * 0.7) return 'text-blue-600';
      return 'text-green-600';
    },

    driftStatusBgClass() {
      const score = this.rebalancingData?.drift_analysis?.drift_score || 0;
      const threshold = this.rebalancingData?.threshold_percent || 10;
      if (score >= threshold) return 'bg-red-50';
      if (score >= threshold * 0.7) return 'bg-blue-50';
      return 'bg-green-50';
    },

    needsRebalancingClass() {
      return this.rebalancingData?.drift_analysis?.needs_rebalancing
        ? 'text-red-600'
        : 'text-green-600';
    },

    needsRebalancingBgClass() {
      return this.rebalancingData?.drift_analysis?.needs_rebalancing
        ? 'bg-red-50'
        : 'bg-green-50';
    },

    accountTypeName() {
      const type = this.rebalancingData?.account_type?.toUpperCase() || 'Investment';
      return type === 'ISA' ? 'ISA' : type === 'SIPP' ? 'Self-Invested Personal Pension' : type === 'LISA' ? 'Lifetime ISA' : type;
    },
  },

  beforeUnmount() {
    if (this.thresholdSavedTimeout) clearTimeout(this.thresholdSavedTimeout);
  },

  watch: {
    account: {
      handler() {
        this.loadRebalancingData();
      },
      immediate: true,
    },
  },

  methods: {
    async loadRebalancingData() {
      if (!this.account?.id) return;

      this.loading = true;
      this.error = null;

      try {
        const response = await rebalancingService.getAccountRebalancing(this.account.id);
        if (response.success) {
          this.rebalancingData = response.data;
          this.editableThreshold = response.data.threshold_percent || 10;
        } else {
          this.error = response.message || 'Failed to load rebalancing data';
        }
      } catch (err) {
        console.error('Failed to load rebalancing data:', err);
        this.error = err.response?.data?.message || 'Failed to load rebalancing data';
      } finally {
        this.loading = false;
      }
    },

    async handleThresholdChange() {
      if (this.editableThreshold < 1 || this.editableThreshold > 50) {
        this.editableThreshold = Math.max(1, Math.min(50, this.editableThreshold));
      }

      this.thresholdSaving = true;
      this.thresholdSaved = false;

      try {
        await rebalancingService.updateRebalancingThreshold(this.account.id, this.editableThreshold);
        this.thresholdSaved = true;
        // Reload to get updated needs_rebalancing status
        await this.loadRebalancingData();
        if (this.thresholdSavedTimeout) clearTimeout(this.thresholdSavedTimeout);
        this.thresholdSavedTimeout = setTimeout(() => {
          this.thresholdSaved = false;
        }, 2000);
      } catch (err) {
        console.error('Failed to update threshold:', err);
        // Revert to previous value
        this.editableThreshold = this.rebalancingData?.threshold_percent || 10;
      } finally {
        this.thresholdSaving = false;
      }
    },

    formatAllocation(value) {
      if (value === null || value === undefined) return 0;
      return parseFloat(value).toFixed(1);
    },

    getAssetBarClass(assetKey) {
      return {
        equities: 'bg-blue-500',
        bonds: 'bg-green-500',
        cash: 'bg-blue-500',
        alternatives: 'bg-purple-500',
      }[assetKey] || 'bg-gray-500';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
