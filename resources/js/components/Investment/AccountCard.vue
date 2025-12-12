<template>
  <div class="account-card bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200">
    <!-- Account Header -->
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-1">
          <h3 class="text-lg font-semibold text-gray-900">{{ account.provider }}</h3>
          <span
            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
            :class="accountTypeBadgeClass"
          >
            {{ accountTypeLabel }}
          </span>
        </div>
        <p class="text-sm text-gray-600">{{ account.platform || 'Platform not specified' }}</p>
      </div>
      <div class="flex gap-2 ml-4">
        <button
          disabled
          class="text-gray-400 cursor-not-allowed"
          title="This functionality is not available for this demo"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <button
          @click="$emit('delete', account)"
          class="text-red-600 hover:text-red-800"
          title="Delete account"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Account Value -->
    <div class="mb-4">
      <p class="text-xs text-gray-600 mb-1">Current Value</p>
      <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(accountValue) }}</p>
    </div>

    <!-- Holdings Count -->
    <div class="flex items-center justify-between py-3 border-t border-gray-200 mb-4">
      <div class="flex items-center gap-2">
        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span class="text-sm text-gray-600">Holdings:</span>
      </div>
      <span class="text-sm font-semibold text-gray-900">{{ holdingsCount }}</span>
    </div>

    <!-- Platform Fee -->
    <div v-if="account.platform_fee_percent" class="flex items-center justify-between py-3 border-t border-gray-200 mb-4">
      <span class="text-sm text-gray-600">Platform Fee:</span>
      <span class="text-sm font-medium text-gray-900">{{ account.platform_fee_percent }}% p.a.</span>
    </div>

    <!-- ISA Specific Information -->
    <div v-if="isISA" class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4">
      <div class="flex items-start gap-2">
        <svg class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="flex-1">
          <p class="text-xs font-medium text-blue-900 mb-1">ISA Account</p>
          <p class="text-xs text-blue-700">Tax-free wrapper - contributions count towards £20,000 annual allowance</p>
        </div>
      </div>
    </div>

    <!-- Action Button -->
    <button
      @click="$emit('view-holdings', account)"
      class="w-full bg-blue-50 text-blue-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-100 transition-colors"
    >
      View Holdings
    </button>
  </div>
</template>

<script>
export default {
  name: 'AccountCard',

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  computed: {
    accountValue() {
      return parseFloat(this.account.current_value || 0);
    },

    holdingsCount() {
      return this.account.holdings?.length || 0;
    },

    isISA() {
      return this.account.account_type === 'isa';
    },

    accountTypeLabel() {
      const typeMap = {
        isa: 'ISA',
        gia: 'General Investment Account',
        sipp: 'SIPP',
        pension: 'Pension',
        other: 'Other',
      };
      return typeMap[this.account.account_type] || this.account.account_type?.toUpperCase() || 'N/A';
    },

    accountTypeBadgeClass() {
      const typeClassMap = {
        isa: 'bg-green-100 text-green-800',
        gia: 'bg-blue-100 text-blue-800',
        sipp: 'bg-purple-100 text-purple-800',
        pension: 'bg-purple-100 text-purple-800',
        other: 'bg-gray-100 text-gray-800',
      };
      return typeClassMap[this.account.account_type] || 'bg-gray-100 text-gray-800';
    },
  },

  methods: {
    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value || 0);
    },
  },
};
</script>
