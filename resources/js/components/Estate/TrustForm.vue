<template>
  <div class="fixed inset-0 bg-horizon-500 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-medium text-horizon-500">
          {{ isEdit ? 'Edit Trust' : 'Add New Trust' }}
        </h3>
        <button @click="$emit('close')" class="text-horizon-400 hover:text-neutral-500">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Trust Name & Type -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="trust_name" class="block text-sm font-medium text-neutral-500">Trust Name</label>
            <input
              v-model="form.trust_name"
              type="text"
              id="trust_name"
              required
              class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
              placeholder="e.g., Smith Family Trust"
            />
          </div>

          <div>
            <label for="trust_type" class="block text-sm font-medium text-neutral-500">Trust Type</label>
            <select
              v-model="form.trust_type"
              id="trust_type"
              required
              @change="onTrustTypeChange"
              class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
            >
              <option value="">Select trust type...</option>
              <option value="bare">Bare Trust</option>
              <option value="interest_in_possession">Interest in Possession Trust</option>
              <option value="discretionary">Discretionary Trust</option>
              <option value="accumulation_maintenance">Accumulation & Maintenance Trust</option>
              <option value="life_insurance">Life Insurance Trust</option>
              <option value="discounted_gift">Discounted Gift Trust</option>
              <option value="loan">Loan Trust</option>
              <option value="mixed">Mixed Trust</option>
              <option value="settlor_interested">Settlor-Interested Trust</option>
            </select>
          </div>
        </div>

        <!-- Trust Type Information -->
        <div v-if="trustTypeInfo" class="bg-violet-50 border border-violet-200 rounded-md p-4">
          <h4 class="text-sm font-semibold text-violet-900 mb-2">{{ trustTypeInfo.name }}</h4>
          <p class="text-xs text-violet-800 mb-2">{{ trustTypeInfo.description }}</p>
          <p class="text-xs text-violet-700"><strong>Inheritance Tax Treatment:</strong> {{ trustTypeInfo.iht_treatment }}</p>
          <p class="text-xs text-violet-700 mt-1"><strong>Best For:</strong> {{ trustTypeInfo.best_for }}</p>
        </div>

        <!-- Creation Date & Values -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label for="trust_creation_date" class="block text-sm font-medium text-neutral-500">Creation Date</label>
            <input
              v-model="form.trust_creation_date"
              type="date"
              id="trust_creation_date"
              required
              class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
            />
          </div>

          <div>
            <label for="initial_value" class="block text-sm font-medium text-neutral-500">Initial Value (£)</label>
            <input
              v-model.number="form.initial_value"
              type="number"
              id="initial_value"
              required
              min="0"
              step="0.01"
              class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
            />
          </div>

          <div>
            <label for="current_value" class="block text-sm font-medium text-neutral-500">Current Value (£)</label>
            <input
              v-model.number="form.current_value"
              type="number"
              id="current_value"
              required
              min="0"
              step="0.01"
              class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
            />
          </div>
        </div>

        <!-- Discounted Gift Trust Fields -->
        <div v-if="form.trust_type === 'discounted_gift'" class="border border-violet-200 rounded-md p-4 bg-violet-50">
          <h4 class="text-sm font-semibold text-violet-900 mb-3">Discounted Gift Trust Details</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="discount_amount" class="block text-sm font-medium text-neutral-500">Discount Amount (£)</label>
              <input
                v-model.number="form.discount_amount"
                type="number"
                id="discount_amount"
                min="0"
                step="0.01"
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
              />
              <p class="mt-1 text-xs text-neutral-500">Actuarial value of retained income stream</p>
            </div>

            <div>
              <label for="retained_income_annual" class="block text-sm font-medium text-neutral-500">Annual Income (£)</label>
              <input
                v-model.number="form.retained_income_annual"
                type="number"
                id="retained_income_annual"
                min="0"
                step="0.01"
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
              />
              <p class="mt-1 text-xs text-neutral-500">Annual income retained by settlor</p>
            </div>
          </div>
        </div>

        <!-- Loan Trust Fields -->
        <div v-if="form.trust_type === 'loan'" class="border border-spring-200 rounded-md p-4 bg-spring-50">
          <h4 class="text-sm font-semibold text-spring-900 mb-3">Loan Trust Details</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label for="loan_amount" class="block text-sm font-medium text-neutral-500">Loan Amount (£)</label>
              <input
                v-model.number="form.loan_amount"
                type="number"
                id="loan_amount"
                min="0"
                step="0.01"
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
              />
              <p class="mt-1 text-xs text-neutral-500">Outstanding loan balance</p>
            </div>

            <div>
              <label class="flex items-center mt-6">
                <input
                  v-model="form.loan_interest_bearing"
                  type="checkbox"
                  class="rounded border-horizon-300 text-violet-600 shadow-sm focus:border-violet-500 focus:ring-violet-500"
                />
                <span class="ml-2 text-sm text-neutral-500">Interest Bearing</span>
              </label>
            </div>

            <div v-if="form.loan_interest_bearing">
              <label for="loan_interest_rate" class="block text-sm font-medium text-neutral-500">Interest Rate (%)</label>
              <input
                v-model.number="form.loan_interest_rate"
                type="number"
                id="loan_interest_rate"
                min="0"
                max="100"
                step="0.01"
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
              />
            </div>
          </div>
        </div>

        <!-- Life Insurance Trust Fields -->
        <div v-if="form.trust_type === 'life_insurance'" class="border border-violet-200 rounded-md p-4 bg-violet-50">
          <h4 class="text-sm font-semibold text-violet-500 mb-3">Life Insurance Trust Details</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="sum_assured" class="block text-sm font-medium text-neutral-500">Sum Assured (£)</label>
              <input
                v-model.number="form.sum_assured"
                type="number"
                id="sum_assured"
                min="0"
                step="0.01"
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
              />
              <p class="mt-1 text-xs text-neutral-500">Life insurance policy payout</p>
            </div>

            <div>
              <label for="annual_premium" class="block text-sm font-medium text-neutral-500">Annual Premium (£)</label>
              <input
                v-model.number="form.annual_premium"
                type="number"
                id="annual_premium"
                min="0"
                step="0.01"
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
              />
            </div>
          </div>
        </div>

        <!-- Beneficiaries & Trustees -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="beneficiaries" class="block text-sm font-medium text-neutral-500">Beneficiaries</label>
            <textarea
              v-model="form.beneficiaries"
              id="beneficiaries"
              rows="3"
              class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
              placeholder="List beneficiaries..."
            ></textarea>
          </div>

          <div>
            <label for="trustees" class="block text-sm font-medium text-neutral-500">Trustees</label>
            <textarea
              v-model="form.trustees"
              id="trustees"
              rows="3"
              class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
              placeholder="List trustees..."
            ></textarea>
          </div>
        </div>

        <!-- Purpose & Notes -->
        <div>
          <label for="purpose" class="block text-sm font-medium text-neutral-500">Purpose</label>
          <textarea
            v-model="form.purpose"
            id="purpose"
            rows="2"
            class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
            placeholder="Purpose of the trust..."
          ></textarea>
        </div>

        <div>
          <label for="notes" class="block text-sm font-medium text-neutral-500">Notes</label>
          <textarea
            v-model="form.notes"
            id="notes"
            rows="2"
            class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"
            placeholder="Additional notes..."
          ></textarea>
        </div>

        <!-- Active Status (for edit) -->
        <div v-if="isEdit">
          <label class="flex items-center">
            <input
              v-model="form.is_active"
              type="checkbox"
              class="rounded border-horizon-300 text-violet-600 shadow-sm focus:border-violet-500 focus:ring-violet-500"
            />
            <span class="ml-2 text-sm text-neutral-500">Trust is Active</span>
          </label>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="bg-raspberry-50 border border-raspberry-200 rounded-md p-3">
          <p class="text-sm text-raspberry-800">{{ error }}</p>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-3 pt-4 border-t">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-button hover:bg-eggshell-500"
            :disabled="submitting"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 border border-transparent rounded-button hover:bg-raspberry-600 disabled:opacity-50"
            :disabled="submitting"
          >
            {{ submitting ? 'Saving...' : (isEdit ? 'Update Trust' : 'Create Trust') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TrustForm',

  props: {
    trust: {
      type: Object,
      default: null,
    },
  },

  emits: ['save', 'close'],

  data() {
    return {
      form: {
        trust_name: '',
        trust_type: '',
        trust_creation_date: '',
        initial_value: 0,
        current_value: 0,
        discount_amount: null,
        retained_income_annual: null,
        loan_amount: null,
        loan_interest_bearing: false,
        loan_interest_rate: null,
        sum_assured: null,
        annual_premium: null,
        beneficiaries: '',
        trustees: '',
        purpose: '',
        notes: '',
        is_active: true,
      },
      submitting: false,
      error: null,
      trustTypes: {},
    };
  },

  computed: {
    isEdit() {
      return !!this.trust;
    },

    trustTypeInfo() {
      if (!this.form.trust_type || !this.trustTypes[this.form.trust_type]) {
        return null;
      }
      return this.trustTypes[this.form.trust_type];
    },
  },

  mounted() {
    // Load trust types from config (in practice, fetch from API)
    this.loadTrustTypes();

    if (this.trust) {
      // Populate form with existing trust data
      Object.keys(this.form).forEach(key => {
        if (this.trust[key] !== undefined) {
          // Format date fields for HTML5 date inputs
          if (key === 'trust_creation_date') {
            this.form[key] = this.formatDateForInput(this.trust[key]);
          } else {
            this.form[key] = this.trust[key];
          }
        }
      });
    }
  },

  methods: {
    formatDateForInput(date) {
      if (!date) return '';
      try {
        // If it's already in YYYY-MM-DD format, return it
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
          return date;
        }
        // Parse and format the date
        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) return '';
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return '';
      }
    },

    loadTrustTypes() {
      // In a real implementation, fetch from API
      // For now, hardcode the trust types
      this.trustTypes = {
        bare: {
          name: 'Bare Trust',
          description: 'Beneficiary has absolute entitlement to capital and income at age 18',
          iht_treatment: 'Assets count in beneficiary estate, not settlor',
          best_for: 'Passing assets to young people with certainty',
        },
        interest_in_possession: {
          name: 'Interest in Possession Trust',
          description: 'Beneficiary entitled to income, trustees hold capital',
          iht_treatment: 'Qualifying Interest in Possession: counts in life tenant estate',
          best_for: 'Providing income to spouse while preserving capital for children',
        },
        discretionary: {
          name: 'Discretionary Trust',
          description: 'Trustees have full discretion over distributions',
          iht_treatment: 'Relevant property regime - outside settlor estate',
          best_for: 'Flexible planning where beneficiary needs uncertain',
        },
        accumulation_maintenance: {
          name: 'Accumulation & Maintenance Trust',
          description: 'Income accumulated for children, capital distributed at set age',
          iht_treatment: 'Relevant property regime (post-2006)',
          best_for: 'Providing for children during minority',
        },
        life_insurance: {
          name: 'Life Insurance Trust',
          description: 'Life insurance policy written in trust',
          iht_treatment: 'Policy proceeds outside estate if written in trust from inception',
          best_for: 'Providing liquid funds to pay Inheritance Tax liability',
        },
        discounted_gift: {
          name: 'Discounted Gift Trust',
          description: 'Gift to trust with retained income stream',
          iht_treatment: 'Actuarial discount applied - only retained income counts in estate',
          best_for: 'Reducing estate while retaining income',
        },
        loan: {
          name: 'Loan Trust',
          description: 'Interest-free loan to trust, growth accrues outside estate',
          iht_treatment: 'Loan remains in estate, growth is outside estate immediately',
          best_for: 'Freezing estate value while maintaining access to capital',
        },
        mixed: {
          name: 'Mixed Trust',
          description: 'Combination of trust types',
          iht_treatment: 'Different parts taxed according to applicable rules',
          best_for: 'Complex estate planning with multiple objectives',
        },
        settlor_interested: {
          name: 'Settlor-Interested Trust',
          description: 'Settlor or spouse can benefit',
          iht_treatment: 'Counts in settlor estate (reservation of benefit)',
          best_for: 'Limited use - potential for reservation of benefit issues',
        },
      };
    },

    onTrustTypeChange() {
      // Reset type-specific fields when trust type changes
      this.form.discount_amount = null;
      this.form.retained_income_annual = null;
      this.form.loan_amount = null;
      this.form.loan_interest_bearing = false;
      this.form.loan_interest_rate = null;
      this.form.sum_assured = null;
      this.form.annual_premium = null;
    },

    validateForm() {
      if (!this.form.trust_name) {
        this.error = 'Trust name is required';
        return false;
      }

      if (!this.form.trust_type) {
        this.error = 'Trust type is required';
        return false;
      }

      if (!this.form.trust_creation_date) {
        this.error = 'Trust creation date is required';
        return false;
      }

      if (this.form.initial_value < 0 || this.form.current_value < 0) {
        this.error = 'Values must be non-negative';
        return false;
      }

      this.error = null;
      return true;
    },

    submitForm() {
      if (!this.validateForm()) {
        return;
      }

      this.submitting = true;
      this.error = null;

      // Emit save event with form data
      this.$emit('save', { ...this.form });
    },
  },
};
</script>

<style scoped>
/* Additional styles if needed */
</style>
