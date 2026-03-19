<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h4 font-semibold text-horizon-500">Liabilities Overview</h2>
      <p class="mt-1 text-body-sm text-neutral-500">
        Summary of all your liabilities
      </p>
    </div>

    <!-- Total Liabilities Card -->
    <div class="card p-6 mb-6 bg-gradient-to-r from-raspberry-50 to-raspberry-100">
      <div class="text-center">
        <p class="text-body-sm font-medium text-raspberry-700">Total Liabilities</p>
        <p class="text-h2 font-display font-bold text-raspberry-900 mt-2">
          {{ formatCurrency(liabilitiesSummary?.total || 0) }}
        </p>
      </div>
    </div>

    <!-- Liabilities List -->
    <div class="space-y-4">
      <!-- Mortgages Section -->
      <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-h5 font-semibold text-horizon-500">Mortgages</h3>
          <span class="text-body-sm text-neutral-500">
            {{ liabilitiesSummary?.mortgages?.count || 0 }} {{ liabilitiesSummary?.mortgages?.count === 1 ? 'mortgage' : 'mortgages' }}
          </span>
        </div>

        <div v-if="liabilitiesSummary?.mortgages?.count > 0" class="space-y-3">
          <div
            v-for="mortgage in mortgages"
            :key="mortgage.id"
            class="flex justify-between items-center py-3 border-b border-light-gray last:border-0"
          >
            <div>
              <p class="text-body-base font-medium text-horizon-500">
                {{ mortgage.lender || 'Mortgage' }}
              </p>
              <p class="text-body-sm text-neutral-500">
                {{ mortgage.interest_rate ? formatInterestRate(mortgage.interest_rate) + '% interest' : 'N/A' }}
              </p>
            </div>
            <div class="text-right">
              <p class="text-body-base font-semibold text-horizon-500">
                {{ formatCurrency(mortgage.outstanding_balance) }}
              </p>
              <p class="text-body-sm text-neutral-500">
                {{ mortgage.monthly_payment ? formatCurrency(mortgage.monthly_payment) + '/mo' : 'N/A' }}
              </p>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-4 text-neutral-500">
          <p class="text-body-sm">No mortgages recorded</p>
        </div>

        <div class="mt-4 pt-4 border-t border-light-gray flex justify-between items-center">
          <p class="text-body-base font-semibold text-horizon-500">Total Mortgage Debt</p>
          <p class="text-h5 font-semibold text-horizon-500">
            {{ formatCurrency(liabilitiesSummary?.mortgages?.total || 0) }}
          </p>
        </div>
      </div>

      <!-- Other Liabilities Section -->
      <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-h5 font-semibold text-horizon-500">Other Liabilities</h3>
          <span class="text-body-sm text-neutral-500">
            {{ liabilitiesSummary?.other?.count || 0 }} {{ liabilitiesSummary?.other?.count === 1 ? 'liability' : 'liabilities' }}
          </span>
        </div>

        <div v-if="liabilitiesSummary?.other?.count > 0" class="space-y-3">
          <div
            v-for="liability in otherLiabilities"
            :key="liability.id"
            class="flex justify-between items-center py-3 border-b border-light-gray last:border-0"
          >
            <div>
              <p class="text-body-base font-medium text-horizon-500">{{ liability.description }}</p>
              <p class="text-body-sm text-neutral-500">
                {{ formatLiabilityType(liability.liability_type) }}
                <span v-if="liability.interest_rate"> • {{ formatInterestRate(liability.interest_rate) }}% interest</span>
              </p>
            </div>
            <div class="text-right">
              <p class="text-body-base font-semibold text-horizon-500">
                {{ formatCurrency(liability.amount) }}
              </p>
              <p v-if="liability.monthly_payment" class="text-body-sm text-neutral-500">
                {{ formatCurrency(liability.monthly_payment) }}/mo
              </p>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-4 text-neutral-500">
          <p class="text-body-sm">No other liabilities recorded</p>
        </div>

        <div class="mt-4 pt-4 border-t border-light-gray flex justify-between items-center">
          <p class="text-body-base font-semibold text-horizon-500">Total Other Liabilities</p>
          <p class="text-h5 font-semibold text-horizon-500">
            {{ formatCurrency(liabilitiesSummary?.other?.total || 0) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { useStore } from 'vuex';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'LiabilitiesOverview',

  setup() {
    const store = useStore();

    const profile = computed(() => store.getters['userProfile/profile']);
    const liabilitiesSummary = computed(() => profile.value?.liabilities_summary);

    // Get mortgages from liabilities summary
    const mortgages = computed(() => {
      return liabilitiesSummary.value?.mortgages?.items || [];
    });

    const otherLiabilities = computed(() => {
      return liabilitiesSummary.value?.other?.items || [];
    });

    const formatLiabilityType = (type) => {
      if (!type) return 'N/A';
      return type.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    };

    const formatInterestRate = (rate) => {
      // Rate is stored as percentage (e.g., 4.89 = 4.89%), display with 2 decimal places
      return Number(rate).toFixed(2);
    };

    return {
      liabilitiesSummary,
      mortgages,
      otherLiabilities,
      formatCurrency,
      formatLiabilityType,
      formatInterestRate,
    };
  },
};
</script>
