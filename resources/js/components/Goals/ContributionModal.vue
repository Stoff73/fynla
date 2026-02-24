<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

      <!-- Modal panel -->
      <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full max-h-[90vh] overflow-y-auto">
        <form @submit.prevent="handleSubmit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                <span class="text-lg">{{ getGoalIcon(goal?.goal_type) }}</span>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Add Contribution</h3>
                <p class="text-sm text-gray-500">{{ goal?.goal_name }}</p>
              </div>
            </div>

            <!-- Current Progress -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
              <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-600">Current Progress</span>
                <span class="font-medium text-gray-900">{{ progressPercent }}%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                <div
                  class="h-2 rounded-full bg-blue-500 transition-all"
                  :style="{ width: Math.min(progressPercent, 100) + '%' }"
                ></div>
              </div>
              <div class="flex justify-between text-xs text-gray-500">
                <span>{{ formatCurrency(goal?.current_amount || 0) }} saved</span>
                <span>{{ formatCurrency(goal?.target_amount || 0) }} target</span>
              </div>
            </div>

            <!-- Contribution Amount -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Contribution Amount</label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-500">£</span>
                <input
                  v-model.number="form.amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                  placeholder="0.00"
                  required
                />
              </div>
            </div>

            <!-- Quick Amount Buttons -->
            <div class="mb-4">
              <p class="text-xs text-gray-500 mb-2">Quick add:</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="amount in quickAmounts"
                  :key="amount"
                  type="button"
                  @click="form.amount = amount"
                  class="px-3 py-1 text-sm rounded-full border border-gray-300 hover:border-primary-500 hover:bg-primary-50 transition-colors"
                  :class="{ 'border-primary-500 bg-primary-50': form.amount === amount }"
                >
                  £{{ amount }}
                </button>
                <button
                  v-if="goal?.monthly_contribution"
                  type="button"
                  @click="form.amount = goal.monthly_contribution"
                  class="px-3 py-1 text-sm rounded-full border border-primary-300 bg-primary-50 text-primary-700 hover:bg-primary-100 transition-colors"
                >
                  Monthly (£{{ goal.monthly_contribution }})
                </button>
              </div>
            </div>

            <!-- Contribution Date -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
              <input
                v-model="form.contribution_date"
                type="date"
                :max="today"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                required
              />
            </div>

            <!-- Notes (Optional) -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
              <input
                v-model="form.notes"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                placeholder="e.g., Bonus payment, Tax refund"
              />
            </div>

            <!-- New Balance Preview -->
            <div v-if="form.amount > 0" class="p-4 bg-green-50 border border-green-200 rounded-lg">
              <p class="text-sm text-green-700">
                <span class="font-medium">After this contribution:</span>
                {{ formatCurrency(newBalance) }} ({{ newProgressPercent }}% complete)
              </p>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end gap-3">
            <button
              type="button"
              @click="close"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-button hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="button"
              @click="handleSubmit"
              :disabled="!isValid || loading"
              class="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-button hover:bg-primary-700 disabled:opacity-50"
            >
              {{ loading ? 'Saving...' : 'Add Contribution' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { getGoalIcon } from '@/constants/goalIcons';

export default {
  name: 'ContributionModal',
  mixins: [currencyMixin],

  props: {
    isOpen: {
      type: Boolean,
      default: false,
    },
    goal: {
      type: Object,
      default: null,
    },
  },

  emits: ['close', 'save'],

  data() {
    return {
      form: {
        amount: null,
        contribution_date: '',
        notes: '',
      },
      loading: false,
      quickAmounts: [50, 100, 250, 500, 1000],
    };
  },

  computed: {
    today() {
      return new Date().toISOString().split('T')[0];
    },

    progressPercent() {
      if (!this.goal?.target_amount) return 0;
      return Math.round(((this.goal.current_amount || 0) / this.goal.target_amount) * 100);
    },

    newBalance() {
      return (this.goal?.current_amount || 0) + (this.form.amount || 0);
    },

    newProgressPercent() {
      if (!this.goal?.target_amount) return 0;
      return Math.round((this.newBalance / this.goal.target_amount) * 100);
    },

    isValid() {
      return this.form.amount > 0 && this.form.contribution_date;
    },
  },

  watch: {
    isOpen(newVal) {
      if (newVal) {
        this.resetForm();
      }
    },
  },

  methods: {
    resetForm() {
      this.form = {
        amount: null,
        contribution_date: this.today,
        notes: '',
      };
      this.loading = false;
    },

    getGoalIcon,

    close() {
      this.$emit('close');
    },

    async handleSubmit() {
      if (!this.isValid) return;

      this.loading = true;
      try {
        this.$emit('save', {
          amount: this.form.amount,
          contribution_date: this.form.contribution_date,
          notes: this.form.notes || null,
        });
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
