<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeModal"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
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
          <div class="bg-white px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
            <!-- Account Type -->
            <div>
              <label for="account_type" class="block text-sm font-medium text-gray-700 mb-1">
                Account Type <span class="text-red-500">*</span>
              </label>
              <select
                id="account_type"
                v-model="formData.account_type"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.account_type }"
                required
              >
                <option value="">Select account type</option>
                <option value="isa">ISA (Stocks & Shares)</option>
                <option value="gia">General Investment Account</option>
                <option value="nsi">NS&I (National Savings & Investments)</option>
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
                Specify Account Type <span class="text-red-500">*</span>
              </label>
              <input
                id="account_type_other"
                v-model="formData.account_type_other"
                type="text"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.account_type_other }"
                placeholder="e.g., Gold, Cryptocurrency, Classic Cars, Art Collection"
                :required="formData.account_type === 'other'"
              />
              <p v-if="errors.account_type_other" class="mt-1 text-sm text-red-600">{{ errors.account_type_other }}</p>
              <p class="mt-1 text-xs text-gray-500">Enter the custom asset class for this investment</p>
            </div>

            <!-- Provider -->
            <div>
              <label for="provider" class="block text-sm font-medium text-gray-700 mb-1">
                Provider <span class="text-red-500">*</span>
              </label>
              <input
                id="provider"
                v-model="formData.provider"
                type="text"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.provider }"
                placeholder="e.g., Vanguard, Hargreaves Lansdown, Interactive Investor"
                required
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
                Current Value (£) <span class="text-red-500">*</span>
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
                required
              />
              <p v-if="errors.current_value" class="mt-1 text-sm text-red-600">{{ errors.current_value }}</p>
              <p class="mt-1 text-xs text-gray-500">Current total value of the account</p>
            </div>

            <!-- Tax Year -->
            <div>
              <label for="tax_year" class="block text-sm font-medium text-gray-700 mb-1">
                Tax Year <span class="text-red-500">*</span>
              </label>
              <select
                id="tax_year"
                v-model="formData.tax_year"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.tax_year }"
                required
              >
                <option value="2025/26">2025/26</option>
                <option value="2024/25">2024/25</option>
                <option value="2023/24">2023/24</option>
              </select>
              <p v-if="errors.tax_year" class="mt-1 text-sm text-red-600">{{ errors.tax_year }}</p>
            </div>

            <!-- Platform Fee Percent -->
            <div>
              <label for="platform_fee_percent" class="block text-sm font-medium text-gray-700 mb-1">
                Platform Fee (% p.a.)
              </label>
              <input
                id="platform_fee_percent"
                v-model.number="formData.platform_fee_percent"
                type="number"
                step="0.01"
                min="0"
                max="5"
                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="e.g., 0.45"
              />
              <p class="mt-1 text-xs text-gray-500">Annual platform/administration fee as a percentage</p>
            </div>

            <!-- ISA-specific fields -->
            <div v-if="isISAType" class="bg-blue-50 border border-blue-200 rounded-md p-4 space-y-4">
              <div class="flex items-start gap-2 mb-3">
                <svg class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <p class="text-sm font-medium text-blue-900">ISA Account Information</p>
                  <p class="text-xs text-blue-700 mt-1">
                    ISA contributions count towards your £20,000 annual allowance (2025/26)
                  </p>
                </div>
              </div>

              <!-- Tax Year Subscription -->
              <div>
                <label for="isa_subscription_current_year" class="block text-sm font-medium text-blue-900 mb-1">
                  Subscription This Tax Year (£)
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
                  Amount contributed in current tax year {{ currentTaxYear }}
                </p>
              </div>

              <!-- ISA Type -->
              <div>
                <label for="isa_type" class="block text-sm font-medium text-blue-900 mb-1">
                  ISA Type
                </label>
                <select
                  id="isa_type"
                  v-model="formData.isa_type"
                  class="w-full border border-blue-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                >
                  <option value="stocks_and_shares">Stocks & Shares ISA</option>
                  <option value="lifetime">Lifetime ISA</option>
                  <option value="innovative_finance">Innovative Finance ISA</option>
                </select>
              </div>

              <!-- Remaining Allowance Display -->
              <div v-if="formData.isa_subscription_current_year" class="bg-white border border-blue-200 rounded-md p-3">
                <div class="flex justify-between items-center mb-2">
                  <span class="text-sm font-medium text-gray-700">Remaining ISA Allowance:</span>
                  <span class="text-lg font-bold" :class="remainingAllowanceClass">
                    {{ formatCurrency(remainingAllowance) }}
                  </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div
                    class="h-2 rounded-full transition-all"
                    :class="allowanceBarClass"
                    :style="{ width: allowanceUsedPercent + '%' }"
                  ></div>
                </div>
                <p class="text-xs text-gray-600 mt-2">
                  {{ allowanceUsedPercent.toFixed(1) }}% of annual allowance used
                </p>
              </div>
            </div>

            <!-- Joint Ownership Section -->
            <div class="space-y-4 pt-4 border-t border-gray-200">
              <h4 class="text-sm font-semibold text-gray-900">Ownership</h4>

              <!-- Ownership Type -->
              <div>
                <label for="ownership_type" class="block text-sm font-medium text-gray-700 mb-1">
                  Ownership Type <span class="text-red-500">*</span>
                </label>
                <select
                  id="ownership_type"
                  v-model="formData.ownership_type"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                >
                  <option value="individual">Individual Owner</option>
                  <option value="joint">Joint Owner</option>
                  <option value="trust">Trust</option>
                </select>
              </div>

              <!-- Joint Owner (if ownership_type is joint) -->
              <div v-if="formData.ownership_type === 'joint'">
                <label for="joint_owner_id" class="block text-sm font-medium text-gray-700 mb-1">
                  Joint Owner <span class="text-red-500">*</span>
                </label>
                <select
                  id="joint_owner_id"
                  v-model="formData.joint_owner_id"
                  :required="formData.ownership_type === 'joint'"
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
                  Trust <span class="text-red-500">*</span>
                </label>
                <select
                  id="trust_id"
                  v-model="formData.trust_id"
                  :required="formData.ownership_type === 'trust'"
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

