<template>
  <div class="bg-white shadow-lg rounded-lg p-6 cursor-pointer border border-gray-200 hover:shadow-xl hover:-translate-y-0.5 hover:border-indigo-500 transition-all duration-200" @click="navigateToDashboard">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-gray-800">Retirement Planning</h2>
      <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
    </div>

    <!-- Total Pension Value (Primary Metric) -->
    <div class="text-center mb-6 py-4 bg-indigo-50 rounded-lg">
      <div class="text-sm text-gray-600 mb-1">Total Pension Value</div>
      <div class="text-3xl font-bold text-indigo-600">{{ formatCurrency(totalPensionValue) }}</div>
    </div>

    <!-- Key Metrics -->
    <div class="space-y-3">
      <!-- Years to Retirement -->
      <div class="flex items-center justify-between">
        <span class="text-sm text-gray-600">Years to Retirement</span>
        <span class="text-sm font-semibold text-gray-900">{{ yearsToRetirement }} years</span>
      </div>

      <!-- Projected Income -->
      <div class="flex items-center justify-between">
        <span class="text-sm text-gray-600">Projected Income</span>
        <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(projectedIncome) }}/year</span>
      </div>
    </div>

    <!-- View Details Link -->
    <div class="mt-6 pt-4 border-t border-gray-200">
      <span class="text-sm text-indigo-600 font-medium flex items-center">
        View Full Analysis
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RetirementOverviewCard',
  props: {
    totalPensionValue: {
      type: Number,
      default: 0,
    },
    projectedIncome: {
      type: Number,
      default: 0,
    },
    yearsToRetirement: {
      type: Number,
      default: 0,
    },
  },
  methods: {
    navigateToDashboard() {
      this.$router.push('/net-worth/retirement');
    },
    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },
  },
};
</script>

<style scoped>
/* Additional styles can be added here if needed */
</style>
