<template>
  <div>
    <PlanSectionHeader
      title="Financial Snapshot"
      subtitle="Your current financial position at a glance"
      color="blue"
    />

    <!-- Net Worth & Cash Flow Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div class="bg-white border border-gray-200 rounded-lg p-5">
        <p class="text-sm text-gray-500 font-medium">Net Worth</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(snapshot.net_worth) }}</p>
      </div>
      <div class="bg-white border border-gray-200 rounded-lg p-5">
        <p class="text-sm text-gray-500 font-medium">Monthly Surplus</p>
        <p class="text-2xl font-bold mt-1" :class="snapshot.monthly_surplus >= 0 ? 'text-green-700' : 'text-red-700'">
          {{ formatCurrency(snapshot.monthly_surplus) }}
        </p>
        <p class="text-xs text-gray-500 mt-1">
          {{ formatCurrency(snapshot.monthly_income) }} income &minus; {{ formatCurrency(snapshot.monthly_expenses) }} expenses
        </p>
      </div>
    </div>

    <!-- Asset Breakdown -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <p class="text-xs text-gray-500 font-medium">Property</p>
        <p class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(snapshot.property_value) }}</p>
      </div>
      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <p class="text-xs text-gray-500 font-medium">Pensions</p>
        <p class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(snapshot.pension_value) }}</p>
      </div>
      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <p class="text-xs text-gray-500 font-medium">Investments</p>
        <p class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(snapshot.investment_value) }}</p>
      </div>
      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <p class="text-xs text-gray-500 font-medium">Liquid Savings</p>
        <p class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(snapshot.liquid_assets) }}</p>
      </div>
    </div>

    <!-- Liabilities -->
    <div v-if="snapshot.liabilities > 0" class="mt-4 bg-white border border-gray-200 rounded-lg p-4">
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500 font-medium">Total Liabilities</p>
        <p class="text-lg font-bold text-red-700">{{ formatCurrency(snapshot.liabilities) }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'FinancialSnapshot',

  components: {
    PlanSectionHeader,
  },

  mixins: [currencyMixin],

  props: {
    snapshot: {
      type: Object,
      required: true,
    },
  },
};
</script>
