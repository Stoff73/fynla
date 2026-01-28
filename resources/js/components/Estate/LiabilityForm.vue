<template>
  <div class="liability-form">
    <div class="form-header">
      <h3>{{ isEditMode ? 'Edit Liability' : 'Add New Liability' }}</h3>
      <p class="subtitle">Track debts and liabilities for estate planning and net worth calculation</p>
    </div>

    <form @submit.prevent="handleSubmit">
      <!-- Liability Type -->
      <div class="form-group">
        <label for="liability_type">Liability Type</label>
        <select
          id="liability_type"
          v-model="formData.liability_type"
          class="form-control"
          :class="{ 'is-invalid': errors.liability_type }"
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
        <span v-if="errors.liability_type" class="error-message">
          {{ errors.liability_type }}
        </span>
      </div>

      <!-- Liability Name -->
      <div class="form-group">
        <label for="liability_name">Liability Name / Description</label>
        <input
          id="liability_name"
          v-model="formData.liability_name"
          type="text"
          class="form-control"
          :class="{ 'is-invalid': errors.liability_name }"
          :placeholder="liabilityNamePlaceholder"
        />
        <span v-if="errors.liability_name" class="error-message">
          {{ errors.liability_name }}
        </span>
      </div>

      <!-- Current Balance -->
      <div class="form-group">
        <label for="current_balance">Current Balance Owed (£)</label>
        <div class="input-with-icon">
          <span class="input-icon">£</span>
          <input
            id="current_balance"
            v-model.number="formData.current_balance"
            type="number"
            class="form-control with-icon"
            :class="{ 'is-invalid': errors.current_balance }"
            placeholder="0"
            min="0"
            step="0.01"
          />
        </div>
        <span v-if="errors.current_balance" class="error-message">
          {{ errors.current_balance }}
        </span>
      </div>

      <!-- Monthly Payment -->
      <div class="form-group">
        <label for="monthly_payment">Monthly Payment (£)</label>
        <div class="input-with-icon">
          <span class="input-icon">£</span>
          <input
            id="monthly_payment"
            v-model.number="formData.monthly_payment"
            type="number"
            class="form-control with-icon"
            placeholder="0"
            min="0"
            step="0.01"
          />
        </div>
        <small class="form-text">
          Regular monthly payment amount (if applicable)
        </small>
      </div>

      <!-- Interest Rate -->
      <div class="form-group">
        <label for="interest_rate">Interest Rate (% per annum)</label>
        <div class="input-with-icon">
          <span class="input-icon">%</span>
          <input
            id="interest_rate"
            v-model.number="formData.interest_rate"
            type="number"
            class="form-control with-icon"
            :class="{ 'is-invalid': errors.interest_rate }"
            placeholder="0.00"
            min="0"
            max="100"
            step="0.01"
          />
        </div>
        <span v-if="errors.interest_rate" class="error-message">
          {{ errors.interest_rate }}
        </span>
      </div>

      <!-- Maturity Date -->
      <div class="form-group">
        <label for="maturity_date">Maturity / End Date</label>
        <input
          id="maturity_date"
          v-model="formData.maturity_date"
          type="date"
          class="form-control"
          :min="todayDate"
        />
        <small class="form-text">
          Expected date when this liability will be fully repaid
        </small>
      </div>

      <!-- Secured Against Asset -->
      <div class="form-group">
        <label for="secured_against">Secured Against Asset (Optional)</label>
        <input
          id="secured_against"
          v-model="formData.secured_against"
          type="text"
          class="form-control"
          placeholder="e.g., Main Residence, Investment Property"
        />
        <small class="form-text">
          Specify if this liability is secured against a particular asset
        </small>
      </div>

      <!-- Priority for Repayment -->
      <div class="form-group">
        <div class="checkbox-group">
          <input
            id="is_priority_debt"
            v-model="formData.is_priority_debt"
            type="checkbox"
            class="form-checkbox"
          />
          <label for="is_priority_debt" class="checkbox-label">
            Priority Debt
          </label>
        </div>
        <small class="form-text">
          {{ priorityDebtDescription }}
        </small>
      </div>

      <!-- Conditional: Mortgage-specific fields -->
      <div v-if="formData.liability_type === 'mortgage'" class="conditional-fields">
        <h4 class="section-title">Mortgage Details</h4>

        <div class="form-row">
          <div class="form-group half-width">
            <label for="mortgage_type">Mortgage Type</label>
            <select
              id="mortgage_type"
              v-model="formData.mortgage_type"
              class="form-control"
            >
              <option value="">Select type...</option>
              <option value="repayment">Repayment</option>
              <option value="interest_only">Interest Only</option>
              <option value="fixed_rate">Fixed Rate</option>
              <option value="variable_rate">Variable Rate</option>
              <option value="tracker">Tracker</option>
            </select>
          </div>

          <div class="form-group half-width">
            <label for="fixed_until">Fixed Rate Until</label>
            <input
              id="fixed_until"
              v-model="formData.fixed_until"
              type="date"
              class="form-control"
            />
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="form-group">
        <label for="notes">Additional Notes (Optional)</label>
        <textarea
          id="notes"
          v-model="formData.notes"
          class="form-control"
          rows="3"
          placeholder="Any additional information about this liability..."
        ></textarea>
      </div>

      <!-- Repayment Projection (if monthly payment provided) -->
      <div v-if="showRepaymentProjection" class="repayment-projection">
        <div class="projection-header">
          <i class="fas fa-calculator"></i>
          <span>Estimated Repayment Timeline</span>
        </div>
        <div class="projection-content">
          <div class="projection-row">
            <span class="projection-label">Estimated Time to Repay:</span>
            <span class="projection-value">{{ estimatedMonthsToRepay }} months ({{ estimatedYearsToRepay }} years)</span>
          </div>
          <div class="projection-row">
            <span class="projection-label">Total Interest:</span>
            <span class="projection-value">{{ formatCurrency(estimatedTotalInterest) }}</span>
          </div>
          <div class="projection-row">
            <span class="projection-label">Total Amount Payable:</span>
            <span class="projection-value">{{ formatCurrency(estimatedTotalPayable) }}</span>
          </div>
        </div>
        <small class="projection-note">
          * Estimates assume fixed interest rate and regular monthly payments
        </small>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" @click="handleCancel">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
          <i v-if="!isSubmitting" class="fas fa-save"></i>
          <i v-else class="fas fa-spinner fa-spin"></i>
          {{ isSubmitting ? 'Saving...' : (isEditMode ? 'Update Liability' : 'Add Liability') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

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
        console.error('Error submitting liability form:', error);
        alert('An error occurred while saving the liability. Please try again.');
      } finally {
        this.isSubmitting = false;
      }
    },

    handleCancel() {
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

<style scoped>
.liability-form {
  background: white;
  border-radius: 8px;
  padding: 24px;
  max-height: 90vh;
  overflow-y: auto;
}

.form-header {
  margin-bottom: 24px;
  padding-bottom: 16px;
  @apply border-b-2 border-gray-200;
}

.form-header h3 {
  font-size: 20px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0 0 8px 0;
}

.subtitle {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-gray-700;
  margin: 24px 0 16px 0;
  padding-bottom: 8px;
  @apply border-b border-gray-200;
}

.form-group {
  margin-bottom: 20px;
}

.form-row {
  display: flex;
  gap: 16px;
}

.half-width {
  flex: 1;
}

label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  @apply text-gray-700;
  margin-bottom: 6px;
}

