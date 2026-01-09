<template>
  <AppLayout>
    <div class="py-4 sm:py-6">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Card 1: Net Worth (Always shown) -->
        <NetWorthOverviewCard />

        <!-- Card 2: Affordability (needs both userProfile AND savings data) -->
        <div v-if="loading.affordability || loading.taxAllowances" class="card animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
          <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
          <div class="h-3 bg-gray-200 rounded w-full"></div>
        </div>
        <AffordabilityOverviewCard v-else />

        <!-- Card 3: Retirement -->
        <div v-if="loading.retirement" class="card animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
          <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
          <div class="h-3 bg-gray-200 rounded w-full"></div>
        </div>
        <RetirementOverviewCard v-else />

        <!-- Card 4: Investments -->
        <div v-if="loading.investment" class="card animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
          <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
          <div class="h-3 bg-gray-200 rounded w-full"></div>
        </div>
        <InvestmentsOverviewCard v-else />

        <!-- Card 5: Tax Optimisation -->
        <div v-if="loading.taxAllowances" class="card animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
          <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
          <div class="h-3 bg-gray-200 rounded w-full"></div>
        </div>
        <TaxOptimisationCard v-else />

        <!-- Card 6: Estate Planning (Only if IHT liability > 0) -->
        <template v-if="shouldShowEstateCard">
          <div v-if="loading.estate" class="card animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
            <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
            <div class="h-3 bg-gray-200 rounded w-full"></div>
          </div>
          <div v-else-if="errors.estate" class="card border-2 border-red-600 bg-white">
            <h3 class="text-h4 text-red-700 mb-2">Estate Module</h3>
            <p class="text-body text-red-600 mb-4">
              Failed to load estate data. {{ errors.estate }}
            </p>
            <button
              @click="retryLoadModule('estate')"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
            >
              Retry
            </button>
          </div>
          <EstateOverviewCard
            v-else
            :taxable-estate="estateData.taxableEstate"
            :iht-liability="estateData.ihtLiability"
            :probate-readiness="estateData.probateReadiness"
            :future-death-age="estateData.futureDeathAge"
            :future-taxable-estate="estateData.futureTaxableEstate"
            :future-iht-liability="estateData.futureIHTLiability"
            :is-married="estateData.isMarried"
          />
        </template>

        <!-- Card 7: Protection (Only if user has policies) -->
        <template v-if="shouldShowProtectionCard">
          <div v-if="loading.protection" class="card animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
            <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
            <div class="h-3 bg-gray-200 rounded w-full"></div>
          </div>
          <div v-else-if="errors.protection" class="card border-2 border-red-600 bg-white">
            <h3 class="text-h4 text-red-700 mb-2">Protection Module</h3>
            <p class="text-body text-red-600 mb-4">
              Failed to load protection data. {{ errors.protection }}
            </p>
            <button
              @click="retryLoadModule('protection')"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
            >
              Retry
            </button>
          </div>
          <ProtectionOverviewCard
            v-else
            :adequacy-score="protectionData.adequacyScore"
            :total-coverage="protectionData.totalCoverage"
            :premium-total="protectionData.premiumTotal"
            :critical-gaps="protectionData.criticalGaps"
            :life-policies="protectionData.lifePolicies"
            :critical-illness-policies="protectionData.criticalIllnessPolicies"
            :income-protection-policies="protectionData.incomeProtectionPolicies"
            :disability-policies="protectionData.disabilityPolicies"
          />
        </template>

        <!-- Card 8: Trusts (Only if user has trusts) -->
        <template v-if="shouldShowTrustsCard">
          <div v-if="loading.trusts" class="card animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
            <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
            <div class="h-3 bg-gray-200 rounded w-full"></div>
          </div>
          <TrustsOverviewCard v-else />
        </template>

        <!-- Card 6: Plans (spans 2 columns) -->
        <div class="sm:col-span-2">
          <div class="card hover:shadow-lg transition-shadow h-full">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h3 class="text-h3 text-gray-900">Plans</h3>
                <p class="text-sm text-gray-600 mt-1">Your financial planning modules</p>
              </div>
              <div class="p-3 bg-primary-600 rounded-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
            </div>

            <!-- Plan Buttons -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Protection Plan -->
              <button
                @click="$router.push('/protection-plan')"
                class="w-full flex items-center justify-between p-3 bg-white border border-secondary-200 hover:border-primary-500 hover:shadow-sm rounded-lg transition-all group"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0 w-10 h-10 bg-primary-600 rounded-lg flex items-center justify-center group-hover:bg-primary-700 transition-colors">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                  </div>
                  <div class="text-left">
                    <p class="text-sm font-semibold text-gray-900 group-hover:text-primary-600 transition-colors">Protection Plan</p>
                    <p class="text-xs text-secondary-500">Life, critical illness & income protection</p>
                  </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>

              <!-- Estate Plan -->
              <button
                @click="$router.push('/estate-plan')"
                class="w-full flex items-center justify-between p-3 bg-white border border-secondary-200 hover:border-primary-500 hover:shadow-sm rounded-lg transition-all group"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0 w-10 h-10 bg-secondary-600 rounded-lg flex items-center justify-center group-hover:bg-secondary-700 transition-colors">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                  </div>
                  <div class="text-left">
                    <p class="text-sm font-semibold text-gray-900 group-hover:text-primary-600 transition-colors">Estate Plan</p>
                    <p class="text-xs text-secondary-500">Inheritance tax & succession planning</p>
                  </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>

              <!-- Investment & Savings Plan -->
              <button
                @click="$router.push('/plans/investment-savings')"
                class="w-full flex items-center justify-between p-3 bg-white border border-secondary-200 hover:border-primary-500 hover:shadow-sm rounded-lg transition-all group"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0 w-10 h-10 bg-primary-600 rounded-lg flex items-center justify-center group-hover:bg-primary-700 transition-colors">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                  </div>
                  <div class="text-left">
                    <p class="text-sm font-semibold text-gray-900 group-hover:text-primary-600 transition-colors">Investment & Savings Plan</p>
                    <p class="text-xs text-secondary-500">Portfolio & cash strategy</p>
                  </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>

              <!-- Retirement Plan (Greyed Out) -->
              <div class="w-full flex items-center justify-between p-3 bg-gray-100 rounded-lg opacity-50 cursor-not-allowed">
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0 w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center">
                    <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <div class="text-left">
                    <p class="text-sm font-semibold text-gray-600">Retirement Plan</p>
                    <p class="text-xs text-gray-500">Coming soon</p>
                  </div>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>

              <!-- Tax Plan (Greyed Out) -->
              <div class="w-full flex items-center justify-between p-3 bg-gray-100 rounded-lg opacity-50 cursor-not-allowed">
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0 w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center">
                    <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <div class="text-left">
                    <p class="text-sm font-semibold text-gray-600">Tax Plan</p>
                    <p class="text-xs text-gray-500">Coming soon</p>
                  </div>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>

              <!-- Financial Plan (Greyed Out) -->
              <div class="w-full flex items-center justify-between p-3 bg-gray-100 rounded-lg opacity-50 cursor-not-allowed">
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0 w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center">
                    <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                  </div>
                  <div class="text-left">
                    <p class="text-sm font-semibold text-gray-600">Financial Plan</p>
                    <p class="text-xs text-gray-500">Coming soon</p>
                  </div>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 7: UK Taxes & Allowances (Admin Only) -->
        <UKTaxesOverviewCard v-if="isAdmin" />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters, mapState } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import NetWorthOverviewCard from '@/components/Dashboard/NetWorthOverviewCard.vue';
