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
              {{ isEditMode ? 'Edit Investment Goal' : 'Add New Investment Goal' }}
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
            <!-- Goal Name -->
            <div>
              <label for="goal_name" class="block text-sm font-medium text-neutral-500 mb-1">
                Goal Name
              </label>
              <input
                id="goal_name"
                v-model="formData.goal_name"
                type="text"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                :class="{ 'border-raspberry-500': errors.goal_name }"
                placeholder="e.g., Retirement Fund, House Deposit, Children's Education"
              />
              <p v-if="errors.goal_name" class="mt-1 text-sm text-raspberry-600">{{ errors.goal_name }}</p>
            </div>

            <!-- Target Amount and Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="target_amount" class="block text-sm font-medium text-neutral-500 mb-1">
                  Target Amount (£)
                </label>
                <input
                  id="target_amount"
                  v-model.number="formData.target_amount"
                  type="number"
                  step="1000"
                  min="0"
                  class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                  :class="{ 'border-raspberry-500': errors.target_amount }"
                  placeholder="e.g., 500000"
                />
                <p v-if="errors.target_amount" class="mt-1 text-sm text-raspberry-600">{{ errors.target_amount }}</p>
              </div>
              <div>
                <label for="target_date" class="block text-sm font-medium text-neutral-500 mb-1">
                  Target Date
                </label>
                <input
                  id="target_date"
                  v-model="formData.target_date"
                  type="date"
                  class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                  :class="{ 'border-raspberry-500': errors.target_date }"
                  :min="minDate"
                />
                <p v-if="errors.target_date" class="mt-1 text-sm text-raspberry-600">{{ errors.target_date }}</p>
              </div>
            </div>

            <!-- Goal Type -->
            <div>
              <label for="goal_type" class="block text-sm font-medium text-neutral-500 mb-1">
                Goal Type
              </label>
              <select
                id="goal_type"
                v-model="formData.goal_type"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                :class="{ 'border-raspberry-500': errors.goal_type }"
              >
                <option value="">Select goal type</option>
                <option value="retirement">Retirement</option>
                <option value="home">Home Purchase</option>
                <option value="education">Education</option>
                <option value="wealth">Wealth Accumulation</option>
              </select>
              <p v-if="errors.goal_type" class="mt-1 text-sm text-raspberry-600">{{ errors.goal_type }}</p>
            </div>

            <!-- Priority -->
            <div>
              <label for="priority" class="block text-sm font-medium text-neutral-500 mb-1">
                Priority
              </label>
              <select
                id="priority"
                v-model="formData.priority"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              >
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>

            <!-- Is Essential -->
            <div>
              <label class="flex items-center">
                <input
                  type="checkbox"
                  v-model="formData.is_essential"
                  class="rounded border-horizon-300 text-violet-600 shadow-sm focus:border-violet-300 focus:ring focus:ring-violet-200 focus:ring-opacity-50"
                />
                <span class="ml-2 text-sm text-neutral-500">Mark as Essential Goal</span>
              </label>
              <p class="mt-1 text-xs text-neutral-500">Essential goals are prioritized in financial planning recommendations</p>
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
              {{ submitting ? 'Saving...' : (isEditMode ? 'Update Goal' : 'Create Goal') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'GoalForm',

  emits: ['save', 'close'],

  mixins: [currencyMixin],

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    goal: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      formData: {
        goal_name: '',
        target_amount: null,
        target_date: '',
        goal_type: '',
        priority: 'medium',
        is_essential: false,
        linked_account_ids: [],
      },
      errors: {},
      submitting: false,
    };
  },

  computed: {
    isEditMode() {
      return !!this.goal;
    },

    minDate() {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      return tomorrow.toISOString().split('T')[0];
    },
  },

  watch: {
    goal: {
      immediate: true,
      handler(newGoal) {
        if (newGoal) {
          this.formData = {
            ...newGoal,
            target_date: this.formatDateForInput(newGoal.target_date),
          };
        } else {
          this.resetForm();
        }
      },
    },
    show(newVal) {
      if (!newVal) {
        this.errors = {};
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

        this.$emit('save', { ...this.formData });
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
      // All fields are optional - no validation required
      return true;
    },

    closeModal() {
      this.$emit('close');
      this.resetForm();
    },

    resetForm() {
      this.formData = {
        goal_name: '',
        target_amount: null,
        target_date: '',
        goal_type: '',
        priority: 'medium',
        is_essential: false,
        linked_account_ids: [],
      };
      this.errors = {};
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
