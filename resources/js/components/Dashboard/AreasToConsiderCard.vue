<template>
  <div>
    <!-- Header -->
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Other Areas to Consider</h3>

    <!-- Areas List -->
    <div v-if="incompleteAreas.length > 0" class="space-y-2">
      <div
        v-for="area in incompleteAreas"
        :key="area.id"
        class="flex items-center justify-between py-2 cursor-pointer hover:bg-gray-50 -mx-2 px-2 rounded transition-colors"
        @click="navigateTo(area.route)"
      >
        <div class="flex items-center gap-3 min-w-0">
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
            :class="area.iconBgClass"
          >
            <!-- Document Icon -->
            <svg v-if="area.icon === 'document'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <!-- Calendar Icon -->
            <svg v-else-if="area.icon === 'calendar'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <!-- Shield Icon -->
            <svg v-else-if="area.icon === 'shield'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <!-- Chart Icon -->
            <svg v-else-if="area.icon === 'chart'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <!-- Cash Icon -->
            <svg v-else-if="area.icon === 'cash'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <!-- Target Icon -->
            <svg v-else-if="area.icon === 'target'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <!-- Currency Icon -->
            <svg v-else-if="area.icon === 'currency'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0">
            <span class="text-sm font-medium text-gray-900 block truncate">{{ area.title }}</span>
            <span class="text-xs text-gray-500 block truncate">{{ area.description }}</span>
          </div>
        </div>
        <svg class="w-4 h-4 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </div>
    </div>

    <!-- All Complete State -->
    <div v-else class="text-center py-6">
      <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <p class="text-sm text-gray-600">All areas complete!</p>
      <p class="text-xs text-gray-400 mt-1">Your financial profile is up to date</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import api from '@/services/api';

export default {
  name: 'AreasToConsiderCard',

  props: {
    limit: {
      type: Number,
      default: 0, // 0 means no limit
    },
  },

  data() {
    return {
      letterData: null,
      letterLoaded: false,
    };
  },

  computed: {
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension']),
    ...mapState('investment', { investmentAccounts: 'accounts' }),
    ...mapState('savings', { cashAccounts: 'accounts' }),
    ...mapState('goals', ['dashboardOverview']),
    ...mapState('userProfile', ['profile', 'incomeOccupation']),
    ...mapGetters('protection', {
      protectionLifePolicies: 'lifePolicies',
      protectionCriticalIllnessPolicies: 'criticalIllnessPolicies',
      protectionIncomeProtectionPolicies: 'incomeProtectionPolicies',
    }),

    user() {
      return this.$store.state.auth.user;
    },

    hasRetirement() {
      return (this.dcPensions && this.dcPensions.length > 0) ||
             (this.dbPensions && this.dbPensions.length > 0) ||
             !!this.statePension;
    },

    hasProtection() {
      return (this.protectionLifePolicies?.length || 0) +
             (this.protectionCriticalIllnessPolicies?.length || 0) +
             (this.protectionIncomeProtectionPolicies?.length || 0) > 0;
    },

    hasInvestments() {
      return this.investmentAccounts && this.investmentAccounts.length > 0;
    },

    hasSavings() {
      return this.cashAccounts && this.cashAccounts.length > 0;
    },

    hasGoals() {
      return this.dashboardOverview?.has_goals || false;
    },

    hasIncome() {
      if (!this.incomeOccupation) return false;
      const total = (this.incomeOccupation.annual_employment_income || 0) +
                   (this.incomeOccupation.annual_self_employment_income || 0) +
                   (this.incomeOccupation.annual_rental_income || 0) +
                   (this.incomeOccupation.annual_dividend_income || 0) +
                   (this.incomeOccupation.annual_other_income || 0);
      return total > 0;
    },

    hasLetterToSpouse() {
      // Don't show until we've loaded the data
      if (!this.letterLoaded) return true;

      // Check if letter has any meaningful content
      if (!this.letterData) return false;

      // Check if any key fields have been filled in
      const keyFields = [
        'immediate_actions',
        'executor_name',
        'executor_contact',
        'attorney_name',
        'financial_advisor_name',
        'immediate_funds_access',
        'password_manager_info',
        'estate_documents_location',
        'funeral_preference',
      ];

      return keyFields.some(field => {
        const value = this.letterData[field];
        return value && value.trim && value.trim().length > 0;
      });
    },

    isMarried() {
      return this.user?.marital_status === 'married';
    },

    allAreas() {
      const areas = [];

      // Letter to Spouse / Expression of Wishes
      if (!this.hasLetterToSpouse) {
        areas.push({
          id: 'letter',
          title: this.isMarried ? 'Letter to Spouse' : 'Expression of Wishes',
          description: 'Important info for loved ones',
          route: '/valuable-info?section=letter',
          icon: 'document',
          iconBgClass: 'bg-purple-100',
          iconClass: 'text-purple-600',
          priority: 1,
        });
      }

      // Retirement
      if (!this.hasRetirement) {
        areas.push({
          id: 'retirement',
          title: 'Retirement Planning',
          description: 'Add your pension details',
          route: '/net-worth/retirement',
          icon: 'calendar',
          iconBgClass: 'bg-blue-100',
          iconClass: 'text-blue-600',
          priority: 2,
        });
      }

      // Protection
      if (!this.hasProtection) {
        areas.push({
          id: 'protection',
          title: 'Protection',
          description: 'Life & income protection',
          route: '/protection',
          icon: 'shield',
          iconBgClass: 'bg-green-100',
          iconClass: 'text-green-600',
          priority: 3,
        });
      }

      // Investments
      if (!this.hasInvestments) {
        areas.push({
          id: 'investments',
          title: 'Investments',
          description: 'Track your investment accounts',
          route: '/net-worth/investments',
          icon: 'chart',
          iconBgClass: 'bg-indigo-100',
          iconClass: 'text-indigo-600',
          priority: 4,
        });
      }

      // Savings
      if (!this.hasSavings) {
        areas.push({
          id: 'savings',
          title: 'Cash & Savings',
          description: 'Add your savings accounts',
          route: '/net-worth/savings',
          icon: 'cash',
          iconBgClass: 'bg-sky-100',
          iconClass: 'text-sky-600',
          priority: 5,
        });
      }

      // Goals
      if (!this.hasGoals) {
        areas.push({
          id: 'goals',
          title: 'Financial Goals',
          description: 'Set and track your goals',
          route: '/goals',
          icon: 'target',
          iconBgClass: 'bg-primary-100',
          iconClass: 'text-primary-600',
          priority: 6,
        });
      }

      // Income
      if (!this.hasIncome) {
        areas.push({
          id: 'income',
          title: 'Income Details',
          description: 'Add your income sources',
          route: '/valuable-info?section=income',
          icon: 'currency',
          iconBgClass: 'bg-emerald-100',
          iconClass: 'text-emerald-600',
          priority: 7,
        });
      }

      return areas;
    },

    incompleteAreas() {
      // Sort by priority and apply limit if specified
      const sorted = [...this.allAreas].sort((a, b) => a.priority - b.priority);
      if (this.limit > 0) {
        return sorted.slice(0, this.limit);
      }
      return sorted;
    },
  },

  async mounted() {
    await this.loadLetterData();
  },

  methods: {
    async loadLetterData() {
      try {
        const response = await api.get('/user/letter-to-spouse');
        this.letterData = response.data.data;
      } catch (error) {
        // Letter might not exist yet, that's fine
        this.letterData = null;
      } finally {
        this.letterLoaded = true;
      }
    },

    navigateTo(route) {
      this.$router.push(route);
    },
  },
};
</script>
