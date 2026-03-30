<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity bg-horizon-500 bg-opacity-75"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="bg-white px-6 py-4 border-b border-light-gray">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-horizon-500">
              {{ isEditMode ? 'Edit Holding' : 'Add New Holding' }}
            </h3>
            <button
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
            <!-- Account Selection -->
            <div>
              <label for="account_id" class="block text-sm font-medium text-neutral-500 mb-1">
                Account
              </label>
              <select
                id="account_id"
                v-model="formData.investment_account_id"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                :class="{ 'border-raspberry-500': errors.investment_account_id }"
                :disabled="accounts.length === 0"
              >
                <option value="">{{ accounts.length === 0 ? 'No accounts available' : 'Select an account' }}</option>
                <option v-for="account in accounts" :key="account.id" :value="account.id">
                  {{ formatAccountName(account) }}
                </option>
              </select>
              <p v-if="errors.investment_account_id" class="mt-1 text-sm text-raspberry-600">{{ errors.investment_account_id }}</p>
              <p v-if="accounts.length === 0" class="mt-2 text-sm text-violet-600 bg-eggshell-500 rounded-md p-2">
                ⚠️ You need to create an investment account first before adding holdings. Please go to the Accounts tab to add an account.
              </p>
            </div>

            <!-- Security Name -->
            <div>
              <label for="security_name" class="block text-sm font-medium text-neutral-500 mb-1">
                Security Name
              </label>
              <input
                id="security_name"
                v-model="formData.security_name"
                type="text"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                :class="{ 'border-raspberry-500': errors.security_name }"
                placeholder="e.g., Vanguard FTSE All-World"
              />
              <p v-if="errors.security_name" class="mt-1 text-sm text-raspberry-600">{{ errors.security_name }}</p>
            </div>

            <!-- Ticker and ISIN -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="ticker" class="block text-sm font-medium text-neutral-500 mb-1">
                  Ticker
                </label>
                <input
                  id="ticker"
                  v-model="formData.ticker"
                  type="text"
                  class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                  placeholder="e.g., VWRL"
                />
              </div>
              <div>
                <label for="isin" class="block text-sm font-medium text-neutral-500 mb-1">
                  ISIN
                </label>
                <input
                  id="isin"
                  v-model="formData.isin"
                  type="text"
                  class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                  placeholder="e.g., IE00B3RBWM25"
                />
              </div>
            </div>

            <!-- Asset Type -->
            <div>
              <label for="asset_type" class="block text-sm font-medium text-neutral-500 mb-1">
                Asset Type
              </label>
              <select
                id="asset_type"
                v-model="formData.asset_type"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                :class="{ 'border-raspberry-500': errors.asset_type }"
              >
                <option value="">Select asset type</option>
                <option value="uk_equity">UK Equity</option>
                <option value="us_equity">US Equity</option>
                <option value="international_equity">International Equity</option>
                <option value="fund">Fund</option>
                <option value="etf">ETF</option>
                <option value="bond">Bond</option>
                <option value="cash">Cash</option>
                <option value="alternative">Alternative</option>
                <option value="property">Property</option>
              </select>
              <p v-if="errors.asset_type" class="mt-1 text-sm text-raspberry-600">{{ errors.asset_type }}</p>
            </div>

            <!-- Fund Type (sub_type) - shown only when asset type is Fund -->
            <div v-if="formData.asset_type === 'fund'">
              <label for="sub_type" class="block text-sm font-medium text-neutral-500 mb-1">
                Fund Type
              </label>
              <select
                id="sub_type"
                v-model="formData.sub_type"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              >
                <option :value="null">Select fund type</option>
                <option value="equity_fund">Equity Fund</option>
                <option value="bond_fund">Bond Fund</option>
                <option value="mixed_fund">Mixed Fund</option>
                <option value="income_fund">Income Fund</option>
                <option value="index_fund">Index Fund</option>
                <option value="money_market_fund">Money Market Fund</option>
                <option value="property_fund">Property Fund</option>
              </select>
            </div>

            <!-- Allocation Percentage and Purchase Price (Optional) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="allocation_percent" class="block text-sm font-medium text-neutral-500 mb-1">
                  Allocation % of Account
                </label>
                <div class="relative">
                  <input
                    id="allocation_percent"
                    v-model.number="formData.allocation_percent"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 pr-8"
                    :class="{ 'border-raspberry-500': errors.allocation_percent }"
                    placeholder="e.g., 25.5"
                  />
                  <span class="absolute right-3 top-2.5 text-neutral-500">%</span>
                </div>
                <p v-if="errors.allocation_percent" class="mt-1 text-sm text-raspberry-600">{{ errors.allocation_percent }}</p>
                <p class="mt-1 text-xs text-neutral-500">Percentage of this account's total value</p>
              </div>
              <div>
                <label for="purchase_price" class="block text-sm font-medium text-neutral-500 mb-1">
                  Purchase Price (£) <span class="text-horizon-400 text-xs">(Optional)</span>
                </label>
                <input
                  id="purchase_price"
                  v-model.number="formData.purchase_price"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                  :class="{ 'border-raspberry-500': errors.purchase_price }"
                  placeholder="0.00"
                />
                <p v-if="errors.purchase_price" class="mt-1 text-sm text-raspberry-600">{{ errors.purchase_price }}</p>
              </div>
            </div>

            <!-- Current Price (Optional) and Purchase Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="current_price" class="block text-sm font-medium text-neutral-500 mb-1">
                  Current Price (£) <span class="text-horizon-400 text-xs">(Optional)</span>
                </label>
                <input
                  id="current_price"
                  v-model.number="formData.current_price"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                  :class="{ 'border-raspberry-500': errors.current_price }"
                  placeholder="0.00"
                />
                <p v-if="errors.current_price" class="mt-1 text-sm text-raspberry-600">{{ errors.current_price }}</p>
              </div>
              <div>
                <label for="purchase_date" class="block text-sm font-medium text-neutral-500 mb-1">
                  Purchase Date <span class="text-horizon-400 text-xs">(Optional)</span>
                </label>
                <input
                  id="purchase_date"
                  v-model="formData.purchase_date"
                  type="date"
                  class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                  :class="{ 'border-raspberry-500': errors.purchase_date }"
                  :max="today"
                />
                <p v-if="errors.purchase_date" class="mt-1 text-sm text-raspberry-600">{{ errors.purchase_date }}</p>
              </div>
            </div>

            <!-- OCF Percent -->
            <div>
              <label for="ocf_percent" class="block text-sm font-medium text-neutral-500 mb-1">
                Ongoing Charge Figure (OCF) %
              </label>
              <input
                id="ocf_percent"
                v-model.number="formData.ocf_percent"
                type="number"
                step="0.01"
                min="0"
                max="10"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                placeholder="e.g., 0.22"
              />
              <p class="mt-1 text-xs text-neutral-500">Annual management fee as a percentage</p>
            </div>

            <!-- Calculated Fields Display -->
            <div v-if="selectedAccount && formData.allocation_percent" class="bg-eggshell-500 rounded-md p-4">
              <h4 class="text-sm font-semibold text-violet-900 mb-2">Calculated Values</h4>
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <span class="text-violet-700">Account Value:</span>
                  <span class="ml-2 font-medium text-violet-900">{{ formatCurrency(selectedAccount.current_value) }}</span>
                </div>
                <div>
                  <span class="text-violet-700">Holding Value:</span>
                  <span class="ml-2 font-medium text-violet-900">{{ formatCurrency(calculatedHoldingValue) }}</span>
                </div>
                <div v-if="formData.purchase_price && formData.current_price">
                  <span class="text-violet-700">Price Return:</span>
                  <span class="ml-2 font-medium" :class="returnClass">{{ formatReturn(returnPercent) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-eggshell-500 px-6 py-4 flex justify-end gap-3">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 border border-horizon-300 rounded-md text-sm font-medium text-neutral-500 hover:bg-savannah-100 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-button text-sm font-medium hover:bg-raspberry-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Saving...' : (isEditMode ? 'Update Holding' : 'Add Holding') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'HoldingForm',

  emits: ['save', 'close'],

  mixins: [currencyMixin],

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    holding: {
      type: Object,
      default: null,
    },
    accounts: {
      type: Array,
      required: true,
    },
    defaultAccountId: {
      type: Number,
      default: null,
    },
  },

  data() {
    return {
      formData: {
        investment_account_id: '',
        security_name: '',
        ticker: '',
        isin: '',
        asset_type: '',
        sub_type: null,
        allocation_percent: null,
        purchase_price: null,
        purchase_date: '',
        current_price: null,
        ocf_percent: 0,
      },
      errors: {},
      submitting: false,
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

    isEditMode() {
      return !!this.holding;
    },

    today() {
      return new Date().toISOString().split('T')[0];
    },

    selectedAccount() {
      if (!this.formData.investment_account_id) return null;
      return this.accounts.find(acc => acc.id === this.formData.investment_account_id);
    },

    calculatedHoldingValue() {
      if (!this.selectedAccount || !this.formData.allocation_percent) return 0;
      return (this.selectedAccount.current_value * this.formData.allocation_percent) / 100;
    },

    returnPercent() {
      if (!this.formData.purchase_price || !this.formData.current_price) return 0;
      return ((this.formData.current_price - this.formData.purchase_price) / this.formData.purchase_price) * 100;
    },

    returnClass() {
      if (this.returnPercent > 0) return 'text-spring-600';
      if (this.returnPercent < 0) return 'text-raspberry-600';
      return 'text-neutral-500';
    },
  },

  watch: {
    holding: {
      immediate: true,
      handler(newHolding) {
        if (newHolding) {
          this.formData = {
            ...newHolding,
            sub_type: newHolding.sub_type || null,
            purchase_date: this.formatDateForInput(newHolding.purchase_date),
          };
        } else {
          this.resetForm();
        }
      },
    },
    'formData.asset_type'(newVal) {
      if (newVal !== 'fund') {
        this.formData.sub_type = null;
      }
    },
    show(newVal) {
      if (!newVal) {
        this.errors = {};
      }
    },

    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'investment_holding' && fill.fields) {
          // Pre-set key fields before field sequence
          if (fill.fields.investment_account_id) {
            this.formData.investment_account_id = fill.fields.investment_account_id;
          }
          if (fill.fields.security_name) {
            this.formData.security_name = fill.fields.security_name;
          }
          if (fill.fields.asset_type) {
            this.formData.asset_type = fill.fields.asset_type;
          }
          const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
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
        }
      }
    },

    filling(isFilling) {
      if (isFilling === false && this.pendingFill?.entityType === 'investment_holding') {
        setTimeout(() => {
          this.$nextTick(() => {
            this.submitForm();
          });
        }, 500);
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

        // Calculate current_value based on allocation percentage
        const holdingData = {
          ...this.formData,
          current_value: this.calculatedHoldingValue,
          // cost_basis will be calculated on backend if purchase price is provided
        };

        this.$emit('save', holdingData);
        this.closeModal();
      } catch (error) {
        logger.error('Form submission error:', error);
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors;
        }
      } finally {
        this.submitting = false;
      }
    },

    validateForm() {
      let isValid = true;

      if (!this.formData.investment_account_id) {
        this.errors.investment_account_id = 'Account is required';
        isValid = false;
      }

      if (!this.formData.security_name || this.formData.security_name.trim().length === 0) {
        this.errors.security_name = 'Security name is required';
        isValid = false;
      }

      if (!this.formData.asset_type) {
        this.errors.asset_type = 'Asset type is required';
        isValid = false;
      }

      if (!this.formData.allocation_percent || this.formData.allocation_percent <= 0) {
        this.errors.allocation_percent = 'Allocation percentage must be greater than 0';
        isValid = false;
      }

      if (this.formData.allocation_percent > 100) {
        this.errors.allocation_percent = 'Allocation percentage cannot exceed 100%';
        isValid = false;
      }

      // Optional validation: if purchase price is provided, it must be > 0
      if (this.formData.purchase_price !== null && this.formData.purchase_price !== '' && this.formData.purchase_price <= 0) {
        this.errors.purchase_price = 'Purchase price must be greater than 0 if provided';
        isValid = false;
      }

      // Optional validation: if current price is provided, it must be > 0
      if (this.formData.current_price !== null && this.formData.current_price !== '' && this.formData.current_price <= 0) {
        this.errors.current_price = 'Current price must be greater than 0 if provided';
        isValid = false;
      }

      return isValid;
    },

    closeModal() {
      this.$emit('close');
      this.resetForm();
    },

    resetForm() {
      this.formData = {
        investment_account_id: this.defaultAccountId || '',
        security_name: '',
        ticker: '',
        isin: '',
        asset_type: '',
        sub_type: null,
        allocation_percent: null,
        purchase_price: null,
        purchase_date: '',
        current_price: null,
        ocf_percent: 0,
      };
      this.errors = {};
    },

    formatReturn(value) {
      const sign = value >= 0 ? '+' : '';
      return `${sign}${value.toFixed(2)}%`;
    },

    formatAccountName(account) {
      // Handle different possible account structures
      if (account.provider && account.account_type) {
        return `${account.provider} - ${this.formatAccountType(account.account_type)}`;
      } else if (account.provider) {
        return account.provider;
      } else if (account.account_name) {
        return account.account_name;
      } else {
        return `Account ${account.id}`;
      }
    },

    formatAccountType(type) {
      const types = {
        isa: 'ISA',
        sipp: 'Self-Invested Personal Pension',
        gia: 'General Investment Account',
        pension: 'Pension',
      };
      return types[type] || type;
    },

    formatDateForInput(date) {
      if (!date) return '';
      if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return date;
      }
      const dateObj = new Date(date);
      if (isNaN(dateObj.getTime())) return '';
      const year = dateObj.getFullYear();
      const month = String(dateObj.getMonth() + 1).padStart(2, '0');
      const day = String(dateObj.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },
  },
};
</script>
