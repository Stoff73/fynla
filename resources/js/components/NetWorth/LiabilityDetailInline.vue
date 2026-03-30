<template>
  <div class="liability-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="detail-inline-back mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Liabilities
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-600"></div>
      <p class="mt-4 text-neutral-500">Loading details...</p>
    </div>

    <!-- Liability Content -->
    <div v-else-if="liability" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
              <span :class="['badge', getTypeBadgeClass(liability.liability_type)]">
                {{ formatLiabilityType(liability.liability_type) }}
              </span>
              <span v-if="liability.is_priority_debt" class="badge badge-red">
                Priority Debt
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ liability.liability_name || 'Unnamed Liability' }}</h1>
          </div>
          <div class="flex space-x-2 w-full sm:w-auto">
            <button
              v-preview-disabled="'edit'"
              @click="$emit('edit', liability)"
              class="px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
            >
              Edit
            </button>
            <button
              v-preview-disabled="'delete'"
              @click="confirmDelete"
              class="px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Balance Owed</p>
            <p class="text-2xl font-bold text-raspberry-600">{{ formatCurrency(liability.current_balance) }}</p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Monthly Payment</p>
            <p class="text-2xl font-bold text-horizon-500">
              {{ liability.monthly_payment ? formatCurrency(liability.monthly_payment) : 'Not set' }}
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Interest Rate</p>
            <p class="text-2xl font-bold text-horizon-500">
              {{ liability.interest_rate !== null && liability.interest_rate !== undefined ? formatPercentage(liability.interest_rate) : 'Not set' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
              :class="
                activeTab === tab.id
                  ? 'border-raspberry-600 text-raspberry-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <div v-if="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Liability Details</h3>
                <dl class="space-y-3">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Type</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatLiabilityType(liability.liability_type) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Balance Owed</dt>
                    <dd class="text-sm font-medium text-raspberry-600">{{ formatCurrency(liability.current_balance) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Interest Rate</dt>
                    <dd class="text-sm font-medium text-horizon-500">
                      {{ liability.interest_rate !== null && liability.interest_rate !== undefined ? formatPercentage(liability.interest_rate) : 'Not recorded' }}
                    </dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Priority Debt</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ liability.is_priority_debt ? 'Yes' : 'No' }}</dd>
                  </div>
                </dl>
              </div>

              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Repayment</h3>
                <dl class="space-y-3">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Monthly Payment</dt>
                    <dd class="text-sm font-medium text-horizon-500">
                      {{ liability.monthly_payment ? formatCurrency(liability.monthly_payment) : 'Not recorded' }}
                    </dd>
                  </div>
                  <div v-if="liability.maturity_date" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Maturity Date</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatDate(liability.maturity_date) }}</dd>
                  </div>
                  <div v-if="liability.secured_against" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Secured Against</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ liability.secured_against }}</dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- Repayment Projection -->
            <div v-if="showRepaymentProjection" class="mt-6">
              <h3 class="text-lg font-semibold text-horizon-500 mb-4">Repayment Projection</h3>
              <div class="bg-savannah-100 rounded-lg p-4">
                <dl class="space-y-3">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Estimated Time to Repay</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ estimatedMonthsToRepay }} months ({{ estimatedYearsToRepay }} years)</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Total Interest</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(estimatedTotalInterest) }}</dd>
                  </div>
                  <div class="flex justify-between pt-2 border-t border-light-gray">
                    <dt class="text-sm font-semibold text-horizon-500">Total Amount Payable</dt>
                    <dd class="text-sm font-bold text-raspberry-600">{{ formatCurrency(estimatedTotalPayable) }}</dd>
                  </div>
                </dl>
                <p class="text-xs text-neutral-500 mt-3 italic">
                  * Estimates assume fixed interest rate and regular monthly payments
                </p>
              </div>
            </div>
          </div>

          <!-- Notes Tab -->
          <div v-if="activeTab === 'notes'" class="space-y-4">
            <h3 class="text-lg font-semibold text-horizon-500">Notes</h3>
            <div v-if="liability.notes" class="bg-savannah-100 rounded-lg p-4">
              <p class="text-neutral-500 whitespace-pre-wrap">{{ liability.notes }}</p>
            </div>
            <div v-else class="text-center py-8 text-neutral-500">
              No notes recorded for this liability.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Liability"
      message="Are you sure you want to delete this liability? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'LiabilityDetailInline',

  mixins: [currencyMixin],

  components: {
    ConfirmDialog,
  },

  props: {
    liabilityId: {
      type: [Number, String],
      required: true,
    },
  },

  emits: ['back', 'edit', 'deleted'],

  data() {
    return {
      loading: false,
      activeTab: 'overview',
      showDeleteConfirm: false,
      tabs: [
        { id: 'overview', label: 'Overview' },
        { id: 'notes', label: 'Notes' },
      ],
    };
  },

  computed: {
    ...mapState('estate', ['liabilities']),

    liability() {
      return this.liabilities.find(l => l.id === Number(this.liabilityId));
    },

    showRepaymentProjection() {
      return (
        this.liability &&
        this.liability.current_balance > 0 &&
        this.liability.monthly_payment > 0 &&
        this.liability.interest_rate !== null &&
        this.liability.interest_rate >= 0
      );
    },

    estimatedMonthsToRepay() {
      if (!this.showRepaymentProjection) return 0;

      const balance = this.liability.current_balance;
      const monthlyPayment = this.liability.monthly_payment;
      const annualRate = this.liability.interest_rate / 100;
      const monthlyRate = annualRate / 12;

      if (monthlyRate === 0) {
        return Math.ceil(balance / monthlyPayment);
      }

      const monthlyInterest = balance * monthlyRate;
      if (monthlyPayment <= monthlyInterest) {
        return 'Never (payment too low)';
      }

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
      const totalPayable = this.liability.monthly_payment * this.estimatedMonthsToRepay;
      return Math.max(0, totalPayable - this.liability.current_balance);
    },

    estimatedTotalPayable() {
      if (!this.showRepaymentProjection || typeof this.estimatedMonthsToRepay === 'string') {
        return 0;
      }
      return this.liability.monthly_payment * this.estimatedMonthsToRepay;
    },
  },

  watch: {
    liabilityId: {
      immediate: true,
      handler() {
        this.loadData();
      },
    },
  },

  methods: {
    ...mapActions('estate', ['fetchEstateData', 'deleteLiability']),

    async loadData() {
      if (!this.liability) {
        this.loading = true;
        try {
          await this.fetchEstateData();
        } catch (error) {
          logger.error('Failed to load liability:', error);
        } finally {
          this.loading = false;
        }
      }
    },

    formatLiabilityType(type) {
      const labels = {
        secured_loan: 'Secured Loan',
        personal_loan: 'Personal Loan',
        credit_card: 'Credit Card',
        overdraft: 'Overdraft',
        hire_purchase: 'Hire Purchase',
        student_loan: 'Student Loan',
        business_loan: 'Business Loan',
        other: 'Other',
      };
      return labels[type] || type;
    },

    getTypeBadgeClass(type) {
      const classes = {
        student_loan: 'badge-blue',
        personal_loan: 'badge-indigo',
        secured_loan: 'badge-slate',
        business_loan: 'badge-purple',
        hire_purchase: 'badge-teal',
        credit_card: 'badge-red',
        overdraft: 'badge-gray',
        other: 'badge-gray',
      };
      return classes[type] || 'badge-gray';
    },

    formatDate(date) {
      if (!date) return 'N/A';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      try {
        await this.deleteLiability(this.liabilityId);
        this.showDeleteConfirm = false;
        this.$emit('deleted');
      } catch (error) {
        logger.error('Failed to delete liability:', error);
      }
    },
  },
};
</script>

<style scoped>
.liability-detail-inline {
  padding: 24px;
}

.badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.badge-blue {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.badge-indigo {
  @apply bg-indigo-100;
  @apply text-indigo-800;
}

.badge-slate {
  @apply bg-slate-100;
  @apply text-slate-800;
}

.badge-purple {
  @apply bg-purple-100;
  @apply text-purple-800;
}

.badge-teal {
  @apply bg-teal-100;
  @apply text-teal-800;
}

.badge-red {
  @apply bg-raspberry-100;
  @apply text-raspberry-800;
}

.badge-gray {
  @apply bg-savannah-100;
  @apply text-neutral-500;
}
</style>
