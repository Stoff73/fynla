<template>
  <div class="module-summaries">
    <PlanSectionHeader
      title="Module Summaries"
      subtitle="Key metrics for each area of your financial plan"
      color="blue"
    />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Protection Module -->
      <div class="module-card bg-white border border-gray-200 rounded-lg p-6" style="animation-delay: 0ms">
        <div class="flex items-center mb-4">
          <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
            <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <h4 class="text-lg font-semibold text-gray-900">Protection</h4>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Coverage Gap</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(summaries.protection?.coverage_gap || 0) }}</span>
          </div>
          <div class="module-message pt-3 border-t border-gray-100">
            <p class="text-sm text-gray-600 leading-relaxed">
              {{ summaries.protection?.key_message }}
            </p>
          </div>
        </div>
      </div>

      <!-- Savings Module -->
      <div class="module-card bg-white border border-gray-200 rounded-lg p-6" style="animation-delay: 60ms">
        <div class="flex items-center mb-4">
          <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
            <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
          </div>
          <h4 class="text-lg font-semibold text-gray-900">Savings</h4>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Emergency Fund</span>
            <span class="text-sm font-semibold text-gray-900">{{ summaries.savings?.emergency_fund_months || 0 }} months</span>
          </div>
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Total Savings</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(summaries.savings?.total_savings || 0) }}</span>
          </div>
          <div class="module-message pt-3 border-t border-gray-100">
            <p class="text-sm text-gray-600 leading-relaxed">
              {{ summaries.savings?.key_message }}
            </p>
          </div>
        </div>
      </div>

      <!-- Investment Module -->
      <div class="module-card bg-white border border-gray-200 rounded-lg p-6" style="animation-delay: 120ms">
        <div class="flex items-center mb-4">
          <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
          </div>
          <h4 class="text-lg font-semibold text-gray-900">Investment</h4>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Portfolio Value</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(summaries.investment?.portfolio_value || 0) }}</span>
          </div>
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Annual Return</span>
            <span class="text-sm font-semibold text-gray-900">{{ summaries.investment?.annual_return || 0 }}%</span>
          </div>
          <div class="module-message pt-3 border-t border-gray-100">
            <p class="text-sm text-gray-600 leading-relaxed">
              {{ summaries.investment?.key_message }}
            </p>
          </div>
        </div>
      </div>

      <!-- Retirement Module -->
      <div class="module-card bg-white border border-gray-200 rounded-lg p-6" style="animation-delay: 180ms">
        <div class="flex items-center mb-4">
          <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
            <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
            </svg>
          </div>
          <h4 class="text-lg font-semibold text-gray-900">Retirement</h4>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Projected Income</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(summaries.retirement?.projected_income || 0) }}/yr</span>
          </div>
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Income Gap</span>
            <span class="text-sm font-semibold" :class="(summaries.retirement?.income_gap || 0) > 0 ? 'text-red-600' : 'text-green-600'">{{ formatCurrency(summaries.retirement?.income_gap || 0) }}/yr</span>
          </div>
          <div class="module-message pt-3 border-t border-gray-100">
            <p class="text-sm text-gray-600 leading-relaxed">
              {{ summaries.retirement?.key_message }}
            </p>
          </div>
        </div>
      </div>

      <!-- Estate Module -->
      <div class="module-card bg-white border border-gray-200 rounded-lg p-6" style="animation-delay: 240ms">
        <div class="flex items-center mb-4">
          <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
            <svg class="h-5 w-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
          </div>
          <h4 class="text-lg font-semibold text-gray-900">Estate Planning</h4>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Net Worth</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(summaries.estate?.net_worth || 0) }}</span>
          </div>
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Inheritance Tax Liability</span>
            <span class="text-sm font-semibold" :class="(summaries.estate?.iht_liability || 0) > 0 ? 'text-red-600' : 'text-gray-900'">{{ formatCurrency(summaries.estate?.iht_liability || 0) }}</span>
          </div>
          <div class="module-message pt-3 border-t border-gray-100">
            <p class="text-sm text-gray-600 leading-relaxed">
              {{ summaries.estate?.key_message }}
            </p>
          </div>
        </div>
      </div>

      <!-- Goals Module -->
      <div v-if="summaries.goals" class="module-card bg-white border border-gray-200 rounded-lg p-6" style="animation-delay: 300ms">
        <div class="flex items-center mb-4">
          <div class="w-10 h-10 bg-teal-100 rounded-lg flex items-center justify-center mr-3">
            <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
          </div>
          <h4 class="text-lg font-semibold text-gray-900">Goals</h4>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">Active Goals</span>
            <span class="text-sm font-semibold text-gray-900">{{ summaries.goals?.total_goals || 0 }}</span>
          </div>
          <div class="flex justify-between items-baseline">
            <span class="text-sm text-gray-500">On Track</span>
            <span class="text-sm font-semibold" :class="(summaries.goals?.on_track_count || 0) === (summaries.goals?.total_goals || 0) ? 'text-green-600' : 'text-gray-900'">{{ summaries.goals?.on_track_count || 0 }} of {{ summaries.goals?.total_goals || 0 }}</span>
          </div>
          <div class="module-message pt-3 border-t border-gray-100">
            <p class="text-sm text-gray-600 leading-relaxed">
              {{ summaries.goals?.key_message }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'ModuleSummaries',
  components: { PlanSectionHeader },
  mixins: [currencyMixin],

  props: {
    summaries: {
      type: Object,
      required: true,
    },
  },
};
</script>

<style scoped>
/* Module card entrance animation with stagger */
.module-card {
  animation: fadeSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.module-card:hover {
  @apply border-gray-300;
  transform: translateY(-2px);
  box-shadow:
    0 1px 2px rgba(0, 0, 0, 0.04),
    0 4px 8px rgba(0, 0, 0, 0.04),
    0 12px 24px rgba(0, 0, 0, 0.03);
}

/* Key message text area */
.module-message {
  transition: all 0.15s ease;
}

.module-card:hover .module-message {
  @apply border-t-gray-200;
}

/* Keyframes */
@keyframes fadeSlideUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
  .module-card {
    animation: none;
  }

  .module-card:hover {
    transform: none;
  }
}
</style>
