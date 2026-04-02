<template>
  <div class="space-y-4">
    <!-- Provider -->
    <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'provider' }">
      <label for="provider" class="block text-sm font-medium text-neutral-500 mb-1">
        Provider
      </label>
      <input
        id="provider"
        v-model="localData.provider"
        type="text"
        class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
        :class="{ 'border-raspberry-500': errors.provider }"
        placeholder="e.g., Vanguard, Hargreaves Lansdown, Interactive Investor"
      />
      <p v-if="errors.provider" class="mt-1 text-sm text-raspberry-600">{{ errors.provider }}</p>
    </div>

    <!-- Country Selector -->
    <div>
      <CountrySelector
        v-model="localData.country"
        label="Country"
        :required="true"
        default-country="United Kingdom"
      />
    </div>

    <!-- Platform -->
    <div>
      <label for="platform" class="block text-sm font-medium text-neutral-500 mb-1">
        Platform/Product Name
      </label>
      <input
        id="platform"
        v-model="localData.platform"
        type="text"
        class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
        placeholder="e.g., Investment Account, ISA"
      />
      <p class="mt-1 text-xs text-neutral-500">Optional: Specific platform or product name</p>
    </div>

    <!-- Current Value -->
    <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'current_value' }">
      <label for="current_value" class="block text-sm font-medium text-neutral-500 mb-1">
        Current Value (£)
      </label>
      <input
        id="current_value"
        v-model.number="localData.current_value"
        type="number"
        step="0.01"
        min="0"
        class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
        :class="{ 'border-raspberry-500': errors.current_value }"
        placeholder="0.00"
      />
      <p v-if="errors.current_value" class="mt-1 text-sm text-raspberry-600">{{ errors.current_value }}</p>
      <p class="mt-1 text-xs text-neutral-500">Current total value of the account</p>
    </div>

    <!-- Bond-specific fields (onshore/offshore bonds) -->
    <div v-if="isBondType" class="space-y-4 pt-4 border-t border-light-gray">
      <h4 class="text-sm font-semibold text-horizon-500">Bond Details</h4>

      <!-- Bond Purchase Date -->
      <div>
        <label for="bond_purchase_date" class="block text-sm font-medium text-neutral-500 mb-1">
          Bond Purchase Date
        </label>
        <input
          id="bond_purchase_date"
          v-model="localData.bond_purchase_date"
          type="date"
          class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
        />
        <p class="mt-1 text-xs text-neutral-500">
          The date you purchased this bond (used to calculate 5% withdrawal allowance)
        </p>
      </div>

      <!-- 5% Withdrawal Taken -->
      <div>
        <label for="bond_withdrawal_taken" class="block text-sm font-medium text-neutral-500 mb-1">
          5% Withdrawal Already Taken (£)
        </label>
        <input
          id="bond_withdrawal_taken"
          v-model.number="localData.bond_withdrawal_taken"
          type="number"
          step="0.01"
          min="0"
          class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
          placeholder="0.00"
        />
        <p class="mt-1 text-xs text-neutral-500">
          Total amount of tax-deferred 5% annual withdrawals you have taken to date
        </p>
      </div>

      <!-- 5% Withdrawal Info Box -->
      <div class="bg-violet-50 border border-violet-200 rounded-md p-3">
        <div class="flex items-start gap-2">
          <svg class="w-5 h-5 text-violet-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="text-sm text-violet-800">
            <p class="font-medium">5% Tax-Deferred Withdrawals</p>
            <p class="mt-1">
              You can withdraw up to 5% of your original investment each year without triggering a chargeable event.
              Unused allowance can be carried forward to future years.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Contributions Section (only for non-ISA, non-VCT/EIS accounts) -->
    <div v-if="!isISAType && !isTaxReliefType" class="space-y-4 pt-4 border-t border-light-gray">
      <h4 class="text-sm font-semibold text-horizon-500">Regular Contributions</h4>

      <!-- Monthly Contribution Amount and Frequency -->
      <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'monthly_contribution_amount' }">
        <label for="monthly_contribution_amount" class="block text-sm font-medium text-neutral-500 mb-1">
          Regular Contribution Amount (£)
        </label>
        <div class="flex gap-2">
          <div class="flex-1">
            <input
              id="monthly_contribution_amount"
              v-model.number="localData.monthly_contribution_amount"
              type="number"
              step="0.01"
              min="0"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="0.00"
            />
          </div>
          <div class="w-32">
            <select
              id="contribution_frequency"
              v-model="localData.contribution_frequency"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="annually">Annually</option>
            </select>
          </div>
        </div>
        <p class="mt-1 text-xs text-neutral-500">
          Regular contributions you make to this account
        </p>
      </div>

      <!-- Planned Lump Sum -->
      <div>
        <label for="planned_lump_sum_amount" class="block text-sm font-medium text-neutral-500 mb-1">
          Planned Lump Sum (£)
        </label>
        <div class="flex gap-2">
          <div class="flex-1">
            <input
              id="planned_lump_sum_amount"
              v-model.number="localData.planned_lump_sum_amount"
              type="number"
              step="0.01"
              min="0"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="0.00"
            />
          </div>
          <div class="w-40">
            <input
              id="planned_lump_sum_date"
              v-model="localData.planned_lump_sum_date"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
        </div>
        <p class="mt-1 text-xs text-neutral-500">
          One-off contribution planned for this account (optional)
        </p>
      </div>
    </div>

    <!-- Platform Fee Section (not shown for NS&I) -->
    <div v-if="!isNSIType" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'platform_fee_percent' }">
      <label class="block text-sm font-medium text-neutral-500 mb-1">
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
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            :placeholder="localData.platform_fee_type === 'percentage' ? 'e.g., 0.45' : 'e.g., 50.00'"
          />
        </div>
        <div class="w-20">
          <select
            id="platform_fee_type"
            v-model="localData.platform_fee_type"
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
          >
            <option value="percentage">%</option>
            <option value="fixed">£</option>
          </select>
        </div>
        <div class="w-32">
          <select
            id="platform_fee_frequency"
            v-model="localData.platform_fee_frequency"
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
          >
            <option value="monthly">Monthly</option>
            <option value="quarterly">Quarterly</option>
            <option value="annually">Annually</option>
          </select>
        </div>
      </div>
      <p class="mt-1 text-xs text-neutral-500">
        {{ feeHelpText }}
      </p>
      <!-- High percentage fee warning -->
      <div v-if="feePercentageWarning" class="mt-2 p-3 bg-violet-50 border border-violet-200 rounded-md">
        <p class="text-sm text-violet-800">
          You have entered <strong>{{ localData.platform_fee_percent }}%</strong> as a percentage fee. Did you mean <strong>£{{ localData.platform_fee_percent }}</strong> instead?
        </p>
        <div class="mt-2 flex gap-2">
          <button type="button" @click="$emit('confirm-fee')" class="px-3 py-1 text-xs font-medium bg-raspberry-500 text-white rounded hover:bg-raspberry-600 transition-colors">
            Yes, it's {{ localData.platform_fee_percent }}%
          </button>
          <button type="button" @click="switchFeeToFixed" class="px-3 py-1 text-xs font-medium border border-violet-600 text-violet-700 rounded hover:bg-violet-100 transition-colors">
            Change to £
          </button>
        </div>
      </div>
    </div>

    <!-- Risk Level Section (hidden during onboarding) -->
    <template v-if="!isOnboarding">
      <div v-if="hasRiskProfile" class="pt-4 border-t border-light-gray">
        <RiskLevelSelector
          v-model="localData.risk_preference"
          :allowed-levels="allowedRiskLevels"
          :profile-level="mainRiskLevel"
          :compact="true"
          :show-allocation="false"
          :show-returns="false"
          :collapsible="true"
          label="Risk Level for This Account"
        />
        <p class="mt-2 text-xs text-neutral-500">
          Your main risk profile is <strong>{{ mainRiskLevelDisplay }}</strong>.
          You can choose a different risk level for this account if needed.
        </p>
      </div>
      <div v-else class="pt-4 border-t border-light-gray">
        <div class="bg-eggshell-500 rounded-md p-3">
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-violet-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <p class="text-sm text-violet-800">
                <router-link to="/risk-profile" class="font-medium underline hover:text-violet-900">
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
    <div v-if="isISAType" class="bg-violet-50 border border-violet-200 rounded-md p-4 space-y-4">
      <div class="flex items-start gap-2 mb-3">
        <svg class="h-5 w-5 text-violet-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="text-sm font-medium text-violet-900">ISA Subscription</p>
          <p class="text-xs text-violet-700 mt-1">
            All ISA contributions (Cash ISA + Stocks &amp; Shares ISA) count towards your £20,000 annual allowance ({{ currentTaxYear }})
          </p>
        </div>
      </div>

      <!-- Tax Year Subscription -->
      <div>
        <label for="isa_subscription_current_year" class="block text-sm font-medium text-violet-900 mb-1">
          Already Subscribed This Tax Year (£)
        </label>
        <input
          id="isa_subscription_current_year"
          v-model.number="localData.isa_subscription_current_year"
          type="number"
          step="0.01"
          min="0"
          :max="ISA_ALLOWANCE"
          class="w-full border border-violet-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
          placeholder="0.00"
        />
        <p class="mt-1 text-xs text-violet-700">
          Amount already contributed to this account for {{ currentTaxYear }} tax year, including {{ paymentsMadeThisTaxYear }} regular payments.
        </p>
      </div>

      <!-- ISA Regular Contribution Amount and Frequency -->
      <div>
        <label for="isa_monthly_contribution_amount" class="block text-sm font-medium text-violet-900 mb-1">
          Regular Contribution Amount (£)
        </label>
        <div class="flex gap-2">
          <div class="flex-1">
            <input
              id="isa_monthly_contribution_amount"
              v-model.number="localData.monthly_contribution_amount"
              type="number"
              step="0.01"
              min="0"
              class="w-full border border-violet-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
              :class="{ 'border-raspberry-500': errors.isa_contribution_exceeds }"
              placeholder="0.00"
            />
          </div>
          <div class="w-32">
            <select
              id="isa_contribution_frequency"
              v-model="localData.contribution_frequency"
              class="w-full border border-violet-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
            >
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="annually">Annually</option>
            </select>
          </div>
        </div>
        <p class="mt-1 text-xs text-violet-700">
          As of {{ todaysDate }}, you have {{ paymentsRemainingThisTaxYear }} contributions remaining for the {{ currentTaxYear }} tax year.
        </p>
      </div>

      <!-- ISA Planned Lump Sum -->
      <div>
        <label for="isa_planned_lump_sum_amount" class="block text-sm font-medium text-violet-900 mb-1">
          Planned Lump Sum (£)
        </label>
        <div class="flex gap-2">
          <div class="flex-1">
            <input
              id="isa_planned_lump_sum_amount"
              v-model.number="localData.planned_lump_sum_amount"
              type="number"
              step="0.01"
              min="0"
              class="w-full border border-violet-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
              :class="{ 'border-raspberry-500': errors.isa_contribution_exceeds }"
              placeholder="0.00"
            />
          </div>
          <div class="w-40">
            <input
              id="isa_planned_lump_sum_date"
              v-model="localData.planned_lump_sum_date"
              type="date"
              class="w-full border border-violet-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
            />
          </div>
        </div>
        <p class="mt-1 text-xs text-violet-700">
          One-off contribution planned for this ISA (counts towards allowance)
        </p>
      </div>

      <!-- ISA Allowance Warning -->
      <div v-if="errors.isa_contribution_exceeds" class="p-3 bg-raspberry-50 border border-raspberry-200 rounded-md">
        <p class="text-sm text-raspberry-800">
          <strong>Warning:</strong> {{ errors.isa_contribution_exceeds }}
        </p>
      </div>

      <!-- Remaining Allowance Display -->
      <div class="bg-white border border-violet-200 rounded-md p-3">
        <div class="flex justify-between items-center mb-2">
          <span class="text-sm font-medium text-neutral-500">ISA Allowance Usage:</span>
          <span class="text-lg font-bold" :class="totalRemainingAllowanceClass">
            {{ formatCurrency(totalRemainingAllowance) }} remaining
          </span>
        </div>
        <div class="w-full bg-savannah-200 rounded-full h-3 mb-2">
          <div class="h-full flex rounded-full overflow-hidden">
            <!-- Cash ISA portion -->
            <div
              v-if="cashISAUsed > 0"
              class="bg-violet-500 h-full"
              :style="{ width: (cashISAUsed / ISA_ALLOWANCE * 100) + '%' }"
              :title="`Cash ISA: ${formatCurrency(cashISAUsed)}`"
            ></div>
            <!-- S&S ISA portion (existing subscriptions) -->
            <div
              v-if="otherStocksISAUsed > 0"
              class="bg-violet-500 h-full"
              :style="{ width: (otherStocksISAUsed / ISA_ALLOWANCE * 100) + '%' }"
              :title="`Other Stocks & Shares ISAs: ${formatCurrency(otherStocksISAUsed)}`"
            ></div>
            <!-- This account's subscription -->
            <div
              v-if="thisAccountSubscription > 0"
              class="bg-spring-500 h-full"
              :style="{ width: (thisAccountSubscription / ISA_ALLOWANCE * 100) + '%' }"
              :title="`This account: ${formatCurrency(thisAccountSubscription)}`"
            ></div>
            <!-- Planned contributions (lighter shade) -->
            <div
              v-if="plannedAnnualContribution > 0"
              class="bg-violet-400 h-full"
              :style="{ width: Math.min(plannedAnnualContribution / ISA_ALLOWANCE * 100, 100 - totalUsedPercent) + '%' }"
              :title="`Planned: ${formatCurrency(plannedAnnualContribution)}`"
            ></div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2 text-xs">
          <div class="flex items-center gap-1">
            <div class="w-2 h-2 rounded-full bg-violet-500"></div>
            <span class="text-neutral-500">Cash ISA: {{ formatCurrency(cashISAUsed) }}</span>
          </div>
          <div class="flex items-center gap-1">
            <div class="w-2 h-2 rounded-full bg-violet-500"></div>
            <span class="text-neutral-500">Other Stocks & Shares ISAs: {{ formatCurrency(otherStocksISAUsed) }}</span>
          </div>
          <div class="flex items-center gap-1">
            <div class="w-2 h-2 rounded-full bg-spring-500"></div>
            <span class="text-neutral-500">This account: {{ formatCurrency(thisAccountSubscription) }}</span>
          </div>
          <div v-if="plannedAnnualContribution > 0" class="flex items-center gap-1">
            <div class="w-2 h-2 rounded-full bg-violet-400"></div>
            <span class="text-neutral-500">Planned: {{ formatCurrency(plannedAnnualContribution) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Joint Ownership Section (not shown for ISA or NS&I - they are always individual) -->
    <div v-if="!isISAType && !isNSIType" class="space-y-4 pt-4 border-t border-light-gray">
      <h4 class="text-sm font-semibold text-horizon-500">Ownership</h4>

      <!-- Ownership Type -->
      <div>
        <label for="ownership_type" class="block text-sm font-medium text-neutral-500 mb-1">
          Ownership Type
        </label>
        <select
          id="ownership_type"
          v-model="localData.ownership_type"
          class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
        >
          <option value="individual">Individual Owner</option>
          <option value="joint">Joint Owner</option>
          <option value="trust">Trust</option>
        </select>
      </div>

      <!-- Joint Owner (if ownership_type is joint) -->
      <div v-if="localData.ownership_type === 'joint'">
        <label for="joint_owner_id" class="block text-sm font-medium text-neutral-500 mb-1">
          Joint Owner
        </label>
        <select
          id="joint_owner_id"
          v-model="localData.joint_owner_id"
          class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
        >
          <option value="">Select joint owner</option>
          <option v-if="spouse" :value="spouse.id">{{ spouse.name }} (Spouse - Linked Account)</option>
          <option v-if="!spouse" value="" disabled>No spouse linked - add spouse in Family Members</option>
        </select>
        <p class="text-sm text-neutral-500 mt-1">
          Joint accounts will appear in both your and your spouse's accounts.
        </p>
      </div>

      <!-- Trust (if ownership_type is trust) -->
      <div v-if="localData.ownership_type === 'trust'">
        <label for="trust_id" class="block text-sm font-medium text-neutral-500 mb-1">
          Trust
        </label>
        <select
          id="trust_id"
          v-model="localData.trust_id"
          class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
        >
          <option value="">Select trust</option>
          <!-- Trust options would be loaded from store/API -->
        </select>
        <p class="text-sm text-neutral-500 mt-1">
          Trust-owned accounts are held for the benefit of trust beneficiaries.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import CountrySelector from '@/components/Shared/CountrySelector.vue';
import RiskLevelSelector from '@/components/Shared/RiskLevelSelector.vue';
import riskService from '@/services/riskService';
import { currencyMixin } from '@/mixins/currencyMixin';
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import { ISA_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

export default {
  name: 'StandardInvestmentFields',

  mixins: [currencyMixin],

  components: {
    CountrySelector,
    RiskLevelSelector,
  },

  props: {
    modelValue: {
      type: Object,
      required: true,
    },
    errors: {
      type: Object,
      default: () => ({}),
    },
    accountType: {
      type: String,
      required: true,
    },
    isOnboarding: {
      type: Boolean,
      default: false,
    },
    riskLevels: {
      type: Array,
      default: () => ['low', 'lower_medium', 'medium', 'upper_medium', 'high'],
    },
    mainRiskLevel: {
      type: String,
      default: null,
    },
    feePercentageWarning: {
      type: Boolean,
      default: false,
    },
    cashISAUsed: {
      type: Number,
      default: 0,
    },
    totalStocksISAUsed: {
      type: Number,
      default: 0,
    },
    account: {
      type: Object,
      default: null,
    },
    highlightedField: {
      type: String,
      default: null,
    },
  },

  emits: ['update:modelValue', 'confirm-fee', 'switch-fee-to-fixed'],

  data() {
    return {
      ISA_ALLOWANCE: ISA_ANNUAL_ALLOWANCE, // Fallback from taxConfig.js; prefer API value
    };
  },

  computed: {
    localData: {
      get() {
        return this.modelValue;
      },
      set(value) {
        this.$emit('update:modelValue', value);
      },
    },

    spouse() {
      return this.$store.getters['userProfile/spouse'];
    },

    isISAType() {
      return this.accountType === 'isa';
    },

    isNSIType() {
      return this.accountType === 'nsi';
    },

    isBondType() {
      return ['onshore_bond', 'offshore_bond'].includes(this.accountType);
    },

    isTaxReliefType() {
      return ['vct', 'eis'].includes(this.accountType);
    },

    hasRiskProfile() {
      return !!this.mainRiskLevel;
    },

    allowedRiskLevels() {
      return this.riskLevels;
    },

    mainRiskLevelDisplay() {
      return riskService.getDisplayName(this.mainRiskLevel);
    },

    platformFeeValue: {
      get() {
        return this.localData.platform_fee_type === 'percentage'
          ? this.localData.platform_fee_percent
          : this.localData.platform_fee_amount;
      },
      set(value) {
        const updatedData = { ...this.localData };
        if (this.localData.platform_fee_type === 'percentage') {
          updatedData.platform_fee_percent = value;
          updatedData.platform_fee_amount = null;
        } else {
          updatedData.platform_fee_amount = value;
          updatedData.platform_fee_percent = null;
        }
        this.$emit('update:modelValue', updatedData);
      },
    },

    feeHelpText() {
      const frequency = this.localData.platform_fee_frequency;
      const frequencyText = {
        monthly: 'per month',
        quarterly: 'per quarter',
        annually: 'per year',
      };
      if (this.localData.platform_fee_type === 'percentage') {
        return `Platform fee as a percentage of assets ${frequencyText[frequency]}`;
      }
      return `Fixed platform fee charged ${frequencyText[frequency]}`;
    },

    // ISA Allowance Tracking
    currentTaxYear() {
      return getCurrentTaxYear();
    },

    todaysDate() {
      const now = new Date();
      return now.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    },

    monthsElapsedInTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();

      let taxYearStart;
      if (month < 3) {
        taxYearStart = new Date(year - 1, 3, 6);
      } else {
        taxYearStart = new Date(year, 3, 6);
      }

      const monthsDiff = (now.getFullYear() - taxYearStart.getFullYear()) * 12 +
                         (now.getMonth() - taxYearStart.getMonth());

      return Math.max(0, monthsDiff);
    },

    paymentsMadeThisTaxYear() {
      const frequency = this.localData.contribution_frequency || 'monthly';
      const monthsElapsed = this.monthsElapsedInTaxYear;

      if (frequency === 'monthly') {
        return monthsElapsed;
      } else if (frequency === 'quarterly') {
        return Math.floor(monthsElapsed / 3);
      } else {
        return monthsElapsed >= 12 ? 1 : 0;
      }
    },

    paymentsRemainingThisTaxYear() {
      const frequency = this.localData.contribution_frequency || 'monthly';
      let paymentsPerYear;

      if (frequency === 'monthly') {
        paymentsPerYear = 12;
      } else if (frequency === 'quarterly') {
        paymentsPerYear = 4;
      } else {
        paymentsPerYear = 1;
      }

      return Math.max(0, paymentsPerYear - this.paymentsMadeThisTaxYear);
    },

    remainingContributionsForYear() {
      const amount = this.localData.monthly_contribution_amount || 0;
      return this.paymentsRemainingThisTaxYear * amount;
    },

    otherStocksISAUsed() {
      if (!this.account) {
        return this.totalStocksISAUsed;
      }
      const thisAccountOriginal = parseFloat(this.account.isa_subscription_current_year) || 0;
      return Math.max(0, this.totalStocksISAUsed - thisAccountOriginal);
    },

    thisAccountSubscription() {
      return this.localData.isa_subscription_current_year || 0;
    },

    plannedAnnualContribution() {
      let planned = this.remainingContributionsForYear;
      planned += this.localData.planned_lump_sum_amount || 0;
      return planned;
    },

    totalISAUsed() {
      return this.cashISAUsed + this.otherStocksISAUsed + this.thisAccountSubscription;
    },

    totalWithPlanned() {
      return this.totalISAUsed + this.plannedAnnualContribution;
    },

    totalRemainingAllowance() {
      return Math.max(0, this.ISA_ALLOWANCE - this.totalWithPlanned);
    },

    totalUsedPercent() {
      return Math.min(100, (this.totalISAUsed / this.ISA_ALLOWANCE) * 100);
    },

    totalRemainingAllowanceClass() {
      if (this.totalWithPlanned > this.ISA_ALLOWANCE) return 'text-raspberry-600';
      if (this.totalRemainingAllowance < 2000) return 'text-violet-600';
      return 'text-spring-600';
    },
  },

  methods: {
    switchFeeToFixed() {
      this.$emit('switch-fee-to-fixed');
    },
  },
};
</script>
