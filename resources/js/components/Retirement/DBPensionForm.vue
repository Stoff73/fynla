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
                <option v-for="option in schemeStatusOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <p class="mt-1 text-xs text-neutral-500">
                Tells us whether this pension is being paid to you now, which decides
                whether it counts towards your income today.
              </p>
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
                <option v-for="option in schemeTypeOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
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

          <!-- Normal Retirement Age and Spouse Pension -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'normal_retirement_age' }">
              <label for="normal_retirement_age" class="block text-sm font-medium text-neutral-500 mb-2">
                Normal Retirement Age
              </label>
              <input
                id="normal_retirement_age"
                v-model.number="formData.normal_retirement_age"
                type="number"
                min="55"
                max="75"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 60"
              />
              <p class="text-xs text-neutral-500 mt-1">The age this scheme pays out in full</p>
            </div>
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'spouse_pension_percent' }">
              <label for="spouse_pension_percent" class="block text-sm font-medium text-neutral-500 mb-2">
                Spouse Pension (%)
              </label>
              <input
                id="spouse_pension_percent"
                v-model.number="formData.spouse_pension_percent"
                type="number"
                step="0.01"
                min="0"
                max="100"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 50"
              />
              <p class="text-xs text-neutral-500 mt-1">Percentage paid to your spouse after your death</p>
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

          <!-- Inflation Protection -->
          <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'inflation_protection' }">
            <label for="inflation_protection" class="block text-sm font-medium text-neutral-500 mb-2">
              Inflation Protection
            </label>
            <select
              id="inflation_protection"
              v-model="formData.inflation_protection"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            >
              <option v-for="option in inflationProtectionOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <p class="text-xs text-neutral-500 mt-1">How the scheme increases your pension before it comes into payment</p>
          </div>

          <!-- Fixed Revaluation Rate -->
          <div v-if="formData.inflation_protection === 'fixed'">
            <label for="revaluation_rate" class="block text-sm font-medium text-neutral-500 mb-2">
              Fixed Revaluation Rate (% a year)
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
import {
  DB_SCHEME_TYPE_OPTIONS,
  DB_SCHEME_STATUS_OPTIONS,
  DB_INFLATION_PROTECTION_OPTIONS,
  buildDbPensionPayload,
} from './dbPensionFields';

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
        normal_retirement_age: null,
        spouse_pension_percent: null,
        accrual_rate: null,
        inflation_protection: 'none',
        revaluation_rate: null,
        pcls_available: null,
        notes: '',
      },
      schemeTypeOptions: DB_SCHEME_TYPE_OPTIONS,
      schemeStatusOptions: DB_SCHEME_STATUS_OPTIONS,
      inflationProtectionOptions: DB_INFLATION_PROTECTION_OPTIONS,
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
          // Editing an existing pension. The record arrives in `db_pensions`
          // column names, which are NOT this form's field names — spreading it
          // straight onto formData left every input bound to an undefined key,
          // so the edit form opened blank and then refused to submit.
          this.formData = {
            ...this.formData,
            employer_name: newPension.scheme_name ?? '',
            scheme_type: newPension.scheme_type ?? 'final_salary',
            // Persists since W-0032, so an edit restores it rather than opening
            // blank and silently clearing what the user last answered.
            scheme_status: newPension.scheme_status ?? '',
            annual_income: this.toNumber(newPension.accrued_annual_pension),
            service_years: this.toNumber(newPension.pensionable_service_years),
            final_salary: this.toNumber(newPension.pensionable_salary),
            normal_retirement_age: this.toNumber(newPension.normal_retirement_age),
            spouse_pension_percent: this.toNumber(newPension.spouse_pension_percent),
            inflation_protection: newPension.inflation_protection ?? 'none',
            revaluation_rate: this.parseRevaluationRate(newPension.revaluation_method),
            pcls_available: this.toNumber(newPension.lump_sum_entitlement),
            notes: newPension.notes ?? '',
          };
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
    /** null/'' stay null so an untouched optional field posts null, not 0. */
    toNumber(value) {
      if (value === null || value === undefined || value === '') return null;
      const parsed = Number(value);
      return Number.isNaN(parsed) ? null : parsed;
    },

    /** '2.5%' -> 2.5. `revaluation_method` is stored as the formatted string. */
    parseRevaluationRate(revaluationMethod) {
      if (!revaluationMethod) return null;
      const match = String(revaluationMethod).match(/(\d+(?:\.\d+)?)/);
      return match ? Number(match[1]) : null;
    },

    handleSubmit() {
      // Basic validation
      if (!this.formData.employer_name) {
        alert('Please enter an employer/scheme name');
        return;
      }

      // W-0032 gave scheme status a column, so an edit now restores it and the
      // requirement can apply to both paths. Records saved before the column
      // existed have nothing to restore, so an edit of one is still allowed
      // through — blocking it would trap the user on a pension they already have.
      if (!this.formData.scheme_status && !(this.isEdit && !this.pension?.scheme_status)) {
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

      this.$emit('save', buildDbPensionPayload({
        schemeName: this.formData.employer_name,
        schemeType: this.formData.scheme_type,
        schemeStatus: this.formData.scheme_status,
        annualIncome: this.formData.annual_income,
        serviceYears: this.formData.service_years,
        pensionableSalary: this.formData.final_salary,
        normalRetirementAge: this.formData.normal_retirement_age,
        spousePensionPercent: this.formData.spouse_pension_percent,
        inflationProtection: this.formData.inflation_protection,
        revaluationRate: this.formData.revaluation_rate,
        lumpSum: this.formData.pcls_available,
      }));
    },
  },
};
</script>

<style scoped>
.fixed {
  animation: fadeIn 0.3s ease-out;
}

</style>