import AffordabilityOverviewCard from '@/components/Dashboard/AffordabilityOverviewCard.vue';
import RetirementOverviewCard from '@/components/Dashboard/RetirementOverviewCard.vue';
import InvestmentsOverviewCard from '@/components/Dashboard/InvestmentsOverviewCard.vue';
import TaxOptimisationCard from '@/components/Dashboard/TaxOptimisationCard.vue';
import ProtectionOverviewCard from '@/components/Protection/ProtectionOverviewCard.vue';
import EstateOverviewCard from '@/components/Estate/EstateOverviewCard.vue';
import TrustsOverviewCard from '@/components/Trusts/TrustsOverviewCard.vue';
import UKTaxesOverviewCard from '@/components/Dashboard/UKTaxesOverviewCard.vue';

export default {
  name: 'Dashboard',

  components: {
    AppLayout,
    NetWorthOverviewCard,
    AffordabilityOverviewCard,
    RetirementOverviewCard,
    InvestmentsOverviewCard,
    TaxOptimisationCard,
    ProtectionOverviewCard,
    EstateOverviewCard,
    TrustsOverviewCard,
    UKTaxesOverviewCard,
  },

  data() {
    return {
      loading: {
        protection: true,
        estate: true,
        trusts: true,
        affordability: true,
        retirement: true,
        investment: true,
        taxAllowances: true,
      },
      errors: {
        protection: null,
        estate: null,
        trusts: null,
      },
      refreshing: false,
      dataLoaded: false,
    };
  },

  computed: {
    ...mapGetters('auth', ['isAdmin']),
    ...mapState('trusts', { trustsList: 'trusts' }),
    ...mapGetters('netWorth', {
      netWorthValue: 'netWorth',
      netWorthAssets: 'totalAssets',
      netWorthLiabilities: 'totalLiabilities',
    }),
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
    ...mapGetters('estate', {
      estateIHTLiability: 'ihtLiability',
      estateProbateReadiness: 'probateReadiness',
      estateTaxableEstate: 'taxableEstate',
      estateFutureDeathAge: 'futureDeathAge',
      estateFutureTaxableEstate: 'futureTaxableEstate',
      estateFutureIHTLiability: 'futureIHTLiability',
    }),

    // Conditional display computed properties
    shouldShowEstateCard() {
      // Show estate card only if there's an IHT liability
      return this.estateData.ihtLiability > 0;
    },

    shouldShowProtectionCard() {
      // Show protection card only if user has any policies
      return (
        this.protectionData.lifePolicies.length > 0 ||
        this.protectionData.criticalIllnessPolicies.length > 0 ||
        this.protectionData.incomeProtectionPolicies.length > 0 ||
        this.protectionData.disabilityPolicies.length > 0
      );
    },

    shouldShowTrustsCard() {
      // Show trusts card only if user has trusts
      const trusts = this.trustsList || [];
      return trusts.length > 0;
    },

    netWorthData() {
      return {
        netWorth: this.netWorthValue || 0,
        totalAssets: this.netWorthAssets || 0,
        totalLiabilities: this.netWorthLiabilities || 0,
      };
    },

    protectionData() {
      // protectionCoverageGaps is an object with gaps_by_category, not an array
      const gaps = this.protectionCoverageGaps?.gaps_by_category || {};
      const criticalGaps = Object.values(gaps).filter(gap => gap > 10000).length || 0;

      // protectionAdequacyScore is an object with {score, category, colour, insights}
      const adequacyScore = typeof this.protectionAdequacyScore === 'object'
        ? (this.protectionAdequacyScore?.score ?? 0)
        : (this.protectionAdequacyScore || 0);

      return {
        adequacyScore: adequacyScore,
        totalCoverage: this.protectionTotalCoverage || 0,
        premiumTotal: this.protectionTotalPremium || 0,
        criticalGaps: criticalGaps,
        lifePolicies: this.protectionLifePolicies || [],
        criticalIllnessPolicies: this.protectionCriticalIllnessPolicies || [],
        incomeProtectionPolicies: this.protectionIncomeProtectionPolicies || [],
        disabilityPolicies: this.protectionDisabilityPolicies || [],
      };
    },

    estateData() {
      const user = this.$store.state.auth.user;
      const isMarried = user && user.marital_status === 'married';

      return {
        taxableEstate: this.estateTaxableEstate || 0,
        ihtLiability: this.estateIHTLiability || 0,
        probateReadiness: this.estateProbateReadiness || 0,
        futureDeathAge: this.estateFutureDeathAge,
        futureTaxableEstate: this.estateFutureTaxableEstate,
        futureIHTLiability: this.estateFutureIHTLiability,
        isMarried: isMarried,
      };
    },
  },

  methods: {
    async loadAllData() {
      // Determine which estate calculation to use based on marital status
      const user = this.$store.state.auth.user;
      const isMarried = user && user.marital_status === 'married';
      const estateCalculationAction = isMarried
        ? 'estate/calculateSecondDeathIHTPlanning'
        : 'estate/calculateIHT';

      // Load all module data in parallel with Promise.allSettled
      const moduleLoaders = [
        { name: 'netWorth', action: 'netWorth/fetchOverview' },
        { name: 'protection', action: 'protection/fetchProtectionData' },
        { name: 'estate', action: 'estate/fetchEstateData' },
        { name: 'estate', action: estateCalculationAction, payload: {} },
        { name: 'trusts', action: 'trusts/fetchTrusts' },
        { name: 'affordability', action: 'userProfile/fetchProfile' },
        { name: 'retirement', action: 'retirement/fetchRetirementData' },
        { name: 'investment', action: 'investment/fetchInvestmentData' },
        { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
      ];

      // Set all modules to loading
      Object.keys(this.loading).forEach(key => {
        this.loading[key] = true;
      });
      Object.keys(this.errors).forEach(key => {
        this.errors[key] = null;
      });

      // Count how many actions per module (estate has 2)
      const moduleActionCounts = {};
      moduleLoaders.forEach(loader => {
        moduleActionCounts[loader.name] = (moduleActionCounts[loader.name] || 0) + 1;
      });

      // Track completed actions per module
      const moduleCompletedCounts = {};

      // Create promises for all module loads
      const promises = moduleLoaders.map(loader =>
        this.$store.dispatch(loader.action, loader.payload)
          .then(() => ({ module: loader.name, success: true }))
          .catch(error => ({
            module: loader.name,
            success: false,
            error: error.response?.data?.message || error.message || 'Unknown error'
          }))
      );

      // Wait for all promises to settle
      const results = await Promise.allSettled(promises);

      // Process results - only set loading=false when ALL actions for a module complete
      results.forEach(result => {
        if (result.status === 'fulfilled') {
          const { module, success, error } = result.value;
          moduleCompletedCounts[module] = (moduleCompletedCounts[module] || 0) + 1;

          if (!success && this.loading.hasOwnProperty(module)) {
            this.errors[module] = error;
          }

          // Only set loading=false when all actions for this module have completed
          if (this.loading.hasOwnProperty(module) &&
              moduleCompletedCounts[module] >= moduleActionCounts[module]) {
            this.loading[module] = false;
          }
        } else {
          console.error('Failed to load module:', result.reason);
        }
      });
    },

    async retryLoadModule(moduleName) {
      this.loading[moduleName] = true;
      this.errors[moduleName] = null;

      // Determine which estate calculation to use based on marital status
      const user = this.$store.state.auth.user;
      const isMarried = user && user.marital_status === 'married';
      const estateCalculationAction = isMarried
        ? 'estate/calculateSecondDeathIHTPlanning'
        : 'estate/calculateIHT';

      const actions = {
        protection: ['protection/fetchProtectionData'],
        estate: ['estate/fetchEstateData', estateCalculationAction],
      };

      try {
        const moduleActions = actions[moduleName] || [];
        await Promise.all(
          moduleActions.map(action => this.$store.dispatch(action))
        );
        this.loading[moduleName] = false;
      } catch (error) {
        this.errors[moduleName] = error.response?.data?.message || error.message || 'Unknown error';
        this.loading[moduleName] = false;
      }
    },

    async refreshDashboard() {
      this.refreshing = true;
      // Use refreshNetWorth to bypass cache, then load other modules
      try {
        // Determine which estate calculation to use based on marital status
        const user = this.$store.state.auth.user;
        const isMarried = user && user.marital_status === 'married';
        const estateCalculationAction = isMarried
          ? 'estate/calculateSecondDeathIHTPlanning'
          : 'estate/calculateIHT';

        await this.$store.dispatch('netWorth/refreshNetWorth');
        // Load other module data
        await Promise.allSettled([
          this.$store.dispatch('protection/fetchProtectionData'),
          this.$store.dispatch('savings/fetchSavingsData'),
          this.$store.dispatch('investment/fetchInvestmentData'),
          this.$store.dispatch('estate/fetchEstateData'),
          this.$store.dispatch(estateCalculationAction),
        ]);
      } catch (error) {
        console.error('Error refreshing dashboard:', error);
      } finally {
        this.refreshing = false;
      }
    },
  },

  watch: {
    // Load data when user becomes available
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
