<template>
  <AppLayout>
    <!-- Gamification level-up celebration (fixed-position overlay) -->
    <GamificationCelebration
      v-if="celebration"
      :level="celebration.level"
      :level-name="celebration.level_name"
      :next-actions="celebration.next_actions"
      @dismiss="onCelebrationDismiss"
    />

    <!-- Journey blur overlay (desktop only, from Quick Start with Fyn registration) -->
    <div
      v-if="journeyBlurActive"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm z-30 hidden lg:block"
    ></div>

    <div class="py-2 sm:py-3">
      <!-- 2FA Security Reminder Notification -->
      <div
        v-if="showMFABanner"
        class="mb-6 bg-spring-100 border border-spring-200 rounded-lg p-4 shadow-sm"
      >
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-spring-200 rounded-full flex items-center justify-center">
              <svg class="w-5 h-5 text-spring-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
          </div>
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-spring-800">Secure your account with two-factor authentication</h3>
            <p class="mt-1 text-sm text-spring-700">
              Protect your financial data by adding an extra layer of security using an authenticator app on your phone.
            </p>
            <div class="mt-3">
              <router-link
                to="/settings/security"
                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-button border border-spring-300 text-spring-800 bg-white hover:bg-spring-50 transition-colors"
              >
                Enable Two-Factor Authentication →
              </router-link>
            </div>
          </div>
          <button
            @click="dismissMFABanner"
            class="flex-shrink-0 text-spring-400 hover:text-spring-600"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Investment Knowledge Nudge -->
      <div
        v-if="showKnowledgeNudge"
        class="mb-6 bg-light-pink-50 border border-light-pink-200 rounded-lg p-4 shadow-sm"
      >
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-light-pink-100 rounded-full flex items-center justify-center">
              <svg class="w-5 h-5 text-raspberry-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
            </div>
          </div>
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-raspberry-800">How would you describe your investment knowledge?</h3>
            <p class="mt-1 text-sm text-raspberry-700">
              This helps us tailor investment recommendations to your experience level.
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <button
                @click="setKnowledgeLevel('novice')"
                :disabled="savingKnowledgeLevel"
                class="px-4 py-2 text-sm font-medium rounded-button border border-light-pink-300 text-raspberry-700 bg-white hover:bg-light-pink-100 transition-colors"
              >
                Beginner — I'm new to investing
              </button>
              <button
                @click="setKnowledgeLevel('intermediate')"
                :disabled="savingKnowledgeLevel"
                class="px-4 py-2 text-sm font-medium rounded-button border border-light-pink-300 text-raspberry-700 bg-white hover:bg-light-pink-100 transition-colors"
              >
                Intermediate — I understand the basics
              </button>
              <button
                @click="setKnowledgeLevel('experienced')"
                :disabled="savingKnowledgeLevel"
                class="px-4 py-2 text-sm font-medium rounded-button border border-light-pink-300 text-raspberry-700 bg-white hover:bg-light-pink-100 transition-colors"
              >
                Experienced — I'm confident with investments
              </button>
            </div>
          </div>
          <button
            @click="dismissKnowledgeNudge"
            class="flex-shrink-0 text-raspberry-400 hover:text-raspberry-600"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <GamifiedDashboard />
    </div>
  </AppLayout>

  <!-- Net Worth donut hover tooltip (teleported to body to avoid overflow:hidden clipping) -->
  <Teleport to="body">
    <div
      v-if="nwHoveredIndex !== null && netWorthDonutSegments[nwHoveredIndex]"
      class="nw-donut-tooltip"
      :style="{ left: nwMouseX + 12 + 'px', top: nwMouseY - 40 + 'px', background: netWorthDonutSegments[nwHoveredIndex].color }"
    >
      <span class="font-semibold">{{ netWorthDonutSegments[nwHoveredIndex].label }}</span>
      <span>{{ formatCurrency(netWorthDonutSegments[nwHoveredIndex].value) }}</span>
      <span class="text-white/70">{{ netWorthDonutSegments[nwHoveredIndex].percent }}%</span>
    </div>
  </Teleport>
</template>