export default {
  name: 'AccountForm',

  components: {
    CountrySelector,
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
        tax_year: '2025/26',
        contributions_ytd: null,
        platform_fee_percent: null,
        isa_type: 'stocks_and_shares',
        isa_subscription_current_year: null,
        ownership_type: 'individual',
        joint_owner_id: null,
        trust_id: null,
      },
      errors: {},
      submitting: false,
      ISA_ALLOWANCE: 20000, // 2025/26 tax year
    };
  },

  computed: {
    isEditMode() {
      return !!this.account;
    },

    spouse() {
      return this.$store.getters['userProfile/spouse'];
    },

    isISAType() {
      return this.formData.account_type === 'isa';
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
          };
        } else {
          this.resetForm();
        }
      },
    },
    show(newVal) {
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
          };
        }
        this.errors = {};
        this.submitting = false;
      } else {
        this.errors = {};
      }
    },
    'formData.account_type'(newType) {
      // Reset ISA-specific fields when account type changes
      if (newType !== 'isa') {
        this.formData.isa_type = 'stocks_and_shares';
        this.formData.isa_subscription_current_year = null;
      }
      // Clear account_type_other when switching away from 'other'
      if (newType !== 'other') {
        this.formData.account_type_other = '';
      }
    },
  },

  methods: {
    async submitForm() {
      this.errors = {};
      this.submitting = true;

      try {
        // Client-side validation
        if (!this.validateForm()) {
          this.submitting = false;
          return;
        }

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
      } catch (error) {
        console.error('Form submission error:', error);
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors;
        }
        this.submitting = false;
      }
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

      if (!this.formData.tax_year) {
        this.errors.tax_year = 'Tax year is required';
        isValid = false;
      }

      if (this.formData.platform_fee_percent !== null &&
          (this.formData.platform_fee_percent < 0 || this.formData.platform_fee_percent > 5)) {
        this.errors.platform_fee_percent = 'Platform fee must be between 0 and 5%';
        isValid = false;
      }

      // ISA-specific validation
      if (this.isISAType && this.formData.isa_subscription_current_year) {
        if (this.formData.isa_subscription_current_year < 0) {
          this.errors.isa_subscription_current_year = 'Subscription amount cannot be negative';
          isValid = false;
        }
        if (this.formData.isa_subscription_current_year > this.ISA_ALLOWANCE) {
          this.errors.isa_subscription_current_year = `Subscription cannot exceed ${this.formatCurrency(this.ISA_ALLOWANCE)} allowance`;
          isValid = false;
        }
      }

      return isValid;
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
        current_value: null,
        tax_year: '2025/26',
        contributions_ytd: null,
        platform_fee_percent: null,
        isa_type: 'stocks_and_shares',
        isa_subscription_current_year: null,
        ownership_type: 'individual',
        joint_owner_id: null,
        trust_id: null,
      };
      this.errors = {};
    },

    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value || 0);
    },
  },
};
</script>
