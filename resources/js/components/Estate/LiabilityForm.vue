<template>
  <div :class="context === 'onboarding' ? '' : 'bg-white rounded-lg p-6 max-h-[90vh] overflow-y-auto'">
    <div class="mb-6 pb-4 border-b-2 border-light-gray">
      <h3 class="text-xl font-semibold text-horizon-500 mb-2">{{ isEditMode ? 'Edit Liability' : 'Add New Liability' }}</h3>
      <p class="text-sm text-neutral-500">Track debts and liabilities for estate planning and net worth calculation</p>
    </div>

    <form @submit.prevent="handleSubmit">
      <!-- Liability Type -->
      <div class="mb-5" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'liability_type' }">
        <label for="liability_type" class="block text-sm font-medium text-neutral-500 mb-1.5">Liability Type</label>
        <select
          id="liability_type"
          v-model="formData.liability_type"
          class="input-field cursor-pointer"
          :class="{ 'border-raspberry-500': errors.liability_type }"
          @change="handleLiabilityTypeChange"
        >
          <option value="">Select liability type...</option>
          <option value="secured_loan">Secured Loan</option>
          <option value="personal_loan">Personal Loan</option>
          <option value="credit_card">Credit Card</option>
          <option value="overdraft">Bank Overdraft</option>
          <option value="hire_purchase">Hire Purchase / Car Finance</option>
          <option value="student_loan">Student Loan</option>
          <option value="business_loan">Business Loan</option>
          <option value="other">Other</option>
        </select>
        <span v-if="errors.liability_type" class="text-sm text-raspberry-500 mt-1 block">
          {{ errors.liability_type }}
        </span>
      </div>

      <!-- Liability Name -->
      <div class="mb-5" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'liability_name' }">
        <label for="liability_name" class="block text-sm font-medium text-neutral-500 mb-1.5">Liability Name / Description</label>
        <input
          id="liability_name"
          v-model="formData.liability_name"
          type="text"
          class="input-field"
          :class="{ 'border-raspberry-500': errors.liability_name }"
          :placeholder="liabilityNamePlaceholder"
        />
        <span v-if="errors.liability_name" class="text-sm text-raspberry-500 mt-1 block">
          {{ errors.liability_name }}
        </span>
      </div>

      <!-- Current Balance -->
      <div class="mb-5" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'current_balance' }">
        <label for="current_balance" class="block text-sm font-medium text-neutral-500 mb-1.5">Current Balance Owed (£)</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500 text-sm font-medium pointer-events-none">£</span>
          <input
            id="current_balance"
            v-model.number="formData.current_balance"
            type="number"
            class="input-field pl-8"
            :class="{ 'border-raspberry-500': errors.current_balance }"
            placeholder="0"
            min="0"
            step="0.01"
          />
        </div>
        <span v-if="errors.current_balance" class="text-sm text-raspberry-500 mt-1 block">
          {{ errors.current_balance }}
        </span>
      </div>

      <!-- Monthly Payment -->
      <div class="mb-5" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'monthly_payment' }">
        <label for="monthly_payment" class="block text-sm font-medium text-neutral-500 mb-1.5">Monthly Payment (£)</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500 text-sm font-medium pointer-events-none">£</span>
          <input
            id="monthly_payment"
            v-model.number="formData.monthly_payment"
            type="number"
            class="input-field pl-8"
            placeholder="0"
            min="0"
            step="0.01"
          />
        </div>
        <small class="block mt-1.5 text-xs text-neutral-500 leading-snug">
          Regular monthly payment amount (if applicable)
        </small>
      </div>

      <!-- Interest Rate -->
      <div class="mb-5" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'interest_rate' }">
        <label for="interest_rate" class="block text-sm font-medium text-neutral-500 mb-1.5">Interest Rate (% per annum)</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500 text-sm font-medium pointer-events-none">%</span>
          <input
            id="interest_rate"
            v-model.number="formData.interest_rate"
            type="number"
            class="input-field pl-8"
            :class="{ 'border-raspberry-500': errors.interest_rate }"
            placeholder="0.00"
            min="0"
            max="100"
            step="0.01"
          />
        </div>
        <span v-if="errors.interest_rate" class="text-sm text-raspberry-500 mt-1 block">
          {{ errors.interest_rate }}
        </span>
      </div>

      <!-- Maturity Date -->
      <div class="mb-5">
        <label for="maturity_date" class="block text-sm font-medium text-neutral-500 mb-1.5">Maturity / End Date</label>
        <input
          id="maturity_date"
          v-model="formData.maturity_date"
          type="date"
          class="input-field"
          :min="todayDate"
        />
        <small class="block mt-1.5 text-xs text-neutral-500 leading-snug">
          Expected date when this liability will be fully repaid
        </small>
      </div>

      <!-- Secured Against Asset -->
      <div class="mb-5">
        <label for="secured_against" class="block text-sm font-medium text-neutral-500 mb-1.5">Secured Against Asset (Optional)</label>
        <input
          id="secured_against"
          v-model="formData.secured_against"
          type="text"
          class="input-field"
          placeholder="e.g., Main Residence, Investment Property"
        />
        <small class="block mt-1.5 text-xs text-neutral-500 leading-snug">
          Specify if this liability is secured against a particular asset
        </small>
      </div>

      <!-- Priority for Repayment -->
      <div class="mb-5">
        <div class="flex items-center gap-2.5">
          <input
            id="is_priority_debt"
            v-model="formData.is_priority_debt"
            type="checkbox"
            class="w-[18px] h-[18px] cursor-pointer"
          />
          <label for="is_priority_debt" class="text-sm text-neutral-500 cursor-pointer !mb-0">
            Priority Debt
          </label>
        </div>
        <small class="block mt-1.5 text-xs text-neutral-500 leading-snug">
          {{ priorityDebtDescription }}
        </small>
      </div>

      <!-- Conditional: Mortgage-specific fields -->
      <div v-if="formData.liability_type === 'mortgage'" class="mt-6 p-5 bg-eggshell-500 rounded-md border border-light-gray">
        <h4 class="text-base font-semibold text-neutral-500 mt-0 mb-4 pb-2 border-b border-light-gray">Mortgage Details</h4>

        <div class="flex gap-4 flex-col sm:flex-row">
          <div class="mb-5 flex-1">
            <label for="mortgage_type" class="block text-sm font-medium text-neutral-500 mb-1.5">Mortgage Type</label>
            <select
              id="mortgage_type"
              v-model="formData.mortgage_type"
              class="input-field cursor-pointer"
            >
              <option value="">Select type...</option>
              <option value="repayment">Repayment</option>
              <option value="interest_only">Interest Only</option>
              <option value="fixed_rate">Fixed Rate</option>
              <option value="variable_rate">Variable Rate</option>
              <option value="tracker">Tracker</option>
            </select>
          </div>

          <div class="mb-5 flex-1">
            <label for="fixed_until" class="block text-sm font-medium text-neutral-500 mb-1.5">Fixed Rate Until</label>
            <input
              id="fixed_until"
              v-model="formData.fixed_until"
              type="date"
              class="input-field"
            />
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="mb-5">
        <label for="notes" class="block text-sm font-medium text-neutral-500 mb-1.5">Additional Notes (Optional)</label>
        <textarea
          id="notes"
          v-model="formData.notes"
          class="input-field resize-y min-h-[80px]"
          rows="3"
          placeholder="Any additional information about this liability..."
        ></textarea>
      </div>

      <!-- Repayment Projection (if monthly payment provided) -->
      <div v-if="showRepaymentProjection" class="my-5 p-4 bg-violet-50 border border-raspberry-200 rounded">
        <div class="flex items-center gap-2 font-semibold text-violet-800 mb-3 text-sm">
          <i class="fas fa-calculator"></i>
          <span>Estimated Repayment Timeline</span>
        </div>
        <div class="mb-2">
          <div class="flex justify-between py-1.5 text-sm">
            <span class="text-violet-900 font-medium">Estimated Time to Repay:</span>
            <span class="text-violet-800 font-semibold">{{ estimatedMonthsToRepay }} months ({{ estimatedYearsToRepay }} years)</span>
          </div>
          <div class="flex justify-between py-1.5 text-sm">
            <span class="text-violet-900 font-medium">Total Interest:</span>
            <span class="text-violet-800 font-semibold">{{ formatCurrency(estimatedTotalInterest) }}</span>
          </div>
          <div class="flex justify-between py-1.5 text-sm">
            <span class="text-violet-900 font-medium">Total Amount Payable:</span>
            <span class="text-violet-800 font-semibold">{{ formatCurrency(estimatedTotalPayable) }}</span>
          </div>
        </div>
        <small class="block text-[11px] text-violet-900 italic">
          * Estimates assume fixed interest rate and regular monthly payments
        </small>
      </div>

      <!-- Form Actions -->
      <div class="flex items-center justify-end space-x-3 mt-6 pt-4 border-t border-light-gray">
        <button
          type="button"
          @click="handleCancel"
          :class="context === 'onboarding'
            ? 'px-4 py-2 bg-light-pink-100 hover:bg-light-pink-200 text-horizon-500 rounded-lg transition-colors text-sm font-medium'
            : 'border border-light-gray text-horizon-500 px-4 py-2 rounded-button font-semibold hover:bg-savannah-100 transition-colors inline-flex items-center gap-2'"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="isSubmitting"
          :class="context === 'onboarding'
            ? 'px-6 py-2 bg-raspberry-500 text-white rounded-lg hover:bg-raspberry-600 transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed'
            : 'bg-raspberry-500 text-white px-4 py-2 rounded-button font-semibold hover:bg-raspberry-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2'"
        >
          <i v-if="!isSubmitting && context !== 'onboarding'" class="fas fa-save"></i>
          <i v-if="isSubmitting && context !== 'onboarding'" class="fas fa-spinner fa-spin"></i>
          {{ isSubmitting ? 'Saving...' : (context === 'onboarding' ? 'Save' : (isEditMode ? 'Update Liability' : 'Add Liability')) }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'LiabilityForm',
  mixins: [currencyMixin],

  props: {
    liability: {
      type: Object,
      default: null,
    },
    mode: {
      type: String,
      default: 'create', // 'create' or 'edit'
    },
    context: {
      type: String,
      default: 'standalone',
      validator: (v) => ['standalone', 'onboarding'].includes(v),
    },
  },

  emits: ['save', 'cancel'],

  data() {
    return {
      formData: {
        liability_type: '',
        liability_name: '',
        current_balance: null,
        monthly_payment: null,
        interest_rate: null,
        maturity_date: '',
        secured_against: '',
        is_priority_debt: false,
        // Mortgage-specific
        mortgage_type: '',
        fixed_until: '',
        // General
        notes: '',
      },
      errors: {},
      isSubmitting: false,
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

    isEditMode() {
      return this.mode === 'edit' && this.liability !== null;
    },

    todayDate() {
      return new Date().toISOString().split('T')[0];
    },

    liabilityNamePlaceholder() {
      const placeholders = {
        mortgage: 'e.g., Main Residence Mortgage, Buy-to-Let Mortgage',
        secured_loan: 'e.g., Homeowner Loan',
        personal_loan: 'e.g., Bank Personal Loan',
        credit_card: 'e.g., Visa Credit Card',
        overdraft: 'e.g., Current Account Overdraft',
        hire_purchase: 'e.g., Car Finance',
        student_loan: 'e.g., Plan 1 Student Loan',
        business_loan: 'e.g., Business Term Loan',
        other: 'e.g., Other Liability',
      };
      return placeholders[this.formData.liability_type] || 'Enter liability name';
    },

    priorityDebtDescription() {
      const priorityDebts = ['mortgage', 'secured_loan', 'hire_purchase'];
      if (priorityDebts.includes(this.formData.liability_type)) {
        return 'Priority debts have serious consequences if unpaid (e.g., home repossession)';
      }
      return 'Priority debts should be repaid first (mortgage, secured loans, council tax, etc.)';
    },

    showRepaymentProjection() {
      return (
        this.formData.current_balance > 0 &&
        this.formData.monthly_payment > 0 &&
        this.formData.interest_rate !== null &&
        this.formData.interest_rate >= 0
      );
    },

    estimatedMonthsToRepay() {
      if (!this.showRepaymentProjection) return 0;

      const balance = this.formData.current_balance;
      const monthlyPayment = this.formData.monthly_payment;
      const annualRate = this.formData.interest_rate / 100;
      const monthlyRate = annualRate / 12;

      // If no interest, simple division
      if (monthlyRate === 0) {
        return Math.ceil(balance / monthlyPayment);
      }

      // If monthly payment doesn't cover interest, return "never"
      const monthlyInterest = balance * monthlyRate;
      if (monthlyPayment <= monthlyInterest) {
        return 'Never (payment too low)';
      }

      // Use amortization formula: n = -log(1 - r*P/M) / log(1 + r)
      // Where P = principal, r = monthly rate, M = monthly payment
      const months = Math.log(1 - (monthlyRate * balance) / monthlyPayment) / Math.log(1 + monthlyRate);
      return Math.ceil(Math.abs(months));
    },

    estimatedYearsToRepay() {
      if (typeof this.estimatedMonthsToRepay === 'string') {
        return this.estimatedMonthsToRepay;
      }
      return (this.estimatedMonthsToRepay / 12).toFixed(1);
    },

    estimatedTotalInterest() {
      if (!this.showRepaymentProjection || typeof this.estimatedMonthsToRepay === 'string') {
        return 0;
      }

      const totalPayable = this.formData.monthly_payment * this.estimatedMonthsToRepay;
      return Math.max(0, totalPayable - this.formData.current_balance);
    },

    estimatedTotalPayable() {
      if (!this.showRepaymentProjection || typeof this.estimatedMonthsToRepay === 'string') {
        return 0;
      }

      return this.formData.monthly_payment * this.estimatedMonthsToRepay;
    },
  },

  watch: {
    liability: {
      immediate: true,
      handler(newLiability) {
        if (newLiability && this.isEditMode) {
          this.populateForm(newLiability);
        }
      },
    },

    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'estate_liability' && fill.fields) {
          // Pre-set critical fields before highlight sequence
          if (fill.fields.liability_type) {
            this.formData.liability_type = fill.fields.liability_type;
          }
          if (fill.fields.liability_name) {
            this.formData.liability_name = fill.fields.liability_name;
          }
          if (fill.fields.current_balance !== undefined) {
            this.formData.current_balance = fill.fields.current_balance;
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
      if (isFilling === false && this.pendingFill?.entityType === 'estate_liability') {
        setTimeout(() => {
          this.handleSubmit();
        }, 250);
      }
    },
  },

  methods: {
    populateForm(liability) {
      this.formData = {
        liability_type: liability.liability_type || '',
        liability_name: liability.liability_name || '',
        current_balance: liability.current_balance || null,
        monthly_payment: liability.monthly_payment || null,
        interest_rate: liability.interest_rate || null,
        maturity_date: liability.maturity_date || '',
        secured_against: liability.secured_against || '',
        is_priority_debt: liability.is_priority_debt || false,
        mortgage_type: liability.mortgage_type || '',
        fixed_until: liability.fixed_until || '',
        notes: liability.notes || '',
      };
    },

    handleLiabilityTypeChange() {
      // Clear mortgage-specific fields if not mortgage
      if (this.formData.liability_type !== 'mortgage') {
        this.formData.mortgage_type = '';
        this.formData.fixed_until = '';
      }

      // Auto-mark certain types as priority debt
      const priorityTypes = ['mortgage', 'secured_loan'];
      if (priorityTypes.includes(this.formData.liability_type)) {
        this.formData.is_priority_debt = true;
      }
    },

    validateForm() {
      // All fields are optional - no validation required
      this.errors = {};
      return true;
    },

    async handleSubmit() {
      if (!this.validateForm()) {
        return;
      }

      this.isSubmitting = true;

      try {
        const payload = {
          ...this.formData,
          id: this.isEditMode ? this.liability.id : undefined,
        };

        this.$emit('save', payload);

        // Reset form if creating new liability
        if (!this.isEditMode) {
          this.resetForm();
        }
      } catch (error) {
        logger.error('Error submitting liability form:', error);
        alert('An error occurred while saving the liability. Please try again.');
      } finally {
        this.isSubmitting = false;
      }
    },

    handleCancel() {
      if (this.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
      this.resetForm();
      this.$emit('cancel');
    },

    resetForm() {
      this.formData = {
        liability_type: '',
        liability_name: '',
        current_balance: null,
        monthly_payment: null,
        interest_rate: null,
        maturity_date: '',
        secured_against: '',
        is_priority_debt: false,
        mortgage_type: '',
        fixed_until: '',
        notes: '',
      };
      this.errors = {};
    },
  },
};
</script>
