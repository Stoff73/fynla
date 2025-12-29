<template>
  <div class="module-summaries">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Module Summaries</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Protection Module -->
      <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center">
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
              <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
            </div>
            <h4 class="text-lg font-semibold text-gray-900">Protection</h4>
          </div>
          <span :class="getStatusBadgeClass(summaries.protection?.status)" class="px-3 py-1 text-xs font-medium rounded-full">
            {{ getStatusLabel(summaries.protection?.status) }}
          </span>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Adequacy Score</span>
            <span class="text-sm font-semibold text-gray-900">{{ summaries.protection?.adequacy_score || 0 }}/100</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Coverage Gap</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(summaries.protection?.coverage_gap || 0) }}</span>
          </div>
          <p class="text-sm text-gray-700 mt-3 pt-3 border-t border-gray-200">
            {{ summaries.protection?.key_message }}
          </p>
        </div>
      </div>

      <!-- Savings Module -->
      <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
              <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
            </div>
            <h4 class="text-lg font-semibold text-gray-900">Savings</h4>
          </div>
          <span :class="getStatusBadgeClass(summaries.savings?.status)" class="px-3 py-1 text-xs font-medium rounded-full">
            {{ getStatusLabel(summaries.savings?.status) }}
          </span>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Emergency Fund</span>
            <span class="text-sm font-semibold text-gray-900">{{ summaries.savings?.emergency_fund_months || 0 }} months</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Total Savings</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(summaries.savings?.total_savings || 0) }}</span>
          </div>
          <p class="text-sm text-gray-700 mt-3 pt-3 border-t border-gray-200">
            {{ summaries.savings?.key_message }}
          </p>
        </div>
      </div>

      <!-- Investment Module -->
      <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
              <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
              </svg>
            </div>
            <h4 class="text-lg font-semibold text-gray-900">Investment</h4>
          </div>
          <span :class="getStatusBadgeClass(summaries.investment?.status)" class="px-3 py-1 text-xs font-medium rounded-full">
            {{ getStatusLabel(summaries.investment?.status) }}
          </span>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Portfolio Value</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(summaries.investment?.portfolio_value || 0) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Annual Return</span>
            <span class="text-sm font-semibold text-gray-900">{{ summaries.investment?.annual_return || 0 }}%</span>
          </div>
          <p class="text-sm text-gray-700 mt-3 pt-3 border-t border-gray-200">
            {{ summaries.investment?.key_message }}
          </p>
        </div>
      </div>

      <!-- Retirement Module -->
      <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center">
            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
              <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
              </svg>
            </div>
            <h4 class="text-lg font-semibold text-gray-900">Retirement</h4>
          </div>
          <span :class="getStatusBadgeClass(summaries.retirement?.status)" class="px-3 py-1 text-xs font-medium rounded-full">
            {{ getStatusLabel(summaries.retirement?.status) }}
          </span>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Projected Income</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(summaries.retirement?.projected_income || 0) }}/yr</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Income Gap</span>
            <span class="text-sm font-semibold" :class="(summaries.retirement?.income_gap || 0) > 0 ? 'text-red-600' : 'text-green-600'">{{ formatCurrency(summaries.retirement?.income_gap || 0) }}/yr</span>
          </div>
          <p class="text-sm text-gray-700 mt-3 pt-3 border-t border-gray-200">
            {{ summaries.retirement?.key_message }}
          </p>
        </div>
      </div>

      <!-- Estate Module -->
      <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow md:col-span-2">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center">
            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
              <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
            </div>
            <h4 class="text-lg font-semibold text-gray-900">Estate Planning</h4>
          </div>
          <span :class="getStatusBadgeClass(summaries.estate?.status)" class="px-3 py-1 text-xs font-medium rounded-full">
            {{ getStatusLabel(summaries.estate?.status) }}
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <span class="text-sm text-gray-600">Net Worth</span>
            <p class="text-lg font-semibold text-gray-900 mt-1">{{ formatCurrency(summaries.estate?.net_worth || 0) }}</p>
          </div>
          <div>
            <span class="text-sm text-gray-600">IHT Liability</span>
            <p class="text-lg font-semibold text-gray-900 mt-1">{{ formatCurrency(summaries.estate?.iht_liability || 0) }}</p>
          </div>
          <div class="md:col-span-1">
            <p class="text-sm text-gray-700">
              {{ summaries.estate?.key_message }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ModuleSummaries',
  mixins: [currencyMixin],

  props: {
    summaries: {
      type: Object,
      required: true,
    },
  },

  methods: {
    getStatusBadgeClass(status) {
      if (status === 'excellent') return 'bg-green-100 text-green-800';
      if (status === 'good') return 'bg-blue-100 text-blue-800';
      if (status === 'needs_improvement') return 'bg-yellow-100 text-yellow-800';
      if (status === 'critical') return 'bg-red-100 text-red-800';
      return 'bg-gray-100 text-gray-800';
    },

    getStatusLabel(status) {
      if (status === 'excellent') return 'Excellent';
      if (status === 'good') return 'Good';
      if (status === 'needs_improvement') return 'Needs Improvement';
      if (status === 'critical') return 'Critical';
      return 'Unknown';
    },
  },
};
</script>
