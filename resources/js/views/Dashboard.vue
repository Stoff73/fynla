<template>
  <AppLayout>
    <div class="py-4 sm:py-6">
      <!-- 2FA Security Reminder Banner -->
      <div
        v-if="showMFABanner"
        class="mb-6 bg-green-100 border border-green-300 rounded-lg p-4 shadow-sm"
      >
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-green-200 rounded-full flex items-center justify-center">
              <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
          </div>
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-green-800">Secure Your Account with Two-Factor Authentication</h3>
            <p class="mt-1 text-sm text-green-700">
              Protect your financial data by enabling 2FA. It adds an extra layer of security using an authenticator app on your phone.
            </p>
            <div class="mt-3 flex items-center gap-3">
              <router-link
                to="/settings/security"
                class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition-colors"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Enable 2FA Now
              </router-link>
              <button
                @click="dismissMFABanner"
                class="text-sm text-green-700 hover:text-green-900 underline"
              >
                Remind me later
              </button>
            </div>
          </div>
          <button
            @click="dismissMFABanner"
            class="flex-shrink-0 text-green-500 hover:text-green-700"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Three-column dashboard grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Net Worth Card -->
        <DashboardCard
          v-if="hasNetWorthData"
          title="Net Worth"
          :loading="loading.netWorth"
          @click="navigateTo('/net-worth/wealth-summary')"
        >
          <div class="space-y-4">
            <!-- Primary value - no heading, just the value -->
            <div class="border-b border-gray-200 pb-4">
              <span
                class="text-3xl font-bold"
                :class="netWorthData.netWorth >= 0 ? 'text-green-600' : 'text-red-600'"
              >
                {{ formatCurrency(netWorthData.netWorth) }}
              </span>
            </div>

            <!-- Assets breakdown by category -->
            <div v-if="netWorthData.totalAssets > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Assets</div>
              <div
                v-for="(value, category) in netWorthBreakdown.assets"
                :key="category"
                class="flex justify-between text-sm"
              >
                <span class="text-gray-600">{{ formatAssetCategory(category) }}</span>
                <span class="font-medium text-gray-900">{{ formatCurrency(value) }}</span>
              </div>
              <div class="flex justify-between text-sm pt-2 border-t border-gray-100">
                <span class="text-gray-700 font-medium">Total Assets</span>
                <span class="font-semibold text-blue-600">{{ formatCurrency(netWorthData.totalAssets) }}</span>
              </div>
            </div>

            <!-- Liabilities breakdown by category -->
            <div v-if="netWorthData.totalLiabilities > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Liabilities</div>
              <div
                v-for="(value, category) in netWorthBreakdown.liabilities"
                :key="category"
                class="flex justify-between text-sm"
              >
                <span class="text-gray-600">{{ formatLiabilityCategory(category) }}</span>
                <span class="font-medium text-gray-900">{{ formatCurrency(value) }}</span>
              </div>
              <div class="flex justify-between text-sm pt-2 border-t border-gray-100">
                <span class="text-gray-700 font-medium">Total Liabilities</span>
                <span class="font-semibold text-red-600">{{ formatCurrency(netWorthData.totalLiabilities) }}</span>
              </div>
            </div>
          </div>
        </DashboardCard>

        <!-- Retirement Card -->
        <DashboardCard
          v-if="hasRetirementData"
          title="Retirement"
          :loading="loading.retirement"
          @click="navigateTo('/net-worth/retirement')"
        >
          <div class="space-y-4">
            <!-- Income row: Projected and Required inline -->
            <div class="flex justify-between border-b border-gray-200 pb-4">
              <div>
                <span class="text-sm text-gray-500">Projected Income</span>
                <div class="flex items-baseline gap-1 mt-1">
                  <span class="text-2xl font-bold text-green-600">
                    {{ formatCurrency(retirementData.projectedIncome) }}
                  </span>
                  <span class="text-xs text-gray-500">/yr</span>
                </div>
              </div>
              <div class="text-right">
                <span class="text-sm text-gray-500">Required Income</span>
                <div class="flex items-baseline justify-end gap-1 mt-1">
                  <span class="text-2xl font-bold text-gray-700">
                    {{ formatCurrency(retirementData.targetIncome) }}
                  </span>
                  <span class="text-xs text-gray-500">/yr</span>
                </div>
              </div>
            </div>

            <!-- Capital row: Projected and Required inline -->
            <div class="flex justify-between border-b border-gray-200 pb-4">
              <div>
                <span class="text-sm text-gray-500">Projected Capital</span>
                <div class="mt-1">
                  <span class="text-2xl font-bold text-green-600">
                    {{ formatCurrency(retirementData.projectedCapital) }}
                  </span>
                </div>
              </div>
              <div class="text-right">
                <span class="text-sm text-gray-500">Capital Required</span>
                <div class="mt-1">
                  <span class="text-2xl font-bold text-gray-700">
                    {{ formatCurrency(retirementData.capitalRequired) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Retirement age and years -->
            <div class="flex justify-between">
              <div v-if="retirementData.retirementAge" class="text-center">
                <span class="text-sm text-gray-500">Retirement Age</span>
                <div class="text-lg font-semibold text-gray-900">{{ retirementData.retirementAge }}</div>
              </div>
              <div v-if="retirementData.yearsToRetirement !== null" class="text-center">
                <span class="text-sm text-gray-500">Years to Retirement</span>
                <div class="text-lg font-semibold text-gray-900">{{ retirementData.yearsToRetirement }} years</div>
              </div>
            </div>
          </div>
        </DashboardCard>

        <!-- Investment & Savings Card -->
        <DashboardCard
          v-if="hasInvestmentData"
          title="Investments & Savings"
          :loading="loading.investment"
          @click="navigateTo('/net-worth/investments')"
        >
          <div class="space-y-4">
            <!-- Investment accounts list -->
            <div v-if="investmentAccountsList.length > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Investments</div>
              <div
                v-for="account in investmentAccountsList"
                :key="'inv-' + account.id"
                class="flex justify-between text-sm"
              >
                <span class="text-gray-600 truncate mr-2">{{ account.account_name }}</span>
                <span class="font-medium text-gray-900 whitespace-nowrap">{{ formatCurrency(account.current_value) }}</span>
              </div>
              <div class="flex justify-between text-sm pt-2 border-t border-gray-100">
                <span class="text-gray-700 font-medium">Total Investments</span>
                <span class="font-semibold text-primary-600">{{ formatCurrency(investmentData.totalInvestments) }}</span>
              </div>
            </div>

            <!-- Cash accounts list -->
            <div v-if="cashAccountsList.length > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Cash Accounts</div>
              <div
                v-for="account in cashAccountsList"
                :key="'cash-' + account.id"
                class="flex justify-between text-sm"
              >
                <span class="text-gray-600 truncate mr-2">{{ account.account_name }}</span>
                <span class="font-medium text-gray-900 whitespace-nowrap">{{ formatCurrency(account.current_balance) }}</span>
              </div>
              <div class="flex justify-between text-sm pt-2 border-t border-gray-100">
                <span class="text-gray-700 font-medium">Total Cash</span>
                <span class="font-semibold text-primary-600">{{ formatCurrency(investmentData.totalCash) }}</span>
              </div>
            </div>
          </div>
        </DashboardCard>

        <!-- Protection Card -->
        <DashboardCard
          v-if="hasProtectionData"
          title="Protection"
          :loading="loading.protection"
          @click="navigateTo('/protection')"
        >
          <div class="space-y-4">
            <!-- Adequacy score -->
            <div class="border-b border-gray-200 pb-4">
              <span class="text-sm text-gray-500">Coverage Adequacy</span>
              <div class="flex items-baseline gap-2 mt-1">
                <span class="text-3xl font-bold text-primary-600">
                  {{ protectionData.adequacyScore }}%
                </span>
              </div>
            </div>

            <!-- Coverage breakdown -->
            <div class="space-y-3">
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Total Coverage</span>
                <span class="font-semibold text-gray-900">{{ formatCurrency(protectionData.totalCoverage) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Monthly Premium</span>
                <span class="font-semibold text-gray-900">{{ formatCurrency(protectionData.premiumTotal) }}/mo</span>
              </div>
            </div>

            <!-- Policy count -->
            <div class="text-sm text-gray-500">
              {{ protectionData.policyCount }} active {{ protectionData.policyCount === 1 ? 'policy' : 'policies' }}
            </div>

            <!-- Critical gaps warning -->
            <div
              v-if="protectionData.criticalGaps > 0"
              class="p-3 bg-white border-2 border-blue-500 rounded-lg"
            >
              <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span class="text-sm font-medium text-blue-700">
                  {{ protectionData.criticalGaps }} coverage {{ protectionData.criticalGaps === 1 ? 'gap' : 'gaps' }} identified
                </span>
              </div>
            </div>
          </div>
        </DashboardCard>

        <!-- Goals & Events Card (spans 2 columns on larger screens) -->
        <DashboardCard
          v-if="hasGoalsData"
          title="Goals & Life Events"
          :loading="loading.goals"
          class="lg:col-span-2"
          @click="navigateTo('/goals')"
        >
          <div class="space-y-4">
            <!-- Goals summary -->
            <div v-if="goalsData.hasGoals" class="border-b border-gray-200 pb-4">
              <div class="flex justify-between items-center">
                <div>
                  <span class="text-sm text-gray-500">Overall Progress</span>
                  <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-2xl font-bold text-primary-600">
                      {{ formatCurrency(goalsData.totalCurrent) }}
                    </span>
                    <span class="text-sm text-gray-500">of {{ formatCurrency(goalsData.totalTarget) }}</span>
                  </div>
                </div>
                <div class="text-right">
                  <span class="text-2xl font-bold text-primary-600">{{ goalsData.overallProgress }}%</span>
                  <div class="text-sm text-gray-500">complete</div>
                </div>
              </div>
              <!-- Progress bar -->
              <div class="mt-3">
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div
                    class="h-2 rounded-full transition-all duration-500"
                    :class="goalsData.overallProgress >= 75 ? 'bg-green-500' : 'bg-blue-500'"
                    :style="{ width: Math.min(goalsData.overallProgress, 100) + '%' }"
                  ></div>
                </div>
                <div class="flex justify-between mt-1">
                  <span class="text-xs text-gray-500">{{ goalsData.totalGoals }} goals</span>
                  <span
                    class="text-xs font-medium"
                    :class="goalsData.onTrackCount === goalsData.totalGoals ? 'text-green-600' : 'text-blue-600'"
                  >
                    {{ goalsData.onTrackCount }}/{{ goalsData.totalGoals }} on track
                  </span>
                </div>
              </div>
            </div>

            <!-- Life events summary -->
            <div v-if="goalsData.lifeEventsCount > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Upcoming Life Events</div>
              <div class="text-sm text-gray-600">
                {{ goalsData.lifeEventsCount }} planned {{ goalsData.lifeEventsCount === 1 ? 'event' : 'events' }}
              </div>
            </div>

            <!-- Mini projection chart placeholder -->
            <div v-if="goalsData.hasProjection" class="pt-4 border-t border-gray-200">
              <GoalsProjectionChartMini />
            </div>

            <!-- Empty state for goals -->
            <div v-if="!goalsData.hasGoals && !goalsData.lifeEventsCount" class="text-center py-4">
              <div class="mx-auto w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <h3 class="text-sm font-semibold text-gray-900 mb-1">Set Your First Goal</h3>
              <p class="text-xs text-gray-500">
                Track your financial goals and life events
              </p>
            </div>
          </div>
        </DashboardCard>

        <!-- Admin Taxes (only for admins) -->
        <DashboardCard
          v-if="isAdmin"
          title="UK Taxes"
          :loading="loading.taxAllowances"
          @click="navigateTo('/uk-taxes')"
        >
          <UKTaxesOverviewCard />
        </DashboardCard>
      </div>

      <!-- Actions Card (full width) -->
      <div class="mt-6">
        <ActionsOverviewCard />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters, mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import DashboardCard from '@/components/Dashboard/DashboardCard.vue';
import GoalsProjectionChartMini from '@/components/Dashboard/GoalsProjectionChartMini.vue';
import UKTaxesOverviewCard from '@/components/Dashboard/UKTaxesOverviewCard.vue';
import ActionsOverviewCard from '@/components/Dashboard/ActionsOverviewCard.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import userProfileService from '@/services/userProfileService';

export default {
  name: 'Dashboard',

  components: {
    AppLayout,
    DashboardCard,
    GoalsProjectionChartMini,
    UKTaxesOverviewCard,
    ActionsOverviewCard,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: {
        netWorth: true,
        retirement: true,
        investment: true,
        protection: true,
        goals: true,
        taxAllowances: true,
      },
      errors: {
        protection: null,
        estate: null,
      },
      dataLoaded: false,
      mfaBannerDismissed: localStorage.getItem('mfaBannerDismissed') === 'true',
      financialCommitmentsData: null,
    };
  },

  computed: {
    ...mapGetters('auth', ['isAdmin']),

    showMFABanner() {
      const user = this.$store.state.auth.user;
      if (!user) return false;
      if (user.is_preview_user) return false;
      if (this.mfaBannerDismissed) return false;
      return user.mfa_enabled !== true;
    },

    // Net Worth data
    ...mapGetters('netWorth', {
      netWorthValue: 'netWorth',
      netWorthAssets: 'totalAssets',
      netWorthLiabilities: 'totalLiabilities',
    }),

    netWorthData() {
      return {
        netWorth: this.netWorthValue || 0,
        totalAssets: this.netWorthAssets || 0,
        totalLiabilities: this.netWorthLiabilities || 0,
      };
    },

    // Breakdown of assets and liabilities by category
    netWorthBreakdown() {
      const overview = this.$store.state.netWorth.overview;
      // Filter out categories with zero values
      const assets = {};
      const liabilities = {};

      if (overview.breakdown) {
        Object.entries(overview.breakdown).forEach(([key, value]) => {
          if (value > 0) {
            assets[key] = value;
          }
        });
      }

      if (overview.liabilitiesBreakdown) {
        Object.entries(overview.liabilitiesBreakdown).forEach(([key, value]) => {
          if (value > 0) {
            liabilities[key] = value;
          }
        });
      }

      return { assets, liabilities };
    },

    hasNetWorthData() {
      return this.netWorthData.totalAssets > 0 || this.netWorthData.totalLiabilities > 0;
    },

    // Retirement data - using real API data
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'profile', 'requiredCapital', 'analysis']),
    ...mapGetters('retirement', ['totalPensionWealth', 'yearsToRetirement', 'projectedIncome']),

    retirementData() {
      // Use real data from API - NOT made up calculations
      const requiredCapital = this.requiredCapital || {};

      return {
        projectedIncome: this.projectedIncome || 0,
        targetIncome: requiredCapital.required_income || 0,
        projectedCapital: this.totalPensionWealth || 0,
        capitalRequired: requiredCapital.required_capital_at_retirement || 0,
        retirementAge: this.profile?.target_retirement_age || null,
        yearsToRetirement: this.yearsToRetirement || null,
      };
    },

    hasRetirementData() {
      return (this.dcPensions && this.dcPensions.length > 0) ||
             (this.dbPensions && this.dbPensions.length > 0) ||
             !!this.statePension;
    },

    // Investment & Savings data
    ...mapState('investment', { investmentAccounts: 'accounts', investmentAnalysis: 'analysis' }),
    ...mapGetters('investment', ['totalPortfolioValue']),
    ...mapState('savings', ['expenditureProfile', 'accounts']),

    // Investment accounts list (for line items)
    investmentAccountsList() {
      return this.investmentAccounts || [];
    },

    // Cash/savings accounts list (for line items)
    cashAccountsList() {
      return this.accounts || [];
    },

    investmentData() {
      // Total investments from investment accounts
      const totalInvestments = this.investmentAccountsList.reduce((sum, account) => {
        return sum + parseFloat(account.current_value || 0);
      }, 0);

      // Total cash from savings accounts
      const totalCash = this.cashAccountsList.reduce((sum, account) => {
        return sum + parseFloat(account.current_balance || 0);
      }, 0);

      return {
        totalInvestments,
        totalCash,
        totalValue: totalInvestments + totalCash,
        accountsCount: (this.investmentAccountsList?.length || 0) + (this.cashAccountsList?.length || 0),
      };
    },

    hasInvestmentData() {
      return (this.investmentAccountsList && this.investmentAccountsList.length > 0) ||
             (this.cashAccountsList && this.cashAccountsList.length > 0);
    },

    // Protection data
    ...mapGetters('protection', {
      protectionAdequacyScore: 'adequacyScore',
      protectionTotalCoverage: 'totalCoverage',
      protectionTotalPremium: 'totalPremium',
      protectionCoverageGaps: 'coverageGaps',
      protectionLifePolicies: 'lifePolicies',
      protectionCriticalIllnessPolicies: 'criticalIllnessPolicies',
      protectionIncomeProtectionPolicies: 'incomeProtectionPolicies',
      protectionDisabilityPolicies: 'disabilityPolicies',
    }),

    protectionData() {
      const gaps = this.protectionCoverageGaps?.gaps_by_category || {};
      const criticalGaps = Object.values(gaps).filter(gap => gap > 10000).length || 0;
      const adequacyScore = typeof this.protectionAdequacyScore === 'object'
        ? (this.protectionAdequacyScore?.score ?? 0)
        : (this.protectionAdequacyScore || 0);

      const policyCount = (this.protectionLifePolicies?.length || 0) +
                          (this.protectionCriticalIllnessPolicies?.length || 0) +
                          (this.protectionIncomeProtectionPolicies?.length || 0) +
                          (this.protectionDisabilityPolicies?.length || 0);

      return {
        adequacyScore,
        totalCoverage: this.protectionTotalCoverage || 0,
        premiumTotal: (this.protectionTotalPremium || 0) / 12, // Monthly premium
        criticalGaps,
        policyCount,
      };
    },

    hasProtectionData() {
      return this.protectionData.policyCount > 0;
    },

    // Goals data
    ...mapState('goals', ['dashboardOverview', 'projectionData']),
    ...mapGetters('goals', ['dashboardData']),

    goalsData() {
      const data = this.dashboardData || {};
      return {
        hasGoals: data.has_goals || false,
        totalGoals: data.total_goals || 0,
        onTrackCount: data.on_track_count || 0,
        totalTarget: data.total_target || 0,
        totalCurrent: data.total_current || 0,
        overallProgress: Math.round(data.overall_progress || 0),
        lifeEventsCount: data.life_events_count || 0,
        hasProjection: !!this.projectionData,
      };
    },

    hasGoalsData() {
      // Always show goals card - it has empty state
      return true;
    },
  },

  methods: {
    ...mapActions('goals', ['fetchDashboardOverview', 'fetchProjection']),
    ...mapActions('retirement', ['fetchRequiredCapital']),

    // Format asset category names for display
    formatAssetCategory(category) {
      const categoryLabels = {
        pensions: 'Pensions',
        property: 'Property',
        investments: 'Investments',
        cash: 'Cash & Savings',
        business: 'Business Interests',
        chattels: 'Chattels',
      };
      return categoryLabels[category] || category.charAt(0).toUpperCase() + category.slice(1).replace(/_/g, ' ');
    },

    // Format liability category names for display
    formatLiabilityCategory(category) {
      const categoryLabels = {
        mortgages: 'Mortgages',
        loans: 'Loans',
        credit_cards: 'Credit Cards',
        other: 'Other Liabilities',
      };
      return categoryLabels[category] || category.charAt(0).toUpperCase() + category.slice(1).replace(/_/g, ' ');
    },

    dismissMFABanner() {
      this.mfaBannerDismissed = true;
      localStorage.setItem('mfaBannerDismissed', 'true');
    },

    navigateTo(path) {
      this.$router.push(path);
    },

    async loadFinancialCommitments() {
      try {
        const response = await userProfileService.getFinancialCommitments();
        if (response.success) {
          this.financialCommitmentsData = response.data;
        }
      } catch (error) {
        console.error('Failed to load financial commitments:', error);
        this.financialCommitmentsData = null;
      }
    },

    async loadAllData() {
      const user = this.$store.state.auth.user;
      const isMarried = user && user.marital_status === 'married';
      const estateCalculationAction = isMarried
        ? 'estate/calculateSecondDeathIHTPlanning'
        : 'estate/calculateIHT';

      const moduleLoaders = [
        { name: 'netWorth', action: 'netWorth/fetchOverview' },
        { name: 'protection', action: 'protection/fetchProtectionData' },
        { name: 'estate', action: 'estate/fetchEstateData' },
        { name: 'estate', action: estateCalculationAction, payload: {} },
        { name: 'retirement', action: 'trusts/fetchTrusts' },
        { name: 'investment', action: 'userProfile/fetchProfile' },
        { name: 'retirement', action: 'retirement/fetchRetirementData' },
        { name: 'retirement', action: 'retirement/fetchRequiredCapital' },
        { name: 'retirement', action: 'retirement/analyseRetirement' },
        { name: 'investment', action: 'investment/fetchInvestmentData' },
        { name: 'investment', action: 'investment/analyseInvestment' },
        { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
        { name: 'goals', action: 'goals/fetchDashboardOverview' },
      ];

      Object.keys(this.loading).forEach(key => {
        this.loading[key] = true;
      });
      Object.keys(this.errors).forEach(key => {
        this.errors[key] = null;
      });

      // Load financial commitments
      this.loadFinancialCommitments();

      const moduleActionCounts = {};
      moduleLoaders.forEach(loader => {
        moduleActionCounts[loader.name] = (moduleActionCounts[loader.name] || 0) + 1;
      });

      const moduleCompletedCounts = {};

      const promises = moduleLoaders.map(loader =>
        this.$store.dispatch(loader.action, loader.payload)
          .then(() => ({ module: loader.name, success: true }))
          .catch(error => ({
            module: loader.name,
            success: false,
            error: error.response?.data?.message || error.message || 'Unknown error'
          }))
      );

      const results = await Promise.allSettled(promises);

      results.forEach(result => {
        if (result.status === 'fulfilled') {
          const { module, success, error } = result.value;
          moduleCompletedCounts[module] = (moduleCompletedCounts[module] || 0) + 1;

          if (!success && this.loading.hasOwnProperty(module)) {
            this.errors[module] = error;
          }

          if (this.loading.hasOwnProperty(module) &&
              moduleCompletedCounts[module] >= moduleActionCounts[module]) {
            this.loading[module] = false;
          }
        } else {
          console.error('Failed to load module:', result.reason);
        }
      });

      // Also try to fetch projection data for goals chart
      try {
        await this.fetchProjection();
      } catch (e) {
        // Projection is optional, don't block
      }
    },
  },

  watch: {
    '$store.state.auth.user': {
      immediate: true,
      handler(user) {
        if (user && !this.dataLoaded) {
          this.dataLoaded = true;
          this.loadAllData();
        }
      }
    }
  },
};
</script>

<style scoped>
/* Card styling inherited from DashboardCard component */
</style>
