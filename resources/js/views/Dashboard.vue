<template>
  <AppLayout>
    <div class="py-4 sm:py-6">
      <!-- Grid layout for dashboard cards -->
      <div class="dashboard-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Net Worth -->
        <div class="flex">
          <NetWorthOverviewCard />
        </div>

        <!-- Retirement -->
        <div class="flex">
          <div v-if="loading.retirement" class="card animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
            <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
            <div class="h-3 bg-gray-200 rounded w-full"></div>
          </div>
          <RetirementOverviewCard v-else />
        </div>

        <!-- Investment -->
        <div class="flex">
          <div v-if="loading.investment" class="card animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
            <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
            <div class="h-3 bg-gray-200 rounded w-full"></div>
          </div>
          <InvestmentsOverviewCard v-else />
        </div>

        <!-- Tax -->
        <div class="flex">
          <div v-if="loading.taxAllowances" class="card animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
            <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
            <div class="h-3 bg-gray-200 rounded w-full"></div>
          </div>
          <TaxOptimisationCard v-else />
        </div>

        <!-- Estate -->
        <div v-if="shouldShowEstateCard" class="flex">
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
        </div>

        <!-- Protection -->
        <div v-if="shouldShowProtectionCard" class="flex">
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
            :sickness-illness-policies="protectionData.sicknessIllnessPolicies"
          />
        </div>

        <!-- Actions (full width) -->
        <div class="flex col-span-1 sm:col-span-2 lg:col-span-3">
          <ActionsOverviewCard />
        </div>

        <!-- Admin Taxes -->
        <div v-if="isAdmin" class="flex">
          <UKTaxesOverviewCard />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import NetWorthOverviewCard from '@/components/Dashboard/NetWorthOverviewCard.vue';
import RetirementOverviewCard from '@/components/Dashboard/RetirementOverviewCard.vue';
import InvestmentsOverviewCard from '@/components/Dashboard/InvestmentsOverviewCard.vue';
import TaxOptimisationCard from '@/components/Dashboard/TaxOptimisationCard.vue';
import ProtectionOverviewCard from '@/components/Protection/ProtectionOverviewCard.vue';
import EstateOverviewCard from '@/components/Estate/EstateOverviewCard.vue';
import UKTaxesOverviewCard from '@/components/Dashboard/UKTaxesOverviewCard.vue';
import ActionsOverviewCard from '@/components/Dashboard/ActionsOverviewCard.vue';

export default {
  name: 'Dashboard',

  components: {
    AppLayout,
    NetWorthOverviewCard,
    RetirementOverviewCard,
    InvestmentsOverviewCard,
    TaxOptimisationCard,
    ProtectionOverviewCard,
    EstateOverviewCard,
    UKTaxesOverviewCard,
    ActionsOverviewCard,
  },

  data() {
    return {
      loading: {
        protection: true,
        estate: true,
        retirement: true,
        investment: true,
        taxAllowances: true,
      },
      errors: {
        protection: null,
        estate: null,
      },
      refreshing: false,
      dataLoaded: false,
    };
  },

  computed: {
    ...mapGetters('auth', ['isAdmin']),
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
      protectionSicknessIllnessPolicies: 'sicknessIllnessPolicies',
    }),
    ...mapGetters('estate', {
      estateIHTLiability: 'ihtLiability',
      estateProbateReadiness: 'probateReadiness',
      estateTaxableEstate: 'taxableEstate',
      estateFutureDeathAge: 'futureDeathAge',
      estateFutureTaxableEstate: 'futureTaxableEstate',
      estateFutureIHTLiability: 'futureIHTLiability',
    }),

    shouldShowEstateCard() {
      return this.estateData.ihtLiability > 0;
    },

    shouldShowProtectionCard() {
      return (
        this.protectionData.lifePolicies.length > 0 ||
        this.protectionData.criticalIllnessPolicies.length > 0 ||
        this.protectionData.incomeProtectionPolicies.length > 0 ||
        this.protectionData.disabilityPolicies.length > 0
      );
    },

    netWorthData() {
      return {
        netWorth: this.netWorthValue || 0,
        totalAssets: this.netWorthAssets || 0,
        totalLiabilities: this.netWorthLiabilities || 0,
      };
    },

    protectionData() {
      const gaps = this.protectionCoverageGaps?.gaps_by_category || {};
      const criticalGaps = Object.values(gaps).filter(gap => gap > 10000).length || 0;
      const adequacyScore = typeof this.protectionAdequacyScore === 'object'
        ? (this.protectionAdequacyScore?.score ?? 0)
        : (this.protectionAdequacyScore || 0);

      return {
        adequacyScore: adequacyScore,
        totalCoverage: this.protectionTotalCoverage || 0,
        premiumTotal: this.protectionTotalPremium || 0,
        criticalGaps: criticalGaps,
        coverageGaps: gaps,
        lifePolicies: this.protectionLifePolicies || [],
        criticalIllnessPolicies: this.protectionCriticalIllnessPolicies || [],
        incomeProtectionPolicies: this.protectionIncomeProtectionPolicies || [],
        disabilityPolicies: this.protectionDisabilityPolicies || [],
        sicknessIllnessPolicies: this.protectionSicknessIllnessPolicies || [],
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
        { name: 'investment', action: 'investment/fetchInvestmentData' },
        { name: 'investment', action: 'investment/analyseInvestment' },
        { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
      ];

      Object.keys(this.loading).forEach(key => {
        this.loading[key] = true;
      });
      Object.keys(this.errors).forEach(key => {
        this.errors[key] = null;
      });

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
    },

    async retryLoadModule(moduleName) {
      this.loading[moduleName] = true;
      this.errors[moduleName] = null;

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
      try {
        const user = this.$store.state.auth.user;
        const isMarried = user && user.marital_status === 'married';
        const estateCalculationAction = isMarried
          ? 'estate/calculateSecondDeathIHTPlanning'
          : 'estate/calculateIHT';

        await this.$store.dispatch('netWorth/refreshNetWorth');
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
/* Card styling with white background and borders */
.dashboard-grid :deep(.card),
.dashboard-grid :deep([class*="overview-card"]) {
  background: white !important;
  border: 1px solid #e5e7eb !important;
  border-radius: 8px !important;
  width: 100%;
  height: 100%;
  transition: all 0.2s ease;
}

/* Hover effect for clickable cards - shadow and slight lift */
.dashboard-grid :deep(.card):hover,
.dashboard-grid :deep([class*="overview-card"]):hover {
  border-color: #d1d5db !important;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  transform: translateY(-2px);
}

/* Keep loading skeletons visible */
.dashboard-grid :deep(.animate-pulse) {
  background: #f3f4f6 !important;
  border-radius: 8px !important;
  width: 100%;
  height: 100%;
}

/* Make grid items stretch */
.dashboard-grid > div {
  display: flex;
}

.dashboard-grid > div > * {
  flex: 1;
}
</style>
