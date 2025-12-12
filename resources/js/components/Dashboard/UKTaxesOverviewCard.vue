<template>
  <div
    class="card cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200 border border-gray-200"
    @click="navigateToModule"
  >
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-h4 text-gray-900">UK Taxes & Allowances</h3>
      <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
    </div>

    <div class="space-y-3 mb-4">
      <div class="flex justify-between items-center">
        <span class="text-body-sm text-gray-600">Tax Year</span>
        <span class="text-body font-semibold text-gray-900">2025/26</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-body-sm text-gray-600">Personal Allowance</span>
        <span class="text-body font-semibold text-gray-900">£12,570</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-body-sm text-gray-600">Tax-Free Savings Allowance</span>
        <span class="text-body font-semibold text-gray-900">£20,000</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-body-sm text-gray-600">Pension Annual Allowance</span>
        <span class="text-body font-semibold text-gray-900">£60,000</span>
      </div>
    </div>

    <div class="flex gap-2 flex-wrap mb-3">
      <span class="badge-success">Income Tax</span>
      <span class="badge-info">Capital Gains Tax</span>
      <span class="badge-warning">Inheritance Tax</span>
      <span class="badge-secondary">Pensions</span>
    </div>

    <p class="text-body-sm text-gray-500 italic">
      View all UK tax rules, rates, and calculation methodologies
    </p>
  </div>
</template>

<script>
export default {
  name: 'UKTaxesOverviewCard',

  methods: {
    navigateToModule() {
      this.$router.push('/uk-taxes');
    },
  },
};
</script>
