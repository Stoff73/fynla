<template>
  <!-- Onboarding: inline form, no modal. Regular: full modal wrapper. -->
  <div v-if="context === 'onboarding' || show" :class="context === 'onboarding' ? '' : 'fixed inset-0 z-50 overflow-y-auto'">
    <div :class="context === 'onboarding' ? '' : 'flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0'">
      <!-- Background overlay (modal only) -->
      <div v-if="context !== 'onboarding'" class="fixed inset-0 transition-opacity bg-horizon-500 bg-opacity-75"></div>

      <!-- Modal panel / inline container -->
      <div :class="context === 'onboarding' ? '' : 'inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-[90vh] overflow-y-auto'">
        <!-- Header -->
        <div :class="context === 'onboarding' ? 'mb-4' : 'bg-white px-6 py-4 border-b border-light-gray'">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-horizon-500">
              {{ isEditMode ? 'Edit Investment Account' : 'Add New Investment Account' }}
            </h3>
            <button
              v-if="context !== 'onboarding'"
              @click="closeModal"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
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
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'account_type' }">
              <label for="account_type" class="block text-sm font-medium text-neutral-500 mb-1">
                Account Type
              </label>
              <select
                id="account_type"
                v-model="formData.account_type"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                :class="{ 'border-raspberry-500': errors.account_type }"
              >
                <option value="">Select account type</option>
                <option value="isa">ISA (Stocks & Shares)</option>
                <option value="gia">General Investment Account</option>
                <option value="onshore_bond">Onshore Bond</option>
                <option value="offshore_bond">Offshore Bond</option>
                <option value="vct">Venture Capital Trust (VCT)</option>
                <option value="eis">Enterprise Investment Scheme (EIS)</option>
                <option value="private_company">Private Company</option>
                <option value="crowdfunding">Crowdfunding Investment</option>
                <option value="saye">SAYE / Sharesave</option>
                <option value="csop">CSOP (Company Share Option Plan)</option>
                <option value="emi">EMI (Enterprise Management Incentives)</option>
                <option value="unapproved_options">Unapproved Share Options</option>
                <option value="rsu">RSUs (Restricted Stock Units)</option>
                <option value="other">Other</option>
              </select>
              <p v-if="errors.account_type" class="mt-1 text-sm text-raspberry-600">{{ errors.account_type }}</p>
            </div>

            <!-- Custom Account Type (if 'other' selected) -->
            <div v-if="formData.account_type === 'other'">
              <label for="account_type_other" class="block text-sm font-medium text-neutral-500 mb-1">
                Specify Account Type
              </label>
              <input
                id="account_type_other"
                v-model="formData.account_type_other"
                type="text"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                :class="{ 'border-raspberry-500': errors.account_type_other }"
                placeholder="e.g., Gold, Cryptocurrency, Classic Cars, Art Collection"
              />
              <p v-if="errors.account_type_other" class="mt-1 text-sm text-raspberry-600">{{ errors.account_type_other }}</p>
              <p class="mt-1 text-xs text-neutral-500">Enter the custom asset class for this investment</p>
            </div>

            <!-- Private Company / Crowdfunding Fields -->
            <PrivateInvestmentFields
              v-if="isPrivateInvestmentType"
              v-model="formData"
              :errors="errors"
              :is-crowdfunding="isCrowdfundingType"
            />

            <!-- Employee Share Scheme Fields -->
            <EmployeeShareSchemeFields
              v-if="isEmployeeShareScheme"
              v-model="formData"
              :errors="errors"
              :scheme-type="formData.account_type"
            />

            <!-- Standard Investment Fields -->
            <StandardInvestmentFields
              v-if="!isPrivateInvestmentType && !isEmployeeShareScheme"
              v-model="formData"
              :errors="errors"
              :account-type="formData.account_type"
              :is-onboarding="isOnboarding"
              :risk-levels="allowedRiskLevels"
              :main-risk-level="mainRiskLevel"
              :fee-percentage-warning="feePercentageWarning"
              :cash-isa-used="cashISAUsed"
              :total-stocks-isa-used="totalStocksISAUsed"
              :account="account"
              :highlighted-field="highlightedField"
              @confirm-fee="confirmFeeAndSubmit"
              @switch-fee-to-fixed="switchFeeToFixed"
            />

            <!-- Inline Holdings Editor (for eligible account types with value entered) -->
            <!-- Placed outside field component conditionals because VCT/EIS are
                 classified as isPrivateInvestmentType but still support holdings -->
            <InlineHoldingsEditor
              v-if="showHoldingsEditor"
              :account-value="parseFloat(formData.current_value) || 0"
              :holdings="formData.holdings"
              :account-id="account?.id || null"
              @update:holdings="formData.holdings = $event"
              @open-holding-details="openHoldingDetails"
            />

          </div>

          <!-- Footer -->
          <div :class="context === 'onboarding' ? 'mt-6 flex justify-end gap-3' : 'bg-eggshell-500 px-6 py-4 flex justify-end gap-3'">
            <button
              type="button"
              @click="closeModal"
              :class="context === 'onboarding'
                ? 'px-4 py-2 bg-light-pink-100 hover:bg-light-pink-200 text-horizon-500 rounded-lg transition-colors text-sm font-medium'
                : 'px-4 py-2 border border-horizon-300 rounded-md text-sm font-medium text-neutral-500 hover:bg-savannah-100 transition-colors'"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-button text-sm font-medium hover:bg-raspberry-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Saving...' : (context === 'onboarding' ? 'Save' : (isEditMode ? 'Update Account' : 'Add Account')) }}
            </button>
          </div>
        </form>

        <!-- Holding Detail Modal (opened from InlineHoldingsEditor "Details" link) -->
        <HoldingForm
          v-if="showHoldingDetailModal"
          :show="showHoldingDetailModal"
          :holding="editingHoldingDetail"
          :accounts="account ? [account] : []"
          :default-account-id="account?.id"
          @close="showHoldingDetailModal = false; editingHoldingDetail = null"
          @save="handleHoldingDetailSave"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import PrivateInvestmentFields from './PrivateInvestmentFields.vue';