label.required::after {
  content: ' *';
  @apply text-red-500;
}

.form-control {
  width: 100%;
  padding: 10px 12px;
  font-size: 14px;
  @apply border border-gray-300;
  border-radius: 6px;
  transition: border-colour 0.2s;
}

.form-control:focus {
  outline: none;
  @apply border-primary-500;
  @apply ring-2 ring-primary-500/20;
}

.form-control.is-invalid {
  @apply border-red-500;
}

.form-control.is-invalid:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.input-with-icon {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  @apply text-gray-500;
  font-weight: 500;
  pointer-events: none;
}

.form-control.with-icon {
  padding-left: 32px;
}

.error-message {
  display: block;
  margin-top: 6px;
  font-size: 13px;
  @apply text-red-500;
}

.form-text {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  @apply text-gray-500;
  line-height: 1.4;
}

select.form-control {
  cursor: pointer;
}

textarea.form-control {
  resize: vertical;
  min-height: 80px;
}

.checkbox-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.form-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.checkbox-label {
  font-size: 14px;
  @apply text-gray-700;
  cursor: pointer;
  margin: 0;
}

.conditional-fields {
  margin-top: 24px;
  padding: 20px;
  @apply bg-gray-50;
  border-radius: 6px;
  @apply border border-gray-200;
}

.repayment-projection {
  margin: 20px 0;
  padding: 16px;
  @apply bg-blue-50;
  @apply border-l-4 border-primary-500;
  border-radius: 4px;
}

.projection-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  @apply text-blue-800;
  margin-bottom: 12px;
  font-size: 14px;
}

.projection-content {
  margin-bottom: 8px;
}

.projection-row {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 14px;
}

.projection-label {
  @apply text-blue-900;
  font-weight: 500;
}

.projection-value {
  @apply text-blue-800;
  font-weight: 600;
}

.projection-note {
  display: block;
  font-size: 11px;
  @apply text-blue-900;
  font-style: italic;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
  padding-top: 20px;
  @apply border-t border-gray-200;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  @apply bg-primary-500;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  @apply bg-blue-600;
}

.btn-secondary {
  @apply bg-gray-200;
  @apply text-gray-700;
}

.btn-secondary:hover {
  @apply bg-gray-300;
}

@media (max-width: 768px) {
  .form-row {
    flex-direction: column;
  }

  .half-width {
    width: 100%;
  }
}
</style>
