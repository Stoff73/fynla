<template>
  <div :class="context === 'onboarding' ? '' : 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4'">
    <div :class="context === 'onboarding' ? '' : 'bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto scrollbar-thin'">
      <!-- Header -->
      <div :class="context === 'onboarding' ? 'mb-4' : 'sticky top-0 bg-white border-b border-light-gray px-6 py-4 flex items-center justify-between'">
        <h3 class="text-xl font-semibold text-horizon-500">
          {{ isEdit ? 'Edit' : 'Add' }} Defined Benefit Pension
        </h3>
        <button
          v-if="context !== 'onboarding'"
          @click="$emit('close')"
          class="text-horizon-400 hover:text-neutral-500 transition-colors"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Important Warning -->
      <div v-if="context !== 'onboarding'" class="mx-6 mt-6 bg-savannah-100 rounded-lg p-4 flex items-start">
        <svg class="w-6 h-6 text-violet-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <div>
          <p class="text-sm font-bold text-violet-900">Important Notice About Defined Benefit Pensions</p>
          <p class="text-sm text-violet-800 mt-2">
            Defined Benefit pension information is captured for <strong>income projection only</strong>.
            This system does <strong>not provide Defined Benefit to Defined Contribution transfer advice</strong>.
            Defined Benefit pension transfers are complex and may not be suitable.
            You should seek specialist financial advice before considering any transfer.
          </p>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" :class="context === 'onboarding' ? '' : 'p-6'">
        <div class="space-y-6">
          <!-- Employer Name -->
          <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'employer_name' }">
            <label for="employer_name" class="block text-sm font-medium text-neutral-500 mb-2">
              Employer / Scheme Name
            </label>
            <input
              id="employer_name"
              v-model="formData.employer_name"
              type="text"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., NHS Pension Scheme"
            />
          </div>

          <!-- Scheme Status and Type -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="scheme_status" class="block text-sm font-medium text-neutral-500 mb-2">
                Scheme Status
              </label>
              <select
                id="scheme_status"
                v-model="formData.scheme_status"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="">Select status</option>
                <option value="Active">Active</option>
                <option value="Deferred">Deferred</option>
                <option value="In Payment">In Payment</option>
              </select>
            </div>
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'scheme_type' }">
              <label for="scheme_type" class="block text-sm font-medium text-neutral-500 mb-2">
                Scheme Type
              </label>
              <select
                id="scheme_type"
                v-model="formData.scheme_type"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="final_salary">Final Salary</option>
                <option value="career_average">Career Average (CARE)</option>
              </select>
            </div>
          </div>

          <!-- Annual Income -->
          <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'annual_income' }">
            <label for="annual_income" class="block text-sm font-medium text-neutral-500 mb-2">
              Annual Income at Retirement (£)
            </label>
            <input
              id="annual_income"
              v-model.number="formData.annual_income"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 15000.00"
            />
            <p class="text-xs text-neutral-500 mt-1">This should be the projected annual pension at your normal retirement age</p>
          </div>

          <!-- Service Years and Final/Pensionable Salary -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'service_years' }">
              <label for="service_years" class="block text-sm font-medium text-neutral-500 mb-2">
                Service Years
              </label>
              <input
                id="service_years"
                v-model.number="formData.service_years"
                type="number"
                step="0.1"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 20.5"
              />
            </div>
            <div>
              <label for="final_salary" class="block text-sm font-medium text-neutral-500 mb-2">
                Pensionable Salary (£)
              </label>
              <input
                id="final_salary"
                v-model.number="formData.final_salary"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 50000.00"
              />
            </div>
          </div>

          <!-- Accrual Rate -->
          <div>
            <label for="accrual_rate" class="block text-sm font-medium text-neutral-500 mb-2">
              Accrual Rate (1/X)
            </label>
            <input
              id="accrual_rate"
              v-model.number="formData.accrual_rate"
              type="number"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 60 (for 1/60th)"
            />
            <p class="text-xs text-neutral-500 mt-1">Common: 60 (public sector), 80 (older schemes)</p>
          </div>

          <!-- Revaluation Rate -->
          <div>
            <label for="revaluation_rate" class="block text-sm font-medium text-neutral-500 mb-2">
              Revaluation Rate (% p.a.)
            </label>
            <input
              id="revaluation_rate"
              v-model.number="formData.revaluation_rate"
              type="number"
              step="0.01"
              min="0"
              max="10"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 2.50"
            />
            <p class="text-xs text-neutral-500 mt-1">For CARE schemes - typical: CPI, CPI+1.5%, or fixed %</p>
          </div>

          <!-- PCLS Available -->
          <div>
            <label for="pcls_available" class="block text-sm font-medium text-neutral-500 mb-2">
              Pension Commencement Lump Sum (PCLS) Available (£)
            </label>
            <input
              id="pcls_available"
              v-model.number="formData.pcls_available"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 50000.00"
            />
            <p class="text-xs text-neutral-500 mt-1">Tax-free lump sum available at retirement (if applicable)</p>
          </div>

          <!-- Notes -->
          <div>
            <label for="notes" class="block text-sm font-medium text-neutral-500 mb-2">
              Notes
            </label>
            <textarea
              id="notes"
              v-model="formData.notes"
              rows="3"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="Any additional notes about this pension..."
            ></textarea>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-light-gray">
          <button
            type="button"
            @click="$emit('close')"
            :class="context === 'onboarding'
              ? 'px-4 py-2 bg-light-pink-100 hover:bg-light-pink-200 text-horizon-500 rounded-lg transition-colors duration-200 text-sm font-medium'
              : 'px-4 py-2 text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-savannah-100 transition-colors duration-200'"
          >
            Cancel
          </button>
          <button
            type="submit"
            :class="context === 'onboarding'
              ? 'px-6 py-2 bg-raspberry-500 text-white rounded-lg hover:bg-raspberry-600 transition-colors duration-200 text-sm font-medium'
              : 'px-6 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition-colors duration-200'"
          >
            {{ context === 'onboarding' ? 'Save' : (isEdit ? 'Update' : 'Add') + ' Pension' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';

export default {
  name: 'DBPensionForm',

  emits: ['save', 'close'],

  props: {
    pension: {
      type: Object,
      default: null,
    },
    context: {
      type: String,
      default: 'standalone',
    },
  },

  data() {
    return {
      formData: {
        employer_name: '',
        scheme_status: '',
        scheme_type: 'final_salary',
        annual_income: null,
        service_years: null,
        final_salary: null,
        accrual_rate: null,
        revaluation_rate: null,
        pcls_available: null,
        notes: '',
      },
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),
    ...mapGetters('auth', ['currentUser']),

    isEdit() {
      return !!this.pension;
    },
  },

  watch: {
    pension: {
      immediate: true,
      handler(newPension) {
        if (newPension) {
          // Editing existing pension - populate form with pension data
          this.formData = { ...newPension };
        }
      },
    },
    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'db_pension' && fill.fields) {
          // Pre-set select dropdowns and required fields before field sequence
          // (Vue <select> v-model may not react to programmatic changes during animation)
          if (fill.fields.scheme_status) {
            this.formData.scheme_status = fill.fields.scheme_status;
          }
          if (fill.fields.scheme_type) {
            this.formData.scheme_type = fill.fields.scheme_type;
          }
          if (fill.fields.employer_name) {
            this.formData.employer_name = fill.fields.employer_name;
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
      if (isFilling === false && this.pendingFill?.entityType === 'db_pension') {
        this._fillTimer = setTimeout(() => {
          this.handleSubmit();
        }, 250);
      }
    },
  },

  beforeUnmount() {
    if (this._fillTimer) clearTimeout(this._fillTimer);
  },

  mounted() {
    // Watcher handles form population, mounted just ensures currentUser is available
  },

  methods: {
    handleSubmit() {
      // Basic validation
      if (!this.formData.employer_name) {
        alert('Please enter an employer/scheme name');
        return;
      }

      if (!this.formData.scheme_status) {
        alert('Please select a scheme status');
        return;
      }

      if (!this.formData.annual_income || this.formData.annual_income < 0) {
        alert('Please enter a valid annual income');
        return;
      }

      if (!this.formData.service_years || this.formData.service_years < 0) {
        alert('Please enter valid service years');
        return;
      }

      // Map form fields to API field names
      const apiData = {
        scheme_name: this.formData.employer_name,
        scheme_type: this.formData.scheme_type,
        accrued_annual_pension: this.formData.annual_income,
        pensionable_service_years: this.formData.service_years,
        pensionable_salary: this.formData.final_salary,
        revaluation_method: this.formData.revaluation_rate ? `${this.formData.revaluation_rate}%` : null,
        lump_sum_entitlement: this.formData.pcls_available,
        // Map accrual_rate if needed by backend
        // inflation_protection can be added if form has it
      };

      this.$emit('save', apiData);
    },
  },
};
</script>

<style scoped>
.fixed {
  animation: fadeIn 0.3s ease-out;
}

</style>
