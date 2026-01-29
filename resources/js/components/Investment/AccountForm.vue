<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="bg-white px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ isEditMode ? 'Edit Investment Account' : 'Add New Investment Account' }}
            </h3>
            <button
              @click="closeModal"
              class="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="submitForm">
          <div class="bg-white px-6 py-4 space-y-4">
            <!-- Account Type -->
            <div>
              <label for="account_type" class="block text-sm font-medium text-gray-700 mb-1">
                Account Type
              </label>
              <select
                id="account_type"
                v-model="formData.account_type"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.account_type }"
              >
                <option value="">Select account type</option>
                <option value="isa">ISA (Stocks & Shares)</option>
                <option value="gia">General Investment Account</option>
                <option value="onshore_bond">Onshore Bond</option>
                <option value="offshore_bond">Offshore Bond</option>
                <option value="vct">Venture Capital Trust (VCT)</option>
                <option value="eis">Enterprise Investment Scheme (EIS)</option>
                <option value="other">Other</option>
              </select>
              <p v-if="errors.account_type" class="mt-1 text-sm text-red-600">{{ errors.account_type }}</p>
            </div>

            <!-- Custom Account Type (if 'other' selected) -->
            <div v-if="formData.account_type === 'other'">
              <label for="account_type_other" class="block text-sm font-medium text-gray-700 mb-1">
                Specify Account Type
              </label>
              <input
                id="account_type_other"
                v-model="formData.account_type_other"
                type="text"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.account_type_other }"
                placeholder="e.g., Gold, Cryptocurrency, Classic Cars, Art Collection"
              />
              <p v-if="errors.account_type_other" class="mt-1 text-sm text-red-600">{{ errors.account_type_other }}</p>
              <p class="mt-1 text-xs text-gray-500">Enter the custom asset class for this investment</p>
            </div>

            <!-- Provider -->
            <div>
              <label for="provider" class="block text-sm font-medium text-gray-700 mb-1">
                Provider
              </label>
              <input
                id="provider"
                v-model="formData.provider"
                type="text"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.provider }"
                placeholder="e.g., Vanguard, Hargreaves Lansdown, Interactive Investor"
              />
              <p v-if="errors.provider" class="mt-1 text-sm text-red-600">{{ errors.provider }}</p>
            </div>

            <!-- Country Selector -->
            <div>
              <CountrySelector
                v-model="formData.country"
                label="Country"
                :required="true"
                default-country="United Kingdom"
              />
            </div>

            <!-- Platform -->
            <div>
              <label for="platform" class="block text-sm font-medium text-gray-700 mb-1">
                Platform/Product Name
              </label>
              <input
                id="platform"
                v-model="formData.platform"
                type="text"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="e.g., Investment Account, ISA"
              />
              <p class="mt-1 text-xs text-gray-500">Optional: Specific platform or product name</p>
            </div>

            <!-- Current Value -->
            <div>
              <label for="current_value" class="block text-sm font-medium text-gray-700 mb-1">
                Current Value (£)
              </label>
              <input
                id="current_value"
                v-model.number="formData.current_value"
                type="number"
                step="0.01"
                min="0"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.current_value }"
                placeholder="0.00"
              />
              <p v-if="errors.current_value" class="mt-1 text-sm text-red-600">{{ errors.current_value }}</p>
              <p class="mt-1 text-xs text-gray-500">Current total value of the account</p>
            </div>

            <!-- Contributions Section (only for non-ISA accounts) -->
            <div v-if="!isISAType" class="space-y-4 pt-4 border-t border-gray-200">
              <h4 class="text-sm font-semibold text-gray-900">Regular Contributions</h4>

              <!-- Monthly Contribution Amount and Frequency -->
              <div>
                <label for="monthly_contribution_amount" class="block text-sm font-medium text-gray-700 mb-1">
                  Regular Contribution Amount (£)
                </label>
                <div class="flex gap-2">
                  <div class="flex-1">
                    <input
                      id="monthly_contribution_amount"
                      v-model.number="formData.monthly_contribution_amount"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="0.00"
                    />
                  </div>
                  <div class="w-32">
                    <select
                      id="contribution_frequency"
                      v-model="formData.contribution_frequency"
                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="monthly">Monthly</option>
                      <option value="quarterly">Quarterly</option>
                      <option value="annually">Annually</option>
                    </select>
                  </div>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                  Regular contributions you make to this account
                </p>
              </div>

              <!-- Planned Lump Sum -->
              <div>
                <label for="planned_lump_sum_amount" class="block text-sm font-medium text-gray-700 mb-1">
                  Planned Lump Sum (£)
                </label>
                <div class="flex gap-2">
                  <div class="flex-1">
                    <input
                      id="planned_lump_sum_amount"
                      v-model.number="formData.planned_lump_sum_amount"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="0.00"
                    />
                  </div>
                  <div class="w-40">
                    <input
                      id="planned_lump_sum_date"
                      v-model="formData.planned_lump_sum_date"
                      type="date"
                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                  One-off contribution planned for this account (optional)
                </p>
              </div>
            </div>

            <!-- Platform Fee Section (not shown for NS&I) -->
            <div v-if="!isNSIType">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Platform Fee
              </label>
              <div class="flex gap-2">
                <div class="flex-1">
                  <input
                    id="platform_fee_value"
                    v-model.number="platformFeeValue"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :placeholder="formData.platform_fee_type === 'percentage' ? 'e.g., 0.45' : 'e.g., 50.00'"
                  />
                </div>
                <div class="w-20">
                  <select
                    id="platform_fee_type"
                    v-model="formData.platform_fee_type"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="percentage">%</option>
                    <option value="fixed">£</option>
                  </select>
                </div>
                <div class="w-32">
                  <select
                    id="platform_fee_frequency"
                    v-model="formData.platform_fee_frequency"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="annually">Annually</option>
                  </select>
                </div>
              </div>
              <p class="mt-1 text-xs text-gray-500">
                {{ feeHelpText }}
              </p>
              <!-- High percentage fee warning -->
              <div v-if="feePercentageWarning" class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-md">
                <p class="text-sm text-amber-800">
                  You have entered <strong>{{ formData.platform_fee_percent }}%</strong> as a percentage fee. Did you mean <strong>£{{ formData.platform_fee_percent }}</strong> instead?
                </p>
                <div class="mt-2 flex gap-2">
                  <button type="button" @click="confirmFeeAndSubmit" class="px-3 py-1 text-xs font-medium bg-amber-600 text-white rounded hover:bg-amber-700 transition-colors">
                    Yes, it's {{ formData.platform_fee_percent }}%
                  </button>
                  <button type="button" @click="switchFeeToFixed" class="px-3 py-1 text-xs font-medium border border-amber-600 text-amber-700 rounded hover:bg-amber-100 transition-colors">
                    Change to £
                  </button>
                </div>
              </div>
            </div>

            <!-- Risk Level Section (hidden during onboarding) -->
            <template v-if="!isOnboarding">
              <div v-if="hasRiskProfile" class="pt-4 border-t border-gray-200">
                <RiskLevelSelector
                  v-model="formData.risk_preference"
                  :allowed-levels="allowedRiskLevels"
                  :profile-level="mainRiskLevel"
                  :compact="true"
                  :show-allocation="false"
                  :show-returns="false"
                  :collapsible="true"
                  label="Risk Level for This Account"
                />
                <p class="mt-2 text-xs text-gray-500">
                  Your main risk profile is <strong>{{ mainRiskLevelDisplay }}</strong>.
                  You can adjust this account within one level of your main preference.
                </p>
              </div>
              <div v-else class="pt-4 border-t border-gray-200">
                <div class="bg-gray-50 rounded-md p-3">
                  <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                      <p class="text-sm text-blue-800">
                        <router-link to="/risk-profile" class="font-medium underline hover:text-blue-900">
                          Set your risk profile
                        </router-link>
                        to get personalised risk guidance for your investments.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- ISA-specific fields -->
            <div v-if="isISAType" class="bg-blue-50 border border-blue-200 rounded-md p-4 space-y-4">
              <div class="flex items-start gap-2 mb-3">
                <svg class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <p class="text-sm font-medium text-blue-900">ISA Subscription</p>
                  <p class="text-xs text-blue-700 mt-1">
                    All ISA contributions (Cash ISA + Stocks &amp; Shares ISA) count towards your £20,000 annual allowance (2025/26)
                  </p>
                </div>
              </div>

              <!-- Tax Year Subscription -->
              <div>
                <label for="isa_subscription_current_year" class="block text-sm font-medium text-blue-900 mb-1">
                  Already Subscribed This Tax Year (£)
                </label>
                <input
                  id="isa_subscription_current_year"
                  v-model.number="formData.isa_subscription_current_year"
                  type="number"
                  step="0.01"
                  min="0"
                  max="20000"
                  class="w-full border border-blue-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                  placeholder="0.00"
                />
                <p class="mt-1 text-xs text-blue-700">
                  Amount already contributed to this account for {{ currentTaxYear }} tax year, including {{ paymentsMadeThisTaxYear }} regular payments.
                </p>
              </div>

              <!-- ISA Regular Contribution Amount and Frequency -->
              <div>
                <label for="isa_monthly_contribution_amount" class="block text-sm font-medium text-blue-900 mb-1">
                  Regular Contribution Amount (£)
                </label>
                <div class="flex gap-2">
                  <div class="flex-1">
                    <input
                      id="isa_monthly_contribution_amount"
                      v-model.number="formData.monthly_contribution_amount"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full border border-blue-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                      :class="{ 'border-red-500': errors.isa_contribution_exceeds }"
                      placeholder="0.00"
                    />
                  </div>
                  <div class="w-32">
                    <select
                      id="isa_contribution_frequency"
                      v-model="formData.contribution_frequency"
                      class="w-full border border-blue-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                    >
                      <option value="monthly">Monthly</option>
                      <option value="quarterly">Quarterly</option>
                      <option value="annually">Annually</option>
                    </select>
                  </div>
                </div>
                <p class="mt-1 text-xs text-blue-700">
                  As of {{ todaysDate }}, you have {{ paymentsRemainingThisTaxYear }} remaining for the {{ currentTaxYear }} tax year.
                </p>
              </div>

              <!-- ISA Planned Lump Sum -->
              <div>
                <label for="isa_planned_lump_sum_amount" class="block text-sm font-medium text-blue-900 mb-1">
                  Planned Lump Sum (£)
                </label>
                <div class="flex gap-2">
                  <div class="flex-1">
                    <input
                      id="isa_planned_lump_sum_amount"
                      v-model.number="formData.planned_lump_sum_amount"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full border border-blue-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                      :class="{ 'border-red-500': errors.isa_contribution_exceeds }"
                      placeholder="0.00"
                    />
                  </div>
                  <div class="w-40">
                    <input
                      id="isa_planned_lump_sum_date"
                      v-model="formData.planned_lump_sum_date"
                      type="date"
                      class="w-full border border-blue-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                    />
                  </div>
                </div>
                <p class="mt-1 text-xs text-blue-700">
                  One-off contribution planned for this ISA (counts towards allowance)
                </p>
              </div>

              <!-- ISA Allowance Warning -->
              <div v-if="errors.isa_contribution_exceeds" class="p-3 bg-red-50 border border-red-200 rounded-md">
                <p class="text-sm text-red-800">
                  <strong>Warning:</strong> {{ errors.isa_contribution_exceeds }}
                </p>
              </div>

              <!-- Remaining Allowance Display -->
              <div class="bg-white border border-blue-200 rounded-md p-3">
                <div class="flex justify-between items-center mb-2">
                  <span class="text-sm font-medium text-gray-700">ISA Allowance Usage:</span>
                  <span class="text-lg font-bold" :class="totalRemainingAllowanceClass">
                    {{ formatCurrency(totalRemainingAllowance) }} remaining
                  </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                  <div class="h-full flex rounded-full overflow-hidden">
                    <!-- Cash ISA portion -->
                    <div
                      v-if="cashISAUsed > 0"
                      class="bg-blue-500 h-full"
                      :style="{ width: (cashISAUsed / ISA_ALLOWANCE * 100) + '%' }"
                      :title="`Cash ISA: ${formatCurrency(cashISAUsed)}`"
                    ></div>
                    <!-- S&S ISA portion (existing subscriptions) -->
                    <div
                      v-if="otherStocksISAUsed > 0"
                      class="bg-purple-500 h-full"
                      :style="{ width: (otherStocksISAUsed / ISA_ALLOWANCE * 100) + '%' }"
                      :title="`Other S&S ISAs: ${formatCurrency(otherStocksISAUsed)}`"
                    ></div>
                    <!-- This account's subscription -->
                    <div
                      v-if="thisAccountSubscription > 0"
                      class="bg-green-500 h-full"
                      :style="{ width: (thisAccountSubscription / ISA_ALLOWANCE * 100) + '%' }"
                      :title="`This account: ${formatCurrency(thisAccountSubscription)}`"
                    ></div>
                    <!-- Planned contributions (lighter shade) -->
                    <div
                      v-if="plannedAnnualContribution > 0"
                      class="bg-amber-400 h-full"
                      :style="{ width: Math.min(plannedAnnualContribution / ISA_ALLOWANCE * 100, 100 - totalUsedPercent) + '%' }"
                      :title="`Planned: ${formatCurrency(plannedAnnualContribution)}`"
                    ></div>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                  <div class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                    <span class="text-gray-600">Cash ISA: {{ formatCurrency(cashISAUsed) }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-purple-500"></div>
                    <span class="text-gray-600">Other S&S ISAs: {{ formatCurrency(otherStocksISAUsed) }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-green-500"></div>
                    <span class="text-gray-600">This account: {{ formatCurrency(thisAccountSubscription) }}</span>
                  </div>
                  <div v-if="plannedAnnualContribution > 0" class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-amber-400"></div>
                    <span class="text-gray-600">Planned: {{ formatCurrency(plannedAnnualContribution) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Joint Ownership Section (not shown for ISA or NS&I - they are always individual) -->
            <div v-if="!isISAType && !isNSIType" class="space-y-4 pt-4 border-t border-gray-200">
              <h4 class="text-sm font-semibold text-gray-900">Ownership</h4>

              <!-- Ownership Type -->
              <div>
                <label for="ownership_type" class="block text-sm font-medium text-gray-700 mb-1">
                  Ownership Type
                </label>
                <select
                  id="ownership_type"
                  v-model="formData.ownership_type"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="individual">Individual Owner</option>
                  <option value="joint">Joint Owner</option>
                  <option value="trust">Trust</option>
                </select>
              </div>

              <!-- Joint Owner (if ownership_type is joint) -->
              <div v-if="formData.ownership_type === 'joint'">
                <label for="joint_owner_id" class="block text-sm font-medium text-gray-700 mb-1">
                  Joint Owner
                </label>
                <select
                  id="joint_owner_id"
                  v-model="formData.joint_owner_id"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select joint owner</option>
                  <option v-if="spouse" :value="spouse.id">{{ spouse.name }} (Spouse - Linked Account)</option>
                  <option v-if="!spouse" value="" disabled>No spouse linked - add spouse in Family Members</option>
                </select>
                <p class="text-sm text-gray-500 mt-1">
                  Joint accounts will appear in both your and your spouse's accounts.
                </p>
              </div>

              <!-- Trust (if ownership_type is trust) -->
              <div v-if="formData.ownership_type === 'trust'">
                <label for="trust_id" class="block text-sm font-medium text-gray-700 mb-1">
                  Trust
                </label>
                <select
                  id="trust_id"
                  v-model="formData.trust_id"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select trust</option>
                  <!-- Trust options would be loaded from store/API -->
                </select>
                <p class="text-sm text-gray-500 mt-1">
                  Trust-owned accounts are held for the benefit of trust beneficiaries.
                </p>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Saving...' : (isEditMode ? 'Update Account' : 'Add Account') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import CountrySelector from '@/components/Shared/CountrySelector.vue';
import RiskLevelSelector from '@/components/Shared/RiskLevelSelector.vue';
import riskService from '@/services/riskService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AccountForm',

  mixins: [currencyMixin],

  components: {
    CountrySelector,
    RiskLevelSelector,
  },

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    account: {
      type: Object,
      default: null,
    },
    isOnboarding: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      formData: {
        account_type: '',
        account_type_other: '',
        provider: '',
        platform: '',
        country: 'United Kingdom',
        current_value: null,
        contributions_ytd: null,
        monthly_contribution_amount: null,
        contribution_frequency: 'monthly',
        planned_lump_sum_amount: null,
        planned_lump_sum_date: null,
        platform_fee_percent: null,
        platform_fee_amount: null,
        platform_fee_type: 'percentage',
        platform_fee_frequency: 'annually',
        isa_type: 'stocks_and_shares',
        isa_subscription_current_year: null,
        ownership_type: 'individual',
        joint_owner_id: null,
        trust_id: null,
        risk_preference: null,
      },
      errors: {},
      submitting: false,
      feePercentageWarning: false,
      ISA_ALLOWANCE: 20000, // 2025/26 tax year
      // Risk profile state
      mainRiskLevel: null,
      allowedRiskLevels: ['low', 'lower_medium', 'medium', 'upper_medium', 'high'],
    };
  },

  computed: {
    isEditMode() {
      return !!this.account;
    },

    hasRiskProfile() {
      return !!this.mainRiskLevel;
    },

    mainRiskLevelDisplay() {
      return riskService.getDisplayName(this.mainRiskLevel);
    },

    spouse() {
      return this.$store.getters['userProfile/spouse'];
    },

    isISAType() {
      return this.formData.account_type === 'isa';
    },

    isNSIType() {
      return this.formData.account_type === 'nsi';
    },

    currentTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();

      // UK tax year runs April 6 to April 5
      if (month < 3) { // Jan-March
        return `${year - 1}/${year}`;
      } else {
        return `${year}/${year + 1}`;
      }
    },

    todaysDate() {
      const now = new Date();
      return now.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    },

    // Calculate months elapsed since start of tax year (April 6)
    monthsElapsedInTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth(); // 0-indexed

      // Tax year starts April 6
      // If we're Jan-March, tax year started previous April
      // If we're April-Dec, tax year started this April
      let taxYearStart;
      if (month < 3) { // Jan (0), Feb (1), Mar (2)
        taxYearStart = new Date(year - 1, 3, 6); // April 6 of previous year
      } else {
        taxYearStart = new Date(year, 3, 6); // April 6 of this year
      }

      // Calculate months difference
      const monthsDiff = (now.getFullYear() - taxYearStart.getFullYear()) * 12 +
                         (now.getMonth() - taxYearStart.getMonth());

      return Math.max(0, monthsDiff);
    },

    // Calculate payments made and remaining based on frequency
    paymentsMadeThisTaxYear() {
      const frequency = this.formData.contribution_frequency || 'monthly';
      const monthsElapsed = this.monthsElapsedInTaxYear;

      if (frequency === 'monthly') {
        return monthsElapsed;
      } else if (frequency === 'quarterly') {
        return Math.floor(monthsElapsed / 3);
      } else { // annually
        return monthsElapsed >= 12 ? 1 : 0;
      }
    },

    paymentsRemainingThisTaxYear() {
      const frequency = this.formData.contribution_frequency || 'monthly';
      let paymentsPerYear;

      if (frequency === 'monthly') {
        paymentsPerYear = 12;
      } else if (frequency === 'quarterly') {
        paymentsPerYear = 4;
      } else { // annually
        paymentsPerYear = 1;
      }

      return Math.max(0, paymentsPerYear - this.paymentsMadeThisTaxYear);
    },

    // Calculate remaining contributions for the rest of the tax year
    remainingContributionsForYear() {
      const amount = this.formData.monthly_contribution_amount || 0;
      return this.paymentsRemainingThisTaxYear * amount;
    },

    // Get Cash ISA usage from savings store
    cashISAUsed() {
      return this.$store.getters['savings/currentYearISASubscription'] || 0;
    },

    // Get total S&S ISA usage from investment store
    totalStocksISAUsed() {
      return this.$store.getters['investment/investmentISASubscription'] || 0;
    },

    // Get other S&S ISA usage (excluding this account if editing)
    otherStocksISAUsed() {
      if (!this.isEditMode || !this.account) {
        return this.totalStocksISAUsed;
      }
      // Subtract this account's subscription from total
      const thisAccountOriginal = parseFloat(this.account.isa_subscription_current_year) || 0;
      return Math.max(0, this.totalStocksISAUsed - thisAccountOriginal);
    },

    // This account's subscription amount
    thisAccountSubscription() {
      return this.formData.isa_subscription_current_year || 0;
    },

    // Calculate planned contribution for remainder of tax year (regular + lump sum)
    // Only counts remaining contributions to avoid double-counting with "Already Subscribed"
    plannedAnnualContribution() {
      // Get remaining contributions for the rest of the tax year
      let planned = this.remainingContributionsForYear;

      // Add planned lump sum
      planned += this.formData.planned_lump_sum_amount || 0;

      return planned;
    },

    // Total ISA usage across all ISAs
    totalISAUsed() {
      return this.cashISAUsed + this.otherStocksISAUsed + this.thisAccountSubscription;
    },

    // Total including planned contributions
    totalWithPlanned() {
      return this.totalISAUsed + this.plannedAnnualContribution;
    },

    // Remaining allowance after all usage
    totalRemainingAllowance() {
      return Math.max(0, this.ISA_ALLOWANCE - this.totalWithPlanned);
    },

    // Percentage used (capped at 100)
    totalUsedPercent() {
      return Math.min(100, (this.totalISAUsed / this.ISA_ALLOWANCE) * 100);
    },

    // Class for remaining allowance display
    totalRemainingAllowanceClass() {
      if (this.totalWithPlanned > this.ISA_ALLOWANCE) return 'text-red-600';
      if (this.totalRemainingAllowance < 2000) return 'text-orange-600';
      return 'text-green-600';
    },

    // Legacy computed for backward compatibility
    remainingAllowance() {
      const subscription = this.formData.isa_subscription_current_year || 0;
      return Math.max(0, this.ISA_ALLOWANCE - subscription);
    },

    allowanceUsedPercent() {
      const subscription = this.formData.isa_subscription_current_year || 0;
      return Math.min(100, (subscription / this.ISA_ALLOWANCE) * 100);
    },

    remainingAllowanceClass() {
      if (this.remainingAllowance === 0) return 'text-red-600';
      if (this.remainingAllowance < 2000) return 'text-orange-600';
      return 'text-green-600';
    },

    allowanceBarClass() {
      if (this.allowanceUsedPercent >= 100) return 'bg-red-600';
      if (this.allowanceUsedPercent >= 75) return 'bg-orange-500';
      if (this.allowanceUsedPercent >= 50) return 'bg-yellow-500';
      return 'bg-green-600';
    },

    platformFeeValue: {
      get() {
        return this.formData.platform_fee_type === 'percentage'
          ? this.formData.platform_fee_percent
          : this.formData.platform_fee_amount;
      },
      set(value) {
        if (this.formData.platform_fee_type === 'percentage') {
          this.formData.platform_fee_percent = value;
          this.formData.platform_fee_amount = null;
        } else {
          this.formData.platform_fee_amount = value;
          this.formData.platform_fee_percent = null;
        }
      },
    },

    feeHelpText() {
      const frequency = this.formData.platform_fee_frequency;
      const frequencyText = {
        monthly: 'per month',
        quarterly: 'per quarter',
        annually: 'per year',
      };
      if (this.formData.platform_fee_type === 'percentage') {
        return `Platform fee as a percentage of assets ${frequencyText[frequency]}`;
      }
      return `Fixed platform fee charged ${frequencyText[frequency]}`;
    },
  },

  watch: {
    account: {
      immediate: true,
      handler(newAccount) {
        if (newAccount) {
          this.formData = {
            ...newAccount,
            account_type_other: newAccount.account_type_other || '',
            isa_type: newAccount.isa_type || 'stocks_and_shares',
            // Use isa_subscription_current_year directly (backend stores this field)
            isa_subscription_current_year: newAccount.isa_subscription_current_year || null,
            ownership_type: newAccount.ownership_type || 'individual',
            joint_owner_id: newAccount.joint_owner_id || null,
            trust_id: newAccount.trust_id || null,
            platform_fee_type: newAccount.platform_fee_type || 'percentage',
            platform_fee_frequency: newAccount.platform_fee_frequency || 'annually',
            // Contribution fields
            monthly_contribution_amount: newAccount.monthly_contribution_amount || null,
            contribution_frequency: newAccount.contribution_frequency || 'monthly',
            planned_lump_sum_amount: newAccount.planned_lump_sum_amount || null,
            planned_lump_sum_date: newAccount.planned_lump_sum_date || null,
          };
        } else {
          this.resetForm();
        }
      },
    },
    async show(newVal) {
      if (newVal) {
        // Re-populate form when modal opens (in case it was reset)
        if (this.account) {
          this.formData = {
            ...this.account,
            account_type_other: this.account.account_type_other || '',
            isa_type: this.account.isa_type || 'stocks_and_shares',
            isa_subscription_current_year: this.account.isa_subscription_current_year || null,
            ownership_type: this.account.ownership_type || 'individual',
            joint_owner_id: this.account.joint_owner_id || null,
            trust_id: this.account.trust_id || null,
            risk_preference: this.account.risk_preference || null,
            platform_fee_type: this.account.platform_fee_type || 'percentage',
            platform_fee_frequency: this.account.platform_fee_frequency || 'annually',
            // Contribution fields
            monthly_contribution_amount: this.account.monthly_contribution_amount || null,
            contribution_frequency: this.account.contribution_frequency || 'monthly',
            planned_lump_sum_amount: this.account.planned_lump_sum_amount || null,
            planned_lump_sum_date: this.account.planned_lump_sum_date || null,
          };
        }
        this.errors = {};
        this.submitting = false;

        // Load risk profile when modal opens (auto-calculated if none exists)
        await this.loadRiskProfile();
      } else {
        this.errors = {};
      }
    },
    'formData.account_type'(newType) {
      // Reset ISA-specific fields when account type changes
      if (newType !== 'isa') {
        this.formData.isa_type = 'stocks_and_shares';
        this.formData.isa_subscription_current_year = null;
      } else {
        // ISA can only be owned by an individual
        this.formData.ownership_type = 'individual';
        this.formData.joint_owner_id = null;
        this.formData.trust_id = null;
      }
      // Clear account_type_other when switching away from 'other'
      if (newType !== 'other') {
        this.formData.account_type_other = '';
      }
      // Auto-populate NS&I fields
      if (newType === 'nsi') {
        this.formData.provider = 'NS&I';
        this.formData.platform = 'NS&I';
        // NS&I has no platform fees
        this.formData.platform_fee_percent = null;
        this.formData.platform_fee_amount = null;
        // NS&I is always individual ownership
        this.formData.ownership_type = 'individual';
        this.formData.joint_owner_id = null;
        this.formData.trust_id = null;
      }
    },
    'formData.platform_fee_type'(newType, oldType) {
      if (oldType && newType !== oldType) {
        // Transfer value to the new field type
        if (newType === 'fixed') {
          this.formData.platform_fee_amount = this.formData.platform_fee_percent;
          this.formData.platform_fee_percent = null;
        } else {
          this.formData.platform_fee_percent = this.formData.platform_fee_amount;
          this.formData.platform_fee_amount = null;
        }
        this.feePercentageWarning = false;
      }
    },
    'formData.platform_fee_percent'() {
      this.feePercentageWarning = false;
    },
  },

  methods: {
    async loadRiskProfile() {
      try {
        const [profileResponse, allowedResponse] = await Promise.all([
          riskService.getProfile(),
          riskService.getAllowedLevels(),
        ]);

        if (profileResponse.data?.risk_level) {
          this.mainRiskLevel = profileResponse.data.risk_level;
        }

        if (allowedResponse.data?.allowed_levels) {
          this.allowedRiskLevels = allowedResponse.data.allowed_levels;
        }
      } catch (error) {
        // Silently fail - risk profile is optional
      }
    },

    submitForm() {
      this.errors = {};

      // Client-side validation
      if (!this.validateForm()) {
        return;
      }

      // Check for high percentage fee warning
      if (this.formData.platform_fee_type === 'percentage' &&
          this.formData.platform_fee_percent > 5 &&
          !this.feePercentageWarning) {
        this.feePercentageWarning = true;
        return;
      }

      this.submitting = true;

      // Clean up data before submission
      const submitData = { ...this.formData };

      // For ISA accounts, keep isa_subscription_current_year (backend expects this field)
      if (submitData.account_type === 'isa') {
        // Backend uses isa_subscription_current_year, not contributions_ytd
        // Keep isa_subscription_current_year as is
      } else {
        // Remove ISA fields if not ISA account
        delete submitData.isa_type;
        delete submitData.isa_subscription_current_year;
      }

      // Emit save event - parent will close modal after successful save
      this.$emit('save', submitData);
      this.submitting = false;
    },

    validateForm() {
      let isValid = true;

      if (!this.formData.account_type) {
        this.errors.account_type = 'Account type is required';
        isValid = false;
      }

      // Validate custom account type if 'other' is selected
      if (this.formData.account_type === 'other') {
        if (!this.formData.account_type_other || this.formData.account_type_other.trim().length === 0) {
          this.errors.account_type_other = 'Please specify the account type';
          isValid = false;
        }
      }

      if (!this.formData.provider || this.formData.provider.trim().length === 0) {
        this.errors.provider = 'Provider is required';
        isValid = false;
      }

      if (this.formData.current_value === null || this.formData.current_value < 0) {
        this.errors.current_value = 'Current value is required and must be 0 or greater';
        isValid = false;
      }

      // Platform fee validation
      if (this.formData.platform_fee_type === 'percentage') {
        if (this.formData.platform_fee_percent !== null && this.formData.platform_fee_percent < 0) {
          this.errors.platform_fee_value = 'Platform fee cannot be negative';
          isValid = false;
        }
      } else if (this.formData.platform_fee_type === 'fixed') {
        if (this.formData.platform_fee_amount !== null && this.formData.platform_fee_amount < 0) {
          this.errors.platform_fee_value = 'Platform fee cannot be negative';
          isValid = false;
        }
      }

      // ISA-specific validation
      if (this.isISAType) {
        if (this.formData.isa_subscription_current_year && this.formData.isa_subscription_current_year < 0) {
          this.errors.isa_subscription_current_year = 'Subscription amount cannot be negative';
          isValid = false;
        }

        // Check if total ISA usage exceeds allowance
        if (this.totalWithPlanned > this.ISA_ALLOWANCE) {
          const excess = this.totalWithPlanned - this.ISA_ALLOWANCE;
          this.errors.isa_contribution_exceeds = `Your planned ISA contributions would exceed the £20,000 allowance by ${this.formatCurrency(excess)}. Consider reducing your regular contributions or lump sum.`;
          isValid = false;
        }
      }

      return isValid;
    },

    confirmFeeAndSubmit() {
      this.submitForm();
    },

    switchFeeToFixed() {
      this.formData.platform_fee_type = 'fixed';
      this.feePercentageWarning = false;
    },

    closeModal() {
      this.$emit('close');
      this.resetForm();
    },

    resetForm() {
      this.formData = {
        account_type: '',
        account_type_other: '',
        provider: '',
        platform: '',
        country: 'United Kingdom',
        current_value: null,
        contributions_ytd: null,
        monthly_contribution_amount: null,
        contribution_frequency: 'monthly',
        planned_lump_sum_amount: null,
        planned_lump_sum_date: null,
        platform_fee_percent: null,
        platform_fee_amount: null,
        platform_fee_type: 'percentage',
        platform_fee_frequency: 'annually',
        isa_type: 'stocks_and_shares',
        isa_subscription_current_year: null,
        ownership_type: 'individual',
        joint_owner_id: null,
        trust_id: null,
        risk_preference: null,
      };
      this.errors = {};
      this.feePercentageWarning = false;
    },
  },
};
</script>
