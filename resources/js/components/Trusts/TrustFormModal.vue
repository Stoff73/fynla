<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
      <div class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"></div>

      <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-2xl font-bold text-horizon-500">
            {{ trust ? 'Edit Trust' : 'Create New Trust' }}
          </h2>
          <button
            @click="handleClose"
            class="text-horizon-400 hover:text-neutral-500"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
          <p class="text-red-800 text-sm">{{ error }}</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit">
          <div class="space-y-4">
            <!-- Trust Name -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'trust_name' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Trust Name
              </label>
              <input
                v-model="formData.trust_name"
                type="text"
                required
                class="input-field"
                placeholder="e.g., Smith Family Discretionary Trust"
              />
            </div>

            <!-- Trust Type -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'trust_type' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Trust Type
              </label>
              <select
                v-model="formData.trust_type"
                required
                class="input-field"
              >
                <option value="">Select trust type</option>
                <option value="bare">Bare Trust</option>
                <option value="interest_in_possession">Interest in Possession</option>
                <option value="discretionary">Discretionary Trust</option>
                <option value="accumulation_maintenance">Accumulation & Maintenance</option>
                <option value="life_insurance">Life Insurance Trust</option>
                <option value="discounted_gift">Discounted Gift Trust</option>
                <option value="loan">Loan Trust</option>
                <option value="mixed">Mixed Trust</option>
                <option value="settlor_interested">Settlor-Interested Trust</option>
                <option value="other">Other</option>
              </select>
            </div>

            <!-- Other Trust Type Fields (shown when 'other' is selected) -->
            <div v-if="formData.trust_type === 'other'" class="space-y-4 p-4 bg-eggshell-500 rounded-lg border border-light-gray">
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Trust Type Description                </label>
                <input
                  v-model="formData.other_type_description"
                  type="text"
                  required
                  class="input-field"
                  placeholder="e.g., Offshore Asset Protection Trust"
                />
                <p class="mt-1 text-xs text-neutral-500">Please describe the type of trust</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Country                </label>
                <select
                  v-model="formData.country"
                  required
                  class="input-field"
                >
                  <option value="">Select country</option>
                  <option value="United Kingdom">United Kingdom</option>
                  <option value="United States">United States</option>
                  <option value="Ireland">Ireland</option>
                  <option value="Jersey">Jersey</option>
                  <option value="Guernsey">Guernsey</option>
                  <option value="Isle of Man">Isle of Man</option>
                  <option value="Gibraltar">Gibraltar</option>
                  <option value="Cayman Islands">Cayman Islands</option>
                  <option value="British Virgin Islands">British Virgin Islands</option>
                  <option value="Luxembourg">Luxembourg</option>
                  <option value="Switzerland">Switzerland</option>
                  <option value="Singapore">Singapore</option>
                  <option value="Hong Kong">Hong Kong</option>
                  <option value="New Zealand">New Zealand</option>
                  <option value="Australia">Australia</option>
                  <option value="Canada">Canada</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div v-if="formData.country === 'other'">
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Other Country                </label>
                <input
                  v-model="formData.other_country"
                  type="text"
                  required
                  class="input-field"
                  placeholder="Enter country name"
                />
              </div>
            </div>

            <!-- Creation Date and Initial Value -->
            <div class="grid grid-cols-2 gap-4">
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'trust_creation_date' }">
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Creation Date
                </label>
                <input
                  v-model="formData.trust_creation_date"
                  type="date"
                  required
                  class="input-field"
                />
              </div>
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'initial_value' }">
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Initial Value
                </label>
                <input
                  v-model.number="formData.initial_value"
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  class="input-field"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Current Value -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'current_value' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Current Value
              </label>
              <input
                v-model.number="formData.current_value"
                type="number"
                step="0.01"
                min="0"
                required
                class="input-field"
                placeholder="0.00"
              />
            </div>

            <!-- Beneficiaries -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Beneficiaries
              </label>
              <textarea
                v-model="formData.beneficiaries"
                rows="2"
                class="input-field"
                placeholder="List beneficiaries..."
              ></textarea>
            </div>

            <!-- Trustees -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Trustees
              </label>
              <textarea
                v-model="formData.trustees"
                rows="2"
                class="input-field"
                placeholder="List trustees..."
              ></textarea>
            </div>

            <!-- Settlor -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'settlor' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Settlor
              </label>
              <input
                v-model="formData.settlor"
                type="text"
                class="input-field"
                placeholder="e.g., John Smith"
              />
              <p class="mt-1 text-xs text-neutral-500">The person who created/funded the trust</p>
            </div>

            <!-- Purpose -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Purpose
              </label>
              <textarea
                v-model="formData.purpose"
                rows="2"
                class="input-field"
                placeholder="Purpose of the trust..."
              ></textarea>
            </div>

            <!-- Is Active -->
            <div class="flex items-center">
              <input
                v-model="formData.is_active"
                type="checkbox"
                id="is_active"
                class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-horizon-300 rounded"
              />
              <label for="is_active" class="ml-2 block text-sm text-neutral-500">
                Trust is currently active
              </label>
            </div>
          </div>

          <!-- Actions -->
          <div class="mt-6 flex justify-end space-x-3">
            <button
              type="button"
              @click="handleClose"
              class="btn-secondary"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="btn-primary"
            >
              {{ submitting ? 'Saving...' : (trust ? 'Update Trust' : 'Create Trust') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
  name: 'TrustFormModal',
  emits: ['save', 'close'],

  props: {
    trust: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      submitting: false,
      error: null,
      formData: {
        trust_name: '',
        trust_type: '',
        other_type_description: '',
        country: '',
        other_country: '',
        trust_creation_date: '',
        initial_value: 0,
        current_value: 0,
        beneficiaries: '',
        trustees: '',
        settlor: '',
        purpose: '',
        is_active: true,
      },
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

    predefinedCountries() {
      return [
        'United Kingdom', 'United States', 'Ireland', 'Jersey', 'Guernsey',
        'Isle of Man', 'Gibraltar', 'Cayman Islands', 'British Virgin Islands',
        'Luxembourg', 'Switzerland', 'Singapore', 'Hong Kong', 'New Zealand',
        'Australia', 'Canada'
      ];
    },
  },

  watch: {
    'formData.trust_type'(newType) {
      // Clear other fields when switching away from "other"
      if (newType !== 'other') {
        this.formData.other_type_description = '';
        this.formData.country = '';
        this.formData.other_country = '';
      }
    },

    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'trust' && fill.fields) {
          // Pre-set trust_name and trust_type before field sequence (Vue reactivity)
          if (fill.fields.trust_name) {
            this.formData.trust_name = fill.fields.trust_name;
          }
          if (fill.fields.trust_type) {
            this.formData.trust_type = fill.fields.trust_type;
          }
          this.$nextTick(() => {
            const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
            this.$store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
          });
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
      if (isFilling === false && this.pendingFill?.entityType === 'trust') {
        setTimeout(() => {
          this.handleSubmit();
        }, 250);
      }
    },
  },

  mounted() {
    if (this.trust) {
      this.formData = { ...this.trust };
      // Handle custom country when editing
      if (this.trust.country && !this.predefinedCountries.includes(this.trust.country)) {
        this.formData.other_country = this.trust.country;
        this.formData.country = 'other';
      }
    }
  },

  methods: {
    handleClose() {
      if (this.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
      this.$emit('close');
    },

    async handleSubmit() {
      this.submitting = true;
      this.error = null;

      try {
        // Ensure numeric fields are actually numbers, not null
        if (this.formData.initial_value === null || this.formData.initial_value === '') {
          this.formData.initial_value = 0;
        }
        if (this.formData.current_value === null || this.formData.current_value === '') {
          this.formData.current_value = 0;
        }

        // Prepare data for submission
        const submitData = { ...this.formData };

        // Handle "other" country selection
        if (submitData.country === 'other' && submitData.other_country) {
          submitData.country = submitData.other_country;
        }
        delete submitData.other_country;

        // Clear other fields if not "other" type
        if (submitData.trust_type !== 'other') {
          submitData.other_type_description = null;
          submitData.country = null;
        }

        this.$emit('save', submitData);
      } catch (err) {
        this.error = err.message || 'An error occurred';
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