<script>
import { mapGetters, mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ASSET_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';
import storage from '@/utils/storage';
import userProfileService from '@/services/userProfileService';
import { getRelativeTime, getCurrentTaxYear } from '@/utils/dateFormatter';
import { LIFETIME_ISA_ALLOWANCE } from '@/constants/taxConfig';

import GamificationCelebration from '@/components/Gamification/GamificationCelebration.vue';
import GamifiedDashboard from '@/views/GamifiedDashboard.vue';

import logger from '@/utils/logger';
export default {
  name: 'DashboardView',

  components: {
    AppLayout,
    GamificationCelebration,
    GamifiedDashboard,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: {
        netWorth: true,
        retirement: true,
        estate: true,
        investment: true,
        protection: true,
        goals: true,
        taxAllowances: true,
        plans: false,
      },
      errors: {
        protection: null,
        estate: null,
      },
      dataLoaded: false,
      mfaBannerDismissed: sessionStorage.getItem('mfaBannerDismissed') === 'true',
      knowledgeNudgeDismissed: storage.get('knowledgeNudgeDismissed') === 'true',
      savingKnowledgeLevel: false,
      savingsAccountsExpanded: true,
      investmentAccountsExpanded: false,
      financialCommitmentsData: null,
      willSelection: null,
      isMobile: window.innerWidth < 768,
      nwHoveredIndex: null,
      nwMouseX: 0,
      nwMouseY: 0,
      journeyBlurActive: false,
    };
  },

  computed: {
    ...mapGetters('auth', ['isAdmin', 'currentUser', 'tierFlags']),
    ...mapGetters('preview', ['effectivePersonaData']),
    ...mapGetters('plans', { getPlan: 'getPlan' }),
    ...mapGetters('lifeStage', {
      currentStage: 'currentStage',
      stageDashboardCards: 'dashboardCards',
    }),
    ...mapGetters('taxConfig', ['isaAnnualAllowance', 'pensionAnnualAllowance']),

    isStudentPersona() {
      return this.currentUser?.preview_persona_id === 'student'
        && this.currentUser?.is_preview_user === true;
    },

    recentTransactions() {
      if (!this.isStudentPersona) return [];
      const transactions = this.effectivePersonaData?.recent_transactions || [];
      return transactions.map(t => ({
        ...t,
        relativeDate: getRelativeTime(t.date),
      }));
    },

    studentLiability() {
      if (!this.isStudentPersona) return null;

      const extractPlanType = (name) => {
        const match = (name || '').match(/Plan\s*(\d)/i);
        return match ? `Plan ${match[1]}` : null;
      };

      const buildResult = (loan) => {
        const name = loan.liability_name || loan.name || 'Student Loan';
        const planType = extractPlanType(name) || 'Plan 2';
        return {
          balance: parseFloat(loan.current_balance || 0),
          name,
          planType,
          interestRate: parseFloat(loan.interest_rate || 0),
          notes: loan.notes || '',
        };
      };

      // Check estate store liabilities first (real user data)
      const estateLiabilities = this.$store.state.estate?.liabilities || [];
      const storeLoan = estateLiabilities.find(l => (l.liability_type || '').includes('student'));
      if (storeLoan) return buildResult(storeLoan);

      // Fallback: net worth overview liabilities
      const overview = this.netWorthOverview;
      const liabilities = overview?.liabilities || [];
      const loan = liabilities.find(l => (l.liability_type || '').includes('student'));
      if (loan) return buildResult(loan);

      // Fallback: persona JSON data
      const personaLiabilities = this.effectivePersonaData?.liabilities || [];
      const personaLoan = personaLiabilities.find(l => (l.liability_type || '').includes('student'));
      if (personaLoan) return buildResult(personaLoan);

      return null;
    },

    studentLoanDetails() {
      const planType = this.studentLiability?.planType || 'Plan 2';
      const details = {
        'Plan 1': { threshold: 24990, writeOff: 25 },
        'Plan 2': { threshold: 27295, writeOff: 30 },
        'Plan 4': { threshold: 31395, writeOff: 30 },
        'Plan 5': { threshold: 25000, writeOff: 40 },
      };
      return details[planType] || details['Plan 2'];
    },

    hasAreasToComplete() {
      const skippedSteps = this.currentUser?.onboarding_skipped_steps || [];
      return skippedSteps.length > 0;
    },

    isQuickOnboardingUser() {
      return this.currentUser?.onboarding_mode === 'quick';
    },

    showEmptyDashboard() {
      return !this.hasAnyFinancialData;
    },

    hasAnyFinancialData() {
      return this.hasNetWorthData || this.hasProtectionData || this.hasInvestmentData || this.hasRetirementData || this.hasSavingsData || this.hasActualGoals;
    },

    // Check if the user is currently retired
    isRetired() {
      return this.currentUser?.employment_status === 'retired';
    },

    userAge() {
      const dob = this.currentUser?.date_of_birth;
      if (!dob) return null;
      const birth = new Date(dob);
      const now = new Date();
      let age = now.getFullYear() - birth.getFullYear();
      const monthDiff = now.getMonth() - birth.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birth.getDate())) {
        age--;
      }
      return age;
    },

    // Dynamic title for retirement card
    retirementCardTitle() {
      return this.isRetired ? 'Retirement Income' : 'Retirement';
    },

    showMFABanner() {
      const user = this.currentUser;
      if (!user) return false;
      if (user.is_preview_user) return false;
      if (this.mfaBannerDismissed) return false;
      return user.mfa_enabled !== true;
    },

    showKnowledgeNudge() {
      const user = this.currentUser;
      if (!user) return false;
      if (user.is_preview_user) return false;
      // Already answered — never ask again (check actual data, not just localStorage)
      const riskProfile = this.$store.state.investment?.riskProfile;
      if (riskProfile?.knowledge_level) return false;
      // Dismissed this session — don't pester
      if (this.knowledgeNudgeDismissed) return false;
      // Only show if user has investment or pension accounts
      const hasInvestments = this.$store.getters['completeness/hasModuleData']?.('investment') || this.$store.getters['completeness/hasModuleData']?.('retirement');
      return hasInvestments;
    },

    // Net Worth data
    ...mapGetters('netWorth', {
      netWorthValue: 'netWorth',
      netWorthAssets: 'totalAssets',
      netWorthLiabilities: 'totalLiabilities',
      netWorthOverview: 'overview',
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
      const overview = this.netWorthOverview;
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

    // Net Worth donut chart data
    netWorthChartCategories() {
      const LIABILITY_COLORS = {
        mortgages: '#E83E6D',
        loans: '#C62D57',
        credit_cards: '#A82248',
        other: '#8A1A39',
      };

      const categories = [];

      const ASSET_ROUTES = {
        property: '/net-worth/property',
        investments: '/net-worth/investments',
        pensions: '/net-worth/retirement',
        savings: '/net-worth/cash',
        business: '/net-worth/business',
        chattels: '/net-worth/chattels',
      };

      const LIABILITY_ROUTES = {
        mortgages: '/net-worth/liabilities',
        loans: '/net-worth/liabilities',
        credit_cards: '/net-worth/liabilities',
        other: '/net-worth/liabilities',
      };

      // Asset categories
      Object.entries(this.netWorthBreakdown.assets).forEach(([key, value]) => {
        if (value > 0) {
          categories.push({
            label: this.formatAssetCategory(key),
            value,
            color: ASSET_COLORS[key] || '#717171',
            route: ASSET_ROUTES[key] || '/net-worth/wealth-summary',
          });
        }
      });

      // Liability categories
      Object.entries(this.netWorthBreakdown.liabilities).forEach(([key, value]) => {
        if (value > 0) {
          categories.push({
            label: this.formatLiabilityCategory(key),
            value,
            color: LIABILITY_COLORS[key] || '#E83E6D',
            route: LIABILITY_ROUTES[key] || '/net-worth/liabilities',
          });
        }
      });

      return categories;
    },

    netWorthChartSeries() {
      return this.netWorthChartCategories.map(c => c.value);
    },

    netWorthChartLabels() {
      return this.netWorthChartCategories.map(c => c.label);
    },

    netWorthChartColors() {
      // Fixed rotating palette: dark blue, pink, mid blue, green, light blue
      const palette = [
        '#1F2A44', // Horizon 500 — dark blue
        '#E83E6D', // Raspberry 500 — pink
        '#5854E6', // Violet 500 — mid blue
        '#20B486', // Spring 500 — green
        '#6C83BC', // Light Blue 500 — light blue
      ];
      return this.netWorthChartCategories.map((_, idx) => palette[idx % palette.length]);
    },

    // Custom SVG donut segments for V6 design (40px stroke, gradients, rounded caps)
    netWorthDonutSegments() {
      const categories = this.netWorthChartCategories;
      const total = categories.reduce((sum, c) => sum + c.value, 0);
      if (total === 0) return [];

      const circumference = 471.2; // 2 * PI * 75
      const gap = 3; // small gap between segments
      let offset = 0;
      return categories.map(cat => {
        const proportion = cat.value / total;
        const arcLength = Math.max(proportion * circumference - gap, 2);
        const seg = {
          label: cat.label,
          value: cat.value,
          percent: (proportion * 100).toFixed(1),
          color: cat.color,
          colorLight: this.lightenColor(cat.color, 0.35),
          route: cat.route,
          arcLength,
          offset,
        };
        offset += proportion * circumference;
        return seg;
      });
    },

    netWorthChartKey() {
      const total = this.netWorthChartSeries.reduce((a, b) => a + b, 0);
      return `nw-donut-${this.netWorthChartSeries.length}-${Math.round(total)}`;
    },

    netWorthChartOptions() {
      const netWorth = this.netWorthData.netWorth;
      const vm = this;

      const colors = this.netWorthChartColors;
      // Build per-segment gradient shades (lighter version of each color)
      const gradientToColors = colors.map(hex => {
        // Lighten each color by blending with white
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        const lighten = (c) => Math.min(255, Math.round(c + (255 - c) * 0.4));
        return `#${lighten(r).toString(16).padStart(2, '0')}${lighten(g).toString(16).padStart(2, '0')}${lighten(b).toString(16).padStart(2, '0')}`;
      });

      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'donut',
        },
        labels: this.netWorthChartLabels,
        colors,
        legend: { show: false },
        dataLabels: { enabled: false },
        stroke: {
          width: 0,
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'dark',
            type: 'diagonal1',
            gradientToColors,
            stops: [0, 100],
          },
        },
        plotOptions: {
          pie: {
            donut: {
              size: '55%',
              labels: {
                show: true,
                name: {
                  show: false,
                },
                value: {
                  show: true,
                  fontSize: '22px',
                  fontWeight: 900,
                  color: netWorth >= 0 ? '#16A34A' : '#A82248',
                  offsetY: 24,
                  formatter: () => vm.formatCurrency(netWorth),
                },
                total: {
                  show: true,
                  showAlways: true,
                  label: '',
                  fontSize: '22px',
                  fontWeight: 900,
                  color: netWorth >= 0 ? '#16A34A' : '#A82248',
                  formatter: () => vm.formatCurrency(netWorth),
                },
              },
            },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => {
              const total = vm.netWorthChartSeries.reduce((a, b) => a + b, 0);
              const percent = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
              return `${vm.formatCurrency(val)} (${percent}%)`;
            },
          },
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: { height: 240 },
            },
          },
        ],
      };
    },

    // Mobile bar chart for net worth (each asset/liability category as a bar)
    netWorthBarChartSeries() {
      return [{
        name: 'Value',
        data: this.netWorthChartCategories.map(c => c.value),
      }];
    },

    netWorthBarChartOptions() {
      const vm = this;
      const categories = this.netWorthChartCategories;
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
          offsetY: 0,
        },
        plotOptions: {
          bar: {
            borderRadius: 4,
            columnWidth: '60%',
            distributed: true,
          },
        },
        colors: categories.map(c => c.color),
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: {
          categories: categories.map(c => c.label),
          labels: {
            style: {
              fontSize: '11px',
              fontWeight: 500,
              colors: categories.map(c => c.color),
            },
            rotate: -45,
            rotateAlways: categories.length > 4,
            trim: false,
            maxHeight: 80,
          },
        },
        yaxis: {
          labels: {
            formatter: (val) => vm.formatCurrency(val),
            style: { fontSize: '10px' },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => vm.formatCurrency(val),
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
      };
    },

    // Retirement data - using real API data
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'profile', 'requiredCapital', 'analysis', 'annualAllowance', 'projections']),
    ...mapGetters('retirement', ['totalPensionWealth', 'yearsToRetirement', 'projectedIncome']),

    retirementData() {
      // Use the SAME data sources as the pension tab (projections from Monte Carlo)
      const requiredCapital = this.requiredCapital || {};
      const potProjection = this.projections?.pension_pot_projection;
      const incomeDrawdown = this.projections?.income_drawdown;

      // Gross income = DC withdrawals + DB pension + State Pension (first year)
      const firstYear = incomeDrawdown?.yearly_income?.[0];
      const grossIncome = firstYear
        ? (firstYear.total_income || 0) + (firstYear.state_pension || 0) + (firstYear.db_pension || 0)
        : 0;

      return {
        projectedIncome: grossIncome || this.projectedIncome || 0,
        targetIncome: requiredCapital.required_income || incomeDrawdown?.target_income || 0,
        projectedCapital: potProjection?.percentile_20_at_retirement || this.totalPensionWealth || 0,
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

    retirementIncomePercent() {
      if (!this.retirementData?.targetIncome || this.retirementData.targetIncome === 0) return 0;
      return Math.round((this.retirementData.projectedIncome / this.retirementData.targetIncome) * 100);
    },

    retirementCapitalPercent() {
      if (!this.retirementData?.capitalRequired || this.retirementData.capitalRequired === 0) return 0;
      return Math.round((this.retirementData.projectedCapital / this.retirementData.capitalRequired) * 100);
    },

    // Retired user income breakdown - uses backend projection data for consistency
    retiredIncomeData() {
      const incomeDrawdown = this.projections?.income_drawdown;
      const firstYear = incomeDrawdown?.yearly_income?.[0];

      const pensionDrawdown = firstYear?.total_income || 0;
      const dbPensionIncome = firstYear?.db_pension || 0;
      const statePensionIncome = firstYear?.state_pension || 0;
      const totalIncome = pensionDrawdown + dbPensionIncome + statePensionIncome;

      return {
        pensionDrawdown,
        dbPensionIncome,
        statePensionIncome,
        totalIncome,
      };
    },

    // Investment & Savings data
    ...mapState('investment', { investmentAccounts: 'accounts', investmentAnalysis: 'analysis' }),
    ...mapGetters('investment', ['totalPortfolioValue']),
    ...mapState('savings', ['expenditureProfile', 'accounts', 'isaAllowance']),

    // Investment accounts list (for line items)
    investmentAccountsList() {
      return this.investmentAccounts || [];
    },

    // Cash/savings accounts list (for line items)
    cashAccountsList() {
      return this.accounts || [];
    },

    investmentData() {
      // Total investments from investment accounts (adjusted for ownership)
      const totalInvestments = this.investmentAccountsList.reduce((sum, account) => {
        return sum + this.ownershipValue(account, 'current_value');
      }, 0);

      // Total cash from savings accounts (adjusted for ownership)
      const totalCash = this.cashAccountsList.reduce((sum, account) => {
        return sum + this.ownershipValue(account, 'current_balance');
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
      protectionTotalCoverage: 'totalCoverage',
      protectionTotalPremium: 'totalPremium',
      protectionLifePolicies: 'lifePolicies',
      protectionCriticalIllnessPolicies: 'criticalIllnessPolicies',
      protectionIncomeProtectionPolicies: 'incomeProtectionPolicies',
      protectionDisabilityPolicies: 'disabilityPolicies',
      protectionSicknessIllnessPolicies: 'sicknessIllnessPolicies',
    }),

    protectionData() {
      return {
        totalCoverage: this.protectionTotalCoverage || 0,
        premiumTotal: this.protectionTotalPremium || 0, // Already monthly from store getter
        policyCount: (this.protectionLifePolicies?.length || 0) +
          (this.protectionCriticalIllnessPolicies?.length || 0) +
          (this.protectionIncomeProtectionPolicies?.length || 0) +
          (this.protectionDisabilityPolicies?.length || 0) +
          (this.protectionSicknessIllnessPolicies?.length || 0),
      };
    },

    hasProtectionData() {
      return (this.protectionLifePolicies?.length || 0) +
             (this.protectionCriticalIllnessPolicies?.length || 0) +
             (this.protectionIncomeProtectionPolicies?.length || 0) +
             (this.protectionDisabilityPolicies?.length || 0) +
             (this.protectionSicknessIllnessPolicies?.length || 0) > 0;
    },

    // Goals data
    ...mapState('goals', ['dashboardOverview', 'projectionData']),
    ...mapGetters('goals', ['dashboardData']),

    // Estate data
    ...mapGetters('estate', ['ihtLiability', 'taxableEstate', 'grossEstate']),
    ...mapState('estate', { willInfo: 'willInfo' }),

    // Gamification celebration overlay
    ...mapState('gamification', { celebration: 'pendingCelebration' }),

    estateData() {
      return {
        taxableEstate: this.taxableEstate || 0,
        ihtLiability: this.ihtLiability || 0,
      };
    },

    hasEstateData() {
      return this.grossEstate > 0 || this.taxableEstate > 0 || this.ihtLiability > 0;
    },

    willAnswered() {
      return this.willInfo?.will_answered === true;
    },

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

    hasActualGoals() {
      const goals = this.$store.state.goals?.goals || [];
      return goals.length > 0;
    },

    // ISA Allowance computed — uses server-calculated tracking data
    // Lifetime ISA eligibility: under 40 and no main residence property
    lisaEligible() {
      if (this.userAge === null) return false;
      if (this.userAge >= 40) return false;
      // No property = likely first-time buyer
      return !this.netWorthBreakdown.assets.property;
    },

    lisaAllowanceData() {
      if (!this.lisaEligible) return null;

      const lisaLimit = LIFETIME_ISA_ALLOWANCE;
      const maxBonus = lisaLimit * 0.25; // 25% government LISA bonus

      // Find LISA contributions from investment accounts
      const lisaAccounts = (this.investmentAccounts || []).filter(a => {
        const type = (a.account_type || '').toLowerCase();
        return type === 'lisa' || type === 'lifetime_isa';
      });

      const used = lisaAccounts.reduce((sum, a) => {
        return sum + parseFloat(a.isa_subscription_current_year || a.annual_contribution || 0);
      }, 0);

      const capped = Math.min(used, lisaLimit);
      const remaining = lisaLimit - capped;
      const percentUsed = (capped / lisaLimit) * 100;
      const bonusEarned = capped * 0.25;

      return { used: capped, remaining, percentUsed, bonusEarned, maxBonus };
    },

    isaAllowanceData() {
      if (!this.isaAllowance) return null;
      const fullAllowance = this.isaAllowance.total_allowance || 20000;
      const totalAllowance = this.lisaAllowanceData ? fullAllowance - LIFETIME_ISA_ALLOWANCE : fullAllowance;
      const cashUsed = parseFloat(this.isaAllowance.cash_isa_used || 0);
      const ssUsed = parseFloat(this.isaAllowance.stocks_shares_isa_used || 0);
      const totalUsed = parseFloat(this.isaAllowance.total_used || 0) || (cashUsed + ssUsed);
      const remaining = totalAllowance - totalUsed;
      const percentUsed = this.isaAllowance.percentage_used || (totalAllowance > 0 ? (totalUsed / totalAllowance) * 100 : 0);

      return {
        totalAllowance,
        cashUsed,
        ssUsed,
        totalUsed,
        remaining,
        percentUsed,
      };
    },

    // Pension Annual Allowance computed
    pensionAllowanceData() {
      if (this.annualAllowance) {
        const available = this.annualAllowance.available_allowance || this.pensionAnnualAllowance;
        const contributions = this.annualAllowance.total_contributions || 0;
        const remaining = this.annualAllowance.remaining_allowance || (available - contributions);
        const percentUsed = available > 0 ? (contributions / available) * 100 : 0;

        return {
          availableAllowance: available,
          totalContributions: contributions,
          remaining,
          percentUsed,
          isTapered: this.annualAllowance.is_tapered || false,
          mpaaTriggered: false,
          hasExcess: this.annualAllowance.has_excess || false,
        };
      }

      // Fallback: calculate from DC pensions if annual allowance API didn't return data
      if (this.dcPensions && this.dcPensions.length > 0) {
        const totalContributions = this.dcPensions.reduce((sum, p) => {
          const employee = parseFloat(p.employee_contribution_amount || 0);
          const employer = parseFloat(p.employer_contribution_amount || 0);
          // Annualise monthly contributions
          const freq = (p.contribution_frequency || 'monthly').toLowerCase();
          const multiplier = freq === 'annual' || freq === 'annually' ? 1 : 12;
          return sum + (employee + employer) * multiplier;
        }, 0);

        if (totalContributions > 0) {
          const available = this.pensionAnnualAllowance;
          const remaining = available - totalContributions;
          const percentUsed = (totalContributions / available) * 100;

          return {
            availableAllowance: available,
            totalContributions,
            remaining,
            percentUsed,
            isTapered: false,
            mpaaTriggered: false,
            hasExcess: totalContributions > available,
          };
        }
      }

      return null;
    },

    // Pension standard used (capped at available allowance)
    pensionStandardUsed() {
      if (!this.pensionAllowanceData) return 0;
      return Math.min(this.pensionAllowanceData.totalContributions, this.pensionAllowanceData.availableAllowance);
    },

    pensionStandardPercent() {
      if (!this.pensionAllowanceData) return 0;
      return (this.pensionStandardUsed / this.pensionAllowanceData.availableAllowance) * 100;
    },

    pensionStandardRemaining() {
      if (!this.pensionAllowanceData) return 0;
      return Math.max(0, this.pensionAllowanceData.availableAllowance - this.pensionStandardUsed);
    },

    taperedTooltip() {
      if (!this.pensionAllowanceData?.isTapered) return '';
      const details = this.annualAllowance?.tapering_details;
      if (details) {
        return `Your adjusted income of ${this.formatCurrency(details.adjusted_income)} exceeds the threshold, reducing your Annual Allowance by ${this.formatCurrency(details.reduction)} from £60,000 to ${this.formatCurrency(this.pensionAllowanceData.availableAllowance)}`;
      }
      return `Your income exceeds the adjusted income threshold, so your Annual Allowance is reduced to ${this.formatCurrency(this.pensionAllowanceData.availableAllowance)}`;
    },

    // Carry forward data (only shown when contributions exceed standard allowance)
    carryForwardData() {
      if (!this.pensionAllowanceData) return null;
      const excess = this.pensionAllowanceData.totalContributions - this.pensionAllowanceData.availableAllowance;
      if (excess <= 0) return null;

      const carryForwardAvailable = this.annualAllowance?.carry_forward_available || this.annualAllowance?.carry_forward || 0;
      if (carryForwardAvailable <= 0) return null;

      const used = Math.min(carryForwardAvailable, excess);
      const remaining = this.pensionAllowanceData.availableAllowance - used;
      const percentUsed = (used / this.pensionAllowanceData.availableAllowance) * 100;

      return { used, remaining, percentUsed };
    },

    currentTaxYear() {
      return getCurrentTaxYear();
    },

    carryForwardTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();
      const day = now.getDate();
      const taxYearStart = (month > 3 || (month === 3 && day >= 6)) ? year : year - 1;
      const cfStart = taxYearStart - 3;
      return `${cfStart}/${String(cfStart + 1).slice(-2)}`;
    },

    hasAllowancesData() {
      return !!this.lisaAllowanceData || !!this.isaAllowanceData || !!this.pensionAllowanceData;
    },

    estateActions() {
      const plan = this.getPlan('estate');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },
    protectionActions() {
      const plan = this.getPlan('protection');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },
    savingsActions() {
      const plan = this.getPlan('savings');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },
    investmentActions() {
      const plan = this.getPlan('investment');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },
    retirementActions() {
      const plan = this.getPlan('retirement');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },

    hasSavingsData() {
      const accounts = this.$store.state.savings.accounts || [];
      return accounts.length > 0;
    },

    savingsTotalBalance() {
      const accounts = this.$store.state.savings.accounts || [];
      return accounts.reduce((sum, acc) => sum + parseFloat(acc.current_balance || 0), 0);
    },

    savingsAccountCount() {
      return (this.$store.state.savings.accounts || []).length;
    },

    investmentPortfolioValue() {
      return this.$store.getters['investment/totalPortfolioValue'] || 0;
    },

    investmentAccountCount() {
      return (this.$store.state.investment.accounts || []).length;
    },

    protectionPolicyList() {
      const list = [];
      (this.protectionLifePolicies || []).forEach(p => list.push({ name: p.policy_name || 'Life Insurance', cover: p.sum_assured || 0 }));
      (this.protectionCriticalIllnessPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Critical Illness', cover: p.sum_assured || 0 }));
      (this.protectionIncomeProtectionPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Income Protection', cover: p.monthly_benefit || 0 }));
      (this.protectionDisabilityPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Disability', cover: p.benefit_amount || 0 }));
      (this.protectionSicknessIllnessPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Sickness & Illness', cover: p.benefit_amount || 0 }));
      return list.slice(0, 5);
    },

    savingsSparklineData() {
      const total = this.savingsTotalBalance || 0;
      const now = new Date();
      const labels = [];
      for (let i = 5; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        labels.push(d.toLocaleString('en-GB', { month: 'short' }));
      }
      // Realistic savings growth: small steady increases (~0.3-0.5% per month from interest + contributions)
      const factors = [0.985, 0.988, 0.991, 0.994, 0.997, 1.0];
      return labels.map((label, i) => ({
        label,
        value: Math.round(total * factors[i]),
      }));
    },

    visibleSavingsAccounts() {
      const sorted = [...(this.savingsAccountList || [])].sort((a, b) =>
        (b.current_balance || 0) - (a.current_balance || 0) || (a.account_name || a.provider || '').localeCompare(b.account_name || b.provider || '')
      );
      if (!this.savingsAccountsExpanded) return [];
      return sorted.slice(0, 5);
    },

    investmentBarData() {
      return (this.$store.state.investment.accounts || [])
        .map(acc => ({
          name: acc.account_name || acc.provider || 'Account',
          value: acc.current_value || acc.total_value || 0,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 6);
    },

    investmentBarSeries() {
      return [{ name: 'Value', data: this.investmentBarData.map(d => d.value) }];
    },

    investmentBarOptions() {
      return {
        chart: { toolbar: { show: false }, parentHeightOffset: 0 },
        plotOptions: { bar: { horizontal: false, columnWidth: this.investmentBarData.length === 1 ? '35%' : '55%', borderRadius: 4, distributed: true } },
        colors: ['#5854E6', '#6C83BC', '#20B486', '#E83E6D', '#E6C9A8', '#1F2A44'],
        xaxis: {
          categories: this.investmentBarData.map(d => d.name.length > 12 ? d.name.substring(0, 12) + '…' : d.name),
          labels: { style: { fontSize: '10px', colors: '#6B7280' }, trim: false },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          show: true,
          labels: {
            style: { fontSize: '9px', colors: ['#6B7280'] },
            formatter: (val) => val >= 1000 ? '£' + Math.round(val / 1000) + 'k' : '£' + val,
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        legend: { show: false },
        grid: { show: false, padding: { left: 2, right: 2, top: -15, bottom: 0 } },
        dataLabels: { enabled: false },
        tooltip: {
          y: { formatter: (val) => this.formatCurrency(val) },
        },
      };
    },

    visibleInvestmentAccounts() {
      const sorted = [...(this.investmentAccountList || [])].sort((a, b) =>
        ((b.current_value || b.total_value || 0) - (a.current_value || a.total_value || 0)) || (a.account_name || a.provider || '').localeCompare(b.account_name || b.provider || '')
      );
      if (!this.investmentAccountsExpanded) return [];
      return sorted.slice(0, 3);
    },

    savingsAccountList() {
      return (this.$store.state.savings.accounts || []).slice(0, 4);
    },

    investmentAccountList() {
      return (this.$store.state.investment.accounts || []).slice(0, 4);
    },
  },

  methods: {
    ...mapActions('goals', ['fetchDashboardOverview', 'fetchProjection']),
    ...mapActions('retirement', ['fetchRequiredCapital']),

    onCelebrationDismiss() {
      this.$store.dispatch('gamification/acknowledge');
    },

    /**
     * Card visibility — always show cards that have data.
     * Life stage only affects the onboarding wizard steps, not what's
     * displayed on the dashboard. If the user has data, show the card.
     */
    isCardVisible() {
      return true;
    },

    // Format asset category names for display
    formatAssetCategory(category) {
      const categoryLabels = {
        pensions: 'Pensions',
        property: 'Property',
        investments: 'Investments',
        cash: 'Cash & Savings',
        business: 'Business Interests',
        chattels: 'Personal Valuables',
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

    // Format cash account name from institution and account type
    ownershipValue(account, valueField) {
      const value = parseFloat(account[valueField] || 0);
      if (account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common') {
        const percentage = account.ownership_percentage ?? 50;
        return value * (percentage / 100);
      }
      return value;
    },

    formatCashAccountName(account) {
      const isJoint = account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common';
      const jointLabel = isJoint ? ' (Joint)' : '';
      const name = account.account_name || '';
      const provider = account.institution || account.provider_name || '';

      if (name) return name + jointLabel;
      if (provider) return provider + jointLabel;
      return 'Cash Account' + jointLabel;
    },

    // Format protection policy name from provider and policy type
    formatPolicyName(policy, fallbackType) {
      const provider = policy.provider || policy.provider_name || '';
      const policyType = policy.policy_type || '';

      // Format policy type for display
      const typeLabels = {
        level_term: 'Level Term',
        decreasing_term: 'Decreasing Term',
        whole_of_life: 'Whole of Life',
        family_income_benefit: 'Family Income Benefit',
      };
      const formattedType = typeLabels[policyType] || policyType.replace(/_/g, ' ');

      if (provider && formattedType) {
        return `${provider} ${formattedType}`;
      }
      return provider || formattedType || fallbackType;
    },

    lightenColor(hex, amount) {
      const r = parseInt(hex.slice(1, 3), 16);
      const g = parseInt(hex.slice(3, 5), 16);
      const b = parseInt(hex.slice(5, 7), 16);
      const lighten = (c) => Math.min(255, Math.round(c + (255 - c) * amount));
      return `#${lighten(r).toString(16).padStart(2, '0')}${lighten(g).toString(16).padStart(2, '0')}${lighten(b).toString(16).padStart(2, '0')}`;
    },

    allowanceBarClass(percentUsed, isOverLimit) {
      if (isOverLimit || percentUsed >= 95) return 'bg-gradient-to-r from-raspberry-400 to-raspberry-600';
      if (percentUsed >= 75) return 'bg-gradient-to-r from-violet-400 to-violet-600';
      return 'bg-gradient-to-r from-horizon-400 to-horizon-500';
    },

    dismissMFABanner() {
      this.mfaBannerDismissed = true;
      sessionStorage.setItem('mfaBannerDismissed', 'true');
    },

    toggleFynChat() {
      window.dispatchEvent(new CustomEvent('fyn-toggle-chat'));
    },

    dismissKnowledgeNudge() {
      this.knowledgeNudgeDismissed = true;
      storage.set('knowledgeNudgeDismissed', 'true');
    },

    async setKnowledgeLevel(level) {
      this.savingKnowledgeLevel = true;
      try {
        await this.$store.dispatch('investment/updateKnowledgeLevel', level);
        this.knowledgeNudgeDismissed = true;
        storage.set('knowledgeNudgeDismissed', 'true');
      } catch (error) {
        logger.error('Failed to save investment knowledge level:', error);
      } finally {
        this.savingKnowledgeLevel = false;
      }
    },

    navigateTo(path) {
      if (path) {
        this.$router.push(path);
      }
    },

    async selectWill(hasWill) {
      this.willSelection = hasWill;
      try {
        await this.$store.dispatch('estate/saveWill', { has_will: hasWill });
      } catch (error) {
        logger.error('Failed to save will information:', error);
      }
    },

    async loadFinancialCommitments() {
      try {
        const response = await userProfileService.getFinancialCommitments();
        if (response.success) {
          this.financialCommitmentsData = response.data;
        }
      } catch (error) {
        logger.error('Failed to load financial commitments:', error);
        this.financialCommitmentsData = null;
      }
    },

    async loadAllData() {
      const user = this.currentUser;
      const isMarried = user && user.marital_status === 'married';
      const estateCalculationAction = isMarried
        ? 'estate/calculateIHTPlanning'
        : 'estate/calculateIHT';
      const hasFullEstateAccess = ['tier2', 'tier3'].includes(this.tierFlags?.resolved_tier);

      // Student persona: only load modules they actually use
      const moduleLoaders = this.isStudentPersona ? [
        { name: 'netWorth', action: 'netWorth/fetchOverview' },
        { name: 'estate', action: 'estate/fetchEstateData' },
        { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
        { name: 'investment', action: 'investment/fetchInvestmentData' },
        { name: 'investment', action: 'userProfile/fetchProfile' },
        { name: 'goals', action: 'goals/fetchDashboardOverview' },
        { name: 'goals', action: 'goals/fetchGoals' },
        { name: 'goals', action: 'goals/fetchLifeEvents', payload: {} },
      ] : [
        { name: 'netWorth', action: 'netWorth/fetchOverview' },
        { name: 'protection', action: 'protection/fetchProtectionData' },
        { name: 'estate', action: 'estate/fetchEstateData' },
        ...(hasFullEstateAccess
          ? [{ name: 'estate', action: estateCalculationAction, payload: {} }]
          : []),
        { name: 'investment', action: 'userProfile/fetchProfile' },
        { name: 'retirement', action: 'retirement/fetchRetirementData' },
        { name: 'retirement', action: 'retirement/fetchRequiredCapital' },
        { name: 'retirement', action: 'retirement/analyseRetirement' },
        { name: 'retirement', action: 'retirement/fetchProjections' },
        { name: 'investment', action: 'investment/fetchInvestmentData' },
        { name: 'investment', action: 'investment/analyseInvestment' },
        { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
        { name: 'taxAllowances', action: 'retirement/fetchAnnualAllowance', payload: getCurrentTaxYear() },
        { name: 'goals', action: 'goals/fetchDashboardOverview' },
        { name: 'goals', action: 'goals/fetchGoals' },
        { name: 'goals', action: 'goals/fetchLifeEvents', payload: {} },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'estate' },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'protection' },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'savings' },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'investment' },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'retirement' },
      ];

      Object.keys(this.loading).forEach(key => {
        this.loading[key] = true;
      });
      Object.keys(this.errors).forEach(key => {
        this.errors[key] = null;
      });

      // Load financial commitments and module completeness
      this.loadFinancialCommitments();
      this.$store.dispatch('completeness/fetchCompleteness');

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

          if (!success && Object.hasOwn(this.loading, module)) {
            this.errors[module] = error;
          }

          if (Object.hasOwn(this.loading, module) &&
              moduleCompletedCounts[module] >= moduleActionCounts[module]) {
            this.loading[module] = false;
          }
        } else {
          logger.error('Failed to load module:', result.reason);
        }
      });

      // Also try to fetch projection data for goals chart
      try {
        await this.fetchProjection();
      } catch {
        // Projection is optional, don't block
      }
    },
  },

  mounted() {
    this._handleResize = () => {
      this.isMobile = window.innerWidth < 768;
    };
    window.addEventListener('resize', this._handleResize);

    // Always refresh journey progress when returning to dashboard
    // (e.g. after onboarding, adding data via Fyn, etc.)
    this.$store.dispatch('lifeStage/refreshCompleteness').catch(() => {});

    // Refresh gamification status (level, progress, any pending celebration).
    this.$store.dispatch('gamification/fetchStatus').catch(() => {});

    // Meta Pixel: StartTrial — first dashboard visit after registration
    // Skip for preview/admin/test users
    if (this.$route.query.newUser === '1') {
      const user = this.$store.state.auth?.user;
      if (!user?.is_preview_user && !user?.is_admin && typeof fbq === 'function') {
        fbq('track', 'StartTrial', { currency: 'GBP', value: 0 });
      }
      // Clean the newUser param
      const cleanQuery = { ...this.$route.query };
      delete cleanQuery.newUser;
      this.$router.replace({ query: cleanQuery });
    }

    // Handle openFyn=journey from the "Quick start with Fyn" CTA.
    // Directly dispatches the Fyn onboarding flow and opens the chat
    // panel — the backend director streams turn 1 via SSE.
    if (this.$route.query.openFyn === 'journey') {
      // Blur dashboard background on desktop until user interacts with chat.
      if (window.innerWidth >= 1024) {
        this.journeyBlurActive = true;
        this._clearBlur = () => { this.journeyBlurActive = false; };
        window.addEventListener('fyn-chat-interaction', this._clearBlur, { once: true });
      }

      // Capture the from query param BEFORE stripping it so the
      // campaign / journey identifier reaches the onboarding director.
      const fromParam = this.$route.query.from;

      // Clean the query param first so a page reload doesn't re-trigger.
      this.$router.replace({ query: {} });

      // Populate the store BEFORE opening the panel so onOpen() sees the
      // active conversation and skips its own initialisation path.
      const expandDockedChat = () => {
        // Docked chat on desktop starts collapsed — force-expand it.
        window.dispatchEvent(new Event('fyn-open-chat'));
        // And open the floating/mobile chat panel.
        this.$store.dispatch('aiChat/open');
      };
      this.$store.dispatch('aiChat/startOnboardingConversation', { from: fromParam })
        .then(expandDockedChat)
        .catch((err) => {
          // Fall back to just opening an empty chat if the director fails.
          console.warn('[Dashboard] startOnboardingConversation failed, falling back', err);
          expandDockedChat();
        });
    }
  },

  beforeUnmount() {
    if (this._handleResize) {
      window.removeEventListener('resize', this._handleResize);
    }
    if (this._clearBlur) {
      window.removeEventListener('fyn-chat-interaction', this._clearBlur);
    }
  },

  watch: {
    currentUser: {
      immediate: true,
      handler(user, oldUser) {
        if (user && !this.dataLoaded) {
          this.dataLoaded = true;
          this.$store.dispatch('lifeStage/fetchStage').catch(() => {});
          this.loadAllData();
        } else if (user && oldUser && user.id !== oldUser.id) {
          // User changed (e.g. preview persona switch) — reload all data
          this.$store.dispatch('lifeStage/fetchStage').catch(() => {});
          this.loadAllData();
        }
      }
    }
  },
};
</script>

<style scoped>
/* Empty cards use order-last to sink below populated cards in the grid */

.dashboard-grid {
  align-items: stretch;
}

.dashboard-grid > * {
  min-width: 0;
  height: 100%;
}

</style>

<style>
.nw-donut-tooltip {
  position: fixed;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 12px;
  border-radius: 8px;
  color: white;
  font-size: 12px;
  line-height: 1.3;
  white-space: nowrap;
  pointer-events: none;
  z-index: 10000;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}
</style>
