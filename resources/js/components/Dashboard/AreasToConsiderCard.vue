<template>
  <div>
    <!-- Header -->
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Other Areas to Consider</h3>

    <!-- Areas List -->
    <div v-if="incompleteAreas.length > 0" class="space-y-2">
      <div
        v-for="area in incompleteAreas"
        :key="area.id"
        class="flex items-center justify-between py-2 cursor-pointer hover:bg-savannah-100 -mx-2 px-2 rounded transition-colors"
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
            <!-- Home Icon -->
            <svg v-else-if="area.icon === 'home'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
          </div>
          <div class="min-w-0">
            <span class="text-sm font-medium text-horizon-500 block truncate">{{ area.title }}</span>
            <span class="text-xs text-neutral-500 block truncate">{{ area.description }}</span>
          </div>
        </div>
        <svg class="w-4 h-4 text-horizon-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </div>
    </div>

    <!-- All Complete State -->
    <div v-else class="text-center py-6">
      <div class="w-12 h-12 rounded-full bg-spring-100 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <p class="text-sm text-neutral-500">All areas complete!</p>
      <p class="text-xs text-horizon-400 mt-1">Your financial profile is up to date</p>
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
      hasLetterContent: false,
      letterLoaded: false,
      willData: null,
      willLoaded: false,
    };
  },

  computed: {
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension']),
    ...mapState('investment', { investmentAccounts: 'accounts' }),
    ...mapState('savings', { cashAccounts: 'accounts' }),
    ...mapState('goals', ['dashboardOverview']),
    ...mapState('userProfile', ['profile', 'incomeOccupation']),
    ...mapState('netWorth', ['overview']),
    ...mapGetters('protection', {
      protectionLifePolicies: 'lifePolicies',
      protectionCriticalIllnessPolicies: 'criticalIllnessPolicies',
      protectionIncomeProtectionPolicies: 'incomeProtectionPolicies',
    }),
    ...mapGetters('auth', ['currentUser']),

    user() {
      return this.currentUser;
    },

    isMarried() {
      return this.user?.marital_status === 'married';
    },

    isRetired() {
      return this.user?.employment_status === 'retired';
    },

    userAge() {
      if (!this.user?.date_of_birth) return null;
      const dob = new Date(this.user.date_of_birth);
      const today = new Date();
      let age = today.getFullYear() - dob.getFullYear();
      const monthDiff = today.getMonth() - dob.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
      }
      return age;
    },

    // Check for GAPS and missing items, not just "has any data"
    allAreas() {
      const areas = [];

      // 1. Letter to Spouse / Expression of Wishes - if not filled in
      if (this.letterLoaded && !this.hasLetterContent) {
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

      // 2. Will - if user doesn't have one (check will data loaded from API)
      // Use truthy check since API may return 1 or true
      const hasWill = this.willLoaded && !!this.willData?.has_will;
      if (this.willLoaded && !hasWill) {
        // Different message for single vs married/partnered users
        const willDescription = this.isMarried
          ? 'Protect your family\'s future'
          : 'Ensure your wishes are followed for your assets';
        areas.push({
          id: 'will',
          title: 'Will',
          description: willDescription,
          route: '/estate/will-builder',
          icon: 'document',
          iconBgClass: 'bg-savannah-100',
          iconClass: 'text-neutral-500',
          priority: 2,
        });
      }

      // 3. Critical Illness - if NO policies
      // Don't suggest for users over 50 (policies often unavailable/unaffordable) or retired users
      const criticalIllnessAppropriate = !this.isRetired && (this.userAge === null || this.userAge <= 50);
      if (criticalIllnessAppropriate && (!this.protectionCriticalIllnessPolicies || this.protectionCriticalIllnessPolicies.length === 0)) {
        areas.push({
          id: 'critical-illness',
          title: 'Critical Illness Cover',
          description: 'Protection if you become seriously ill',
          route: '/protection',
          icon: 'shield',
          iconBgClass: 'bg-raspberry-100',
          iconClass: 'text-raspberry-600',
          priority: 3,
        });
      }

      // 4. Income Protection - if NO policies
      // Don't suggest for retired users (they have pension income, not employment income to protect)
      if (!this.isRetired && (!this.protectionIncomeProtectionPolicies || this.protectionIncomeProtectionPolicies.length === 0)) {
        areas.push({
          id: 'income-protection',
          title: 'Income Protection',
          description: 'Cover your income if unable to work',
          route: '/protection',
          icon: 'shield',
          iconBgClass: 'bg-violet-100',
          iconClass: 'text-violet-600',
          priority: 4,
        });
      }

      // 5. Life Insurance - if NO policies (only show for married users or those with dependants)
      // Single users without dependants typically don't need life insurance
      // Don't suggest for retired users (too old for meaningful coverage)
      if (!this.isRetired && (!this.protectionLifePolicies || this.protectionLifePolicies.length === 0)) {
        // Only recommend life insurance for married/partnered users who likely have dependants
        if (this.isMarried) {
          areas.push({
            id: 'life-insurance',
            title: 'Life Insurance',
            description: 'Protect your family if something happens',
            route: '/protection',
            icon: 'shield',
            iconBgClass: 'bg-spring-100',
            iconClass: 'text-spring-600',
            priority: 5,
          });
        }
      }

      // 6. Pensions - if none at all
      const hasPensions = (this.dcPensions?.length > 0) || (this.dbPensions?.length > 0) || !!this.statePension;
      if (!hasPensions) {
        areas.push({
          id: 'pensions',
          title: 'Pensions',
          description: 'Track your retirement savings',
          route: '/net-worth/retirement',
          icon: 'calendar',
          iconBgClass: 'bg-indigo-100',
          iconClass: 'text-indigo-600',
          priority: 6,
        });
      }

      // 7. ISA - if no ISA accounts (not using tax-free allowance)
      const hasISA = this.investmentAccounts?.some(a => a.account_type === 'isa') ||
                     this.cashAccounts?.some(a => a.is_isa || a.account_type === 'cash_isa');
      if (!hasISA) {
        areas.push({
          id: 'isa',
          title: 'ISA Allowance',
          description: 'Use your £20k tax-free allowance',
          route: '/net-worth/investments',
          icon: 'cash',
          iconBgClass: 'bg-emerald-100',
          iconClass: 'text-emerald-600',
          priority: 7,
        });
      }

      // 8. Emergency Fund - if no savings or very low
      const totalSavings = this.cashAccounts?.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0) || 0;
      if (totalSavings < 1000) {
        areas.push({
          id: 'emergency-fund',
          title: 'Emergency Fund',
          description: 'Build a financial safety net',
          route: '/net-worth/savings',
          icon: 'cash',
          iconBgClass: 'bg-sky-100',
          iconClass: 'text-sky-600',
          priority: 8,
        });
      }

      // 9. Goals - if none set
      const hasGoals = this.dashboardOverview?.has_goals || false;
      if (!hasGoals) {
        areas.push({
          id: 'goals',
          title: 'Financial Goals',
          description: 'Set and track your objectives',
          route: '/goals',
          icon: 'target',
          iconBgClass: 'bg-raspberry-100',
          iconClass: 'text-raspberry-500',
          priority: 9,
        });
      }

      // 10. Income - if not recorded
      // Check employment income
      const hasEmploymentIncome = this.incomeOccupation && (
        (this.incomeOccupation.annual_employment_income || 0) +
        (this.incomeOccupation.annual_self_employment_income || 0) +
        (this.incomeOccupation.annual_rental_income || 0) +
        (this.incomeOccupation.annual_dividend_income || 0) +
        (this.incomeOccupation.annual_other_income || 0)
      ) > 0;
      // Check pension income (state pension, DB pensions, or DC pensions for retired users)
      const hasStatePensionIncome = this.statePension?.annual_amount > 0;
      const hasDBPensionIncome = this.dbPensions?.some(p => p.accrued_annual_pension > 0);
      const hasDCPensionIncome = this.isRetired && this.dcPensions?.length > 0;
      const hasPensionIncome = hasStatePensionIncome || hasDBPensionIncome || hasDCPensionIncome;
      const hasIncome = hasEmploymentIncome || hasPensionIncome;
      if (!hasIncome) {
        areas.push({
          id: 'income',
          title: 'Income Details',
          description: 'Record your income sources',
          route: '/valuable-info?section=income',
          icon: 'currency',
          iconBgClass: 'bg-teal-100',
          iconClass: 'text-teal-600',
          priority: 10,
        });
      }

      // 11. Properties - if no properties AND not renting
      // Users who are renting (paying rent) don't need a prompt to add properties
      // Check overview breakdown for property value (overview is loaded by Dashboard)
      const hasProperties = (this.overview?.breakdown?.property || 0) > 0;
      const isPayingRent = (this.user?.rent || 0) > 0;
      if (!hasProperties && !isPayingRent) {
        areas.push({
          id: 'properties',
          title: 'Your Properties',
          description: 'Track your property assets',
          route: '/net-worth',
          icon: 'home',
          iconBgClass: 'bg-slate-100',
          iconClass: 'text-slate-600',
          priority: 11,
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
    // Load letter and will data in parallel
    await Promise.all([
      this.loadLetterData(),
      this.loadWillData(),
    ]);
  },

  methods: {
    async loadLetterData() {
      try {
        const response = await api.get('/user/letter-to-spouse/exists');
        this.hasLetterContent = response.data.has_content || false;
      } catch (error) {
        // Letter might not exist yet, that's fine
        this.hasLetterContent = false;
      } finally {
        this.letterLoaded = true;
      }
    },

    async loadWillData() {
      try {
        const response = await api.get('/estate/will');
        this.willData = response.data.data || null;
      } catch (error) {
        // Will might not exist yet, that's fine
        this.willData = null;
      } finally {
        this.willLoaded = true;
      }
    },

    navigateTo(route) {
      this.$router.push(route);
    },
  },
};
</script>
