<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Background overlay -->
    <div
      class="fixed inset-0 bg-eggshell-5000 bg-opacity-75 transition-opacity"
    ></div>

    <!-- Modal container -->
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Modal panel -->
      <div
        class="relative inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-[90vh] overflow-y-auto scrollbar-thin"
      >
        <!-- Header -->
        <div class="bg-white px-6 pt-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-horizon-500">
              {{ isEditing ? 'Edit Savings Goal' : 'Create New Savings Goal' }}
            </h3>
            <button
              @click="handleClose"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
            >
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="px-6 pb-6">
          <div class="space-y-4 pr-2">
            <!-- Goal Name -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Goal Name
              </label>
              <input
                v-model="formData.goal_name"
                type="text"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., Emergency Fund, House Deposit, Wedding"
              />
            </div>

            <!-- Target Amount -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Target Amount
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                <input
                  v-model.number="formData.target_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full pl-8 pr-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Current Saved -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Current Amount Saved
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                <input
                  v-model.number="formData.current_saved"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full pl-8 pr-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="0.00"
                />
              </div>
              <p class="text-xs text-neutral-500 mt-1">Leave at 0 if starting fresh</p>
            </div>

            <!-- Target Date -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Target Date
              </label>
              <input
                v-model="formData.target_date"
                type="date"
                :min="minDate"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              />
              <p v-if="monthsToTarget > 0" class="text-xs text-neutral-500 mt-1">
                {{ monthsToTarget }} months away - Requires ~{{ formatCurrency(requiredMonthlySavings) }}/month
              </p>
            </div>

            <!-- Priority -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Priority
              </label>
              <select
                v-model="formData.priority"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="high">High Priority</option>
                <option value="medium">Medium Priority</option>
                <option value="low">Low Priority</option>
              </select>
            </div>

            <!-- Linked Account -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Link to Savings Account (Optional)
              </label>
              <select
                v-model="formData.linked_account_id"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option :value="null">Not linked to any account</option>
                <option
                  v-for="account in accounts"
                  :key="account.id"
                  :value="account.id"
                >
                  {{ account.institution }} - {{ formatCurrency(account.current_balance) }}
                </option>
              </select>
              <p class="text-xs text-neutral-500 mt-1">
                Link this goal to a specific savings account for tracking
              </p>
            </div>

            <!-- Auto Transfer Amount -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Auto Transfer Amount (Monthly)
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                <input
                  v-model.number="formData.auto_transfer_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full pl-8 pr-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="0.00"
                />
              </div>
              <p class="text-xs text-neutral-500 mt-1">
                Optional: Set up a regular monthly savings amount
              </p>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="mt-6 flex gap-3">
            <button
              type="submit"
              :disabled="submitting"
              class="flex-1 px-6 py-3 bg-raspberry-500 text-white font-medium rounded-button hover:bg-raspberry-600 disabled:bg-savannah-300 disabled:cursor-not-allowed transition-colors"
            >
              {{ submitting ? 'Saving...' : (isEditing ? 'Update Goal' : 'Create Goal') }}
            </button>
            <button
              type="button"
              @click="handleClose"
              class="px-6 py-3 bg-savannah-100 text-neutral-500 font-medium rounded-lg hover:bg-savannah-200 transition-colors"
            >
              Cancel
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
  name: 'SaveGoalModal',

  emits: ['save', 'close'],

  mixins: [currencyMixin],

  props: {
    goal: {
      type: Object,
      default: null,
    },
    isEditing: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      submitting: false,
      formData: {
        goal_name: '',
        target_amount: 0,
        current_saved: 0,
        target_date: '',
        priority: 'medium',
        linked_account_id: null,
        auto_transfer_amount: null,
      },
    };
  },

  computed: {
    ...mapState('savings', ['accounts']),

    minDate() {
      // Minimum date is tomorrow
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      return tomorrow.toISOString().split('T')[0];
    },

    monthsToTarget() {
      if (!this.formData.target_date) return 0;
      const now = new Date();
      const target = new Date(this.formData.target_date);
      const months = Math.ceil((target - now) / (1000 * 60 * 60 * 24 * 30));
      return Math.max(0, months);
    },

    requiredMonthlySavings() {
      if (this.monthsToTarget === 0) return 0;
      const remaining = this.formData.target_amount - (this.formData.current_saved || 0);
      return Math.max(0, remaining / this.monthsToTarget);
    },
  },

  mounted() {
    if (this.isEditing && this.goal) {
      this.loadGoalData();
    }
  },

  methods: {
    loadGoalData() {
      this.formData = {
        goal_name: this.goal.goal_name || '',
        target_amount: parseFloat(this.goal.target_amount) || 0,
        current_saved: parseFloat(this.goal.current_saved) || 0,
        target_date: this.formatDateForInput(this.goal.target_date),
        priority: this.goal.priority || 'medium',
        linked_account_id: this.goal.linked_account_id || null,
        auto_transfer_amount: this.goal.auto_transfer_amount ? parseFloat(this.goal.auto_transfer_amount) : null,
      };
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

    async handleSubmit() {
      this.submitting = true;

      try {
        const goalData = this.prepareGoalData();
        this.$emit('save', goalData);
      } catch (error) {
        logger.error('Form submission error:', error);
      } finally {
        this.submitting = false;
      }
    },

    prepareGoalData() {
      const data = {
        goal_name: this.formData.goal_name,
        target_amount: this.formData.target_amount,
        current_saved: this.formData.current_saved || 0,
        target_date: this.formData.target_date,
        priority: this.formData.priority,
        linked_account_id: this.formData.linked_account_id || null,
        auto_transfer_amount: this.formData.auto_transfer_amount || null,
      };

      return data;
    },

    handleClose() {
      this.$emit('close');
    },
  },
};
</script>

