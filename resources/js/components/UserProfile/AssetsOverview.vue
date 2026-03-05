<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h4 font-semibold text-horizon-500">Assets Overview</h2>
      <p class="mt-1 text-body-sm text-neutral-500">
        Summary of all your assets across different categories
      </p>
    </div>

    <!-- Total Assets Card -->
    <div class="card p-6 mb-6 bg-gradient-to-r from-raspberry-50 to-raspberry-100">
      <div class="text-center">
        <p class="text-body-sm font-medium text-raspberry-700">Total Assets</p>
        <p class="text-h2 font-display font-bold text-horizon-600 mt-2">
          {{ formatCurrency(assetsSummary?.total || 0) }}
        </p>
      </div>
    </div>

    <!-- Asset Categories -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <!-- Properties -->
      <div
        class="card p-6 cursor-pointer hover:shadow-lg transition-shadow"
        @click="navigateToProperties"
      >
        <div class="flex justify-between items-start">
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500">Properties</h3>
            <p class="text-body-xs text-neutral-500 mt-1">
              {{ assetsSummary?.properties?.count || 0 }} {{ assetsSummary?.properties?.count === 1 ? 'property' : 'properties' }}
            </p>
          </div>
          <div class="text-raspberry-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
          </div>
        </div>
        <div class="mt-4">
          <p class="text-h5 font-semibold text-horizon-500">
            {{ formatCurrency(assetsSummary?.properties?.total || 0) }}
          </p>
        </div>
      </div>

      <!-- Investments -->
      <div
        class="card p-6 cursor-pointer hover:shadow-lg transition-shadow"
        @click="navigateToInvestment"
      >
        <div class="flex justify-between items-start">
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500">Investments</h3>
            <p class="text-body-xs text-neutral-500 mt-1">
              {{ assetsSummary?.investments?.count || 0 }} {{ assetsSummary?.investments?.count === 1 ? 'account' : 'accounts' }}
            </p>
          </div>
          <div class="text-success-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
            </svg>
          </div>
        </div>
        <div class="mt-4">
          <p class="text-h5 font-semibold text-horizon-500">
            {{ formatCurrency(assetsSummary?.investments?.total || 0) }}
          </p>
        </div>
      </div>

      <!-- Cash Accounts -->
      <div
        class="card p-6 cursor-pointer hover:shadow-lg transition-shadow"
        @click="navigateToSavings"
      >
        <div class="flex justify-between items-start">
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500">Cash</h3>
            <p class="text-body-xs text-neutral-500 mt-1">
              {{ assetsSummary?.cash?.count || 0 }} {{ assetsSummary?.cash?.count === 1 ? 'account' : 'accounts' }}
            </p>
          </div>
          <div class="text-blue-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
        </div>
        <div class="mt-4">
          <p class="text-h5 font-semibold text-horizon-500">
            {{ formatCurrency(assetsSummary?.cash?.total || 0) }}
          </p>
        </div>
      </div>

      <!-- Business Interests -->
      <div
        class="card p-6 cursor-pointer hover:shadow-lg transition-shadow"
        @click="navigateToEstate('business')"
      >
        <div class="flex justify-between items-start">
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500">Business Interests</h3>
            <p class="text-body-xs text-neutral-500 mt-1">
              {{ assetsSummary?.business?.count || 0 }} {{ assetsSummary?.business?.count === 1 ? 'business' : 'businesses' }}
            </p>
          </div>
          <div class="text-blue-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
          </div>
        </div>
        <div class="mt-4">
          <p class="text-h5 font-semibold text-horizon-500">
            {{ formatCurrency(assetsSummary?.business?.total || 0) }}
          </p>
        </div>
      </div>

      <!-- Chattels -->
      <div
        class="card p-6 cursor-pointer hover:shadow-lg transition-shadow"
        @click="navigateToEstate('chattels')"
      >
        <div class="flex justify-between items-start">
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500">Personal Valuables</h3>
            <p class="text-body-xs text-neutral-500 mt-1">
              {{ assetsSummary?.chattels?.count || 0 }} {{ assetsSummary?.chattels?.count === 1 ? 'item' : 'items' }}
            </p>
          </div>
          <div class="text-purple-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
          </div>
        </div>
        <div class="mt-4">
          <p class="text-h5 font-semibold text-horizon-500">
            {{ formatCurrency(assetsSummary?.chattels?.total || 0) }}
          </p>
        </div>
      </div>

      <!-- Pensions -->
      <div
        class="card p-6 cursor-pointer hover:shadow-lg transition-shadow"
        @click="navigateToRetirement"
      >
        <div class="flex justify-between items-start">
          <div>
            <h3 class="text-body-base font-semibold text-horizon-500">Pensions</h3>
            <p class="text-body-xs text-neutral-500 mt-1">
              {{ assetsSummary?.pensions?.count || 0 }} {{ assetsSummary?.pensions?.count === 1 ? 'pension' : 'pensions' }}
            </p>
          </div>
          <div class="text-indigo-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
          </div>
        </div>
        <div class="mt-4">
          <p class="text-h5 font-semibold text-horizon-500">
            {{ formatCurrency(assetsSummary?.pensions?.total || 0) }}
          </p>
        </div>
      </div>
    </div>

    <!-- View Net Worth Dashboard Button -->
    <div class="mt-8 text-center">
      <button
        @click="navigateToEstate()"
        class="btn-primary"
      >
        View Full Net Worth Dashboard
      </button>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'AssetsOverview',

  setup() {
    const store = useStore();
    const router = useRouter();

    const profile = computed(() => store.getters['userProfile/profile']);
    const assetsSummary = computed(() => profile.value?.assets_summary);

    const navigateToProperties = () => {
      router.push({ name: 'NetWorthProperty' });
    };

    const navigateToInvestment = () => {
      router.push({ name: 'Investment' });
    };

    const navigateToSavings = () => {
      router.push({ name: 'Savings' });
    };

    const navigateToEstate = (tab = null) => {
      if (tab) {
        router.push({ name: 'Estate', query: { tab } });
      } else {
        router.push({ name: 'Estate' });
      }
    };

    const navigateToRetirement = () => {
      router.push({ name: 'Retirement' });
    };

    return {
      assetsSummary,
      formatCurrency,
      navigateToProperties,
      navigateToInvestment,
      navigateToSavings,
      navigateToEstate,
      navigateToRetirement,
    };
  },
};
</script>
