<template>
  <div v-if="visibleCards.length > 0" class="col-span-full">
    <div class="mb-4">
      <h3 class="text-lg font-semibold text-horizon-500">Complete Your Profile</h3>
      <p class="text-sm text-neutral-500">Add detail to get the most from your financial planning dashboards</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
      <div
        v-for="card in visibleCards"
        :key="card.id"
        class="bg-savannah-100 rounded-lg border border-light-gray p-4"
      >
        <div class="flex items-start gap-3 mb-3">
          <div
            class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
            :class="card.iconBgClass"
          >
            <svg class="w-5 h-5" :class="card.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="card.iconPath" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-semibold text-horizon-500">{{ card.title }}</h4>
            <p class="text-xs text-neutral-500 mt-0.5">{{ card.description }}</p>
          </div>
        </div>
        <router-link
          :to="card.route"
          v-preview-disabled
          class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-raspberry-500 rounded-button hover:bg-raspberry-600 transition-colors"
        >
          {{ card.actionText }}
          <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </router-link>
      </div>
    </div>

    <!-- Full Setup Link -->
    <div class="mt-3 text-center">
      <router-link
        to="/onboarding/full"
        class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors underline"
      >
        Or complete the full setup in one go
      </router-link>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapState } from 'vuex';

export default {
  name: 'ProfileCompletionCards',

  computed: {
    ...mapGetters('auth', ['currentUser']),
    ...mapState('netWorth', ['properties']),
    ...mapState('savings', ['accounts']),
    ...mapState('investment', { investmentAccounts: 'accounts' }),
    ...mapState('retirement', ['dcPensions', 'dbPensions']),
    ...mapGetters('protection', {
      protectionLifePolicies: 'lifePolicies',
      protectionCriticalIllnessPolicies: 'criticalIllnessPolicies',
      protectionIncomeProtectionPolicies: 'incomeProtectionPolicies',
      protectionDisabilityPolicies: 'disabilityPolicies',
      protectionSicknessIllnessPolicies: 'sicknessIllnessPolicies',
    }),

    assetFlags() {
      return this.currentUser?.onboarding_asset_flags || {};
    },

    onboardingMode() {
      return this.currentUser?.onboarding_mode;
    },

    isQuickOnboarding() {
      return this.onboardingMode === 'quick';
    },

    hasJourneySelections() {
      return (this.currentUser?.journey_selections || []).length > 0;
    },

    allCards() {
      const useJourneyRoutes = this.hasJourneySelections;
      return [
        {
          id: 'properties',
          title: 'Add Your Properties',
          description: 'Add details to unlock estate planning insights and net worth tracking.',
          route: useJourneyRoutes ? '/onboarding/journey/estate' : '/net-worth/property',
          actionText: 'Add Properties',
          flagKey: 'properties',
          dataCheck: 'hasProperties',
          iconBgClass: 'bg-spring-100',
          iconClass: 'text-spring-600',
          iconPath: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        },
        {
          id: 'savings',
          title: 'Add Your Savings',
          description: 'Add account details to track balances and optimise your ISA usage.',
          route: useJourneyRoutes ? '/onboarding/journey/budgeting' : '/savings',
          actionText: 'Add Savings',
          flagKey: 'savings',
          dataCheck: 'hasSavings',
          iconBgClass: 'bg-violet-100',
          iconClass: 'text-violet-600',
          iconPath: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        },
        {
          id: 'investments',
          title: 'Add Your Investments',
          description: 'Add account details to see portfolio analysis and performance tracking.',
          route: useJourneyRoutes ? '/onboarding/journey/investment' : '/net-worth/investments',
          actionText: 'Add Investments',
          flagKey: 'investments',
          dataCheck: 'hasInvestments',
          iconBgClass: 'bg-raspberry-100',
          iconClass: 'text-raspberry-600',
          iconPath: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        },
        {
          id: 'pensions',
          title: 'Add Your Pensions',
          description: 'Add details to see retirement projections and contribution analysis.',
          route: useJourneyRoutes ? '/onboarding/journey/retirement' : '/net-worth/retirement',
          actionText: 'Add Pensions',
          flagKey: 'pensions',
          dataCheck: 'hasPensions',
          iconBgClass: 'bg-savannah-200',
          iconClass: 'text-neutral-600',
          iconPath: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        },
        {
          id: 'protection',
          title: 'Add Your Protection Policies',
          description: 'Add details to see coverage analysis and gap identification.',
          route: useJourneyRoutes ? '/onboarding/journey/protection' : '/protection',
          actionText: 'Add Policies',
          flagKey: 'protection',
          dataCheck: 'hasProtection',
          iconBgClass: 'bg-horizon-100',
          iconClass: 'text-horizon-600',
          iconPath: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        },
      ];
    },

    visibleCards() {
      if (!this.isQuickOnboarding) return [];

      return this.allCards.filter(card => {
        const wasFlagged = card.flagKey ? this.assetFlags[card.flagKey] : false;
        if (!wasFlagged) return false;

        return !this[card.dataCheck];
      });
    },

    hasProperties() {
      return (this.properties || []).length > 0;
    },

    hasSavings() {
      return (this.accounts || []).length > 0;
    },

    hasInvestments() {
      return (this.investmentAccounts || []).length > 0;
    },

    hasPensions() {
      return (this.dcPensions || []).length > 0 || (this.dbPensions || []).length > 0;
    },

    hasProtection() {
      return (this.protectionLifePolicies?.length || 0) +
             (this.protectionCriticalIllnessPolicies?.length || 0) +
             (this.protectionIncomeProtectionPolicies?.length || 0) +
             (this.protectionDisabilityPolicies?.length || 0) +
             (this.protectionSicknessIllnessPolicies?.length || 0) > 0;
    },
  },
};
</script>
