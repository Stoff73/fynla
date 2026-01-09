<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" @click.self="$emit('close')">
    <div class="flex items-center justify-center min-h-screen px-4">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

      <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-2xl font-bold text-gray-900">
            {{ trust ? 'Edit Trust' : 'Create New Trust' }}
          </h2>
          <button
            @click="$emit('close')"
            class="text-gray-400 hover:text-gray-600"
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
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
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
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
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
              </select>
            </div>

            <!-- Creation Date and Initial Value -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Creation Date
                </label>
                <input
                  v-model="formData.trust_creation_date"
                  type="date"
                  required
                  class="input-field"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
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
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
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
              <label class="block text-sm font-medium text-gray-700 mb-1">
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
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Trustees
              </label>
              <textarea
                v-model="formData.trustees"
                rows="2"
                class="input-field"
                placeholder="List trustees..."
              ></textarea>
            </div>

            <!-- Purpose -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
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
                class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded"
              />
              <label for="is_active" class="ml-2 block text-sm text-gray-700">
                Trust is currently active
              </label>
            </div>
          </div>

          <!-- Actions -->
          <div class="mt-6 flex justify-end space-x-3">
            <button
              type="button"
              @click="$emit('close')"
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
export default {
  name: 'TrustFormModal',

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
        trust_creation_date: '',
        initial_value: 0,
        current_value: 0,
        beneficiaries: '',
        trustees: '',
        purpose: '',
        is_active: true,
      },
    };
  },

  mounted() {
    if (this.trust) {
      this.formData = { ...this.trust };
    }
  },

  methods: {
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

        this.$emit('save', this.formData);
      } catch (err) {
        this.error = err.message || 'An error occurred';
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
