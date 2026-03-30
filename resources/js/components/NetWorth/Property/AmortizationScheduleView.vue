<template>
  <div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h3 class="text-xl font-semibold text-horizon-500">Amortization Schedule</h3>
        <p class="text-sm text-neutral-500 mt-1">{{ mortgage.lender_name }} - {{ mortgage.mortgage_type }}</p>
      </div>
      <button
        @click="downloadCSV"
        class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors flex items-center space-x-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span>Download CSV</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      <p class="mt-4 text-neutral-500">Loading amortization schedule...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-savannah-100 rounded-lg p-6 text-center">
      <p class="text-raspberry-600">{{ error }}</p>
      <button
        @click="loadSchedule"
        class="mt-4 px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
      >
        Retry
      </button>
    </div>

    <!-- Schedule Content -->
    <div v-else-if="schedule.length > 0" class="space-y-6">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-savannah-100 rounded-lg p-4">
          <p class="text-sm text-violet-700">Loan Amount</p>
          <p class="text-xl font-bold text-violet-900">{{ formatCurrencyWithPence(mortgage.original_loan_amount) }}</p>
        </div>

        <div class="bg-savannah-100 rounded-lg p-4">
          <p class="text-sm text-spring-700">Monthly Payment</p>
          <p class="text-xl font-bold text-spring-900">{{ formatCurrencyWithPence(mortgage.monthly_payment) }}</p>
        </div>

        <div class="bg-savannah-100 rounded-lg p-4">
          <p class="text-sm text-purple-700">Interest Rate</p>
          <p class="text-xl font-bold text-purple-900">{{ parseFloat(mortgage.interest_rate).toFixed(2) }}%</p>
        </div>

        <div class="bg-savannah-100 rounded-lg p-4">
          <p class="text-sm text-violet-700">Remaining Term</p>
          <p class="text-xl font-bold text-violet-900">{{ remainingYears }} years</p>
        </div>
      </div>

      <!-- Total Interest Summary -->
      <div class="bg-savannah-100 rounded-lg p-4">
        <div class="flex justify-between items-center">
          <div>
            <p class="text-sm text-raspberry-700">Total Interest to be Paid</p>
            <p class="text-2xl font-bold text-raspberry-900">{{ formatCurrencyWithPence(totalInterest) }}</p>
          </div>
          <div class="text-right">
            <p class="text-sm text-raspberry-700">Total Amount Payable</p>
            <p class="text-2xl font-bold text-raspberry-900">{{ formatCurrencyWithPence(totalPayable) }}</p>
          </div>
        </div>
      </div>

      <!-- Pagination Controls -->
      <div class="flex justify-between items-center">
        <div class="text-sm text-neutral-500">
          Showing payments {{ startIndex + 1 }} to {{ endIndex }} of {{ schedule.length }}
        </div>
        <div class="flex space-x-2">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="px-3 py-1 bg-savannah-200 text-neutral-500 rounded-button hover:bg-horizon-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Previous
          </button>
          <span class="px-3 py-1 text-neutral-500">
            Page {{ currentPage }} of {{ totalPages }}
          </span>
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 bg-savannah-200 text-neutral-500 rounded-button hover:bg-horizon-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next
          </button>
        </div>
      </div>

      <!-- Amortization Table -->
      <div class="overflow-x-auto border border-light-gray rounded-lg">
        <table class="w-full">
          <thead class="bg-savannah-100">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-medium text-horizon-500">Month</th>
              <th class="px-4 py-3 text-right text-sm font-medium text-horizon-500">Opening Balance</th>
              <th class="px-4 py-3 text-right text-sm font-medium text-horizon-500">Payment</th>
              <th class="px-4 py-3 text-right text-sm font-medium text-horizon-500">Interest</th>
              <th class="px-4 py-3 text-right text-sm font-medium text-horizon-500">Principal</th>
              <th class="px-4 py-3 text-right text-sm font-medium text-horizon-500">Closing Balance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200">
            <tr
              v-for="payment in paginatedSchedule"
              :key="payment.month"
              class="hover:bg-savannah-100"
            >
              <td class="px-4 py-3 text-sm text-horizon-500">{{ payment.month }}</td>
              <td class="px-4 py-3 text-sm text-right text-horizon-500">{{ formatCurrencyWithPence(payment.opening_balance) }}</td>
              <td class="px-4 py-3 text-sm text-right font-medium text-horizon-500">{{ formatCurrencyWithPence(payment.payment) }}</td>
              <td class="px-4 py-3 text-sm text-right text-raspberry-600">{{ formatCurrencyWithPence(payment.interest) }}</td>
              <td class="px-4 py-3 text-sm text-right text-spring-600">{{ formatCurrencyWithPence(payment.principal) }}</td>
              <td class="px-4 py-3 text-sm text-right font-medium text-horizon-500">{{ formatCurrencyWithPence(payment.closing_balance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Controls (Bottom) -->
      <div class="flex justify-end">
        <div class="flex space-x-2">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="px-3 py-1 bg-savannah-200 text-neutral-500 rounded-button hover:bg-horizon-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Previous
          </button>
          <span class="px-3 py-1 text-neutral-500">
            Page {{ currentPage }} of {{ totalPages }}
          </span>
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 bg-savannah-200 text-neutral-500 rounded-button hover:bg-horizon-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-12 text-neutral-500">
      <p>No amortization schedule available.</p>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'AmortizationScheduleView',

  mixins: [currencyMixin],

  props: {
    mortgage: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      schedule: [],
      loading: false,
      error: null,
      currentPage: 1,
      itemsPerPage: 12, // Show 12 months (1 year) per page
    };
  },

  computed: {
    totalPages() {
      return Math.ceil(this.schedule.length / this.itemsPerPage);
    },

    startIndex() {
      return (this.currentPage - 1) * this.itemsPerPage;
    },

    endIndex() {
      return Math.min(this.startIndex + this.itemsPerPage, this.schedule.length);
    },

    paginatedSchedule() {
      return this.schedule.slice(this.startIndex, this.endIndex);
    },

    totalInterest() {
      return this.schedule.reduce((sum, payment) => sum + (payment.interest || 0), 0);
    },

    totalPayable() {
      return this.schedule.reduce((sum, payment) => sum + (payment.payment || 0), 0);
    },

    remainingYears() {
      return (this.schedule.length / 12).toFixed(1);
    },
  },

  mounted() {
    this.loadSchedule();
  },

  methods: {
    ...mapActions('netWorth', ['getAmortizationSchedule']),

    async loadSchedule() {
      this.loading = true;
      this.error = null;

      try {
        const result = await this.getAmortizationSchedule(this.mortgage.id);
        this.schedule = result.schedule || [];
      } catch (error) {
        this.error = 'Failed to load amortization schedule. Please try again.';
        logger.error('Failed to load amortization schedule:', error);
      } finally {
        this.loading = false;
      }
    },

    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
      }
    },

    previousPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
      }
    },

    downloadCSV() {
      // Create CSV content
      const headers = ['Month', 'Opening Balance', 'Payment', 'Interest', 'Principal', 'Closing Balance'];
      const rows = this.schedule.map(payment => [
        payment.month,
        payment.opening_balance.toFixed(2),
        payment.payment.toFixed(2),
        payment.interest.toFixed(2),
        payment.principal.toFixed(2),
        payment.closing_balance.toFixed(2),
      ]);

      const csvContent = [
        headers.join(','),
        ...rows.map(row => row.join(',')),
      ].join('\n');

      // Create blob and download
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);

      link.setAttribute('href', url);
      link.setAttribute('download', `amortization_schedule_${this.mortgage.lender_name.replace(/\s+/g, '_')}.csv`);
      link.style.visibility = 'hidden';

      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    },
  },
};
</script>