import EmployeeShareSchemeFields from './EmployeeShareSchemeFields.vue';
import StandardInvestmentFields from './StandardInvestmentFields.vue';
import InlineHoldingsEditor from './InlineHoldingsEditor.vue';
import HoldingForm from './HoldingForm.vue';
import riskService from '@/services/riskService';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ISA_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';
import logger from '@/utils/logger';

const HOLDABLE_ACCOUNT_TYPES = ['isa', 'gia', 'onshore_bond', 'offshore_bond', 'vct', 'eis'];

export default {
  name: 'AccountForm',

  emits: ['save', 'close'],

  mixins: [currencyMixin],

  components: {
    PrivateInvestmentFields,
    EmployeeShareSchemeFields,
    StandardInvestmentFields,
    InlineHoldingsEditor,
    HoldingForm,
  },

  props: {
    show: {
      type: Boolean,
      default: true,
    },
    account: {
      type: Object,
      default: null,
    },
    isOnboarding: {
      type: Boolean,
      default: false,
    },
    context: {
      type: String,
      default: 'standalone',
      validator: (value) => ['standalone', 'onboarding'].includes(value),
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
        // Inline holdings
        holdings: [],
        // Bond-specific fields (onshore/offshore bonds)
        bond_purchase_date: null,
        bond_withdrawal_taken: null,
        // Private Company / Crowdfunding fields
        company_legal_name: '',
        company_registration_number: '',
        company_country: 'United Kingdom',
        company_website: '',
        company_trading_name: '',
        crowdfunding_platform: '',
        investment_date: null,
        investment_amount: null,
        investment_currency: 'GBP',
        funding_round: '',
        pre_money_valuation: null,
        post_money_valuation: null,
        price_per_share: null,
        number_of_shares: null,
        instrument_type: '',
        share_class: '',
        liquidation_preference: '',
        has_anti_dilution: false,
        holding_structure: 'direct',
        nominee_name: '',
        conversion_terms: '',
        interest_rate: null,
        maturity_date: null,
        tax_relief_type: '',
        eis3_certificate_number: '',
        hmrc_reference: '',
        relief_claimed_date: null,
        relief_amount_claimed: null,
        clawback_risk: false,
        clawback_notes: '',
        latest_valuation: null,
        latest_valuation_date: null,
        current_ownership_percent: null,
        company_status: 'active',
        status_notes: '',
        exit_type: '',
        exit_date: null,
        exit_gross_proceeds: null,
        exit_fees: null,
        exit_net_proceeds: null,
        exit_moic: null,
        loss_relief_eligible: false,
        capital_loss_amount: null,
        negligible_value_claim: false,
        // Business Asset Disposal Relief (BADR) fields
        badr_eligible: false,
        badr_is_employee: false,
        badr_trading_company: false,
        badr_5_percent_holding: false,
        badr_held_2_years: false,
        badr_emi_shares: false,
        badr_lifetime_used: null,
        // Employee Share Scheme fields
        // Group 1: Employer Details
        employer_name: '',
        employer_registration: '',
        employer_ticker: '',
        employer_is_listed: false,
        parent_company_name: '',
        parent_company_country: '',
        ers_scheme_reference: '',
        ers_registered: false,
        // Group 2: Grant Details
        grant_date: null,
        grant_reference: '',
        units_granted: null,
        exercise_price: null,
        market_value_at_grant: null,
        share_class_scheme: '',
        grant_currency: 'GBP',
        option_price_paid: null,
        scheme_start_date: null,
        scheme_duration_months: null,
        // Group 3: Vesting Schedule
        vesting_type: '',
        cliff_date: null,
        cliff_percentage: null,
        vesting_period_months: null,
        vesting_frequency_months: null,
        has_performance_conditions: false,
        performance_conditions_description: '',
        performance_period_end: null,
        performance_vesting_min_percent: null,
        performance_vesting_max_percent: null,
        full_vest_date: null,
        accelerated_vesting_allowed: false,
        // Group 4: Current Status
        units_vested: 0,
        units_unvested: 0,
        units_exercised: 0,
        units_forfeited: 0,
        units_expired: 0,
        scheme_status: 'active',
        current_share_price: null,
        share_price_date: null,
        // Group 5: Exercise & Expiry
        exercise_window_start: null,
        exercise_window_end: null,
        last_exercise_date: null,
        total_exercise_proceeds: null,
        total_exercise_cost: null,
        // Group 6: Tax Treatment
        tax_treatment: '',
        is_readily_convertible_asset: null,
        paye_via_payroll: true,
        income_tax_at_vest_exercise: null,
        ni_at_vest_exercise: null,
        csop_disqualifying_event: false,
        csop_three_year_date: null,
        cost_basis_for_cgt: null,
        // Group 7: SAYE-Specific
        saye_monthly_savings: null,
        saye_current_savings_balance: null,
        saye_maturity_date: null,
        saye_option_discount_percent: null,
        saye_bonus_amount: null,
        // Group 8: Leaver Terms
        leaver_category: '',
        post_termination_exercise_days: null,
        termination_date: null,
        leaver_notes: '',
      },
      errors: {},
      submitting: false,
      feePercentageWarning: false,
      showHoldingDetailModal: false,
      editingHoldingDetail: null,
      ISA_ALLOWANCE: ISA_ANNUAL_ALLOWANCE,
      // Risk profile state
      mainRiskLevel: null,
      allowedRiskLevels: ['low', 'lower_medium', 'medium', 'upper_medium', 'high'],
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

    isEditMode() {
      return !!this.account;
    },

    showHoldingsEditor() {
      return HOLDABLE_ACCOUNT_TYPES.includes(this.formData.account_type)
        && parseFloat(this.formData.current_value) > 0;
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

    isBondType() {
      return ['onshore_bond', 'offshore_bond'].includes(this.formData.account_type);
    },

    isPrivateInvestmentType() {
      return ['private_company', 'crowdfunding', 'eis'].includes(this.formData.account_type);
    },

    isEmployeeShareScheme() {
      return ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'].includes(this.formData.account_type);
    },

    isOptionsScheme() {
      return ['saye', 'csop', 'emi', 'unapproved_options'].includes(this.formData.account_type);
    },

    isSAYEScheme() {
      return this.formData.account_type === 'saye';
    },

    isCSOPScheme() {
      return this.formData.account_type === 'csop';
    },

    isRSUScheme() {
      return this.formData.account_type === 'rsu';
    },

    // Calculate intrinsic value for options
    intrinsicValue() {
      if (!this.isOptionsScheme || !this.formData.current_share_price || !this.formData.exercise_price) {
        return null;
      }
      const gain = Math.max(0, this.formData.current_share_price - this.formData.exercise_price);
      return gain * (this.formData.units_vested || 0);
    },

    // Calculate unvested value
    unvestedValue() {
      if (!this.formData.current_share_price) return null;
      if (this.isOptionsScheme) {
        const gain = Math.max(0, this.formData.current_share_price - (this.formData.exercise_price || 0));
        return gain * (this.formData.units_unvested || 0);
      }
      return this.formData.current_share_price * (this.formData.units_unvested || 0);
    },

    isCrowdfundingType() {
      return this.formData.account_type === 'crowdfunding';
    },

    requiresTaxReliefTracking() {
      return ['eis', 'seis', 'sitr', 'vct'].includes(this.formData.tax_relief_type);
    },

    isDebtInstrument() {
      return ['convertible_loan_note', 'safe'].includes(this.formData.instrument_type);
    },

    showExitFields() {
      return this.formData.company_status === 'exited';
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
      if (this.totalWithPlanned > this.ISA_ALLOWANCE) return 'text-raspberry-600';
      if (this.totalRemainingAllowance < 2000) return 'text-violet-600';
      return 'text-spring-600';
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
      if (this.remainingAllowance === 0) return 'text-raspberry-600';
      if (this.remainingAllowance < 2000) return 'text-violet-600';
      return 'text-spring-600';
    },

    allowanceBarClass() {
      if (this.allowanceUsedPercent >= 100) return 'bg-raspberry-600';
      if (this.allowanceUsedPercent >= 75) return 'bg-violet-500';
      if (this.allowanceUsedPercent >= 50) return 'bg-violet-500';
      return 'bg-spring-600';
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
          // Load existing holdings for edit mode (filter out auto-created cash)
          if (newAccount.holdings?.length) {
            this.formData.holdings = newAccount.holdings
              .filter(h => h.asset_type !== 'cash')
              .map(h => ({
                id: h.id,
                security_name: h.security_name,
                asset_type: h.asset_type,
                allocation_percent: h.allocation_percent,
                cost_basis: h.cost_basis,
              }));
          } else {
            this.formData.holdings = [];
          }
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
          // Load existing holdings for edit mode (filter out auto-created cash)
          if (this.account.holdings?.length) {
            this.formData.holdings = this.account.holdings
              .filter(h => h.asset_type !== 'cash')
              .map(h => ({
                id: h.id,
                security_name: h.security_name,
                asset_type: h.asset_type,
                allocation_percent: h.allocation_percent,
                cost_basis: h.cost_basis,
              }));
          } else {
            this.formData.holdings = [];
          }
        } else {
          // Reset form when opening in "add" mode (no account)
          this.resetForm();
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
    pendingFill: {
      handler(fill) {
        logger.debug('AI Fill', 'pendingFill watcher fired:', fill?.entityType, fill?.fields ? Object.keys(fill.fields) : 'no fields');
        if (fill && fill.entityType === 'investment_account' && fill.fields) {
          // Set account_type immediately before the field sequence starts —
          // this controls which conditional sub-components are visible (bonds, private, employee schemes)
          if (fill.fields.account_type) {
            this.formData.account_type = fill.fields.account_type;
            logger.debug('AI Fill', 'Set account_type to:', fill.fields.account_type);
          }
          const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
          logger.debug('AI Fill', 'Starting field sequence:', fieldOrder);
          this.$store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
        }
      },
      immediate: true,
    },
    highlightedField(fieldKey) {
      if (fieldKey && this.pendingFill?.fields) {
        const value = this.pendingFill.fields[fieldKey];
        if (value !== undefined && value !== null) {
          this.formData[fieldKey] = value;
          logger.debug('AI Fill', 'Set field:', fieldKey, '=', value);
        }
      }
    },
    filling(isFilling) {
      logger.debug('AI Fill', 'filling watcher fired:', isFilling, 'pendingFill:', this.pendingFill?.entityType);
      if (isFilling === false && this.pendingFill?.entityType === 'investment_account') {
        logger.debug('AI Fill', 'All fields done, will auto-submit. formData:', JSON.stringify(this.formData));
        // Longer delay for complex form types to let Vue reactivity settle
        const isComplexType = this.isPrivateInvestmentType || this.isEmployeeShareScheme ||
          ['onshore_bond', 'offshore_bond'].includes(this.formData.account_type);
        const delay = isComplexType ? 500 : 250;
        logger.debug('AI Fill', 'Using delay:', delay, 'isComplexType:', isComplexType);
        this._fillTimer = setTimeout(() => {
          // Wait for Vue to process all reactive updates before submitting
          this.$nextTick(() => {
            logger.debug('AI Fill', 'Calling submitForm now');
            this.submitForm();
            // If validation failed (form still open, errors present), report to chat
            if (Object.keys(this.errors).length > 0) {
              logger.debug('AI Fill', 'Validation errors:', this.errors);
              const errorList = Object.values(this.errors).join(', ');
              this.$store.commit('aiChat/ADD_MESSAGE', {
                id: 'fill_error_' + Date.now(),
                role: 'assistant',
                content: `I wasn't able to save the account — the form has validation errors: ${errorList}. Please check the form and try again.`,
                created_at: new Date().toISOString(),
              }, { root: true });
              this.$store.dispatch('aiFormFill/cancelFill');
            }
          });
        }, delay);
      }
    },
  },

  async mounted() {
    // In onboarding context, load risk profile immediately (no show watcher to trigger it)
    if (this.context === 'onboarding') {
      await this.loadRiskProfile();
    }
  },

  beforeUnmount() {
    if (this._fillTimer) clearTimeout(this._fillTimer);
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

    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    },

    submitForm() {
      logger.debug('AI Fill', 'submitForm() called, account_type:', this.formData.account_type, 'provider:', this.formData.provider, 'current_value:', this.formData.current_value);
      this.errors = {};

      // Client-side validation
      if (!this.validateForm()) {
        logger.debug('AI Fill', 'validateForm() returned false, errors:', JSON.stringify(this.errors));
        return;
      }
      logger.debug('AI Fill', 'validateForm() passed');

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

      // The ...newAccount spread pulls in ALL fields from the API resource,
      // including computed fields, relationships, and MissingValue objects ({}).
      // Only send fields that are actual form inputs to avoid DB constraint violations.
      const allowedFields = [
        'account_type', 'account_type_other', 'provider', 'platform', 'country',
        'current_value', 'contributions_ytd', 'monthly_contribution_amount',
        'contribution_frequency', 'planned_lump_sum_amount', 'planned_lump_sum_date',
        'platform_fee_percent', 'platform_fee_amount', 'platform_fee_type', 'platform_fee_frequency',
        'isa_type', 'isa_subscription_current_year',
        'ownership_type', 'ownership_percentage', 'joint_owner_id', 'trust_id',
        'risk_preference',
        // Bond fields
        'bond_purchase_date', 'bond_withdrawal_taken',
        // Private company fields
        'company_legal_name', 'company_registration_number', 'company_country',
        'company_website', 'company_trading_name', 'crowdfunding_platform',
        'investment_date', 'investment_amount', 'investment_currency',
        'funding_round', 'pre_money_valuation', 'post_money_valuation',
        'price_per_share', 'number_of_shares', 'instrument_type', 'share_class',
        'liquidation_preference', 'has_anti_dilution', 'holding_structure',
        'nominee_name', 'conversion_terms', 'interest_rate', 'maturity_date',
        'tax_relief_type', 'eis3_certificate_number', 'hmrc_reference',
        'relief_claimed_date', 'relief_amount_claimed', 'disposal_restriction_date',
        'clawback_risk', 'clawback_notes', 'latest_valuation', 'latest_valuation_date',
        'current_ownership_percent', 'company_status', 'status_notes',
        'exit_type', 'exit_date', 'exit_gross_proceeds', 'exit_fees',
        'exit_net_proceeds', 'exit_moic', 'loss_relief_eligible',
        'capital_loss_amount', 'negligible_value_claim',
        'badr_eligible', 'badr_is_employee', 'badr_trading_company',
        'badr_5_percent_holding', 'badr_held_2_years', 'badr_emi_shares', 'badr_lifetime_used',
        // Employee share scheme fields
        'employer_name', 'employer_registration', 'employer_ticker', 'employer_is_listed',
        'parent_company_name', 'parent_company_country', 'ers_scheme_reference', 'ers_registered',
        'grant_date', 'grant_reference', 'units_granted', 'exercise_price',
        'market_value_at_grant', 'share_class_scheme', 'grant_currency', 'option_price_paid',
        'scheme_start_date', 'scheme_duration_months',
        'vesting_type', 'cliff_date', 'cliff_percentage', 'vesting_period_months',
        'vesting_frequency_months', 'has_performance_conditions', 'performance_conditions_description',
        'performance_period_end', 'performance_vesting_min_percent', 'performance_vesting_max_percent',
        'full_vest_date', 'accelerated_vesting_allowed',
        'units_vested', 'units_unvested', 'units_exercised', 'units_forfeited', 'units_expired',
        'scheme_status', 'current_share_price', 'share_price_date',
        'exercise_window_start', 'exercise_window_end', 'last_exercise_date',
        'total_exercise_proceeds', 'total_exercise_cost', 'exercise_history_json',
        'tax_treatment', 'is_readily_convertible_asset', 'paye_via_payroll',
        'income_tax_at_vest_exercise', 'ni_at_vest_exercise',
        'csop_disqualifying_event', 'csop_three_year_date', 'cost_basis_for_cgt',
        'saye_monthly_savings', 'saye_current_savings_balance', 'saye_maturity_date',
        'saye_option_discount_percent', 'saye_bonus_amount',
        'leaver_category', 'post_termination_exercise_days', 'termination_date', 'leaver_notes',
        // Inline holdings
        'holdings',
      ];
      // Only keep allowed form fields, removing computed/relationship/API-only fields
      for (const key of Object.keys(submitData)) {
        if (!allowedFields.includes(key)) {
          delete submitData[key];
        }
      }
      // Remove fields that are MissingValue objects ({}) from $this->when() — don't send them at all
      for (const key of Object.keys(submitData)) {
        if (submitData[key] !== null && typeof submitData[key] === 'object' && !Array.isArray(submitData[key]) && !(submitData[key] instanceof Date)) {
          delete submitData[key];
        }
      }

      // Emit save event - parent will close modal after successful save
      logger.debug('AI Fill', 'Emitting save event with data:', JSON.stringify(submitData).substring(0, 500));
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

      // Provider not required for private investments or employee share schemes
      if (!this.isPrivateInvestmentType && !this.isEmployeeShareScheme) {
        if (!this.formData.provider || this.formData.provider.trim().length === 0) {
          this.errors.provider = 'Provider is required';
          isValid = false;
        }
      }

      // Current value not required for private investments or employee share schemes
      if (!this.isPrivateInvestmentType && !this.isEmployeeShareScheme) {
        if (this.formData.current_value === null || this.formData.current_value < 0) {
          this.errors.current_value = 'Current value is required and must be 0 or greater';
          isValid = false;
        }
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

      // Employee Share Scheme validation
      if (this.isEmployeeShareScheme) {
        if (!this.formData.employer_name || this.formData.employer_name.trim() === '') {
          this.errors.employer_name = 'Employer name is required';
          isValid = false;
        }
        if (!this.formData.grant_date) {
          // SAYE: fall back to scheme_start_date as grant date
          if (this.formData.account_type === 'saye' && this.formData.scheme_start_date) {
            this.formData.grant_date = this.formData.scheme_start_date;
          } else {
            this.errors.grant_date = 'Grant date is required';
            isValid = false;
          }
        }
        if (this.formData.account_type !== 'saye' && (!this.formData.units_granted || this.formData.units_granted <= 0)) {
          this.errors.units_granted = 'Units granted is required';
          isValid = false;
        }
        // Exercise price required for options (not RSUs)
        if (this.isOptionsScheme && (!this.formData.exercise_price || this.formData.exercise_price < 0)) {
          this.errors.exercise_price = 'Exercise price is required for options';
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
      if (this.$store.state.aiFormFill.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
      this.$emit('close');
      this.resetForm();
    },

    openHoldingDetails(holding) {
      this.editingHoldingDetail = holding;
      this.showHoldingDetailModal = true;
    },

    async handleHoldingDetailSave(holdingData) {
      if (holdingData.id) {
        try {
          await this.$store.dispatch('investment/updateHolding', {
            id: holdingData.id,
            data: holdingData,
          });
          await this.$store.dispatch('investment/fetchInvestmentData');
        } catch (error) {
          logger.error('Failed to update holding:', error);
        }
      }
      this.showHoldingDetailModal = false;
      this.editingHoldingDetail = null;
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
        // Inline holdings
        holdings: [],
        // Bond-specific fields (onshore/offshore bonds)
        bond_purchase_date: null,
        bond_withdrawal_taken: null,
        // Private Company / Crowdfunding fields
        company_legal_name: '',
        company_registration_number: '',
        company_country: 'United Kingdom',
        company_website: '',
        company_trading_name: '',
        crowdfunding_platform: '',
        investment_date: null,
        investment_amount: null,
        investment_currency: 'GBP',
        funding_round: '',
        pre_money_valuation: null,
        post_money_valuation: null,
        price_per_share: null,
        number_of_shares: null,
        instrument_type: '',
        share_class: '',
        liquidation_preference: '',
        has_anti_dilution: false,
        holding_structure: 'direct',
        nominee_name: '',
        conversion_terms: '',
        interest_rate: null,
        maturity_date: null,
        tax_relief_type: '',
        eis3_certificate_number: '',
        hmrc_reference: '',
        relief_claimed_date: null,
        relief_amount_claimed: null,
        clawback_risk: false,
        clawback_notes: '',
        latest_valuation: null,
        latest_valuation_date: null,
        current_ownership_percent: null,
        company_status: 'active',
        status_notes: '',
        exit_type: '',
        exit_date: null,
        exit_gross_proceeds: null,
        exit_fees: null,
        exit_net_proceeds: null,
        exit_moic: null,
        loss_relief_eligible: false,
        capital_loss_amount: null,
        negligible_value_claim: false,
        // Business Asset Disposal Relief (BADR) fields
        badr_eligible: false,
        badr_is_employee: false,
        badr_trading_company: false,
        badr_5_percent_holding: false,
        badr_held_2_years: false,
        badr_emi_shares: false,
        badr_lifetime_used: null,
        // Employee Share Scheme fields
        employer_name: '',
        employer_registration: '',
        employer_ticker: '',
        employer_is_listed: false,
        parent_company_name: '',
        parent_company_country: '',
        ers_scheme_reference: '',
        ers_registered: false,
        grant_date: null,
        grant_reference: '',
        units_granted: null,
        exercise_price: null,
        market_value_at_grant: null,
        share_class_scheme: '',
        grant_currency: 'GBP',
        option_price_paid: null,
        scheme_start_date: null,
        scheme_duration_months: null,
        vesting_type: '',
        cliff_date: null,
        cliff_percentage: null,
        vesting_period_months: null,
        vesting_frequency_months: null,
        has_performance_conditions: false,
        performance_conditions_description: '',
        performance_period_end: null,
        performance_vesting_min_percent: null,
        performance_vesting_max_percent: null,
        full_vest_date: null,
        accelerated_vesting_allowed: false,
        units_vested: 0,
        units_unvested: 0,
        units_exercised: 0,
        units_forfeited: 0,
        units_expired: 0,
        scheme_status: 'active',
        current_share_price: null,
        share_price_date: null,
        exercise_window_start: null,
        exercise_window_end: null,
        last_exercise_date: null,
        total_exercise_proceeds: null,
        total_exercise_cost: null,
        tax_treatment: '',
        is_readily_convertible_asset: null,
        paye_via_payroll: true,
        income_tax_at_vest_exercise: null,
        ni_at_vest_exercise: null,
        csop_disqualifying_event: false,
        csop_three_year_date: null,
        cost_basis_for_cgt: null,
        saye_monthly_savings: null,
        saye_current_savings_balance: null,
        saye_maturity_date: null,
        saye_option_discount_percent: null,
        saye_bonus_amount: null,
        leaver_category: '',
        post_termination_exercise_days: null,
        termination_date: null,
        leaver_notes: '',
      };
      this.errors = {};
      this.feePercentageWarning = false;
    },
  },
};
</script>
